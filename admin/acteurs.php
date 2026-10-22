<?php
/** Back-office VIB — liste des fiches acteurs : recherche, catégories, fiches à compléter. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();

$items = vib_public_items('entreprises');
usort($items, fn($a, $b) => strcmp(vib_fold($a['name']), vib_fold($b['name'])));
$counts = array_count_values(array_column($items, 'category'));
$gaps = [];
foreach ($items as $it) $gaps[$it['slug']] = vib_item_gaps('entreprises', $it);
$todo = count(array_filter($gaps));

vib_admin_header('Acteurs', $user, 'acteurs', true);
?>
  <div class="adm-head">
    <div>
      <h1>Acteurs</h1>
      <p class="adm-muted"><?= count($items) ?> fiches publiées dans l’annuaire. Cliquez sur une fiche pour la modifier.</p>
    </div>
    <a class="adm-btn" href="/admin/nouveau.php?c=entreprises">+ Ajouter un acteur</a>
  </div>
  <?php if (($_GET['supprime'] ?? '') !== ''): ?>
    <div class="adm-flash adm-flash--ok" role="status"><strong>« <?= e((string) $_GET['supprime']) ?> » supprimé.</strong> La fiche a disparu de l’annuaire ; son ancienne adresse redirige vers l’annuaire.</div>
  <?php endif; ?>

  <div class="adm-toolbar" id="adm-filters">
    <label class="adm-search">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input type="search" id="adm-q" placeholder="Rechercher un nom, une adresse…" aria-label="Rechercher" autocomplete="off">
    </label>
    <div class="adm-pills" role="group" aria-label="Catégorie">
      <button type="button" class="adm-pill is-active" data-cat="" aria-pressed="true">Toutes <span><?= count($items) ?></span></button>
      <?php foreach (VIB_CATEGORIES as $k => $label): ?>
        <button type="button" class="adm-pill" data-cat="<?= e($k) ?>" aria-pressed="false"><?= e($label) ?> <span><?= (int) ($counts[$k] ?? 0) ?></span></button>
      <?php endforeach; ?>
      <button type="button" class="adm-pill adm-pill--todo" data-todo aria-pressed="false">À compléter <span><?= $todo ?></span></button>
    </div>
  </div>

  <ul class="adm-rows" id="adm-list">
    <?php foreach ($items as $it): $g = $gaps[$it['slug']]; ?>
      <li data-cat="<?= e($it['category']) ?>" data-search="<?= e(vib_fold($it['name'] . ' ' . $it['address'] . ' ' . $it['slug'])) ?>"<?= $g ? ' data-todo' : '' ?>>
        <a class="adm-rowcard" href="/admin/acteur.php?slug=<?= e(rawurlencode($it['slug'])) ?>">
          <?php if (!empty($it['photos'])): ?>
            <img class="adm-rowcard__img" src="<?= e($it['photos'][0]) ?>" alt="" loading="lazy">
          <?php else: ?>
            <span class="adm-rowcard__img adm-rowcard__ph" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($it['name'], 0, 1))) ?></span>
          <?php endif; ?>
          <span class="adm-rowcard__main">
            <span class="adm-rowcard__name"><?= e($it['name']) ?></span>
            <span class="adm-rowcard__sub"><span class="adm-tag"><?= e($it['category_label']) ?></span> <?= e($it['address'] ?: 'Adresse non renseignée') ?></span>
          </span>
          <span class="adm-rowcard__side">
            <?php foreach ($g as $x): ?><span class="adm-chip"><?= e($x) ?></span><?php endforeach; ?>
            <span class="adm-rowcard__go" aria-hidden="true">→</span>
          </span>
        </a>
      </li>
    <?php endforeach; ?>
    <li class="adm-empty" id="adm-none" hidden>Aucune fiche ne correspond.</li>
  </ul>
<?php vib_admin_footer('/admin/admin.js?v=20261022d');
