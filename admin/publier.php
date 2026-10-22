<?php
/** Back-office VIB — republier une fiche dépubliée (acteur ou local), telle qu'elle était au moment du retrait. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/', true, 303); exit; }
vib_csrf_check($user);

$col = vib_admin_collection();
$store = new Store($col['c']);
$slug = (string) ($_POST['slug'] ?? '');
$rec = null;
foreach ($store->retired(false) as $r) if (($r['item']['slug'] ?? null) === $slug) { $rec = $r; break; }
if ($rec) {
    try {
        $store->addItem($rec['item'], $user['email'], 'Réintégration de la fiche « ' . $rec['item']['name'] . ' » (' . $slug . ')');
        header('Location: ' . $col['edit'] . '?slug=' . rawurlencode($slug) . '&ok=reintegre', true, 303);
        exit;
    } catch (StoreConflict) { /* déjà republiée entre-temps */ }
}
header('Location: ' . $col['list'], true, 303);
