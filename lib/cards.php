<?php
/**
 * Cartes des listes publiques (annuaire, louer un local, accueil), fabriquées depuis les fiches du coffre.
 *
 * Le balisage reproduit À L'IDENTIQUE les cartes écrites à la main jusqu'en octobre 2026 : toute
 * évolution de la carte se fait ici, et nulle part ailleurs.
 *
 * Horaires : la carte n'a plus son propre horaire à saisir. Son attribut data-hours (format attendu par
 * main.js : « Mo 09:00-12:00,14:00-18:00;Tu … ») est calculé depuis le texte « hours » de la fiche,
 * avec la même lecture que le badge de la fiche (open-status.js) : carte et fiche ne peuvent plus diverger.
 */

declare(strict_types=1);

require_once __DIR__ . '/render.php';
require_once __DIR__ . '/hours.php';

/**
 * Lecture « tolérante » d'un texte d'horaires, identique à parseHours() de open-status.js.
 * @return list<array{int,int,int}>|null [jour 0=lundi, début, fin] en minutes ; null = pas de badge
 */
function vib_hours_intervals(string $text): ?array
{
    if (trim($text) === '') return null;
    $norm = vib_hours_norm($text);
    if (preg_match('/24\s*h\s*\/\s*24/u', $norm)) return array_map(fn($d) => [$d, 0, 1440], range(0, 6));
    if (preg_match('/sur\s+rendez/u', $norm)) return null;
    $out = [];
    $any = false;
    foreach (preg_split('/\s*·\s*|\n+/u', $text) as $seg) {
        $seg = trim($seg);
        if ($seg === '') continue;
        $days = vib_hours_days($seg);
        // Comme extractTimes() : « 15h3 » se lit 15h00 (minutes sur un seul chiffre ignorées)
        preg_match_all('/(\d{1,2})\s*[hH:](\d{0,2})/u', $seg, $m, PREG_SET_ORDER);
        $times = [];
        foreach ($m as [, $h, $mi]) {
            $min = strlen($mi) === 2 ? (int) $mi : 0;
            if ((int) $h > 24 || $min > 59) continue;
            $times[] = (int) $h * 60 + $min;
        }
        if ($days === null) {
            if (count($times) >= 2) $days = range(0, 6); else continue;
        }
        if (preg_match('/\bferme[es]*\b/u', vib_hours_norm($seg))) { $any = true; continue; }
        if (count($times) < 2) continue;
        for ($i = 0; $i + 1 < count($times); $i += 2) foreach ($days as $d) $out[] = [$d, $times[$i], $times[$i + 1]];
        $any = true;
    }
    return $any && $out ? $out : null;
}

/** data-hours d'une carte (format main.js), '' = pas de badge. */
function vib_hours_osm(string $text): string
{
    $iv = vib_hours_intervals($text);
    if (!$iv) return '';
    $codes = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
    $fmt = fn(int $m): string => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    $byDay = [];
    foreach ($iv as [$d, $a, $b]) {
        // 24h/24 : écrit « 00:00-23:59 » comme sur les cartes d'origine ; fin à minuit : « 24:00 »
        $byDay[$d][] = ($a === 0 && $b === 1440) ? '00:00-23:59' : $fmt($a) . '-' . $fmt($b);
    }
    ksort($byDay);
    $out = [];
    foreach ($byDay as $d => $slots) $out[] = $codes[$d] . ' ' . implode(',', $slots);
    return implode(';', $out);
}

/** Accroche automatique d'une carte : début de la description, coupé sur un mot (≈ 100 caractères). */
function vib_card_excerpt_auto(string $desc, int $max = 100): string
{
    $s = trim(preg_replace('/\s+/u', ' ', preg_replace('/\n\s*[•\-]\s*/u', ' ', $desc)));
    if (mb_strlen($s) <= $max) return $s;
    $t = mb_substr($s, 0, $max);
    $sp = mb_strrpos($t, ' ');
    if ($sp !== false) $t = mb_substr($t, 0, $sp);
    return rtrim($t, " ,;:.-–") . '…';
}

