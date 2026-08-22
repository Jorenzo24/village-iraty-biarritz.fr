<?php
/**
 * VIB - Traitement du formulaire de contact
 * Lit la config SMTP depuis .env, envoie via PHPMailer.
 */

declare(strict_types=1);

// ─── 1. Sécurité : POST uniquement ───
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// ─── 2. Charger la config .env ───
function load_env(string $path): array {
    if (!file_exists($path)) {
        return [];
    }
    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Retirer les guillemets éventuels
        if (preg_match('/^"(.*)"$/', $value, $m)) {
            $value = $m[1];
        } elseif (preg_match("/^'(.*)'$/", $value, $m)) {
            $value = $m[1];
        }
        $env[$key] = $value;
    }
    return $env;
}

$env = load_env(__DIR__ . '/.env');

// ─── 2 bis. Outils anti-spam ───────────────────────────────────────────────

/**
 * IP reelle du visiteur.
 * Le site est derriere Cloudflare : REMOTE_ADDR est l'IP d'un edge Cloudflare,
 * pas celle du visiteur. La vraie IP arrive dans CF-Connecting-IP.
 */
function client_ip(): string {
    $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        return $cf;
    }
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : 'unknown';
}

/**
 * Journalise un rejet dans logs/spam.log (TSV : date, IP, raison, detail, extrait).
 * Sert a verifier qu'il n'y a pas de faux positifs. Le dossier logs/ est
 * interdit d'acces par .htaccess et ignore par Git.
 */
function spam_log(string $reason, string $ip, string $message, string $detail = ''): void {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $excerpt = (string) preg_replace('/\s+/u', ' ', $message);
    $excerpt = mb_substr(trim($excerpt), 0, 100);
    $line = sprintf(
        "%s\t%s\t%s\t%s\t%s\n",
        date('c'),
        $ip,
        $reason,
        str_replace(["\t", "\n"], ' ', $detail),
        $excerpt
    );
    @file_put_contents($dir . '/spam.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Reponse « faux succes » : strictement identique a un envoi reussi, pour ne
 * donner aucun indice au bot (honeypot + filtrage de contenu).
 */
function fake_success(): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'message' => 'Message envoyé avec succès']);
    exit;
}

/**
 * Verifie un token Turnstile aupres de Cloudflare (cURL, timeout 10 s).
 * Retourne ['ok' => bool, 'reason' => string, 'transport' => bool]
 * transport = true → panne reseau/API (on laisse passer, cf. commentaire plus bas).
 */
function turnstile_verify(string $secret, string $token, string $ip): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'reason' => 'curl_absent', 'transport' => true];
    }

    $fields = ['secret' => $secret, 'response' => $token];
    if ($ip !== 'unknown') {
        $fields['remoteip'] = $ip;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'reason' => 'curl:' . $err, 'transport' => true];
    }

    $json = json_decode((string) $raw, true);
    if (!is_array($json) || !array_key_exists('success', $json)) {
        return ['ok' => false, 'reason' => 'reponse_illisible', 'transport' => true];
    }

    if ($json['success'] === true) {
        return ['ok' => true, 'reason' => '', 'transport' => false];
    }

    $codes = $json['error-codes'] ?? [];
    return [
        'ok'        => false,
        'reason'    => is_array($codes) ? implode(',', $codes) : 'echec',
        'transport' => false,
    ];
}

/**
 * Filtrage de contenu (couche 3). Retourne la raison du rejet, ou null si OK.
 */
function spam_reason(string $message): ?string {
    // a) Toute URL est refusee : les vrais visiteurs n'en mettent pas.
    if (preg_match('~https?://|www\.~i', $message)) {
        return 'url';
    }

    // b) Vocabulaire typique des spams crypto.
    foreach (['token', 'tokens', 'USD', 'crypto', 'bitcoin', 'balance', 'credited'] as $mot) {
        if (preg_match('/\b' . preg_quote($mot, '/') . '\b/i', $message)) {
            return 'mot_cle:' . strtolower($mot);
        }
    }

    // c) Message d'une certaine longueur sans le moindre mot francais courant.
    if (mb_strlen($message) > 20) {
        $fr = ['le', 'la', 'les', 'de', 'des', 'un', 'une', 'pour', 'bonjour', 'merci', 'je', 'nous', 'vous'];
        if (!preg_match('/\b(' . implode('|', $fr) . ')\b/i', $message)) {
            return 'aucun_mot_francais';
        }
    }

    return null;
}

