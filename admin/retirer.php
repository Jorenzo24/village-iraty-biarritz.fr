<?php
/** Back-office VIB — retrait d'une fiche acteur (confirmation, puis retrait réintégrable). */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/fields.php';
$user = vib_admin_user();

$store = new Store('entreprises');
$slug = (string) ($_POST['slug'] ?? $_GET['slug'] ?? '');
$item = vib_find($store->read()['items'], $slug);
$error = '';

if ($item && $_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    try {
        $store->retireItem($slug, (string) ($_POST['hash'] ?? ''), $user['email']);
        header('Location: /admin/acteurs.php?retire=' . rawurlencode($item['name']), true, 303);
        exit;
    } catch (StoreConflict $ex) {
        $error = $ex->getMessage();
        $item = vib_find($store->read()['items'], $slug);
    }
}

vib_admin_header('Retirer une fiche', $user, 'acteurs');
if (!$item):
    http_response_code(404); ?>
  <p><a class="adm-back" href="/admin/acteurs.php">← Tous les acteurs</a></p>
  <div class="adm-flash adm-flash--ko">Cette fiche n’existe pas (ou a déjà été retirée).</div>
<?php else: ?>
  <p><a class="adm-back" href="/admin/acteur.php?slug=<?= e(rawurlencode($slug)) ?>">← Retour à la fiche</a></p>
  <h1>Retirer « <?= e($item['name']) ?> » ?</h1>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>
  <div class="adm-card">
    <p>À utiliser quand l’acteur a quitté le VILLAGE. Concrètement :</p>
    <ul>
      <li>la fiche disparaît du site, et son adresse <strong>/acteur/<?= e($slug) ?></strong> redirige vers l’annuaire (pas de page d’erreur pour les visiteurs ni pour Google) ;</li>
      <li>rien n’est effacé : la fiche, ses photos et son historique sont conservés, et elle peut être réintégrée à tout moment depuis la page Historique.</li>
    </ul>
    <form method="post" action="/admin/retirer.php" class="adm-inline-form">
      <input type="hidden" name="csrf" value="<?= e(vib_csrf_token($user['email'])) ?>">
      <input type="hidden" name="slug" value="<?= e($slug) ?>">
      <input type="hidden" name="hash" value="<?= e(Store::itemHash($item)) ?>">
      <button type="submit" class="adm-btn">Retirer la fiche</button>
      <a class="adm-btn adm-btn--ghost" href="/admin/acteur.php?slug=<?= e(rawurlencode($slug)) ?>">Annuler</a>
    </form>
  </div>
<?php endif;
vib_admin_footer();
