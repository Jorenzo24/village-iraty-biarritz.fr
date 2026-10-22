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
    foreach ($store->retired(false) as $r) if (($r['item']['slug'] ?? null) === $slug) { $rec = $r; break; }
    if (!$rec) $error = 'Cette fiche a déjà été réintégrée.';
    else {
        try {
            $store->addItem($rec['item'], $user['email'], 'Réintégration de la fiche « ' . $rec['item']['name'] . ' » (' . $slug . ')');
            header('Location: ' . $col['edit'] . '?slug=' . rawurlencode($slug) . '&ok=reintegre', true, 303);
            exit;
        } catch (StoreConflict $ex) { $error = $ex->getMessage(); }
    }
}

$names = array_column($store->read()['items'], 'name', 'slug');
$log = $store->history(150);
$retired = $store->retired(false);

vib_admin_header('Historique', $user, 'accueil');
?>
  <p><a class="adm-back" href="/admin/">← Accueil</a></p>
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
    <h2>Fiches dépubliées</h2>
    <?php if (!$retired): ?>
      <p class="adm-muted">Aucune fiche dépubliée.</p>
    <?php else: ?>
      <ul class="adm-history">
        <?php foreach ($retired as $r): ?>
          <li>
            <span><strong><?= e($r['item']['name']) ?></strong> · dépubliée <?= e(vib_when((string) $r['at'])) ?> par <?= e(vib_author_label((string) $r['author'])) ?></span>
            <form method="post" action="/admin/publier.php">
              <input type="hidden" name="csrf" value="<?= e(vib_csrf_token($user['email'])) ?>">
              <input type="hidden" name="c" value="<?= e($col['c']) ?>">
              <input type="hidden" name="slug" value="<?= e($r['item']['slug']) ?>">
              <button type="submit" class="adm-btn adm-btn--ghost adm-btn--small">Republier</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="adm-card">
    <h2>Dernières modifications</h2>
    <ol class="adm-feed">
      <?php foreach ($log as $en): $d = vib_log_describe($en, $names); ?>
        <li>
          <span class="adm-feed__dot adm-feed__dot--<?= e($d['kind']) ?>" aria-hidden="true"></span>
          <div>
            <?php if ($d['name'] !== null): ?>
              <p><?php if (isset($names[$d['slug']])): ?><a href="<?= e($col['edit'] . '?slug=' . rawurlencode($d['slug'])) ?>#historique"><strong><?= e($d['name']) ?></strong></a><?php else: ?><strong><?= e($d['name']) ?></strong><?php endif; ?></p>
            <?php endif; ?>
            <p class="adm-feed__what"><?= e($d['label']) ?></p>
            <p class="adm-feed__meta"><?= e(vib_when((string) $en['at'])) ?> · <?= e(vib_author_label((string) $en['author'])) ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
<?php vib_admin_footer();
