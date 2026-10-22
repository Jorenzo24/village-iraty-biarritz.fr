<?php
/**
 * Lecture des statistiques d'audience collectées par api/stat.php (totaux par jour, anonymes).
 * Fichiers : <données>/stats/AAAA-MM.json (sur le site de test : ceux de la production, cf. config).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Tous les jours enregistrés (lus une seule fois par requête), triés. */
function vib_stats_raw(): array
{
    static $all = null;
    if ($all !== null) return $all;
    $all = [];
    foreach (glob(vib_config()['stats_dir'] . '/*.json') ?: [] as $f) {
        if (!preg_match('/(\d{4}-\d{2})\.json$/', $f)) continue;
        foreach ((array) json_decode((string) file_get_contents($f), true) as $day => $d) $all[$day] = $d;
    }
    ksort($all);
    return $all;
}

/** Totaux sur les $days derniers jours (aujourd'hui compris). */
function vib_stats(int $days): array
{
    return vib_stats_range(date('Y-m-d', strtotime('-' . ($days - 1) . ' days')), date('Y-m-d'));
}

/**
 * Totaux du $from au $to inclus (AAAA-MM-JJ).
 * @return array{from: string, to: string, first: ?string, visitors: int, views: array<string,int>,
 *   events: array<string,array<string,int>>, src: array<string,int>, dev: array<string,int>,
 *   daily: array<string,array{visitors:int,views:int,contacts:int}>}
 */
function vib_stats_range(string $from, string $to): array
{
    $all = vib_stats_raw();
    $out = ['from' => $from, 'to' => $to, 'first' => array_key_first($all), 'visitors' => 0, 'views' => [], 'events' => [], 'src' => [], 'dev' => [], 'daily' => []];
    for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) $out['daily'][date('Y-m-d', $t)] = ['visitors' => 0, 'views' => 0, 'contacts' => 0];
    foreach ($all as $day => $d) {
        if ($day < $from || $day > $to) continue;
        $out['visitors'] += (int) ($d['visiteurs'] ?? 0);
        $out['daily'][$day]['visitors'] = (int) ($d['visiteurs'] ?? 0);
        foreach ((array) ($d['v'] ?? []) as $p => $n) {
            $out['views'][$p] = ($out['views'][$p] ?? 0) + $n;
            $out['daily'][$day]['views'] += $n;
        }
        foreach ((array) ($d['e'] ?? []) as $p => $evs) foreach ((array) $evs as $k => $n) {
            $out['events'][$p][$k] = ($out['events'][$p][$k] ?? 0) + $n;
            $out['daily'][$day]['contacts'] += $n;
        }
        foreach (['src', 'dev'] as $k) foreach ((array) ($d[$k] ?? []) as $x => $n) $out[$k][$x] = ($out[$k][$x] ?? 0) + $n;
    }
    arsort($out['views']);
    return $out;
}

/** Courbe d'une seule page (fiche) : vues et contacts (appels + clics) par jour. */
function vib_stats_daily_page(string $from, string $to, string $path): array
{
    $all = vib_stats_raw();
    $out = [];
    for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
        $day = date('Y-m-d', $t);
        $d = $all[$day] ?? [];
        $out[$day] = ['visitors' => (int) ($d['v'][$path] ?? 0), 'contacts' => (int) array_sum((array) ($d['e'][$path] ?? []))];
    }
    return $out;
}

/** Somme d'un type de clic (tel, web, mail, social) sur les pages dont le chemin commence par $prefix. */
function vib_stats_sum(array $s, string $kind, string $prefix = '/'): int
{
    $n = 0;
    foreach ($s['events'] as $p => $evs) if (str_starts_with($p, $prefix)) $n += (int) ($evs[$kind] ?? 0);
    return $n;
}

/** Classement des pages d'un préfixe (/acteur/, /local/) pour un indicateur : vues, ou un type de clic. */
function vib_stats_top(array $s, string $prefix, string $metric = 'v', int $limit = 10): array
{
    $rows = [];
    if ($metric === 'v') {
        foreach ($s['views'] as $p => $n) if (str_starts_with($p, $prefix)) $rows[substr($p, strlen($prefix))] = $n;
    } else {
        foreach ($s['events'] as $p => $evs) if (str_starts_with($p, $prefix) && !empty($evs[$metric])) $rows[substr($p, strlen($prefix))] = (int) $evs[$metric];
    }
    arsort($rows);
    return array_slice($rows, 0, $limit, true);
}

/** Chiffres d'une fiche sur la période : vues et clics. */
function vib_stats_page(array $s, string $path): array
{
    return ['v' => (int) ($s['views'][$path] ?? 0)] + array_map('intval', $s['events'][$path] ?? []);
}

/** Haut de l'axe : 4 paliers ronds au-dessus du maximum (7 → 8 : 2-4-6-8 ; 140 → 200 : 50-100-150-200). */
function vib_nice_max(int $n): int
{
    $raw = max(1, $n) / 4;
    $p = 10 ** (int) floor(log10($raw));
    foreach ($p >= 10 ? [1, 2, 2.5, 5, 10] : [1, 2, 5, 10] as $m) if ($m * $p >= $raw) return (int) max(4, 4 * $m * $p);
    return (int) (40 * $p);
}

/**
 * Courbe en barres (barre claire = visiteurs ou vues, barre foncée = appels et clics), avec axe gradué.
 * @param array<string,array{visitors:int,contacts:int}> $daily
 */