$ip = client_ip();

if (empty($env['SMTP_HOST']) || empty($env['SMTP_USER']) || empty($env['SMTP_PASS']) || empty($env['SMTP_TO'])) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Configuration serveur incomplète']);
    exit;
}

// ─── 3. Couche 2 : honeypot ───
// Champ caché "website" (hors écran dans contact.html) : s'il est rempli,
// c'est un bot. On simule un succès pour ne pas lui apprendre qu'il est filtré.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    spam_log('honeypot', $ip, (string) ($_POST['message'] ?? ''), (string) ($_POST['email'] ?? ''));
    fake_success();
}

// ─── 4. Rate limit basique (par IP, fichier temp) ───
$rate_file = sys_get_temp_dir() . '/vib_rate_' . md5($ip) . '.txt';
$now = time();
if (file_exists($rate_file)) {
    $last = (int) file_get_contents($rate_file);
    if ($now - $last < 30) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Merci d\'attendre quelques secondes avant de renvoyer un message']);
        exit;
    }
}

// ─── 5. Validation des champs ───
$nom = trim((string) ($_POST['nom'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$telephone = trim((string) ($_POST['telephone'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$sujet = trim((string) ($_POST['sujet'] ?? 'general'));

$errors = [];
if ($nom === '' || mb_strlen($nom) < 2 || mb_strlen($nom) > 100) {
    $errors[] = 'Nom invalide';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email invalide';
}
if ($message === '' || mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
    $errors[] = 'Message trop court ou trop long';
}
if ($telephone !== '' && !preg_match('/^[\d\s\+\-\.\(\)]{6,30}$/', $telephone)) {
    $errors[] = 'Téléphone invalide';
}

if (!empty($errors)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => implode(', ', $errors)]);
    exit;
}

// ─── 5 bis. Couche 1 : Cloudflare Turnstile ───
// Verification du token AVANT tout envoi de mail.
$turnstile_secret = trim((string) ($env['TURNSTILE_SECRET_KEY'] ?? ''));

if ($turnstile_secret === '' || $turnstile_secret === 'replace_me') {
    // Pas de secret configure : on ne bloque pas le formulaire (les couches 2
    // et 3 restent actives), mais on le trace pour que ce ne soit pas silencieux.
    spam_log('turnstile_non_configure', $ip, $message, $email);
} else {
    $token = (string) ($_POST['cf-turnstile-response'] ?? '');

    if ($token === '') {
        spam_log('turnstile_token_absent', $ip, $message, $email);
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => 'Vérification anti-robot manquante. Merci de cocher la case de sécurité puis de renvoyer le message.',
        ]);
        exit;
    }

    $check = turnstile_verify($turnstile_secret, $token, $ip);

    if (!$check['ok'] && $check['transport']) {
        // Panne reseau ou API Cloudflare injoignable : on laisse passer plutot
        // que de couper le seul canal de contact du site. C'est trace dans le log.
        spam_log('turnstile_indisponible', $ip, $message, $check['reason']);
    } elseif (!$check['ok']) {
        // Verdict negatif de Cloudflare : rejet ferme.
        spam_log('turnstile_echec', $ip, $message, $check['reason']);
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => 'La vérification anti-robot a échoué. Merci de réessayer ; si le problème persiste, contactez-nous par téléphone.',
        ]);
        exit;
    }
}

// ─── 5 ter. Couche 3 : filtrage du contenu ───
// Même stratégie que le honeypot : faux succès silencieux, aucun mail envoyé.
$reason = spam_reason($message);
if ($reason !== null) {
    spam_log('contenu:' . $reason, $ip, $message, $email);
    fake_success();
}

// ─── 6. Envoyer l'email via PHPMailer ───
require __DIR__ . '/vendor/phpmailer/Exception.php';
require __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require __DIR__ . '/vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP as SMTPClient;

