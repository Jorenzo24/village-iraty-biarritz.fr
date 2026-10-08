<?php
/**
 * VIB - Enregistrement des réponses de la page de validation des fiches acteurs.
 * GET  ?cle=...            -> réponses enregistrées
 * POST {cle, answers, comment} -> enregistre (fichier JSON + historique)
 *
 * Le dépôt étant public, la clé n'est jamais écrite ici : seule son empreinte
 * SHA-256 l'est. La clé elle-même est dans le lien transmis au client
 * (et dans le .env local du poste de dev, variable VALIDATION_ACTEURS_CLE).
 */

declare(strict_types=1);

const KEY_HASH  = 'dc740c820d842873d0b668af577f74a02fd18659e67753b0e5f1cb826fe2ed69';
const DATA_DIR  = __DIR__ . '/data';
const DATA_FILE = DATA_DIR . '/reponses.json';
const HIST_DIR  = DATA_DIR . '/historique';
const HIST_KEEP = 200;
const MAX_BYTES = 1_000_000;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

function reply(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function key_ok(?string $key): bool {
    return is_string($key) && $key !== '' && hash_equals(KEY_HASH, hash('sha256', $key));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    if (!key_ok($_GET['cle'] ?? null)) reply(403, ['ok' => false, 'error' => 'Clé d’accès invalide']);
    if (!is_file(DATA_FILE)) reply(200, ['ok' => true, 'data' => null, 'saved_at' => null]);
    $stored = json_decode((string) file_get_contents(DATA_FILE), true);
    if (!is_array($stored)) reply(500, ['ok' => false, 'error' => 'Fichier de réponses illisible']);
    reply(200, ['ok' => true, 'data' => $stored['data'] ?? null, 'saved_at' => $stored['saved_at'] ?? null]);
}

if ($method !== 'POST') reply(405, ['ok' => false, 'error' => 'Méthode non autorisée']);

$raw = file_get_contents('php://input', false, null, 0, MAX_BYTES + 1);
if ($raw === false || strlen($raw) > MAX_BYTES) reply(413, ['ok' => false, 'error' => 'Envoi trop volumineux']);
$in = json_decode($raw, true);
if (!is_array($in)) reply(400, ['ok' => false, 'error' => 'Données invalides']);
if (!key_ok($in['cle'] ?? null)) reply(403, ['ok' => false, 'error' => 'Clé d’accès invalide']);
if (!is_array($in['answers'] ?? null)) reply(400, ['ok' => false, 'error' => 'Réponses manquantes']);

// On ne garde que des valeurs scalaires courtes : rien d'exécutable n'est jamais relu côté serveur.
$answers = [];
foreach ($in['answers'] as $id => $fields) {
    if (!is_string($id) || strlen($id) > 120 || !is_array($fields)) continue;
    foreach ($fields as $k => $v) {
        if (!is_string($k) || strlen($k) > 20) continue;
        if (is_bool($v)) $answers[$id][$k] = $v;
        elseif (is_string($v)) $answers[$id][$k] = mb_substr($v, 0, 5000);
    }
}
$comment = is_string($in['comment'] ?? null) ? mb_substr($in['comment'], 0, 20000) : '';

$now = (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format(DATE_ATOM);
$payload = json_encode(
    ['saved_at' => $now, 'ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '', 'data' => ['answers' => $answers, 'comment' => $comment]],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);

if (!is_dir(HIST_DIR) && !@mkdir(HIST_DIR, 0755, true)) reply(500, ['ok' => false, 'error' => 'Dossier de sauvegarde non accessible en écriture']);
$tmp = DATA_FILE . '.tmp';
if (@file_put_contents($tmp, $payload, LOCK_EX) === false || !@rename($tmp, DATA_FILE)) {
    reply(500, ['ok' => false, 'error' => 'Écriture impossible sur le serveur']);
}

// Historique : une copie par enregistrement, on garde les HIST_KEEP plus récentes.
@file_put_contents(HIST_DIR . '/reponses-' . date('Ymd-His') . '.json', $payload);
$hist = glob(HIST_DIR . '/reponses-*.json') ?: [];
sort($hist);
foreach (array_slice($hist, 0, max(0, count($hist) - HIST_KEEP)) as $old) @unlink($old);

reply(200, ['ok' => true, 'saved_at' => $now]);
