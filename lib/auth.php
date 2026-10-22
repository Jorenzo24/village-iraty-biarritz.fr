<?php
/**
 * Comptes du back-office : identifiant (adresse e-mail) + mot de passe.
 *
 *  - mots de passe hachés (password_hash, irréversibles) ; jamais stockés ni envoyés en clair ;
 *  - comptes dans <données>/utilisateurs.json (hors du site et hors Git), écritures sous verrou ;
 *  - anti-force brute : après 5 échecs en 15 minutes, le compte (et l'adresse IP après 20) est bloqué
 *    15 minutes ; même message d'erreur que le compte existe ou non ;
 *  - session : cookie HttpOnly / Secure / SameSite=Lax limité à /admin, identifiant renouvelé à la
 *    connexion, fermée après 8 h d'inactivité ; changer son mot de passe ferme les autres sessions ;
 *  - mot de passe oublié et invitation : lien à usage unique (jeton aléatoire, seule son empreinte est
 *    conservée), valable 1 h (oubli) ou 72 h (invitation).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const VIB_IDLE = 8 * 3600;
const VIB_MAX_FAILS = 5;
const VIB_LOCK = 15 * 60;
const VIB_PW_MIN = 10;

final class AuthError extends RuntimeException {}

function vib_auth_dir(): string
{
    $d = vib_config()['data_dir'] . '/comptes';
    if (!is_dir($d) && !@mkdir($d, 0750, true) && !is_dir($d)) throw new RuntimeException('Dossier des comptes inaccessible');
    return $d;
}

/** Lit et, si $fn est fourni, modifie la liste des comptes sous verrou exclusif. */
function vib_users(?callable $fn = null): array
{
    $dir = vib_auth_dir();
    $lock = fopen("$dir/.lock", 'c');
    flock($lock, $fn ? LOCK_EX : LOCK_SH);
    try {
        $f = "$dir/utilisateurs.json";
        $users = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        if ($fn) {
            $users = $fn($users);
            $tmp = "$f.tmp-" . bin2hex(random_bytes(4));
            file_put_contents($tmp, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            @chmod($tmp, 0600);
            rename($tmp, $f);
        }
        return $users;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function vib_norm_email(string $e): string { return strtolower(trim($e)); }

function vib_user_find(string $email): ?array
{
    foreach (vib_users() as $u) if ($u['email'] === vib_norm_email($email)) return $u;
    return null;
}

function vib_client_ip(): string { return (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? ''); }

// --- Anti-force brute ---------------------------------------------------------------------------

/** @return int secondes restantes de blocage (0 = autorisé) */
function vib_throttle(string $email, bool $record = false, bool $reset = false): int
{
    $f = vib_auth_dir() . '/echecs.json';
    $lock = fopen("$f.lock", 'c');
    flock($lock, LOCK_EX);
    try {
        $now = time();
        $all = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        $keys = ['e:' . hash('sha256', vib_norm_email($email)), 'i:' . hash('sha256', vib_client_ip())];
        foreach ($all as $k => $times) $all[$k] = array_values(array_filter($times, fn($t) => $t > $now - VIB_LOCK));
        $all = array_filter($all);
        if ($reset) unset($all[$keys[0]]);
        if ($record) foreach ($keys as $k) $all[$k][] = $now;
        file_put_contents($f, json_encode($all));
        $wait = 0;
        foreach ([[$keys[0], VIB_MAX_FAILS], [$keys[1], 4 * VIB_MAX_FAILS]] as [$k, $max]) {
            $t = $all[$k] ?? [];
            if (count($t) >= $max) $wait = max($wait, $t[count($t) - $max] + VIB_LOCK - $now);
        }
        return max(0, $wait);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

// --- Session -------------------------------------------------------------------------------------

function vib_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('vib_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/admin', 'secure' => vib_env() !== 'local',
                                'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) VIB_IDLE);
    session_start();
}

/**
 * Vérifie identifiant et mot de passe, puis ouvre la session.
 * @throws AuthError message à afficher tel quel
 */
function vib_login(string $email, string $password): array
{
    $wait = vib_throttle($email);
    if ($wait > 0) throw new AuthError('Trop de tentatives. Réessayez dans ' . (int) ceil($wait / 60) . ' min.');
    $u = vib_user_find($email);
    // Même durée de calcul que le compte existe ou non (pas d'indice sur les adresses enregistrées)
    $ok = password_verify($password, $u['hash'] ?? '$2y$12$abcdefghijklmnopqrstuuJ5n1Zl3d4h0b0bVQ8k5C9wq3XgYc1bG') && $u && empty($u['disabled']) && !empty($u['hash']);
    if (!$ok) {
        vib_throttle($email, true);
        usleep(300000);
        throw new AuthError('Adresse e-mail ou mot de passe incorrect.');
    }
    vib_throttle($email, false, true);
    if (password_needs_rehash($u['hash'], PASSWORD_DEFAULT)) {
        $h = password_hash($password, PASSWORD_DEFAULT);
        vib_users(fn($all) => array_map(fn($x) => $x['email'] === $u['email'] ? ['hash' => $h] + $x : $x, $all));
    }
    vib_users(fn($all) => array_map(fn($x) => $x['email'] === $u['email'] ? ['last_login' => date(DATE_ATOM)] + $x : $x, $all));
    vib_session_start();
    session_regenerate_id(true);
    $_SESSION = ['email' => $u['email'], 'since' => time(), 'seen' => time()];
    return $u;
}

function vib_logout(): void
{
    vib_session_start();
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

/** Utilisateur connecté, ou null (session absente, expirée, compte désactivé ou mot de passe changé depuis). */
function vib_session_user(): ?array
{
    if (empty($_COOKIE['vib_admin'])) return null;
    vib_session_start();
    $email = $_SESSION['email'] ?? null;
    if (!$email || time() - (int) ($_SESSION['seen'] ?? 0) > VIB_IDLE) { vib_logout(); return null; }
    $u = vib_user_find($email);
    if (!$u || !empty($u['disabled']) || (int) ($_SESSION['since'] ?? 0) < strtotime((string) ($u['pw_changed_at'] ?? '1970-01-01'))) { vib_logout(); return null; }
    $_SESSION['seen'] = time();
    return $u;
}

// --- Mot de passe : définir, changer, liens ------------------------------------------------------

function vib_password_problem(string $pw, string $confirm): ?string
{
    if (mb_strlen($pw) < VIB_PW_MIN) return 'Le mot de passe doit contenir au moins ' . VIB_PW_MIN . ' caractères.';
    if ($pw !== $confirm) return 'Les deux mots de passe ne sont pas identiques.';
    return null;
}

/** Enregistre un nouveau mot de passe ; les autres sessions de ce compte sont fermées. */
function vib_set_password(string $email, string $pw): void
{
    $h = password_hash($pw, PASSWORD_DEFAULT);
    $now = date(DATE_ATOM, time() - 1);
    vib_users(fn($all) => array_map(fn($x) => $x['email'] === vib_norm_email($email)
        ? ['hash' => $h, 'pw_changed_at' => $now, 'token' => null] + $x : $x, $all));
}

/** Crée un lien à usage unique (oubli : 1 h ; invitation : 72 h). Renvoie le jeton en clair (à envoyer), seule son empreinte est gardée. */
function vib_issue_token(string $email, int $ttl): ?string
{
    $tok = bin2hex(random_bytes(24));
    $found = false;
    vib_users(function ($all) use ($email, $tok, $ttl, &$found) {
        foreach ($all as &$x) if ($x['email'] === vib_norm_email($email) && empty($x['disabled'])) {
            $x['token'] = ['hash' => hash('sha256', $tok), 'exp' => time() + $ttl];
            $found = true;
        }
        return $all;
    });
    return $found ? $tok : null;
}

/** Compte correspondant à un lien encore valable, ou null. */
function vib_token_user(string $tok): ?array
{
    if (!preg_match('/^[0-9a-f]{48}$/', $tok)) return null;
    foreach (vib_users() as $u) {
        $t = $u['token'] ?? null;
        if ($t && hash_equals($t['hash'], hash('sha256', $tok)) && $t['exp'] > time() && empty($u['disabled'])) return $u;
    }
    return null;
}

/** Ajoute un compte (sans mot de passe : la personne le choisit via son lien d'invitation). */
function vib_user_add(string $email, string $name, string $role): void
{
    $email = vib_norm_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AuthError('Adresse e-mail invalide.');
    if (!in_array($role, ['admin', 'editeur'], true)) throw new AuthError('Rôle inconnu.');
    vib_users(function ($all) use ($email, $name, $role) {
        foreach ($all as $x) if ($x['email'] === $email) throw new AuthError('Ce compte existe déjà.');
        $all[] = ['email' => $email, 'name' => trim($name), 'role' => $role, 'hash' => '', 'created_at' => date(DATE_ATOM)];
        return $all;
    });
}

// --- Envoi des liens par e-mail (réglages SMTP du formulaire de contact, fichier .env) ------------

function vib_mail(string $to, string $subject, string $text): bool
{
    $env = [];
    foreach (@file(vib_config()['repo_dir'] . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
        if ($l[0] === '#' || !str_contains($l, '=')) continue;
        [$k, $v] = explode('=', $l, 2);
        $env[trim($k)] = trim(trim($v), "\"'");
    }
    if (empty($env['SMTP_HOST']) || empty($env['SMTP_USER']) || empty($env['SMTP_PASS'])) {
        error_log('VIB comptes : SMTP non configuré, e-mail non envoyé à ' . $to);
        return false;
    }
    require_once vib_config()['repo_dir'] . '/vendor/phpmailer/PHPMailer.php';
    require_once vib_config()['repo_dir'] . '/vendor/phpmailer/SMTP.php';
    require_once vib_config()['repo_dir'] . '/vendor/phpmailer/Exception.php';
    $m = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host = $env['SMTP_HOST'];
        $m->Port = (int) ($env['SMTP_PORT'] ?? 587);
        $m->SMTPAuth = true;
        $m->Username = $env['SMTP_USER'];
        $m->Password = $env['SMTP_PASS'];
        $m->SMTPSecure = strtolower($env['SMTP_SECURE'] ?? 'tls') === 'ssl' ? 'ssl' : 'tls';
        $m->CharSet = 'UTF-8';
        $m->setFrom($env['SMTP_FROM'] ?? $env['SMTP_USER'], 'VILLAGE Iraty-BIARRITZ');
        $m->addAddress($to);
        $m->Subject = $subject;
        $m->Body = $text;
        $m->send();
        return true;
    } catch (Throwable $e) {
        error_log('VIB comptes : envoi impossible à ' . $to . ' (' . $e->getMessage() . ')');
        return false;
    }
}

function vib_site_url(): string
{
    return match (vib_env()) { 'test' => 'https://test.village-iraty-biarritz.fr', 'prod' => 'https://village-iraty-biarritz.fr', default => 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') };
}
