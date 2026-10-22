<?php
/**
 * Mesure d'audience du site, anonyme et sans cookie (lue par l'onglet « Statistiques » du back-office).
 *
 * Reçoit de assets-2026/js/main.js : la page vue, ou le clic sur un lien de contact d'une fiche
 * (téléphone, e-mail, site web, réseau social). N'enregistre QUE des totaux par jour :
 *  - aucune adresse IP ni identifiant conservés ;
 *  - pour ne pas compter deux fois la même personne dans la journée, une empreinte de la visite est
 *    calculée avec un sel aléatoire du jour ; sel et empreintes sont effacés le lendemain (impossible
 *    ensuite de relier deux journées ou de retrouver quelqu'un) ;
 *  - robots exclus (ils n'exécutent généralement pas le JavaScript, et leur signature est filtrée).
 *
 * Autonome : ne dépend pas du reste du back-office (fonctionne aussi sur la production actuelle).
 * Fichiers : <données>/stats/AAAA-MM.json  →  { "AAAA-MM-JJ": { "v": {page: n}, "e": {page: {tel: n, …}},
 *            "visiteurs": n, "src": {moteur: n, …}, "dev": {mobile: n, ordinateur: n} } }
 */

declare(strict_types=1);

http_response_code(204);
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

$raw = (string) file_get_contents('php://input', false, null, 0, 2048);
$in = json_decode($raw, true);
if (!is_array($in)) exit;

$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|pingdom|monitor|curl|wget|python|httpclient|facebookexternalhit|whatsapp|embedly|uptime/i', $ua)) exit;

// Page : seules les pages connues du site sont comptées (rien d'arbitraire n'est écrit)
$path = strtolower(rtrim((string) ($in['p'] ?? ''), '/')) ?: '/';
if (!preg_match('#^/[a-z0-9/\-]{0,90}$#', $path)) exit;
$ev = (string) ($in['e'] ?? 'v');
if (!in_array($ev, ['v', 'tel', 'mail', 'web', 'social'], true)) exit;

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$home = '/home/villageiratybiar';
$dataDir = getenv('VIB_DATA_DIR') ?: match ($host) {
    'village-iraty-biarritz.fr', 'www.village-iraty-biarritz.fr' => $home . '/vib-data',
    'test.village-iraty-biarritz.fr' => $home . '/vib-data-test',
    default => dirname(__DIR__) . '/.local-data',
};

$pages = ['/', '/le-village', '/activites', '/louer-un-local', '/services', '/a-propos', '/contact', '/nos-articles', '/faq', '/regie-vib'];
if (!in_array($path, $pages, true)) {
    if (!preg_match('#^/(acteur|local)/([a-z0-9\-]+)$#', $path, $m)) exit;
    $c = $m[1] === 'acteur' ? 'entreprises' : 'locaux';
    // Fiches existantes : coffre du back-office s'il existe, sinon fichier du dépôt
    $src = is_file("$dataDir/$c.json") ? "$dataDir/$c.json" : dirname(__DIR__) . "/data/$c.json";
    $slugs = array_column((array) json_decode((string) @file_get_contents($src), true), 'slug');
    if (!in_array($m[2], $slugs, true)) exit;
} elseif ($ev !== 'v' && $ev !== 'tel' && $ev !== 'mail') {
    exit;   // pages générales : vues, et clics d'appel / e-mail vers le VILLAGE
}

$dir = "$dataDir/stats";
if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) exit;
$day = date('Y-m-d');

$lock = @fopen("$dir/.lock", 'c');
if (!$lock || !flock($lock, LOCK_EX)) exit;
try {
    // Sel du jour (aléatoire) ; ceux des jours précédents et leurs empreintes sont effacés
    $saltFile = "$dir/.sel-$day";
    if (!is_file($saltFile)) {
        foreach (glob("$dir/.sel-*") ?: [] as $f) @unlink($f);
        foreach (glob("$dir/.vu-*") ?: [] as $f) @unlink($f);
        file_put_contents($saltFile, bin2hex(random_bytes(32)));
    }
    $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    $salt = (string) file_get_contents($saltFile);
    $visitor = substr(hash('sha256', "$salt|$ip|$ua"), 0, 16);                 // le visiteur, pour la journée
    $mark = substr(hash('sha256', "$salt|$ip|$ua|$path|$ev"), 0, 16);          // ce visiteur, cette page, cette action
    $seenFile = "$dir/.vu-$day";
    $seen = is_file($seenFile) ? (string) file_get_contents($seenFile) : '';
    if (str_contains($seen, $mark)) exit;   // déjà compté aujourd'hui pour cette personne
    file_put_contents($seenFile, $mark . "\n", FILE_APPEND);

    $file = "$dir/" . substr($day, 0, 7) . '.json';
    $data = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    $d = &$data[$day];
    if ($ev === 'v') {
        $d['v'][$path] = ($d['v'][$path] ?? 0) + 1;
        // Provenance et appareil : une fois par visiteur et par jour (première page vue)
        if (!str_contains($seen, "s$visitor")) {
            file_put_contents($seenFile, "s$visitor\n", FILE_APPEND);
            $d['visiteurs'] = ($d['visiteurs'] ?? 0) + 1;
            $ref = strtolower((string) parse_url((string) ($in['r'] ?? ''), PHP_URL_HOST));
            $srcCat = match (true) {
                $ref === '' => 'direct',
                str_contains($ref, 'village-iraty-biarritz') => 'direct',
                (bool) preg_match('/(^|\.)(google|bing|qwant|duckduckgo|ecosia|yahoo|yandex)\./', $ref) => 'moteur',
                (bool) preg_match('/facebook|instagram|linkedin|tiktok|twitter|^t\.co$|x\.com|pinterest|youtube|whatsapp|snapchat/', $ref) => 'reseaux',
                default => 'autre_site',
            };
            $d['src'][$srcCat] = ($d['src'][$srcCat] ?? 0) + 1;
            $dev = preg_match('/Mobi|Android|iPhone|iPod/i', $ua) ? 'mobile' : (preg_match('/iPad|Tablet/i', $ua) ? 'tablette' : 'ordinateur');
            $d['dev'][$dev] = ($d['dev'][$dev] ?? 0) + 1;
        }
    } else {
        $d['e'][$path][$ev] = ($d['e'][$path][$ev] ?? 0) + 1;
    }
    unset($d);
    $tmp = "$file.tmp-" . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES)) !== false) rename($tmp, $file);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
