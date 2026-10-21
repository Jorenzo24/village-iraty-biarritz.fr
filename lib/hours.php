<?php
/**
 * Horaires d'ouverture : grille du back-office ⇄ texte « hours » des fiches.
 *
 * Le texte reste la seule donnée enregistrée (lu par la fiche et par le badge Ouvert/Fermé).
 * La grille n'est qu'une façon de l'écrire sans faute :
 *  - vib_hours_parse() relit un texte en grille, en suivant les règles de open-status.js, et renvoie
 *    null dès qu'une partie du texte ne tiendrait pas dans la grille (« sur RDV », « 24h/24 », jours
 *    sans heures, coquille…) : la fiche s'ouvre alors en texte libre, rien n'est perdu ;
 *  - vib_hours_compose() écrit le texte au format maison (« Du lundi au vendredi de 9h à 12h et de 14h à 18h »).
 *
 * Grille : 7 entrées (0 = lundi), chacune une liste de 0 à 2 plages [début, fin] en minutes.
 * Une fin plus tôt que le début = fermeture après minuit (« de 18h à 2h »). Fin à minuit = 1440 (« 24h »).
 */

declare(strict_types=1);

const VIB_DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
const VIB_HOURS_MAX_SLOTS = 2;

function vib_hours_norm(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, ['à' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c']);
}

/** Jours visés par un segment (même logique que parseDaySpec de open-status.js), null si aucun. */
function vib_hours_days(string $seg): ?array
{
    $idx = ['lundi' => 0, 'mardi' => 1, 'mercredi' => 2, 'jeudi' => 3, 'vendredi' => 4, 'samedi' => 5, 'dimanche' => 6,
            'lun' => 0, 'mar' => 1, 'mer' => 2, 'jeu' => 3, 'ven' => 4, 'sam' => 5, 'dim' => 6];
    $t = vib_hours_norm($seg);
    if (preg_match('/\btous les jours\b/u', $t)) {
        if (preg_match('/sauf\s+(?:le\s+)?(\w+)/u', $t, $m) && isset($idx[$m[1]])) return array_values(array_diff(range(0, 6), [$idx[$m[1]]]));
        return range(0, 6);
    }
    preg_match_all('/\b(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche|lun|mar|mer|jeu|ven|sam|dim)\b/u', $t, $m, PREG_OFFSET_CAPTURE);
    $days = $m[1];
    if (!$days) return null;
    if (count($days) === 2) {
        $between = substr($t, $days[0][1] + strlen($days[0][0]), $days[1][1] - $days[0][1] - strlen($days[0][0]));
        if (preg_match('/\bau\b|[-–—]/u', $between) && !preg_match('/\bet\b|,/u', $between)) {
            $out = []; $d = $idx[$days[0][0]];
            for ($i = 0; $i < 8; $i++) { $out[] = $d; if ($d === $idx[$days[1][0]]) break; $d = ($d + 1) % 7; }
            return $out;
        }
    }
    return array_map(fn($d) => $idx[$d[0]], $days);
}

/**
 * Heures d'un segment, en minutes. null si une heure est mal écrite (« 15h3à » : on ne devine pas).
 * @return list<int>|null
 */
function vib_hours_times(string $seg): ?array
{
    preg_match_all('/(\d{1,2})\s*[hH:](\d*)/u', $seg, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as [, $h, $min]) {
        if (!in_array(strlen($min), [0, 2], true) || (int) $h > 24 || (int) $min > 59) return null;
        $out[] = (int) $h * 60 + (int) $min;
    }
    return $out;
}

/**
 * Relit un texte d'horaires en grille.
 * @return array{grid: list<list<array{int,int}>>, note: string}|null null = ne tient pas dans la grille
 */
function vib_hours_parse(string $text): ?array
{
    $text = trim($text);
    if ($text === '' || preg_match('/24\s*h\s*\/\s*24/u', $text)) return null;
    $grid = array_fill(0, 7, []);
    $seen = array_fill(0, 7, false);
    $notes = [];
    foreach (preg_split('/\s*·\s*|\n+/u', $text) as $seg) {
        $seg = trim($seg);
        if ($seg === '') continue;
        $days = vib_hours_days($seg);
        $times = vib_hours_times($seg);
        if ($times === null) return null;
        if ($days === null) {
            if ($times) return null;          // heures sans jour : on ne devine pas
            $notes[] = $seg;                  // « Le soir sur réservation… » : précision, gardée telle quelle
            continue;
        }
        if ($notes) return null;              // une précision au milieu des horaires : ordre non reproductible
        $closed = (bool) preg_match('/\bferme[es]*\b/u', vib_hours_norm($seg));
        if ($closed ? $times !== [] : (count($times) < 2 || count($times) % 2 || count($times) > 2 * VIB_HOURS_MAX_SLOTS)) return null;
        // Le texte entre jours et heures doit être du remplissage connu, sinon on perdrait une information (« sur RDV »…)
        $rest = preg_replace(['/\b(tous les jours|sauf|du|au|le|et|de|a|ferme[es]*|lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)\b/u', '/\d{1,2}\s*[h:]\d*/u', '/[\s,:\-–—]+/u'], '', vib_hours_norm($seg));
        if ($rest !== '') return null;
        foreach ($days as $d) {
            if ($seen[$d]) return null;       // même jour décrit deux fois
            $seen[$d] = true;
            if (!$closed) for ($i = 0; $i < count($times); $i += 2) $grid[$d][] = [$times[$i], $times[$i + 1] === 0 ? 1440 : $times[$i + 1]];
        }
    }
    if (!array_filter($grid)) return null;
    return ['grid' => $grid, 'note' => implode(' · ', $notes)];
}

function vib_hours_fmt(int $min): string
{
    $h = intdiv($min, 60); $m = $min % 60;
    return $h . 'h' . ($m ? sprintf('%02d', $m) : '');
}

/** Écrit le texte au format maison : jours consécutifs identiques regroupés, jours fermés omis. */
function vib_hours_compose(array $grid, string $note = ''): string
{
    $slots = fn(array $s): string => implode(' et ', array_map(fn($p) => 'de ' . vib_hours_fmt($p[0]) . ' à ' . vib_hours_fmt($p[1]), $s));
    $segs = [];
    for ($d = 0; $d < 7; $d++) {
        if (!$grid[$d]) continue;
        $e = $d;
        while ($e < 6 && $grid[$e + 1] === $grid[$d]) $e++;
        $label = $e - $d === 6 ? 'Tous les jours' : ($e === $d ? ucfirst(VIB_DAYS[$d]) : 'Du ' . VIB_DAYS[$d] . ' au ' . VIB_DAYS[$e]);
        $segs[] = $label . ' ' . $slots($grid[$d]);
        $d = $e;
    }
    if (trim($note) !== '') $segs[] = trim($note);
    return implode(' · ', $segs);
}

/** « 09:30 » → 570 ; null si invalide. « 00:00 » en fin de plage = minuit (1440). */
function vib_hours_from_input(string $v, bool $isEnd): ?int
{
    if (!preg_match('/^(\d{1,2})[:h](\d{2})$/', trim($v), $m) || (int) $m[1] > 23 || (int) $m[2] > 59) return null;
    $min = (int) $m[1] * 60 + (int) $m[2];
    return $isEnd && $min === 0 ? 1440 : $min;
}

function vib_hours_to_input(int $min): string
{
    return sprintf('%02d:%02d', intdiv($min % 1440, 60), $min % 60);
}
