<?php
/** Back-office VIB — liste des locaux à louer. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();

$items = vib_public_items('locaux');

vib_admin_header('Locaux à louer', $user, 'locaux');
?>
  <div class="adm-head">
    <div>
      <h1>Locaux à louer</h1>
      <p class="adm-muted"><?= count($items) ?> locaux publiés. Cliquez sur un local pour le modifier.</p>
    </div>
    <a class="adm-btn" href="/admin/nouveau.php?c=locaux">Ajouter un local</a>
  </div>
  <?php if (($_GET['retire'] ?? '') !== ''): ?>
    <div class="adm-flash adm-flash--ok" role="status"><strong>« <?= e((string) $_GET['retire']) ?> » retiré du site.</strong> Son adresse redirige vers la page « Louer un local ». Il reste réintégrable depuis l’<a href="/admin/historique.php?c=locaux#retirees">historique</a>.</div>
  <?php endif; ?>
  <ul class="adm-list">
    <?php foreach ($items as $it): ?>
      <li>
        <a class="adm-row" href="/admin/local.php?slug=<?= e(rawurlencode($it['slug'])) ?>">
          <span class="adm-row__name"><?= e($it['name']) ?></span>
          <span class="adm-row__cat"><?= e(vib_num_to_input($it['surface'])) ?> m² · <?= e(vib_price($it['price_ht'])) ?></span>
          <span class="adm-row__addr"><?= e($it['type'] ?: 'Type non renseigné') ?></span>
        </a>
      </li>
    <?php endforeach; ?>
    <?php if (!$items): ?><li class="adm-empty">Aucun local à louer en ce moment.</li><?php endif; ?>
  </ul>
<?php vib_admin_footer();
