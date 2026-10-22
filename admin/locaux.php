<?php
/** Back-office VIB — liste des locaux à louer, en cartes. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();

$items = vib_public_items('locaux');

vib_admin_header('Locaux à louer', $user, 'locaux', true);
?>
  <div class="adm-head">
    <div>
      <h1>Locaux à louer</h1>
      <p class="adm-muted"><?= count($items) ?> <?= count($items) > 1 ? 'locaux publiés' : 'local publié' ?> sur la page « Louer un local ». Un local loué se dépublie depuis sa fiche, et se republie quand il est de nouveau disponible.</p>
    </div>
    <a class="adm-btn" href="/admin/nouveau.php?c=locaux">+ Ajouter un local</a>
  </div>
  <?php if (($_GET['retire'] ?? '') !== ''): ?>
    <div class="adm-flash adm-flash--ok" role="status"><strong>« <?= e((string) $_GET['retire']) ?> » dépublié.</strong> Il n’apparaît plus sur le site (son adresse redirige vers la page « Louer un local ») et reste en bas de cette page, prêt à être republié.</div>
  <?php elseif (($_GET['supprime'] ?? '') !== ''): ?>
    <div class="adm-flash adm-flash--ok" role="status"><strong>« <?= e((string) $_GET['supprime']) ?> » supprimé définitivement.</strong></div>
  <?php endif; ?>

  <ul class="adm-cards">
    <?php foreach ($items as $it): $g = vib_item_gaps('locaux', $it); ?>
      <li>
        <a class="adm-lcard" href="/admin/local.php?slug=<?= e(rawurlencode($it['slug'])) ?>">
          <span class="adm-lcard__media">
            <?php if (!empty($it['photos'])): ?><img src="<?= e($it['photos'][0]) ?>" alt="" loading="lazy"><?php endif; ?>
            <span class="adm-lcard__price"><?= e(vib_price($it['price_ht'])) ?> / mois</span>
          </span>
          <span class="adm-lcard__body">
            <span class="adm-lcard__title"><?= e($it['name']) ?></span>
            <span class="adm-lcard__meta"><?= e(vib_num_to_input($it['surface'])) ?> m²<?= $it['type'] ? ' · ' . e($it['type']) : '' ?></span>
            <?php if ($g): ?><span class="adm-rowcard__side"><?php foreach ($g as $x): ?><span class="adm-chip"><?= e($x) ?></span><?php endforeach; ?></span><?php endif; ?>
          </span>
        </a>
      </li>
    <?php endforeach; ?>
    <li>
      <a class="adm-lcard adm-lcard--add" href="/admin/nouveau.php?c=locaux">
        <span aria-hidden="true">+</span>
        Ajouter un local
      </a>
    </li>
  </ul>
<?php vib_admin_unpublished(vib_admin_collection('locaux'), $user); ?>
<?php vib_admin_footer();