/** Initiales affichées à la place de la photo : « ASSOCIATION BLAK » → « AB ». */
function vib_initials(string $name): string
{
    $w = preg_split('/[\s\-\']+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    return mb_strtoupper(implode('', array_map(fn($x) => mb_substr($x, 0, 1), array_slice($w, 0, 2))));
}

/** Carte d'un acteur dans l'annuaire (/activites). */
function vib_card_actor(array $e): string
{
    $url = '/acteur/' . $e['slug'];
    $osm = vib_hours_osm((string) ($e['hours'] ?? ''));
    $desc = ($e['card_desc'] ?? '') !== '' ? $e['card_desc'] : vib_card_excerpt_auto((string) $e['description']);
    $photo = $e['photos'][0] ?? '';
    $media = $photo !== ''
        ? '<a href="' . ha($url) . '" class="acteur-card__media">' . "\n"
          . '              <img src="' . ha($photo) . '" alt="' . ha($e['name'] . ', VILLAGE Iraty-Biarritz') . '" loading="lazy">'
        : '<a href="' . ha($url) . '" class="acteur-card__media acteur-card__media--ph" aria-label="' . ha($e['name'] . ', voir la fiche') . '">' . "\n"
          . '              <span class="acteur-card__ph" aria-hidden="true">' . h(vib_initials($e['name'])) . '</span>';
    return '<li>
          <article class="acteur-card" data-cat="' . ha($e['category']) . '" data-name="' . ha(mb_strtolower($e['name'] . ' ' . $e['category_label'])) . '"' . ($osm !== '' ? ' data-hours="' . ha($osm) . '"' : '') . '>
            ' . $media . '
              <span class="badge badge--category">' . h($e['category_label']) . '</span>
              <span class="badge acteur-card__status" data-status hidden></span>
            </a>
            <div class="acteur-card__body">
              <h3 class="acteur-card__name">' . h($e['name']) . '</h3>' . ($desc !== '' ? '
              <p class="acteur-card__desc">' . h($desc) . '</p>' : '') . '
              <a href="' . ha($url) . '" class="acteur-card__more">Voir la fiche →</a>
            </div>
          </article>
        </li>';
}

/** Prix d'une carte de local : « 1&nbsp;165&nbsp;€ » (entier, comme sur les cartes d'origine). */
function vib_card_price($n): string
{
    return number_format((float) $n, floor((float) $n) == (float) $n ? 0 : 2, ',', "\u{00A0}") . "\u{00A0}€";
}

/** Champs de la carte d'un local, avec repli automatique si la carte n'a pas été rédigée. */
function vib_card_local_texts(array $l): array
{
    [$ref, $title] = array_pad(explode(' - ', (string) $l['name'], 2), 2, '');
    $first = preg_match('/^(.+?[.!?])(\s|$)/u', (string) $l['description'], $m) ? $m[1] : vib_card_excerpt_auto((string) $l['description']);
    return [
        'ref'   => ($l['card_ref'] ?? '') !== '' ? $l['card_ref'] : $ref,
        'title' => ($l['card_title'] ?? '') !== '' ? $l['card_title'] : ($title !== '' ? $title : (string) $l['name']),
        'sub'   => ($l['card_sub'] ?? '') !== '' ? $l['card_sub'] : $first,
        'alt'   => ($l['card_alt'] ?? '') !== '' ? $l['card_alt'] : $l['name'] . ' au VILLAGE Iraty-BIARRITZ',
    ];
}

/** Carte d'un local (page « Louer un local »). */
function vib_card_local(array $l): string
{
    $t = vib_card_local_texts($l);
    $url = '/local/' . $l['slug'];
    $photo = $l['photos'][0] ?? '/assets-2026/images/story-interieur.jpg';
    return '<li>
          <article class="local-card">
            <a href="' . ha($url) . '" class="local-card__media" aria-label="' . ha(str_replace(' - ', ', ', (string) $l['name'])) . '">
              <img src="' . ha($photo) . '" alt="' . ha($t['alt']) . '" loading="lazy">
              <span class="badge badge--available"><span class="badge__dot"></span>Disponible</span>
            </a>
            <div class="local-card__body">
              <p class="local-card__ref">' . h($t['ref']) . '</p>
              <h3 class="local-card__title">' . h($t['title']) . '</h3>
              <p class="local-card__meta">
                <span class="local-card__surface">' . h(vib_jsnum($l['surface'])) . ' m²</span>
                <span aria-hidden="true" class="local-card__dot">·</span>
                <span class="local-card__price"><span>' . h(vib_card_price($l['price_ht'])) . '</span><span class="local-card__price-unit">/mois HT</span></span>
              </p>
              <p class="local-card__sub">' . h($t['sub']) . '</p>
              <a href="' . ha($url) . '" class="local-card__cta">
                En savoir plus <span class="local-card__arrow" aria-hidden="true">→</span>
              </a>
            </div>
          </article>
        </li>';
}

/** Tri de l'annuaire : alphabétique, accents ignorés (« ÉCOLE » rangée à E). */
function vib_sort_actors(array $items): array
{
    $fold = fn(string $s): string => strtr(mb_strtolower($s), ['à' => 'a', 'â' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u']);
    usort($items, fn($a, $b) => strcmp($fold($a['name']), $fold($b['name'])));
    return $items;
}
