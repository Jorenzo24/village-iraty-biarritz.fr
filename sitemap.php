<?php
/**
 * Plan du site (/sitemap.xml), fabriqué par le serveur.
 *  - pages fixes : reprises de data/sitemap-base.xml (l'ancien sitemap.xml écrit à la main) ;
 *  - fiches acteurs et locaux : celles du coffre du back-office (une fiche ajoutée y entre, une fiche
 *    retirée en sort), datées de leur dernière modification, sinon de la date du sitemap d'origine.
 */
declare(strict_types=1);
require __DIR__ . '/lib/render.php';

const VIB_SITE = 'https://village-iraty-biarritz.fr';
$base = [];
// Lecture sans l'extension SimpleXML (pas garantie sur le serveur, comme exif)
preg_match_all('#<url>(.*?)</url>#s', (string) @file_get_contents(__DIR__ . '/data/sitemap-base.xml'), $m);
foreach ($m[1] as $u) {
    $tag = fn(string $t): string => preg_match("#<$t>\\s*(.*?)\\s*</$t>#s", $u, $x) ? html_entity_decode($x[1], ENT_XML1) : '';
    $base[$tag('loc')] = ['lastmod' => $tag('lastmod'), 'changefreq' => $tag('changefreq'), 'priority' => $tag('priority')];
}

/** Date de dernière modification de chaque fiche, d'après le journal du back-office. */
$changed = function (string $c): array {
    $out = [];
    foreach ((new Store($c))->history(5000) as $e) {
        if (!isset($e['key'])) continue;
        $d = substr((string) $e['at'], 0, 10);
        if (($out[$e['key']] ?? '') < $d) $out[$e['key']] = $d;
    }
    return $out;
};

$urls = [];
foreach ($base as $loc => $u) {
    if (!preg_match('#^' . preg_quote(VIB_SITE, '#') . '/(acteur|local)/#', $loc)) $urls[$loc] = $u;
}
foreach (['locaux' => 'local', 'entreprises' => 'acteur'] as $c => $prefix) {
    $items = vib_public_items($c);
    if ($c === 'entreprises') usort($items, fn($a, $b) => strcmp($a['slug'], $b['slug']));
    $dates = $changed($c);
    foreach ($items as $it) {
        $loc = VIB_SITE . '/' . $prefix . '/' . $it['slug'];
        $old = $base[$loc] ?? ['lastmod' => '', 'changefreq' => 'monthly', 'priority' => '0.7'];
        $old['lastmod'] = max($old['lastmod'], $dates[$it['slug']] ?? '') ?: date('Y-m-d');
        $urls[$loc] = $old;
    }
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: no-cache');
header('Expires: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n", '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($urls as $loc => $u) {
    echo "    <url>\n        <loc>", htmlspecialchars($loc, ENT_XML1), "</loc>\n";
    if ($u['lastmod'] !== '') echo '        <lastmod>', $u['lastmod'], "</lastmod>\n";
    if ($u['changefreq'] !== '') echo '        <changefreq>', $u['changefreq'], "</changefreq>\n";
    if ($u['priority'] !== '') echo '        <priority>', $u['priority'], "</priority>\n";
    echo "    </url>\n";
}
echo "</urlset>\n";
