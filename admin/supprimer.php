<?php
/** Back-office VIB — suppression définitive d'une fiche déjà dépubliée (confirmation obligatoire). */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();

$col = vib_admin_collection();
$store = new Store($col['c']);
$slug = (string) ($_POST['slug'] ?? $_GET['slug'] ?? '');
$rec = null;
foreach ($store->retired(false) as $r) if (($r['item']['slug'] ?? null) === $slug) { $rec = $r; break; }
$error = '';

if ($rec && $_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    if (empty($_POST['confirm'])) $error = 'Cochez la case pour confirmer la suppression.';
    else {
        try {
            $store->deleteRetired($slug, $user['email']);
            header('Location: ' . $col['list'] . '?supprime=' . rawurlencode($rec['item']['name']), true, 303);
            exit;
        } catch (StoreConflict $ex) { $error = $ex->getMessage(); }
    }
}

vib_admin_header('Supprimer une fiche', $user, $col['nav']);
if (!$rec):
    http_response_code(404); ?>
  <p><a class="adm-back" href="<?= e($col['list']) ?>">← <?= e($col['title']) ?></a></p>
  <div class="adm-flash adm-flash--ko">Seule une fiche dépubliée peut être supprimée. Dépubliez-la d’abord depuis sa page.</div>
<?php else: ?>
  <p><a class="adm-back" href="<?= e($col['list']) ?>">← <?= e($col['title']) ?></a></p>
  <h1>Supprimer définitivement « <?= e($rec['item']['name']) ?> » ?</h1>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>
  <div class="adm-card">
    <p>La fiche disparaîtra du back-office et ne pourra plus être republiée. Pour la mettre seulement de côté, inutile de la supprimer : une fiche dépubliée reste invisible sur le site.</p>
    <p class="adm-muted">Son ancienne adresse <?= e($col['public'] . $slug) ?> continuera de rediriger vers <?= e($col['gone_label']) ?>.</p>
    <form method="post" action="/admin/supprimer.php" class="adm-inline-form adm-inline-form--col">
      <input type="hidden" name="csrf" value="<?= e(vib_csrf_token($user['email'])) ?>">
      <input type="hidden" name="c" value="<?= e($col['c']) ?>">
      <input type="hidden" name="slug" value="<?= e($slug) ?>">
      <label class="adm-check"><input type="checkbox" name="confirm" value="1"> Je confirme la suppression définitive de cette fiche</label>
      <div class="adm-inline-form">
        <button type="submit" class="adm-btn adm-btn--danger">Supprimer définitivement</button>
        <a class="adm-btn adm-btn--ghost" href="<?= e($col['list']) ?>">Annuler</a>
      </div>
    </form>
  </div>
<?php endif;
vib_admin_footer();
