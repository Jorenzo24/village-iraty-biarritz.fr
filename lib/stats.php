<?php
/**
 * Lecture des statistiques d'audience collectées par api/stat.php (totaux par jour, anonymes).
 * Fichiers : <données>/stats/AAAA-MM.json (sur le site de test : ceux de la production, cf. config).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Totaux sur les $days derniers jours (aujourd'hui compris).
 * @return array{days: array<string,array>, from: string, to: string, first: ?string,
 *   visitors: int, views: array<string,int>, events: array<string,array<string,int>>,
 *   src: array<string,int>, dev: array<string,int>, daily: array<string,array{visitors:int,views:int,contacts:int}>}
 */
function vib_stats(int $days): array
{
    $dir = vib_config()['stats_dir'];
    $to = date('Y-m-d');
    $from = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
    $all = [];
    foreach (glob("$dir/*.json") ?: [] as $f) {
        if (!preg_match('/(\d{4}-\d{2})\.json$/', $f)) continue;
        foreach ((array) json_decode((string) file_get_contents($f), true) as $day => $d) $all[$day] = $d;
    }
    ksort($all);
    $out = ['from' => $from, 'to' => $to, 'first' => array_key_first($all), 'visitors' => 0, 'views' => [], 'events' => [], 'src' => [], 'dev' => [], 'daily' => []];
    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) $out['daily'][date('Y-m-d', $t)] = ['visitors' => 0, 'views' => 0, 'contacts' => 0];
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
