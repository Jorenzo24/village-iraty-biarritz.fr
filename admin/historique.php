<?php
/** Back-office VIB — journal de toutes les modifications, et fiches retirées (réintégrables), par type de fiche. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();

$col = vib_admin_collection();
$store = new Store($col['c']);
$store->seedIfEmpty();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    $slug = (string) ($_POST['slug'] ?? '');
    $rec = null;
    foreach ($store->retired() as $r) if (($r['item']['slug'] ?? null) === $slug) { $rec = $r; break; }
    if (!$rec) $error = 'Cette fiche a déjà été réintégrée.';
    else {
        try {
            $store->addItem($rec['item'], $user['email'], 'Réintégration de la fiche « ' . $rec['item']['name'] . ' » (' . $slug . ')');
            header('Location: ' . $col['edit'] . '?slug=' . rawurlencode($slug) . '&ok=reintegre', true, 303);
            exit;
        } catch (StoreConflict $ex) { $error = $ex->getMessage(); }
    }
}

$live = array_flip(array_column($store->read()['items'], 'slug'));
$log = $store->history(150);
$retired = $store->retired();

vib_admin_header('Historique', $user, 'historique');
?>
  <div>
    <h1>Historique</h1>
    <p class="adm-muted">Toutes les modifications, la plus récente en haut. Pour revenir en arrière sur une fiche, ouvrez-la : son historique est en bas de page.</p>
  </div>
  <nav class="adm-tabs" aria-label="Type de fiche">
    <?php foreach (['entreprises', 'locaux'] as $c): $d = vib_admin_collection($c); ?>
      <a href="/admin/historique.php?c=<?= e($c) ?>"<?= $c === $col['c'] ? ' aria-current="page"' : '' ?>><?= e($d['title']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>

  <section class="adm-card" id="retirees">
    <h2>Fiches retirées</h2>
    <?php if (!$retired): ?>
      <p class="adm-muted">Aucune fiche retirée depuis la mise en place du back-office.</p>
    <?php else: ?>
      <ul class="adm-history">
        <?php foreach ($retired as $r): ?>
          <li>
            <span><strong><?= e($r['item']['name']) ?></strong> · retirée le <?= e(date('d/m/Y à H:i', strtotime($r['at']))) ?> par <?= e($r['author']) ?></span>
            <form method="post" action="/admin/historique.php">
              <input type="hidden" name="csrf" value="<?= e(vib_csrf_token($user['email'])) ?>">
              <input type="hidden" name="c" value="<?= e($col['c']) ?>">
              <input type="hidden" name="slug" value="<?= e($r['item']['slug']) ?>">
              <button type="submit" class="adm-btn adm-btn--ghost adm-btn--small">Réintégrer sur le site</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="adm-card">
    <h2>Dernières modifications</h2>
    <ol class="adm-history">
      <?php foreach ($log as $en):
        $k = $en['key'] ?? (preg_match('/\(([a-z0-9-]+)\)/', (string) $en['message'], $m) ? $m[1] : null);
        $msg = preg_replace('/ \([a-z0-9-]+\)/', '', (string) $en['message']); ?>
        <li>
          <span><strong><?= e(date('d/m/Y à H:i', strtotime($en['at']))) ?></strong> · <?= e($en['author']) ?></span>
          <span><?php if ($k && isset($live[$k])): ?><a href="<?= e($col['edit'] . '?slug=' . rawurlencode($k)) ?>#historique"><?= e($msg) ?></a><?php else: ?><?= e($msg) ?><?php endif; ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
<?php vib_admin_footer();
