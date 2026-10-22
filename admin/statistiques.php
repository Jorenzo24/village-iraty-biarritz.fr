<?php
/** Back-office VIB — statistiques d'audience (anonymes) : fiches vues, appels, clics, provenance, appareils. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
require __DIR__ . '/../lib/stats.php';
$user = vib_admin_user();

$per = vib_stats_period();
$s = vib_stats_range($per['from'], $per['to']);
$q = vib_stats_query($per);

// Noms et catégories des fiches (y compris retirées, pour les jours où elles étaient en ligne)
$info = ['entreprises' => [], 'locaux' => []];
foreach (['entreprises', 'locaux'] as $c) {
    $st = new Store($c);
    foreach ($st->retired() as $r) $info[$c][$r['item']['slug']] = $r['item'] + ['_gone' => true];
    foreach (vib_public_items($c) as $it) $info[$c][$it['slug']] = $it;
}
$label = fn(string $c, string $slug): string => $info[$c][$slug]['name'] ?? $slug;

// Export tableur : une ligne par fiche
if (($_GET['format'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="statistiques-vib-' . $s['from'] . '-au-' . $s['to'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // accents lisibles dans Excel
    fputcsv($out, ['Type', 'Fiche', 'Catégorie', 'Vues', 'Appels', 'Clics site web', 'E-mails', 'Réseaux sociaux'], ';');
    foreach (['entreprises' => ['acteur', 'Acteur'], 'locaux' => ['local', 'Local']] as $c => [$pre, $type]) {
        foreach ($info[$c] as $slug => $it) {
            if (!empty($it['_gone'])) continue;
            $m = vib_stats_page($s, "/$pre/$slug");
            fputcsv($out, [$type, $it['name'], $it['category_label'] ?? '', $m['v'], $m['tel'] ?? 0, $m['web'] ?? 0, $m['mail'] ?? 0, $m['social'] ?? 0], ';');
        }
    }
    exit;
}

// Ligne 1 : les visites ; ligne 2 : les contacts générés pour les acteurs
$kpi = [
    ['Visiteurs uniques', $s['visitors'], 'chacun compté une fois par jour'],
    ['Fiches acteurs vues', array_sum(vib_stats_top($s, '/acteur/', 'v', 10000)), 'toutes fiches confondues'],
    ['Annonces de locaux vues', array_sum(vib_stats_top($s, '/local/', 'v', 10000)), 'page « Louer un local » non comprise'],
    ['Appels', vib_stats_sum($s, 'tel', '/acteur/'), 'clics sur « Appeler »'],
    ['Clics vers leur site', vib_stats_sum($s, 'web', '/acteur/'), 'visiteurs envoyés aux acteurs'],
    ['E-mails et réseaux', vib_stats_sum($s, 'mail', '/acteur/') + vib_stats_sum($s, 'social', '/acteur/'), 'e-mail, Facebook, Instagram…'],
];

// Vues par catégorie d'acteurs
$byCat = [];
foreach (vib_stats_top($s, '/acteur/', 'v', 10000) as $slug => $n) {
    $cat = $info['entreprises'][$slug]['category_label'] ?? 'Autre';
    $byCat[$cat] = ($byCat[$cat] ?? 0) + $n;
}
arsort($byCat);

$pages = ['/' => 'Accueil', '/activites' => 'Annuaire des acteurs', '/louer-un-local' => 'Louer un local', '/le-village' => 'Le VILLAGE',
          '/services' => 'Services', '/contact' => 'Contact', '/faq' => 'Questions fréquentes', '/a-propos' => 'À propos', '/nos-articles' => 'Articles', '/regie-vib' => 'Régie VIB'];
$sitePages = [];
foreach ($pages as $p => $l) if (!empty($s['views'][$p])) $sitePages[$l] = $s['views'][$p];
arsort($sitePages);
$srcLabels = ['moteur' => 'Moteurs de recherche (Google…)', 'direct' => 'Accès direct, favoris', 'reseaux' => 'Réseaux sociaux', 'autre_site' => 'Autres sites (liens)'];
$devLabels = ['mobile' => 'Mobile', 'ordinateur' => 'Ordinateur', 'tablette' => 'Tablette'];
$relabel = function (array $data, array $labels): array { $o = []; foreach ($data as $k => $n) $o[$labels[$k] ?? $k] = $n; arsort($o); return $o; };

/** Classement en barres horizontales ; $links : libellé → lien. */
$ranking = function (string $title, array $rows, string $unit, array $links = [], string $empty = 'Pas encore de données sur la période.'): void {
    echo '<section class="adm-card adm-rank"><h2>' . e($title) . '</h2>';
    if (!$rows) { echo '<p class="adm-muted">' . e($empty) . '</p></section>'; return; }
    $max = max($rows) ?: 1;
    echo '<ol>';
    foreach ($rows as $l => $n) {
        $name = isset($links[$l]) ? '<a href="' . e($links[$l]) . '">' . e((string) $l) . '</a>' : e((string) $l);
        echo '<li><span class="adm-rank__name">' . $name . '</span><span class="adm-rank__val">' . (int) $n . ' <small>' . e($unit) . '</small></span>'
           . '<span class="adm-rank__bar"><span style="width:' . round($n / $max * 100, 1) . '%"></span></span></li>';
    }
    echo '</ol></section>';
};
$named = function (array $top, string $c) use ($label, $info, $q): array {
    $rows = []; $links = [];
    foreach ($top as $slug => $n) {
        $l = $label($c, (string) $slug) . (!empty($info[$c][$slug]['_gone']) ? ' (retirée)' : '');
        $rows[$l] = $n;
        if (empty($info[$c][$slug]['_gone'])) $links[$l] = '/admin/stats-fiche.php?' . http_build_query(['c' => $c, 'slug' => (string) $slug] + $q);
    }
    return [$rows, $links];
};

