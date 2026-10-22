<?php
/** Back-office VIB — dépublier une fiche (acteur ou local) : elle quitte le site mais reste conservée, prête à être republiée. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();

$col = vib_admin_collection();
if (empty($col['unpublish'])) {   // acteurs : pas de dépublication, seulement la suppression
    header('Location: ' . vib_admin_url($col, 'supprimer.php', ['slug' => (string) ($_GET['slug'] ?? '')]), true, 303);
    exit;
}
$store = new Store($col['c']);
$slug = (string) ($_POST['slug'] ?? $_GET['slug'] ?? '');
$item = vib_find($store->read()['items'], $slug);
$error = '';
$edit = $col['edit'] . '?slug=' . rawurlencode($slug);

if ($item && $_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    try {
        $store->retireItem($slug, (string) ($_POST['hash'] ?? ''), $user['email']);
        header('Location: ' . $col['list'] . '?retire=' . rawurlencode($item['name']) . '#depubliees', true, 303);
        exit;
    } catch (StoreConflict $ex) {
        $error = $ex->getMessage();
        $item = vib_find($store->read()['items'], $slug);
    }
}

vib_admin_header('Dépublier une fiche', $user, $col['nav']);
if (!$item):
    http_response_code(404); ?>
  <p><a class="adm-back" href="<?= e($col['list']) ?>">← <?= e($col['title']) ?></a></p>
  <div class="adm-flash adm-flash--ko">Cette fiche n’existe pas (ou a déjà été dépubliée).</div>
<?php else: ?>
  <p><a class="adm-back" href="<?= e($edit) ?>">← Retour à la fiche</a></p>
  <h1>Dépublier « <?= e($item['name']) ?> » ?</h1>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>
  <div class="adm-card">
    <p>À utiliser <?= e($col['leave']) ?>. Concrètement :</p>
    <ul>
      <li>la fiche disparaît du site, et son adresse <strong><?= e($col['public'] . $slug) ?></strong> redirige vers <?= e($col['gone_label']) ?> (pas de page d’erreur pour les visiteurs ni pour Google) ;</li>
      <li>rien n’est effacé : la fiche reste dans la liste <?= e($col['title']) ?>, grisée, avec un bouton « Republier » pour la remettre en ligne telle quelle.</li>
    </ul>
    <form method="post" action="/admin/retirer.php" class="adm-inline-form">
      <input type="hidden" name="csrf" value="<?= e(vib_csrf_token($user['email'])) ?>">
      <input type="hidden" name="c" value="<?= e($col['c']) ?>">
      <input type="hidden" name="slug" value="<?= e($slug) ?>">
      <input type="hidden" name="hash" value="<?= e(Store::itemHash($item)) ?>">
      <button type="submit" class="adm-btn">Dépublier la fiche</button>
      <a class="adm-btn adm-btn--ghost" href="<?= e($edit) ?>">Annuler</a>
    </form>
  </div>
<?php endif;
vib_admin_footer();
