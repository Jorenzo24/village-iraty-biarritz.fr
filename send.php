<?php
/**
 * VIB — Traitement du formulaire de contact
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

if (empty($env['SMTP_HOST']) || empty($env['SMTP_USER']) || empty($env['SMTP_PASS']) || empty($env['SMTP_TO'])) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Configuration serveur incomplète']);
    exit;
}

// ─── 3. Honeypot anti-spam ───
// Champ caché "website" : s'il est rempli, c'est un bot.
if (!empty($_POST['website'] ?? '')) {
    // Faux succès pour les bots
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'message' => 'Message envoyé']);
    exit;
}

// ─── 4. Rate limit basique (par IP, fichier temp) ───
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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

    // Contenu
    $mail->Subject = '[VIB] ' . $sujet_libelle . ' — ' . $nom;

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
    $html_body .= '<p style="margin-top:20px;font-size:12px;color:#888;">Envoyé depuis village-iraty-biarritz.fr — IP : ' . htmlspecialchars($ip) . '</p>';
    $html_body .= '</div></body></html>';

    $text_body = "Nouveau message — VILLAGE Iraty-Biarritz\n";
    $text_body .= "==========================================\n\n";
    $text_body .= "Sujet : $sujet_libelle\n";
    $text_body .= "Nom : $nom\n";
    $text_body .= "Email : $email\n";
    if ($telephone !== '') {
        $text_body .= "Téléphone : $telephone\n";
    }
    $text_body .= "\nMessage :\n$message\n\n";
    $text_body .= "—\nIP : $ip\n";

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