vib_admin_header('Statistiques', $user, 'statistiques', true);
?>
  <div class="adm-head">
    <div>
      <h1>Statistiques</h1>
      <p class="adm-muted">Du <?= e(date('d/m/Y', strtotime($s['from']))) ?> au <?= e(date('d/m/Y', strtotime($s['to']))) ?> · mesure anonyme, sans cookie, robots exclus.</p>
    </div>
    <a class="adm-btn adm-btn--ghost adm-btn--small" href="?<?= e(http_build_query($q + ['format' => 'csv'])) ?>">Exporter (tableur)</a>
  </div>
  <?= vib_stats_picker($per) ?>

  <?php if (!$s['first'] || $s['first'] > $s['from']): ?>
    <div class="adm-flash adm-flash--info" role="status">La mesure a démarré <?= $s['first'] ? 'le ' . e(date('d/m/Y', strtotime($s['first']))) : 'aujourd’hui' ?> : les chiffres s’étoffent jour après jour.</div>
  <?php endif; ?>

  <div class="adm-kpis">
    <?php foreach ($kpi as [$l, $n, $hint]): ?>
      <div class="adm-stat"><span class="adm-stat__label"><?= e($l) ?></span><span class="adm-stat__value"><?= number_format((int) $n, 0, ',', "\u{202F}") ?></span><span class="adm-stat__meta"><?= e($hint) ?></span></div>
    <?php endforeach; ?>
  </div>

  <section class="adm-card">
    <div class="adm-card__head"><h2>Visiteurs par jour</h2><span class="adm-legend"><i class="adm-legend__v"></i> visiteurs <i class="adm-legend__c"></i> appels et clics</span></div>
    <?= vib_stats_chart($s['daily']) ?>
  </section>

  <div class="adm-ranks">
    <?php [$r, $l] = $named(vib_stats_top($s, '/acteur/', 'v'), 'entreprises'); $ranking('Fiches les plus vues', $r, 'vues', $l); ?>
    <?php [$r, $l] = $named(vib_stats_top($s, '/acteur/', 'tel'), 'entreprises'); $ranking('Les plus appelés', $r, 'appels', $l, 'Aucun appel depuis une fiche sur la période.'); ?>
    <?php [$r, $l] = $named(vib_stats_top($s, '/acteur/', 'web'), 'entreprises'); $ranking('Clics vers leur site web', $r, 'clics', $l, 'Aucun clic vers un site sur la période.'); ?>
    <?php $ranking('Vues par catégorie', $byCat, 'vues'); ?>
    <?php [$r, $l] = $named(vib_stats_top($s, '/local/', 'v'), 'locaux'); $ranking('Locaux les plus consultés', $r, 'vues', $l); ?>
    <?php $ranking('Pages du site', $sitePages, 'vues'); ?>
    <?php $ranking('D’où viennent les visiteurs', $relabel($s['src'], $srcLabels), 'visiteurs'); ?>
    <?php $ranking('Appareils', $relabel($s['dev'], $devLabels), 'visiteurs'); ?>
  </div>
  <p class="adm-hint">Les bloqueurs de publicité empêchent une partie des mesures : les chiffres sont légèrement sous-estimés, jamais gonflés. Un même visiteur n’est compté qu’une fois par jour pour une même page ou une même action.</p>
<?php vib_admin_footer();
