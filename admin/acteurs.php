<?php
/** Back-office VIB — liste des fiches acteurs, avec recherche et filtre par catégorie. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/fields.php';
$user = vib_admin_user();

$items = vib_public_items('entreprises');
usort($items, fn($a, $b) => strcmp(vib_fold($a['name']), vib_fold($b['name'])));
$counts = array_count_values(array_column($items, 'category'));

vib_admin_header('Acteurs', $user, 'acteurs');
?>
  <div class="adm-head">
    <div>
      <h1>Acteurs</h1>
      <p class="adm-muted"><?= count($items) ?> fiches publiées. Cliquez sur une fiche pour la modifier.</p>
    </div>
    <a class="adm-btn" href="/admin/nouveau.php?c=entreprises">Ajouter un acteur</a>
  </div>
  <?php if (($_GET['retire'] ?? '') !== ''): ?>
    <div class="adm-flash adm-flash--ok" role="status"><strong>Fiche « <?= e((string) $_GET['retire']) ?> » retirée du site.</strong> Son adresse redirige vers l’annuaire. Elle reste réintégrable depuis l’<a href="/admin/historique.php?c=entreprises#retirees">historique</a>.</div>
  <?php endif; ?>
  <div class="adm-filters">
    <input type="search" id="adm-q" placeholder="Rechercher un nom, une adresse…" aria-label="Rechercher" autocomplete="off">
    <select id="adm-cat" aria-label="Catégorie">
      <option value="">Toutes les catégories (<?= count($items) ?>)</option>
      <?php foreach (VIB_CATEGORIES as $k => $label): ?>
        <option value="<?= e($k) ?>"><?= e($label) ?> (<?= (int) ($counts[$k] ?? 0) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>
  <ul class="adm-list" id="adm-list">
    <?php foreach ($items as $it): ?>
      <li data-cat="<?= e($it['category']) ?>" data-search="<?= e(vib_fold($it['name'] . ' ' . $it['address'] . ' ' . $it['slug'])) ?>">
        <a class="adm-row" href="/admin/acteur.php?slug=<?= e(rawurlencode($it['slug'])) ?>">
          <span class="adm-row__name"><?= e($it['name']) ?></span>
          <span class="adm-row__cat"><?= e($it['category_label']) ?></span>
          <span class="adm-row__addr"><?= e($it['address'] ?: 'Adresse non renseignée') ?></span>
        </a>
      </li>
    <?php endforeach; ?>
    <li class="adm-empty" id="adm-none" hidden>Aucune fiche ne correspond.</li>
  </ul>
<?php vib_admin_footer('/admin/admin.js?v=20261021e');
