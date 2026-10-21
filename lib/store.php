<?php
/**
 * Moteur d'enregistrement des données du back-office.
 *
 * Leçons de la page de validation (réponses perdues le 15/10/2026), appliquées dès le départ :
 *  - jamais d'écrasement silencieux : chaque écriture indique la version sur laquelle elle s'appuie
 *    (« base ») ; si quelqu'un a enregistré entre-temps, l'écriture est REFUSÉE (conflit), pas fusionnée
 *    à l'aveugle ;
 *  - verrou exclusif pendant lecture + écriture ;
 *  - écriture atomique (fichier temporaire puis renommage) : jamais de fichier à moitié écrit ;
 *  - historique complet, jamais purgé, avec auteur et motif ; une restauration est elle-même une
 *    nouvelle version (donc annulable) ;
 *  - toujours un tableau JSON (liste de fiches), jamais un objet, jamais vide par accident.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class StoreConflict extends RuntimeException {}

final class Store
{
    private string $dir;

    public function __construct(private string $collection, ?string $dataDir = null)
    {
        $cfg = vib_config();
        if (!isset($cfg['collections'][$collection])) throw new InvalidArgumentException("Collection inconnue : $collection");
        $this->dir = rtrim($dataDir ?? $cfg['data_dir'], '/');
        foreach ([$this->dir, $this->histDir()] as $d) {
            if (!is_dir($d) && !@mkdir($d, 0750, true) && !is_dir($d)) throw new RuntimeException("Dossier de données inaccessible : $d");
        }
    }

    private function file(): string     { return "{$this->dir}/{$this->collection}.json"; }
    private function lockFile(): string { return "{$this->dir}/{$this->collection}.lock"; }
    private function histDir(): string  { return "{$this->dir}/historique/{$this->collection}"; }
    private function logFile(): string  { return "{$this->dir}/historique/{$this->collection}.log"; }

    /** Version = empreinte du contenu exact du fichier. */
    public static function versionOf(string $raw): string { return substr(hash('sha256', $raw), 0, 16); }

    /** @return array{items: array, version: string} */
    public function read(): array
    {
        $lock = $this->lock(LOCK_SH);
        try { return $this->readUnlocked(); } finally { $this->unlock($lock); }
    }

    /**
     * Enregistre la liste complète des fiches.
     * @param string $base   version lue avant modification (obligatoire)
     * @return string nouvelle version
     * @throws StoreConflict si la version courante n'est plus $base
     */
    public function write(array $items, string $base, string $author, string $message): string
    {
        if (!array_is_list($items)) throw new InvalidArgumentException('Les fiches doivent former une liste');
        $key = vib_config()['collections'][$this->collection]['key'];
        $seen = [];
        foreach ($items as $i => $it) {
            if (!is_array($it) || !isset($it[$key]) || !is_string($it[$key]) || $it[$key] === '') throw new InvalidArgumentException("Fiche n°$i sans « $key »");
            if (isset($seen[$it[$key]])) throw new InvalidArgumentException("« $key » en double : {$it[$key]}");
            $seen[$it[$key]] = true;
        }
        $raw = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . "\n";

        $lock = $this->lock(LOCK_EX);
        try {
            $current = $this->readUnlocked();
            if ($current['version'] !== $base) {
                throw new StoreConflict('Les données ont été modifiées entre-temps (par un autre onglet ou une autre personne). Rechargez avant d’enregistrer.');
            }
            // Garde-fou : refuser de vider la collection sauf intention explicite
            if (count($items) === 0 && count($current['items']) > 0 && $message !== 'VIDER') {
                throw new InvalidArgumentException('Enregistrement d’une liste vide refusé');
            }
            $this->atomicWrite($this->file(), $raw);
            $version = self::versionOf($raw);
            $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
            $this->atomicWrite($this->histDir() . "/$id.json", $raw);
            $this->appendLog(['id' => $id, 'version' => $version, 'base' => $base, 'at' => date(DATE_ATOM),
                              'author' => $author, 'message' => $message, 'count' => count($items)]);
            return $version;
        } finally {
            $this->unlock($lock);
        }
    }

    /** Contenu exact du fichier (servi tel quel au public par api/data.php). */
    public function rawJson(): string
    {
        $lock = $this->lock(LOCK_SH);
        try { $this->readUnlocked(); return (string) file_get_contents($this->file()); } finally { $this->unlock($lock); }
    }

    /** @return list<array> entrées du journal, la plus récente d'abord */
    public function history(int $limit = 100): array
    {
        if (!is_file($this->logFile())) return [];
        $lines = file($this->logFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $out = [];
        foreach (array_reverse($lines) as $l) {
            $e = json_decode($l, true);
            if (is_array($e)) $out[] = $e;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** Contenu d'une version passée. */
    public function historyItems(string $id): array
    {
        if (!preg_match('/^\d{8}-\d{6}-[0-9a-f]{6}$/', $id)) throw new InvalidArgumentException('Version invalide');
        $f = $this->histDir() . "/$id.json";
        if (!is_file($f)) throw new InvalidArgumentException('Version introuvable');
        $items = json_decode((string) file_get_contents($f), true);
        if (!is_array($items) || !array_is_list($items)) throw new RuntimeException('Version illisible');
        return $items;
    }

    /** Restaure une version passée : crée une NOUVELLE version identique (donc elle-même annulable). */
    public function restore(string $id, string $base, string $author): string
    {
        return $this->write($this->historyItems($id), $base, $author, "Restauration de la version $id");
    }

    /**
     * Initialise la collection depuis le fichier du dépôt si elle n'existe pas encore.
     * Ne fait RIEN si des données existent déjà (jamais d'écrasement par la migration).
     */
    public function seedIfEmpty(): bool
    {
        $lock = $this->lock(LOCK_EX);
        try {
            if (is_file($this->file())) return false;
            $seed = vib_config()['collections'][$this->collection]['seed'];
            $items = json_decode((string) @file_get_contents($seed), true);
            if (!is_array($items) || !array_is_list($items) || !$items) throw new RuntimeException("Fichier de départ illisible : $seed");
            $raw = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . "\n";
            $this->atomicWrite($this->file(), $raw);
            $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
            $this->atomicWrite($this->histDir() . "/$id.json", $raw);
            $this->appendLog(['id' => $id, 'version' => self::versionOf($raw), 'base' => '', 'at' => date(DATE_ATOM),
                              'author' => 'système', 'message' => 'Import initial depuis ' . basename($seed), 'count' => count($items)]);
            return true;
        } finally {
            $this->unlock($lock);
        }
    }

    // --- interne ---------------------------------------------------------------------------

    private function readUnlocked(): array
    {
        if (!is_file($this->file())) throw new RuntimeException("Collection « {$this->collection} » non initialisée");
        $raw = (string) file_get_contents($this->file());
        $items = json_decode($raw, true);
        if (!is_array($items) || !array_is_list($items)) throw new RuntimeException("Fichier « {$this->collection} » illisible");
        return ['items' => $items, 'version' => self::versionOf($raw)];
    }

    private function atomicWrite(string $path, string $raw): void
    {
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $raw) !== strlen($raw)) { @unlink($tmp); throw new RuntimeException("Écriture impossible : $path"); }
        if (!@rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException("Écriture impossible : $path"); }
    }

    private function appendLog(array $entry): void
    {
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        if (@file_put_contents($this->logFile(), $line, FILE_APPEND | LOCK_EX) === false) throw new RuntimeException('Journal inaccessible');
    }

    /** @return resource */
    private function lock(int $mode)
    {
        $h = @fopen($this->lockFile(), 'c');
        if ($h === false || !flock($h, $mode)) throw new RuntimeException('Verrou indisponible');
        return $h;
    }

    private function unlock($h): void { flock($h, LOCK_UN); fclose($h); }
}
