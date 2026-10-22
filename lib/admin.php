<?php
/**
 * Amorce commune à toutes les pages du back-office (/admin) :
 * en-têtes de sécurité, puis identité vérifiée (Cloudflare Access) — sinon arrêt immédiat.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access.php';
require_once __DIR__ . '/store.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

/** @return array{email: string, kind: string} */
function vib_admin_user(): array
{
    // Poste de développement uniquement : le serveur PHP intégré (php -S) n'existe jamais en ligne.
    if (PHP_SAPI === 'cli-server' && vib_env() === 'local') return ['email' => 'dev@local', 'kind' => 'user'];
    try {
        return vib_access_identity();
    } catch (AccessDenied $e) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Accès refusé</title><p>Accès refusé.</p>';
        error_log('VIB admin : accès refusé (' . $e->getMessage() . ')');
        exit;
    }
}

function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/**
 * Jeton anti-falsification des formulaires : un site tiers ne peut pas faire enregistrer une modification
 * à la place de l'utilisateur connecté. Clé secrète propre à chaque environnement, hors du dépôt.
 */
function vib_csrf_token(string $email): string
{
    $f = vib_config()['data_dir'] . '/cache/csrf.key';
    if (!is_file($f)) {
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0750, true);
        @file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
        @chmod($f, 0600);
    }
    $key = (string) @file_get_contents($f);
    if (strlen($key) < 64) throw new RuntimeException('Clé de formulaire indisponible');
    return hash_hmac('sha256', 'form|' . $email, $key);
}

/** Refuse un envoi de formulaire qui ne vient pas du back-office lui-même. */
function vib_csrf_check(array $user): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $o = parse_url($origin);
    $sameOrigin = $origin === '' || (($o['host'] ?? '') . (isset($o['port']) ? ':' . $o['port'] : '')) === strtolower($_SERVER['HTTP_HOST'] ?? '');
    if (!$sameOrigin || !hash_equals(vib_csrf_token($user['email']), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Formulaire expiré</title><p>Formulaire expiré ou invalide. Revenez en arrière, rechargez la page et recommencez.</p>';
        exit;
    }
}

/** Début de page commun : $nav = rubrique active (accueil, acteurs, locaux). */
function vib_admin_header(string $title, array $user, string $nav = '', bool $wide = false): void
{
    header('Content-Type: text/html; charset=utf-8');
    $items = ['accueil' => ['/admin/', 'Accueil'], 'acteurs' => ['/admin/acteurs.php', 'Acteurs'], 'locaux' => ['/admin/locaux.php', 'Locaux']];   // l'historique est accessible depuis l'accueil
    ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> - Back-office VIB</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets-2026/css/design-system.css?v=20261021a">
<link rel="stylesheet" href="/admin/admin.css?v=20261022g">
</head>
<body>
<header class="adm-bar">
  <a class="adm-bar__brand" href="/admin/" aria-label="Back-office, accueil">
    <img src="/assets-2026/images/logo/vib-full.svg" alt="VILLAGE Iraty-BIARRITZ" width="132" height="28">
    <span class="adm-bar__tag">Back-office</span>
  </a>
  <nav class="adm-bar__nav" aria-label="Back-office">
    <?php foreach ($items as $k => [$href, $label]): ?>
      <a href="<?= e($href) ?>"<?= $k === $nav ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <span class="adm-bar__user">
    <?php if (vib_env() !== 'prod'): ?><span class="adm-env"><?= e(vib_env() === 'test' ? 'Site de test' : 'Poste local') ?></span><?php endif; ?>
    <span class="adm-bar__email"><?= e($user['email']) ?></span>
  </span>
</header>
<main class="adm-main<?= $wide ? ' adm-main--wide' : '' ?>">
<?php
}

function vib_admin_footer(string $script = ''): void
{
    if ($script !== '') echo '<script src="' . e($script) . '" defer></script>' . "\n";
    echo "</main>\n</body>\n</html>\n";
}
