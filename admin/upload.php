<?php
/**
 * Back-office VIB — réception d'une photo (ou d'un logo d'acteur) pour une fiche (appelé par admin.js).
 * L'image est enregistrée mais la fiche n'est PAS modifiée : elle n'apparaît sur le site qu'au clic
 * sur « Enregistrer » (même contrôle de conflit que le reste du formulaire).
 */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/render.php';
require __DIR__ . '/../lib/images.php';
$user = vib_admin_user();

header('Content-Type: application/json; charset=utf-8');
$fail = function (int $code, string $msg): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') $fail(405, 'Méthode non autorisée');
// Envoi plus gros que la limite du serveur : PHP vide $_POST et $_FILES sans autre signe
if (!$_POST && !$_FILES) $fail(413, 'Image trop lourde pour le serveur (2 Mo maximum).');
vib_csrf_check($user);

$slug = (string) ($_POST['slug'] ?? '');
$kind = (string) ($_POST['kind'] ?? '');
$c = (string) ($_POST['c'] ?? 'entreprises');
if (!in_array($c, ['entreprises', 'locaux'], true)) $fail(400, 'Type de fiche inconnu');
if (!in_array($kind, $c === 'locaux' ? ['photo'] : ['photo', 'logo'], true)) $fail(400, 'Type d’image inconnu');
if (!vib_find(vib_public_items($c), $slug)) $fail(404, 'Fiche introuvable');

$f = $_FILES['file'] ?? null;
if (!$f || !is_array($f) || is_array($f['error'])) $fail(400, 'Aucune image reçue');
if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) $fail(413, 'Image trop lourde pour le serveur (2 Mo maximum).');
if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) $fail(400, 'Envoi interrompu, réessayez.');

try {
    $path = vib_image_store($f['tmp_name'], $kind, $slug, $c);
} catch (ImageRejected $e) {
    $fail(422, $e->getMessage());
} catch (Throwable $e) {
    error_log('VIB upload : ' . $e->getMessage());
    $fail(500, 'Enregistrement impossible, réessayez.');
}
echo json_encode(['ok' => true, 'path' => $path], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
