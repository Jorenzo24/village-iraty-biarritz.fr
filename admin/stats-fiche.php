<?php
/** Back-office VIB — statistiques d'une fiche (acteur ou local) : vues, appels, clics, rang, évolution. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
require __DIR__ . '/../lib/stats.php';
$user = vib_admin_user();

$col = vib_admin_collection();
$slug = (string) ($_GET['slug'] ?? '');
$it = vib_find(vib_public_items($col['c']), $slug);
if (!$it) foreach ((new Store($col['c']))->retired() as $r) if (($r['item']['slug'] ?? null) === $slug) $it = $r['item'] + ['_gone' => true];
if (!$it) { header('Location: /admin/statistiques.php', true, 303); exit; }

$per = vib_stats_period();
$q = vib_stats_query($per);
$s = vib_stats_range($per['from'], $per['to']);
$path = $col['public'] . $slug;
$m = vib_stats_page($s, $path);
// Période précédente, de même durée, pour comparer
$len = (int) round((strtotime($per['to']) - strtotime($per['from'])) / 86400) + 1;
$prevTo = date('Y-m-d', strtotime($per['from'] . ' -1 day'));
$prev = vib_stats_page(vib_stats_range(date('Y-m-d', strtotime($prevTo . ' -' . ($len - 1) . ' days')), $prevTo), $path);
$hasPrev = vib_stats_raw() && array_key_first(vib_stats_raw()) <= $prevTo;
// Rang parmi les fiches du même type
$all = vib_stats_top($s, $col['public'], 'v', 100000);
$rank = array_search($slug, array_keys($all), true);
$count = count(vib_public_items($col['c']));

$delta = function (int $now, int $before) use ($hasPrev): string {
    if (!$hasPrev) return '';
    if ($before === 0) return $now > 0 ? '<span class="adm-delta adm-delta--up">nouveau</span>' : '';
    $pct = (int) round(($now - $before) / $before * 100);
    return '<span class="adm-delta adm-delta--' . ($pct >= 0 ? 'up' : 'down') . '">' . ($pct >= 0 ? '+' : '') . $pct . ' %</span>';
};
$cells = [['Vues de la fiche', $m['v'], $prev['v']]];
if ($col['c'] === 'entreprises') {
    $cells[] = ['Appels', $m['tel'] ?? 0, $prev['tel'] ?? 0];
    $cells[] = ['Clics vers le site', $m['web'] ?? 0, $prev['web'] ?? 0];
    $cells[] = ['E-mails et réseaux', ($m['mail'] ?? 0) + ($m['social'] ?? 0), ($prev['mail'] ?? 0) + ($prev['social'] ?? 0)];
}

vib_admin_header('Statistiques : ' . $it['name'], $user, 'statistiques', true);
?>
  <p><a class="adm-back" href="/admin/statistiques.php?<?= e(http_build_query($q)) ?>">← Toutes les statistiques</a></p>
  <div class="adm-head">
    <div class="adm-head__id">
      <?php if (!empty($it['photos'])): ?><img src="<?= e($it['photos'][0]) ?>" alt=""><?php endif; ?>
      <div>
        <h1><?= e($it['name']) ?></h1>
        <p class="adm-muted"><?= e($it['category_label'] ?? 'Local à louer') ?><?= !empty($it['_gone']) ? ' · fiche retirée' : '' ?> · du <?= e(date('d/m/Y', strtotime($s['from']))) ?> au <?= e(date('d/m/Y', strtotime($s['to']))) ?></p>
      </div>
    </div>
    <?php if (empty($it['_gone'])): ?>
      <div class="adm-head__actions">
        <a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e($col['public'] . rawurlencode($slug)) ?>" target="_blank" rel="noopener">Voir en ligne ↗</a>
        <a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e($col['edit'] . '?slug=' . rawurlencode($slug)) ?>">Modifier la fiche</a>
      </div>
    <?php endif; ?>
  </div>
  <?= vib_stats_picker($per, ['c' => $col['c'], 'slug' => $slug]) ?>

  <div class="adm-kpis adm-kpis--<?= count($cells) ?>">
    <?php foreach ($cells as [$l, $n, $before]): ?>
      <div class="adm-stat"><span class="adm-stat__label"><?= e($l) ?></span><span class="adm-stat__value"><?= (int) $n ?> <?= $delta((int) $n, (int) $before) ?></span>
        <span class="adm-stat__meta"><?= $hasPrev ? 'période précédente : ' . (int) $before : '&nbsp;' ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php if ($rank !== false): ?>
    <p class="adm-rankline"><strong><?= $rank + 1 ?><?= $rank === 0 ? 'ʳᵉ' : 'ᵉ' ?></strong> <?= $col['c'] === 'locaux' ? 'annonce la plus consultée' : 'fiche la plus vue' ?> sur <?= $count ?> sur la période.</p>
  <?php endif; ?>

  <section class="adm-card">
    <div class="adm-card__head"><h2>Vues par jour</h2><span class="adm-legend"><i class="adm-legend__v"></i> vues<?php if ($col['c'] === 'entreprises'): ?> <i class="adm-legend__c"></i> appels et clics<?php endif; ?></span></div>
    <?= vib_stats_chart(vib_stats_daily_page($per['from'], $per['to'], $path), 'vues') ?>
  </section>
  <p class="adm-hint">Un même visiteur n’est compté qu’une fois par jour pour une même action. Les bloqueurs de publicité empêchent une partie des mesures : les chiffres sont légèrement sous-estimés.</p>
<?php vib_admin_footer();
