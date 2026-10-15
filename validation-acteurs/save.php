<?php
/**
 * VIB - Enregistrement des réponses de la page de validation des fiches acteurs.
 *
 * GET  ?cle=...                         -> réponses enregistrées
 * GET  ?cle=...&historique=1            -> liste des copies de l'historique
 * GET  ?cle=...&version=reponses-….json -> contenu d'une copie de l'historique
 * POST {cle, v, answers, comment, comment_at} -> FUSIONNE avec l'existant
 *
 * Fusion, jamais remplacement : pour chaque ligne, la réponse dont l'horodatage
 * `at` est le plus récent l'emporte, et une ligne absente de l'envoi est conservée.
 * (La v1 remplaçait tout le fichier : un onglet resté ouvert sur un état vide a
 * effacé 89 réponses le 15/10/2026.) L'historique n'est plus jamais purgé.
 *
 * Le dépôt étant public, la clé n'est jamais écrite ici : seule son empreinte
 * SHA-256 l'est. La clé elle-même est dans le lien transmis au client
 * (et dans le .env local du poste de dev, variable VALIDATION_ACTEURS_CLE).
 */

declare(strict_types=1);

const KEY_HASH   = 'dc740c820d842873d0b668af577f74a02fd18659e67753b0e5f1cb826fe2ed69';
const DATA_DIR   = __DIR__ . '/data';
const DATA_FILE  = DATA_DIR . '/reponses.json';
const LOCK_FILE  = DATA_DIR . '/reponses.lock';
const HIST_DIR   = DATA_DIR . '/historique';
const MAX_BYTES  = 1_000_000;
const MIN_CLIENT = 2;   // version minimale de la page ; une page plus ancienne doit être rechargée

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