function vib_stats_chart(array $daily, string $what = 'visiteurs'): string
{
    $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $max = vib_nice_max(max(1, ...array_map(fn($d) => max($d['visitors'], $d['contacts']), array_values($daily) ?: [['visitors' => 0, 'contacts' => 0]])));
    $n = max(1, count($daily));
    $svg = '<svg class="adm-chart" viewBox="0 0 ' . ($n * 10) . ' 100" preserveAspectRatio="none" role="img" aria-label="' . $h(ucfirst($what)) . ' par jour">';
    foreach ([0.25, 0.5, 0.75, 1] as $g) $svg .= '<line class="adm-chart__grid" x1="0" x2="' . ($n * 10) . '" y1="' . (100 - $g * 100) . '" y2="' . (100 - $g * 100) . '" vector-effect="non-scaling-stroke"/>';
    $i = 0;
    foreach ($daily as $day => $d) {
        $hv = $d['visitors'] / $max * 100; $hc = $d['contacts'] / $max * 100;
        $svg .= '<g data-day="' . $h(vib_date_court($day)) . '" data-v="' . $d['visitors'] . ' ' . $h($what) . '" data-c="' . $d['contacts'] . ' appels et clics">'
              . '<rect class="adm-chart__hit" x="' . ($i * 10) . '" y="0" width="10" height="100"/>'
              . '<rect class="adm-chart__v" x="' . ($i * 10 + 1) . '" y="' . (100 - $hv) . '" width="8" height="' . $hv . '"/>'
              . '<rect class="adm-chart__c" x="' . ($i * 10 + 3) . '" y="' . (100 - $hc) . '" width="4" height="' . $hc . '"/></g>';
        $i++;
    }
    $svg .= '</svg>';
    $axis = '';
    foreach ([1, 0.75, 0.5, 0.25, 0] as $g) $axis .= '<span style="top:' . ((1 - $g) * 100) . '%">' . (int) round($max * $g) . '</span>';
    $days = array_keys($daily);
    $mid = $days[intdiv(count($days), 2)] ?? null;
    return '<div class="adm-chartbox"><div class="adm-chart__y">' . $axis . '</div>' . $svg . '<div class="adm-tip" hidden></div></div>'
         . '<div class="adm-chart__axis"><span>' . $h(date('d/m', strtotime((string) reset($days)))) . '</span>'
         . ($mid && count($days) > 6 ? '<span>' . $h(date('d/m', strtotime($mid))) . '</span>' : '')
         . '<span>' . $h(date('d/m', strtotime((string) end($days)))) . '</span></div>';
}

/** Période demandée : boutons (?p=7|30|90) ou dates précises (?du=…&au=…). @return array{from:string,to:string,p:?int} */
function vib_stats_period(): array
{
    $today = date('Y-m-d');
    $ok = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    if ($ok($_GET['du'] ?? null) && $ok($_GET['au'] ?? null)) {
        $from = min($_GET['du'], $_GET['au']); $to = min(max($_GET['du'], $_GET['au']), $today);
        if (strtotime($to) - strtotime($from) > 366 * 86400) $from = date('Y-m-d', strtotime($to . ' -366 days'));
        return ['from' => $from, 'to' => $to, 'p' => null];
    }
    $p = in_array((int) ($_GET['p'] ?? 30), [7, 30, 90], true) ? (int) ($_GET['p'] ?? 30) : 30;
    return ['from' => date('Y-m-d', strtotime('-' . ($p - 1) . ' days')), 'to' => $today, 'p' => $p];
}

/** Choix de la période : boutons 7 / 30 / 90 jours et calendrier « du … au … ». $keep : paramètres à conserver. */
function vib_stats_picker(array $per, array $keep = []): string
{
    $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $out = '<div class="adm-period"><div class="adm-pills" role="group" aria-label="Période">';
    foreach ([7 => '7 jours', 30 => '30 jours', 90 => '90 jours'] as $d => $l) {
        $on = $per['p'] === $d;
        $out .= '<a class="adm-pill' . ($on ? ' is-active' : '') . '" href="?' . $h(http_build_query($keep + ['p' => $d])) . '"' . ($on ? ' aria-current="true"' : '') . '>' . $l . '</a>';
    }
    $out .= '</div><form class="adm-dates" method="get">';
    foreach ($keep as $k => $v) $out .= '<input type="hidden" name="' . $h($k) . '" value="' . $h($v) . '">';
    $out .= '<label>Du <input type="date" name="du" value="' . $h($per['from']) . '" max="' . date('Y-m-d') . '" required></label>'
          . '<label>au <input type="date" name="au" value="' . $h($per['to']) . '" max="' . date('Y-m-d') . '" required></label>'
          . '<button type="submit" class="adm-btn adm-btn--small' . ($per['p'] === null ? '' : ' adm-btn--ghost') . '">Afficher</button></form></div>';
    return $out;
}

/** Paramètres d'URL de la période en cours (pour l'export et les liens). */
function vib_stats_query(array $per): array
{
    return $per['p'] === null ? ['du' => $per['from'], 'au' => $per['to']] : ['p' => $per['p']];
}

/** « jeu. 22 oct. » */
function vib_date_court(string $day): string
{
    $t = strtotime($day);
    return ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'][(int) date('w', $t)] . ' ' . (int) date('j', $t) . ' '
         . ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int) date('n', $t) - 1];
}
