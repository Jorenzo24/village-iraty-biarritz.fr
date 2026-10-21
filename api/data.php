<?php
/**
 * Données publiques des fiches (/data/entreprises.json, /data/locaux.json, via .htaccess),
 * lues dans le coffre du back-office, avec la forme exacte de chaque champ (objets vides compris).
 */
declare(strict_types=1);
require __DIR__ . '/../lib/render.php';

$c = (string) ($_GET['c'] ?? '');
if (!in_array($c, ['entreprises', 'locaux'], true)) { http_response_code(404); exit; }
try {
    $s = new Store($c);
    $s->seedIfEmpty();
    $raw = $s->publicJson();
} catch (Throwable $e) {
    error_log('VIB api/data : ' . $e->getMessage());
    http_response_code(503);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');   // une modification du back-office doit se voir tout de suite
header('X-Robots-Tag: noindex');
echo $raw;