$sujet_libelle = match ($sujet) {
    'installer-entreprise' => 'Demande : Installer mon entreprise',
    'louer-local' => 'Demande : Louer un local',
    'fastlink-10mbps' => 'Demande : Fastlink 10 Mbps',
    'fastlink-20mbps' => 'Demande : Fastlink 20 Mbps',
    'fastlink-50mbps' => 'Demande : Fastlink 50 Mbps',
    default => 'Contact général',
};

$mail = new PHPMailer(true);

try {
    // Serveur SMTP
    $mail->isSMTP();
    $mail->Host = $env['SMTP_HOST'];
    $mail->Port = (int) $env['SMTP_PORT'];
    $mail->SMTPAuth = true;
    $mail->Username = $env['SMTP_USER'];
    $mail->Password = $env['SMTP_PASS'];

    $secure = strtolower($env['SMTP_SECURE'] ?? 'tls');
    if ($secure === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($secure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }

    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    // $mail->SMTPDebug = SMTPClient::DEBUG_SERVER; // Activer pour debug

    // Expéditeur
    $mail->setFrom($env['SMTP_FROM'], $env['SMTP_FROM_NAME'] ?? 'VILLAGE Iraty-Biarritz');
    $mail->addReplyTo($email, $nom);

    // Destinataire
    $mail->addAddress($env['SMTP_TO'], $env['SMTP_TO_NAME'] ?? '');

    // Copies (CC) — liste séparée par des virgules dans SMTP_CC, optionnel
    if (!empty($env['SMTP_CC'])) {
        foreach (explode(',', $env['SMTP_CC']) as $cc) {
            $cc = trim($cc);
            if ($cc !== '' && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($cc);
            }
        }
    }

    // Contenu
    $mail->Subject = '[VIB] ' . $sujet_libelle . ' - ' . $nom;

    $html_body = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#222;max-width:600px;margin:0 auto;">';
    $html_body .= '<div style="background:#b21927;color:#fff;padding:20px;text-align:center;">';
    $html_body .= '<h2 style="margin:0;">VILLAGE Iraty-Biarritz</h2>';
    $html_body .= '<p style="margin:5px 0 0;font-size:14px;">Nouveau message du site web</p>';
    $html_body .= '</div>';
    $html_body .= '<div style="padding:20px;background:#f7f7f5;">';
    $html_body .= '<p><strong>Sujet :</strong> ' . htmlspecialchars($sujet_libelle) . '</p>';
    $html_body .= '<p><strong>Nom :</strong> ' . htmlspecialchars($nom) . '</p>';
    $html_body .= '<p><strong>Email :</strong> <a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a></p>';
    if ($telephone !== '') {
        $html_body .= '<p><strong>Téléphone :</strong> ' . htmlspecialchars($telephone) . '</p>';
    }
    $html_body .= '<p><strong>Message :</strong></p>';
    $html_body .= '<div style="background:#fff;padding:15px;border-left:4px solid #b21927;white-space:pre-wrap;">' . htmlspecialchars($message) . '</div>';
    $html_body .= '<p style="margin-top:20px;font-size:12px;color:#888;">Envoyé depuis village-iraty-biarritz.fr - IP : ' . htmlspecialchars($ip) . '</p>';
    $html_body .= '</div></body></html>';

    $text_body = "Nouveau message - VILLAGE Iraty-Biarritz\n";
    $text_body .= "==========================================\n\n";
    $text_body .= "Sujet : $sujet_libelle\n";
    $text_body .= "Nom : $nom\n";
    $text_body .= "Email : $email\n";
    if ($telephone !== '') {
        $text_body .= "Téléphone : $telephone\n";
    }
    $text_body .= "\nMessage :\n$message\n\n";
    $text_body .= "-\nIP : $ip\n";

    $mail->isHTML(true);
    $mail->Body = $html_body;
    $mail->AltBody = $text_body;

    $mail->send();

    // Save rate limit timestamp
    @file_put_contents($rate_file, (string) $now);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'message' => 'Message envoyé avec succès']);
} catch (Exception $e) {
    error_log('[VIB] SMTP error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Erreur lors de l\'envoi. Réessayez ou contactez-nous directement.']);
}
