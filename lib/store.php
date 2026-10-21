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
        $raw = $this->validateAndEncode($items);
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
            return $this->commitUnlocked($raw, $base, $author, $message, count($items));
        } finally {
            $this->unlock($lock);
        }
    }

    /** Empreinte d'une fiche : sert à détecter qu'elle a été modifiée par quelqu'un d'autre. */
    public static function itemHash(array $item): string
    {
        return substr(hash('sha256', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)), 0, 16);
    }

    /**
     * Modifie UNE fiche. Le contrôle porte sur cette fiche seulement : deux personnes peuvent modifier
     * deux fiches différentes en même temps ; deux modifications de la MÊME fiche → la seconde est refusée.
     * @param string   $expectedHash empreinte de la fiche telle qu'affichée dans le formulaire
     * @param callable $fn           reçoit la fiche actuelle, renvoie la fiche modifiée
     * @return array{version: string, item: array}
     */
    public function updateItem(string $keyValue, string $expectedHash, callable $fn, string $author, string $message): array
    {
        $key = vib_config()['collections'][$this->collection]['key'];
        $lock = $this->lock(LOCK_EX);
        try {
            $current = $this->readUnlocked();
            $items = $current['items'];
            $idx = null;
            foreach ($items as $i => $it) if (($it[$key] ?? null) === $keyValue) { $idx = $i; break; }
            if ($idx === null) throw new StoreConflict('Cette fiche n’existe plus (retirée entre-temps).');
            if (self::itemHash($items[$idx]) !== $expectedHash) {
                throw new StoreConflict('Cette fiche a été modifiée entre-temps (par un autre onglet ou une autre personne). Vos modifications n’ont pas été enregistrées : rechargez la fiche pour voir la version actuelle.');
            }
            $new = $fn($items[$idx]);
            if (!is_array($new) || ($new[$key] ?? null) !== $keyValue) throw new InvalidArgumentException('Modification invalide');
            $items[$idx] = $new;
            $raw = $this->validateAndEncode($items);
            $version = $this->commitUnlocked($raw, $current['version'], $author, $message, count($items), $keyValue);
            return ['version' => $version, 'item' => $new];
        } finally {
            $this->unlock($lock);
        }
    }

    /** Ajoute une fiche. Refusé si la clé existe déjà (fiche publiée ou retirée). */
    public function addItem(array $item, string $author, string $message): string
    {
        $key = vib_config()['collections'][$this->collection]['key'];
        $kv = (string) ($item[$key] ?? '');
        $lock = $this->lock(LOCK_EX);
        try {
            $current = $this->readUnlocked();
            foreach ($current['items'] as $it) if (($it[$key] ?? null) === $kv) throw new StoreConflict('Une fiche porte déjà cet identifiant.');
            $retired = $this->readRetiredUnlocked();
            $items = $current['items'];
            $items[] = $item;
            $raw = $this->validateAndEncode($items);
            $version = $this->commitUnlocked($raw, $current['version'], $author, $message, count($items), $kv);
            // Fiche réintégrée : elle quitte la liste des fiches retirées
            $left = array_values(array_filter($retired, fn($r) => ($r['item'][$key] ?? null) !== $kv));
            if (count($left) !== count($retired)) $this->writeRetiredUnlocked($left);
            return $version;
        } finally {
            $this->unlock($lock);
        }
    }

    /**
     * Retire une fiche du site. Elle est conservée dans la liste des fiches retirées (réintégrable),
     * et son adresse publique redirige vers l'annuaire.
     */
    public function retireItem(string $keyValue, string $expectedHash, string $author): string
    {
        $key = vib_config()['collections'][$this->collection]['key'];
        $lock = $this->lock(LOCK_EX);
        try {
            $current = $this->readUnlocked();
            $items = $current['items'];
            $idx = null;
            foreach ($items as $i => $it) if (($it[$key] ?? null) === $keyValue) { $idx = $i; break; }
            if ($idx === null) throw new StoreConflict('Cette fiche n’existe plus (retirée entre-temps).');
            if (self::itemHash($items[$idx]) !== $expectedHash) throw new StoreConflict('Cette fiche a été modifiée entre-temps. Rechargez-la avant de la retirer.');
            $item = $items[$idx];
            array_splice($items, $idx, 1);
            $raw = $this->validateAndEncode($items);
            $retired = $this->readRetiredUnlocked();
            $retired[] = ['item' => $item, 'at' => date(DATE_ATOM), 'author' => $author];
            $this->writeRetiredUnlocked($retired);
            return $this->commitUnlocked($raw, $current['version'], $author, 'Retrait de la fiche « ' . ($item['name'] ?? $keyValue) . ' » (' . $keyValue . ')', count($items), $keyValue);
        } finally {
            $this->unlock($lock);
        }
    }

    /** @return list<array{item: array, at: string, author: string}> fiches retirées, la plus récente d'abord */
    public function retired(): array
    {
        $lock = $this->lock(LOCK_SH);
        try { return array_reverse($this->readRetiredUnlocked()); } finally { $this->unlock($lock); }
    }

    /**
     * Versions successives d'UNE fiche (la plus récente d'abord), avec son contenu à chaque version.
     * @return list<array{entry: array, item: ?array}> item null = fiche absente à cette version (retirée)
     */
    public function itemHistory(string $keyValue, int $limit = 20): array
    {
        $key = vib_config()['collections'][$this->collection]['key'];
        $all = [];
        foreach ($this->history(5000) as $e) {
            // Opérations sur une fiche : marquées de sa clé. Opérations sans clé (import, restauration
            // globale) : retenues seulement si elles ont changé cette fiche (filtre ci-dessous).
            if (isset($e['key']) && $e['key'] !== $keyValue) continue;
            try { $items = $this->historyItems((string) $e['id']); } catch (Throwable) { continue; }
            $it = null;
            foreach ($items as $x) if (($x[$key] ?? null) === $keyValue) { $it = $x; break; }
            $all[] = ['entry' => $e, 'item' => $it];
            if (count($all) > $limit * 3) break;
        }
        $out = [];
        foreach ($all as $i => $v) {
            $older = $all[$i + 1] ?? null;
            if ($older !== null && $older['item'] === $v['item']) continue;   // n'a pas changé cette fiche
            if ($older === null && $v['item'] === null) continue;
            $out[] = $v;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    private function validateAndEncode(array $items): string
    {
        if (!array_is_list($items)) throw new InvalidArgumentException('Les fiches doivent former une liste');
        $key = vib_config()['collections'][$this->collection]['key'];
        $seen = [];
        foreach ($items as $i => $it) {
            if (!is_array($it) || !isset($it[$key]) || !is_string($it[$key]) || $it[$key] === '') throw new InvalidArgumentException("Fiche n°$i sans « $key »");
            if (isset($seen[$it[$key]])) throw new InvalidArgumentException("« $key » en double : {$it[$key]}");
            $seen[$it[$key]] = true;
        }
        return $this->encode($items);
    }

    /** Écrit la nouvelle version + l'historique. Doit être appelé verrou exclusif tenu. */
    private function commitUnlocked(string $raw, string $base, string $author, string $message, int $count, string $key = ''): string
    {
        $this->atomicWrite($this->file(), $raw);
        $version = self::versionOf($raw);
        $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $this->atomicWrite($this->histDir() . "/$id.json", $raw);
        $this->appendLog(['id' => $id, 'version' => $version, 'base' => $base, 'at' => date(DATE_ATOM),
                          'author' => $author, 'message' => $message, 'count' => $count] + ($key !== '' ? ['key' => $key] : []));
        return $version;
    }

    /** JSON public (api/data.php) : fiches réencodées avec la forme attendue de chaque champ. */
    public function publicJson(): string
    {
        return $this->encode($this->read()['items']);
    }

    /** Encodage unique de toute la collection : objets vides gardés en {}, nombres réécrits à l'identique. */
    public function encode(array $items): string
    {
        $objects = vib_config()['collections'][$this->collection]['objects'] ?? [];
        foreach ($items as &$it) {
            foreach ($objects as $f) if (array_key_exists($f, $it) && is_array($it[$f]) && $it[$f] === []) $it[$f] = new stdClass();
        }
        unset($it);
        return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . "\n";
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
            $raw = $this->encode($items);
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

    private function retiredFile(): string { return "{$this->dir}/{$this->collection}.retires.json"; }

    private function readRetiredUnlocked(): array
    {
        if (!is_file($this->retiredFile())) return [];
        $r = json_decode((string) file_get_contents($this->retiredFile()), true);
        if (!is_array($r) || !array_is_list($r)) throw new RuntimeException('Liste des fiches retirées illisible');
        return $r;
    }

    private function writeRetiredUnlocked(array $list): void
    {
        $objects = vib_config()['collections'][$this->collection]['objects'] ?? [];
        foreach ($list as &$r) foreach ($objects as $f) if (isset($r['item'][$f]) && $r['item'][$f] === []) $r['item'][$f] = new stdClass();
        unset($r);
        $this->atomicWrite($this->retiredFile(), json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . "\n");
    }

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
