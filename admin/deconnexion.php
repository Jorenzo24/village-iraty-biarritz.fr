<?php
/** Back-office VIB — déconnexion. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
$user = vib_admin_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    vib_logout();
}
header('Location: /admin/connexion.php?ok=deconnecte', true, 303);
