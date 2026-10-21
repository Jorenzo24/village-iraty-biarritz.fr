<?php
/**
 * Rendu HTML côté serveur des fiches publiques (acteurs, locaux).
 *
 * Ces fonctions reproduisent À L'IDENTIQUE le rendu que faisaient les scripts du navigateur
 * (assets-2026/js/entreprise.js, local.js) : même structure, mêmes règles d'affichage des horaires
 * et des descriptions. Toute évolution du rendu se fait ici, et nulle part ailleurs.
 *
 * Les expressions régulières utilisent (*UCP) pour que \s couvre les espaces insécables, comme en
 * JavaScript (les données en contiennent : « Lundi : 16h »).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/store.php';

/** Texte échappé pour un nœud texte (équivalent de textContent → innerHTML). */
function h(?string $s): string { return htmlspecialchars((string) $s, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
/** Valeur échappée pour un attribut. */
function ha(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
/** trim() Unicode, comme String.prototype.trim(). */
function jtrim(string $s): string { return preg_replace('/(*UCP)^\s+|\s+$/u', '', $s); }

/** Fiches publiques d'une collection (coffre de données ; import initial depuis le dépôt si besoin). */
function vib_public_items(string $collection): array
{
    $s = new Store($collection);
    $s->seedIfEmpty();
    return $s->read()['items'];
}

function vib_find(array $items, string $slug): ?array
{
    foreach ($items as $it) if (($it['slug'] ?? null) === $slug) return $it;
    return null;
}

/** Horaires : un segment par ligne, libellé de jour(s) en gras, plages insécables. */
function vib_render_hours(string $text): string
{
    $DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    $DAY = implode('|', $DAYS);
    $SEP = '\s*(?:,|\/|-|–|et|à|au)\s*';
    // Libellés reconnus : « Tous les jours », « Du mardi au samedi », « Le samedi », ou un ou plusieurs jours
    $DAY_LIST_RE = "/(*UCP)^(tous les jours|du\\s+(?:$DAY)\\s+au\\s+(?:$DAY)|(?:le\\s+)?(?:$DAY)(?:$SEP(?:$DAY))*)\\s*:?\\s*/iu";
    $RANGE_RE = '/(*UCP)(?:de\s+)?\d{1,2}\s*[h:]\s*\d{0,2}\s*(?:[-–]|à)\s*\d{1,2}\s*[h:]?\s*\d{0,2}/iu';

    // Une suite de 3 jours consécutifs ou plus est compactée en plage (« Lundi - Vendredi ») ;
    // une liste non consécutive est laissée telle quelle.
    $compact = function (string $label) use ($DAYS, $DAY): string {
        $cap = fn($d) => mb_strtoupper(mb_substr($d, 0, 1)) . mb_substr($d, 1);
        if (preg_match('/^tous les jours$/iu', $label)) return 'Tous les jours';
        if (preg_match('/(*UCP)^le\s/iu', $label)) $label = $cap(preg_replace('/(*UCP)^le\s+/iu', '', $label));   // « Le samedi » → « Samedi »
        preg_match_all("/$DAY/u", mb_strtolower($label), $m);
        $found = $m[0];
        // « Du mardi au samedi » est une plage par définition : même forme que les autres
        if (preg_match('/(*UCP)^du\s/iu', $label) && count($found) === 2) return $cap($found[0]) . ' - ' . $cap($found[1]);
        if (count($found) < 3) return $label;
        $idx = array_map(fn($d) => array_search($d, $DAYS, true), $found);
        foreach ($idx as $i => $v) if ($i > 0 && $v !== $idx[$i - 1] + 1) return $label;
        return $cap($found[0]) . ' - ' . $cap($found[count($found) - 1]);
    };
    $slots = fn(string $str) => preg_replace_callback($RANGE_RE, fn($r) => '<span class="hours-slot">' . $r[0] . '</span>', h($str));

    $items = array_values(array_filter(array_map('jtrim', preg_split('/(*UCP)\s*·\s*|\n+/u', $text)), fn($s) => $s !== ''));
    $out = '';
    foreach ($items as $item) {
        if (preg_match($DAY_LIST_RE, $item, $m)) {
            $days = $compact(jtrim($m[1]));
            $rest = jtrim(substr($item, strlen($m[0])));
            $out .= '<span class="hours-row"><strong>' . h($days) . '</strong> <span>' . $slots($rest !== '' ? $rest : 'Fermé') . '</span></span>';
        } else {
            $out .= '<span class="hours-row">' . $slots($item) . '</span>';
        }
    }
    return $out;
}

/** Description : un paragraphe par ligne ; lignes à puce (● • · - – —) regroupées en liste. */
function vib_render_description(string $text): string
{
    $isBullet = fn($l) => (bool) preg_match('/(*UCP)^\s*[●•·\-–—]\s*\S/u', $l);
    $strip = fn($l) => jtrim(preg_replace('/(*UCP)^\s*[●•·\-–—]\s*/u', '', $l));
    $lines = array_values(array_filter(array_map('jtrim', preg_split('/\n+/', $text)), fn($s) => $s !== ''));
    $out = ''; $buf = [];
    $flush = function () use (&$out, &$buf) {
        if ($buf) { $out .= '<ul>' . implode('', array_map(fn($li) => '<li>' . h($li) . '</li>', $buf)) . '</ul>'; $buf = []; }
    };
    foreach ($lines as $l) {
        if ($isBullet($l)) { $buf[] = $strip($l); }
        else { $flush(); $out .= '<p>' . h($l) . '</p>'; }
    }
    $flush();
    return $out;
}

const VIB_SOCIAL = [
    'facebook'  => ['Facebook', '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>'],
    'instagram' => ['Instagram', '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>'],
    'linkedin'  => ['LinkedIn', '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.36V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13zm1.78 13.02H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0z"/></svg>'],
    'tiktok'    => ['TikTok', '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5.8 20.1a6.34 6.34 0 0 0 10.86-4.43V8.79a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.84-.22z"/></svg>'],
    'twitter'   => ['Twitter / X', '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>'],
];

function vib_render_social(array $social): string
{
    $out = '';
    foreach ($social as $kind => $url) {
        if (!$url) continue;
        [$label, $svg] = VIB_SOCIAL[$kind] ?? [$kind, ''];
        $out .= '<li><a href="' . ha($url) . '" target="_blank" rel="noopener" aria-label="' . ha($label) . '" title="' . ha($label) . '">' . $svg . '</a></li>';
    }
    return $out;
}

/** Données structurées schema.org d'une fiche acteur (désormais visibles des robots : la page arrive remplie). */
function vib_jsonld_actor(array $e, string $url): string
{
    $sameAs = array_values(array_filter(array_merge([$e['website'] ?? ''], array_values($e['social'] ?? []))));
    $data = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $e['name'],
        'description' => $e['description'] ?: null,
        'url' => $url,
        'image' => !empty($e['photos'][0]) ? 'https://village-iraty-biarritz.fr' . $e['photos'][0] : null,
        'logo' => !empty($e['logo']) ? 'https://village-iraty-biarritz.fr' . $e['logo'] : null,
        'telephone' => $e['phone'] ?: null,
        'email' => $e['email'] ?: null,
        'address' => $e['address'] ? ['@type' => 'PostalAddress', 'streetAddress' => $e['address'], 'postalCode' => '64200', 'addressLocality' => 'Biarritz', 'addressCountry' => 'FR'] : null,
        'containedInPlace' => ['@id' => 'https://village-iraty-biarritz.fr/#organization'],
        'sameAs' => $sameAs ?: null,
    ], fn($v) => $v !== null);
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}

/** Nombre écrit comme JavaScript l'écrit dans un gabarit (`${n}`) : 90 → "90", 90.5 → "90.5", 179.0 → "179". */
function vib_jsnum(int|float $n): string
{
    if (is_float($n) && floor($n) === $n && abs($n) < 1e15) return (string) (int) $n;
    return (string) $n;
}

/** Prix comme toLocaleString('fr-FR', 2 décimales) : « 1 165,00 € HT » (espace fine insécable U+202F). */
function vib_price(int|float $p): string
{
    return number_format((float) $p, 2, ',', "\u{202F}") . ' € HT';
}
