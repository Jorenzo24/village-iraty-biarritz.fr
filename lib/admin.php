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