function reply(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function key_ok(mixed $key): bool {
    return is_string($key) && $key !== '' && hash_equals(KEY_HASH, hash('sha256', $key));
}

function load_file(string $path): array {
    if (!is_file($path)) return ['saved_at' => null, 'data' => ['answers' => [], 'comment' => '', 'comment_at' => '']];
    $stored = json_decode((string) file_get_contents($path), true);
    if (!is_array($stored)) reply(500, ['ok' => false, 'error' => 'Fichier de réponses illisible']);
    $stored['data'] = is_array($stored['data'] ?? null) ? $stored['data'] : [];
    $stored['data']['answers'] = is_array($stored['data']['answers'] ?? null) ? $stored['data']['answers'] : [];
    return $stored;
}

// Nombre de lignes réellement renseignées (hors horodatage et hors propositions non vérifiées).
function count_filled(array $answers): int {
    $n = 0;
    foreach ($answers as $fields) {
        if (!is_array($fields) || empty($fields['at'])) continue;
        foreach ($fields as $k => $v) {
            if ($k !== 'at' && $v !== '' && $v !== false) { $n++; break; }
        }
    }
    return $n;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    if (!key_ok($_GET['cle'] ?? null)) reply(403, ['ok' => false, 'error' => 'Clé d’accès invalide']);

    if (isset($_GET['historique'])) {
        $list = [];
        foreach (glob(HIST_DIR . '/reponses-*.json') ?: [] as $f) {
            $h = load_file($f);
            $list[] = ['version' => basename($f), 'saved_at' => $h['saved_at'] ?? null,
                       'lignes' => count_filled($h['data']['answers']), 'octets' => filesize($f)];
        }
        usort($list, fn($a, $b) => strcmp($b['version'], $a['version']));
        reply(200, ['ok' => true, 'historique' => $list]);
    }

    if (isset($_GET['version'])) {
        $v = (string) $_GET['version'];
        if (!preg_match('/^reponses-\d{8}-\d{6}(-[0-9a-f]+)?\.json$/', $v) || !is_file(HIST_DIR . '/' . $v)) {
            reply(404, ['ok' => false, 'error' => 'Version introuvable']);
        }
        $h = load_file(HIST_DIR . '/' . $v);
        reply(200, ['ok' => true, 'data' => $h['data'], 'saved_at' => $h['saved_at'] ?? null]);
    }

    $stored = load_file(DATA_FILE);
    reply(200, ['ok' => true, 'data' => $stored['data'], 'saved_at' => $stored['saved_at']]);
}

if ($method !== 'POST') reply(405, ['ok' => false, 'error' => 'Méthode non autorisée']);

$raw = file_get_contents('php://input', false, null, 0, MAX_BYTES + 1);
if ($raw === false || strlen($raw) > MAX_BYTES) reply(413, ['ok' => false, 'error' => 'Envoi trop volumineux']);
$in = json_decode($raw, true);
if (!is_array($in)) reply(400, ['ok' => false, 'error' => 'Données invalides']);
if (!key_ok($in['cle'] ?? null)) reply(403, ['ok' => false, 'error' => 'Clé d’accès invalide']);
if ((int) ($in['v'] ?? 0) < MIN_CLIENT) {
    reply(409, ['ok' => false, 'error' => 'Cette page n’est plus à jour : rechargez-la (touche F5) pour continuer']);
}
if (!is_array($in['answers'] ?? null)) reply(400, ['ok' => false, 'error' => 'Réponses manquantes']);

// On ne garde que des valeurs scalaires courtes : rien d'exécutable n'est jamais relu côté serveur.
$incoming = [];
foreach ($in['answers'] as $id => $fields) {
    if (!is_string($id) || strlen($id) > 120 || !is_array($fields)) continue;
    foreach ($fields as $k => $v) {
        if (!is_string($k) || strlen($k) > 20) continue;
        if (is_bool($v)) $incoming[$id][$k] = $v;
        elseif (is_string($v)) $incoming[$id][$k] = mb_substr($v, 0, 5000);
    }
}

if (!is_dir(HIST_DIR) && !@mkdir(HIST_DIR, 0755, true)) reply(500, ['ok' => false, 'error' => 'Dossier de sauvegarde non accessible en écriture']);

// Verrou : deux enregistrements simultanés ne doivent pas se fusionner sur une base périmée.
$lock = @fopen(LOCK_FILE, 'c');
if ($lock === false || !flock($lock, LOCK_EX)) reply(500, ['ok' => false, 'error' => 'Écriture impossible sur le serveur']);

$stored = load_file(DATA_FILE);
$merged = $stored['data']['answers'];
foreach ($incoming as $id => $fields) {
    $newAt = (string) ($fields['at'] ?? '');
    $oldAt = (string) ($merged[$id]['at'] ?? '');
    // Une ligne sans horodatage (proposition pré-remplie jamais touchée) n'écrase jamais une vraie réponse.
    if ($newAt === '' && $oldAt !== '') continue;
    if ($newAt >= $oldAt) $merged[$id] = $fields;
}
$comment   = (string) ($stored['data']['comment'] ?? '');
$commentAt = (string) ($stored['data']['comment_at'] ?? '');
$inAt      = is_string($in['comment_at'] ?? null) ? $in['comment_at'] : '';
if (is_string($in['comment'] ?? null) && $inAt !== '' && $inAt >= $commentAt) {
    $comment = mb_substr($in['comment'], 0, 20000);
    $commentAt = $inAt;
}

$now = (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format(DATE_ATOM);
$payload = json_encode(
    ['saved_at' => $now, 'ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
     'data' => ['answers' => $merged, 'comment' => $comment, 'comment_at' => $commentAt]],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);

$tmp = DATA_FILE . '.tmp';
if (@file_put_contents($tmp, $payload) === false || !@rename($tmp, DATA_FILE)) {
    reply(500, ['ok' => false, 'error' => 'Écriture impossible sur le serveur']);
}
// Historique complet, jamais purgé (quelques Ko par copie).
$hist = HIST_DIR . '/reponses-' . date('Ymd-His') . '.json';
if (is_file($hist)) $hist = substr($hist, 0, -5) . '-' . bin2hex(random_bytes(2)) . '.json';
@file_put_contents($hist, $payload);

flock($lock, LOCK_UN);
fclose($lock);

reply(200, ['ok' => true, 'saved_at' => $now, 'data' => ['answers' => $merged, 'comment' => $comment, 'comment_at' => $commentAt]]);
