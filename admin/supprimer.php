<?php
/**
 * Back-office VIB — suppression définitive d'une fiche, publiée ou dépubliée (confirmation obligatoire).
 * La fiche disparaît du site et du back-office ; son ancienne adresse reste redirigée vers la liste.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();

$col = vib_admin_collection();
$store = new Store($col['c']);
$slug = (string) ($_POST['slug'] ?? $_GET['slug'] ?? '');
$item = vib_find($store->read()['items'], $slug);   // fiche publiée
$rec = null;                                         // ou fiche dépubliée
if (!$item) foreach ($store->retired(false) as $r) if (($r['item']['slug'] ?? null) === $slug) { $rec = $r; break; }
$name = $item['name'] ?? $rec['item']['name'] ?? null;
$back = $item ? $col['edit'] . '?slug=' . rawurlencode($slug) : $col['list'];
$error = '';

if ($name !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    if (empty($_POST['confirm'])) $error = 'Cochez la case pour confirmer la suppression.';
    else {
        try {
            if ($item) $store->deleteItem($slug, (string) ($_POST['hash'] ?? ''), $user['email']);
            else $store->deleteRetired($slug, $user['email']);
            header('Location: ' . $col['list'] . '?supprime=' . rawurlencode($name), true, 303);
            exit;
        } catch (StoreConflict $ex) { $error = $ex->getMessage(); }
    }
}

vib_admin_header('Supprimer une fiche', $user, $col['nav']);
if ($name === null):
    http_response_code(404); ?>
  <p><a class="adm-back" href="<?= e($col['list']) ?>">← <?= e($col['title']) ?></a></p>
  <div class="adm-flash adm-flash--ko">Cette fiche n’existe pas (ou a déjà été supprimée).</div>
<?php else: ?>
  <p><a class="adm-back" href="<?= e($back) ?>">← <?= $item ? 'Retour à la fiche' : e($col['title']) ?></a></p>
  <h1>Supprimer « <?= e($name) ?> » ?</h1>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>
  <div class="adm-card">
    <p>La fiche disparaît du site et du back-office, définitivement.</p>
    <?php if (!empty($col['unpublish']) && $item): ?>
      <p class="adm-muted">Le local pourrait redevenir disponible ? Mieux vaut le <a href="<?= e(vib_admin_url($col, 'retirer.php', ['slug' => $slug])) ?>">dépublier</a> : il sera mis de côté, prêt à être republié tel quel.</p>
    <?php endif; ?>
    <form method="post" action="/admin/supprimer.php" class="adm-inline-form adm-inline-form--col">
      <input type="hidden" name="csrf" value="<?= e(vib_csrf_token($user['email'])) ?>">
      <input type="hidden" name="c" value="<?= e($col['c']) ?>">
      <input type="hidden" name="slug" value="<?= e($slug) ?>">
      <?php if ($item): ?><input type="hidden" name="hash" value="<?= e(Store::itemHash($item)) ?>"><?php endif; ?>
      <label class="adm-check"><input type="checkbox" name="confirm" value="1"> Je confirme la suppression définitive de « <?= e($name) ?> »</label>
      <div class="adm-inline-form">
        <button type="submit" class="adm-btn adm-btn--danger">Supprimer définitivement</button>
        <a class="adm-btn adm-btn--ghost" href="<?= e($back) ?>">Annuler</a>
      </div>
    </form>
  </div>
<?php endif;
vib_admin_footer();
