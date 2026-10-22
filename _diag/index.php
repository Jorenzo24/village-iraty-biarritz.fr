<?php
/**
 * Diagnostic temporaire du serveur pour le chantier back-office.
 * Protégé par la même clé que la page de validation (seule son empreinte est ici).
 * ⚠️ À SUPPRIMER avant la fusion de feature/back-office dans main.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
const KEY_HASH = 'dc740c820d842873d0b668af577f74a02fd18659e67753b0e5f1cb826fe2ed69';
$k = $_GET['cle'] ?? '';
if (!is_string($k) || !hash_equals(KEY_HASH, hash('sha256', $k))) { http_response_code(403); echo '{"ok":false}'; exit; }

// Lecture seule du .htaccess de la production (modifié sur le serveur hors Git, bloque la mise à jour cPanel)
if (($_GET['fichier'] ?? '') === 'htaccess-prod') {
    header('Content-Type: text/plain; charset=utf-8');
    $f = dirname(__DIR__, 2) . '/public_html/.htaccess';
    echo is_file($f) ? 'mtime ' . date('c', (int) filemtime($f)) . "\n----\n" . file_get_contents($f) : 'introuvable';
    exit;
}

$disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
$can = fn(string $f) => function_exists($f) && !in_array($f, $disabled, true);
$which = function (string $bin) use ($can): string {
    if (!$can('shell_exec')) return 'shell_exec indisponible';
    return trim((string) @shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null')) ?: 'absent';
};
$home = dirname(__DIR__, 2);              // /home/<user> (le site est dans /home/<user>/test_html)
$probe = $home . '/vib-diag-ecriture-' . bin2hex(random_bytes(3));
$writeHome = @mkdir($probe, 0750) && @file_put_contents($probe . '/t.json', '{}') !== false;
if ($writeHome) { @unlink($probe . '/t.json'); @rmdir($probe); }
$gd = function_exists('gd_info') ? gd_info() : [];

echo json_encode([
    'php' => PHP_VERSION, 'sapi' => PHP_SAPI, 'utilisateur' => function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user(),
    'dossier_site' => dirname(__DIR__), 'home' => $home,
    'ecriture_hors_site' => $writeHome, 'open_basedir' => ini_get('open_basedir') ?: '(aucun)',
    'extensions' => array_values(array_intersect(['gd', 'imagick', 'fileinfo', 'mbstring', 'curl', 'zip', 'exif', 'json', 'openssl', 'sodium', 'intl'], array_map('strtolower', get_loaded_extensions()))),
    'gd_webp' => $gd['WebP Support'] ?? false, 'gd_jpeg' => $gd['JPEG Support'] ?? false, 'gd_png' => $gd['PNG Support'] ?? false,
    'imagick_webp' => class_exists('Imagick') ? in_array('WEBP', \Imagick::queryFormats('WEBP'), true) : false,
    'upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'), 'max_execution_time' => ini_get('max_execution_time'),
    'fonctions_desactivees' => $disabled,
    'binaires' => ['git' => $which('git'), 'cwebp' => $which('cwebp'), 'magick' => $which('magick'), 'convert' => $which('convert')],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
