<?php
/**
 * Règles de saisie du back-office : catégories, adresse au format client, contrôles.
 * Une seule source pour le formulaire, l'enregistrement et les tests.
 */

declare(strict_types=1);

require_once __DIR__ . '/render.php';   // VIB_SOCIAL : réseaux affichables sur la fiche
require_once __DIR__ . '/hours.php';

const VIB_CATEGORIES = [
    'commerces'   => 'Commerces',
    'restaurants' => 'Restaurants & Bars',
    'sante'       => 'Santé & soins',
    'services'    => 'Services',
    'sport'       => 'Sports & Loisirs',
    'createurs'   => 'Créateurs',
    'entreprises' => 'Entreprises',
];

const VIB_BUILDINGS = ['Halle DARLA', 'Halle DALBA', 'Halle OCCABE', 'Les ALDADES', 'BIARRITZ Activités'];

/**
 * Décompose une adresse « Bâtiment - N° rue - Lot n°X - 64200 BIARRITZ ».
 * @return array{building: string, street: string, lot: string, ok: bool} ok=false si l'adresse ne suit pas ce format
 */
function vib_address_parse(string $address): array
{
    $out = ['building' => '', 'street' => '', 'lot' => '', 'ok' => true];
    if (trim($address) === '') return $out;
    $parts = array_map('trim', explode(' - ', $address));
    if (end($parts) !== '64200 BIARRITZ') $out['ok'] = false; else array_pop($parts);
    foreach ($parts as $p) {
        if (in_array($p, VIB_BUILDINGS, true) && $out['building'] === '') $out['building'] = $p;
        elseif (preg_match('/^Lots?\s+(?:n°)?\s*(.+)$/u', $p, $m) && $out['lot'] === '') $out['lot'] = $m[1];
        elseif (preg_match('/^Bureau\s+\S+/u', $p) && $out['lot'] === '') $out['lot'] = $p;
        elseif (preg_match('/^\d+\s*(?:bis|ter)?\s+(?:rue|allée|avenue|impasse)\b/iu', $p) && $out['street'] === '') $out['street'] = $p;
        elseif (preg_match('/^(?:rue|allée)\b/iu', $p) && $out['street'] === '') $out['street'] = $p;
        else $out['ok'] = false;
    }
    // Recomposer doit redonner exactement l'adresse : sinon on ne sait pas l'interpréter sans perte
    if ($out['ok'] && vib_address_compose($out['building'], $out['street'], $out['lot']) !== $address) $out['ok'] = false;
    return $out;
}

/** Recompose l'adresse au format client. Le lot s'écrit « 24 », « 9 et 10 », « B2 » ou « Bureau 2 ». */
function vib_address_compose(string $building, string $street, string $lot): string
{
    $lot = trim($lot);
    if ($lot !== '') {
        if (preg_match('/^Bureau\s/u', $lot)) { /* tel quel */ }
        elseif (preg_match('/\set\s|,|\s-\s|-/u', $lot)) $lot = 'Lots ' . preg_replace('/\s*-\s*/u', ' et ', $lot);
        else $lot = 'Lot n°' . $lot;
    }
    $parts = array_values(array_filter([trim($building), trim($street), $lot], fn($s) => $s !== ''));
    return $parts ? implode(' - ', $parts) . ' - 64200 BIARRITZ' : '';
}

/** Téléphone français à 10 chiffres → « 05 59 23 93 10 ». Numéros en 08 (numéro vert « 0 800 … ») et autres formats gardés tels quels. */
function vib_phone_format(string $p): string
{
    $p = trim($p);
    $d = preg_replace('/\D/', '', $p);
    if (str_starts_with($d, '33') && strlen($d) === 11) $d = '0' . substr($d, 2);
    return strlen($d) === 10 && $d[0] === '0' && $d[1] !== '8' ? implode(' ', str_split($d, 2)) : $p;
}

function vib_valid_url(string $u): bool
{
    return (bool) preg_match('#^https?://[^\s/$.?\#][^\s]*$#iu', $u) && filter_var($u, FILTER_VALIDATE_URL) !== false;
}

function vib_valid_email(string $e): bool
{
    return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

/** Libellés des champs, pour les messages d'erreur et l'historique. */
const VIB_ACTOR_LABELS = [
    'name' => 'nom', 'category' => 'catégorie', 'description' => 'description', 'address' => 'adresse',
    'phone' => 'téléphone', 'email' => 'e-mail', 'website' => 'site web', 'social' => 'réseaux sociaux', 'hours' => 'horaires',
];

/** Grille du formulaire, normalisée : 7 jours × {open, s1, e1, s2, e2} (case décochée = absente du formulaire). */
function vib_hours_form_norm($h): array
{
    $out = [];
    for ($d = 0; $d < 7; $d++) {
        $r = is_array($h) && is_array($h[$d] ?? null) ? $h[$d] : [];
        $out[$d] = ['open' => ($r['open'] ?? '') !== '' ? '1' : ''];
        foreach (['s1', 'e1', 's2', 'e2'] as $k) $out[$d][$k] = trim((string) ($r[$k] ?? ''));
    }
    return $out;
}

/** Valeurs du formulaire pour un texte d'horaires. */
function vib_hours_form_values(string $text): array
{
    $h = vib_hours_form_norm([]);
    $p = vib_hours_parse($text);
    if ($p) foreach ($p['grid'] as $d => $slots) {
        if (!$slots) continue;
        $h[$d]['open'] = '1';
        foreach ($slots as $i => [$a, $b]) { $h[$d]['s' . ($i + 1)] = vib_hours_to_input($a); $h[$d]['e' . ($i + 1)] = vib_hours_to_input($b); }
    }
    $mode = trim($text) === '' ? 'aucun' : (preg_match('/^sur (rendez-vous|rdv)\.?$/iu', trim($text)) ? 'rdv' : ($p ? 'grille' : 'libre'));
    return ['hours_mode' => $mode, 'hours_free' => $text, 'hours_note' => $p['note'] ?? '', 'h' => $h];
}

/**
 * Texte d'horaires à enregistrer d'après le formulaire.
 * Grille identique à celle relue depuis le texte actuel → texte actuel gardé mot pour mot.
 * @return array{text: string, error: ?string}
 */
function vib_hours_apply(string $current, array $post): array
{
    $mode = (string) ($post['hours_mode'] ?? 'grille');
    if ($mode === 'aucun') return ['text' => '', 'error' => null];
    if ($mode === 'rdv') return ['text' => preg_match('/^sur (rendez-vous|rdv)\.?$/iu', trim($current)) ? $current : 'Sur rendez-vous', 'error' => null];
    if ($mode === 'libre') {
        $t = trim(preg_replace('/\s*(\r\n|\r|\n)+\s*/u', ' · ', (string) ($post['hours_free'] ?? '')));
        return ['text' => $t === trim($current) ? $current : $t, 'error' => null];
    }
    $grid = array_fill(0, 7, []);
    $errs = [];
    foreach (vib_hours_form_norm($post['h'] ?? []) as $d => $r) {
        if ($r['open'] === '') continue;
        $day = ucfirst(VIB_DAYS[$d]);
        $s1 = vib_hours_from_input($r['s1'], false); $e1 = vib_hours_from_input($r['e1'], true);
        if ($s1 === null || $e1 === null) { $errs[] = "$day : indiquez l’heure d’ouverture et de fermeture."; continue; }
        if ($s1 === $e1 % 1440) { $errs[] = "$day : l’ouverture et la fermeture sont à la même heure."; continue; }
        $grid[$d][] = [$s1, $e1];
        if ($r['s2'] === '' && $r['e2'] === '') continue;
        $s2 = vib_hours_from_input($r['s2'], false); $e2 = vib_hours_from_input($r['e2'], true);
        if ($s2 === null || $e2 === null) { $errs[] = "$day : la 2e plage est incomplète (ou videz-la)."; continue; }
        if ($e1 < $s1 || $s2 < $e1) { $errs[] = "$day : la 2e plage doit commencer après la fin de la 1re."; continue; }
        if ($s2 === $e2 % 1440) { $errs[] = "$day : la 2e plage ouvre et ferme à la même heure."; continue; }
        $grid[$d][] = [$s2, $e2];
    }
    if ($errs) return ['text' => $current, 'error' => implode(' ', $errs)];
    if (!array_filter($grid)) return ['text' => $current, 'error' => 'Aucun jour ouvert : cochez au moins un jour, ou choisissez « Non renseignés ».'];
    $note = trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) ($post['hours_note'] ?? '')));
    $p = vib_hours_parse($current);
    if ($p && $p['grid'] === $grid && $p['note'] === $note) return ['text' => $current, 'error' => null];
    return ['text' => vib_hours_compose($grid, $note), 'error' => null];
}

/** Adresse web saisie sans protocole (« www.exemple.fr ») → https:// ajouté. */
function vib_url_normalize(string $u): string
{
    $u = trim($u);
    return $u !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $u) && preg_match('#^[\w-]+(\.[\w-]+)+(/|$)#u', $u) ? 'https://' . $u : $u;
}

/**
 * Applique le formulaire du back-office à une fiche acteur. Seuls les champs du formulaire sont touchés :
 * horaires, photos, logo, slug et tout champ inconnu sont recopiés tels quels.
 * @return array{item: array, errors: array<string,string>, changed: list<string>}
 */
function vib_actor_apply(array $cur, array $post): array
{
    $t = fn(string $k): string => trim(str_replace(["\r\n", "\r"], "\n", (string) ($post[$k] ?? '')));
    $new = $cur;
    $errors = [];

    $new['name'] = $t('name');
    if ($new['name'] === '') $errors['name'] = 'Le nom est obligatoire.';

    $cat = $t('category');
    if (!isset(VIB_CATEGORIES[$cat])) $errors['category'] = 'Catégorie inconnue.';
    else { $new['category'] = $cat; $new['category_label'] = VIB_CATEGORIES[$cat]; }

    $new['description'] = $t('description');

    $new['address'] = ($post['address_mode'] ?? '') === 'libre'
        ? $t('address_free')
        : vib_address_compose($t('building'), $t('street'), $t('lot'));
    if ($t('building') !== '' && ($post['address_mode'] ?? '') !== 'libre' && !in_array($t('building'), VIB_BUILDINGS, true)) $errors['address'] = 'Bâtiment inconnu.';

    $new['phone'] = vib_phone_format($t('phone'));

    $new['email'] = $t('email');
    if ($new['email'] !== '' && !vib_valid_email($new['email'])) $errors['email'] = 'Adresse e-mail invalide.';

    $new['website'] = vib_url_normalize($t('website'));
    if ($new['website'] !== '' && !vib_valid_url($new['website'])) $errors['website'] = 'Adresse web invalide (elle doit commencer par https://).';

    // Réseaux : ordre existant conservé, nouveaux réseaux ajoutés à la suite, réseaux vidés retirés
    $in = is_array($post['social'] ?? null) ? $post['social'] : [];
    $social = [];
    foreach (array_unique(array_merge(array_keys($cur['social'] ?? []), array_keys(VIB_SOCIAL))) as $k) {
        $v = vib_url_normalize((string) ($in[$k] ?? ''));
        if ($v === '') continue;
        if (!vib_valid_url($v)) { $errors['social'] = 'Lien ' . (VIB_SOCIAL[$k][0] ?? $k) . ' invalide.'; continue; }
        $social[$k] = $v;
    }
    $new['social'] = $social;

    $h = vib_hours_apply((string) ($cur['hours'] ?? ''), $post);
    $new['hours'] = $h['text'];
    if ($h['error']) $errors['hours'] = $h['error'];

    $changed = [];
    foreach (VIB_ACTOR_LABELS as $k => $label) {
        if (($cur[$k] ?? null) != ($new[$k] ?? null) || ($k === 'social' && array_keys($cur[$k] ?? []) !== array_keys($new[$k]))) $changed[] = $label;
    }
    return ['item' => $new, 'errors' => $errors, 'changed' => $changed];
}

/** Valeurs initiales du formulaire pour une fiche (l'inverse exact de vib_actor_apply). */
function vib_actor_form_values(array $e): array
{
    $a = vib_address_parse((string) ($e['address'] ?? ''));
    return [
        'name' => $e['name'] ?? '', 'category' => $e['category'] ?? '', 'description' => $e['description'] ?? '',
        'address_mode' => $a['ok'] ? 'structuree' : 'libre', 'address_free' => $e['address'] ?? '',
        'building' => $a['building'], 'street' => $a['street'], 'lot' => $a['lot'],
        'phone' => $e['phone'] ?? '', 'email' => $e['email'] ?? '', 'website' => $e['website'] ?? '',
        'social' => is_array($e['social'] ?? null) ? $e['social'] : [],
    ] + vib_hours_form_values((string) ($e['hours'] ?? ''));
}

/** Clé de tri et de recherche : minuscules sans accents (« Élan » se range avec les E). */
function vib_fold(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','í'=>'i',
                      'ô'=>'o','ö'=>'o','ó'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u','ÿ'=>'y','ñ'=>'n','œ'=>'oe','æ'=>'ae','’'=>"'"]);
}

/**
 * Fiche modifiée par quelqu'un d'autre pendant la saisie : fusionne champ par champ.
 *  - champ que je n'ai pas touché → la valeur de l'autre personne ;
 *  - champ que moi seul ai modifié → ma valeur ;
 *  - champ modifié des deux côtés, différemment → ma valeur, signalé comme conflit.
 * @param array $orig  valeurs du formulaire quand je l'ai ouvert
 * @param array $mine  ce que j'ai envoyé
 * @param array $fresh valeurs actuellement enregistrées
 * @return array{values: array, conflicts: array<string,string>} conflicts : libellé → valeur de l'autre personne
 */
function vib_form_merge(array $orig, array $mine, array $fresh): array
{
    $n = fn($s): string => trim(str_replace(["\r\n", "\r"], "\n", is_string($s) ? $s : ''));
    $labels = ['name' => 'nom', 'category' => 'catégorie', 'description' => 'description', 'phone' => 'téléphone', 'email' => 'e-mail',
               'website' => 'site web', 'building' => 'adresse', 'street' => 'adresse', 'lot' => 'adresse', 'address_free' => 'adresse', 'address_mode' => 'adresse'];
    $out = $fresh;
    $conflicts = [];
    $pick = function ($o, $m, $f, string $label, $show) use ($n, &$conflicts) {
        if ($n($m) === $n($o)) return $f;                       // pas touché par moi
        if ($n($f) !== $n($o) && $n($f) !== $n($m)) $conflicts[$label] = (string) $show;
        return $m;
    };
    foreach ($labels as $k => $label) {
        $show = $label === 'adresse' ? ($fresh['address_mode'] === 'libre' ? $fresh['address_free'] : vib_address_compose($fresh['building'], $fresh['street'], $fresh['lot'])) : $fresh[$k];
        if ($k === 'category') $show = VIB_CATEGORIES[$fresh[$k]] ?? $fresh[$k];
        $out[$k] = $pick($orig[$k] ?? '', $mine[$k] ?? '', $fresh[$k] ?? '', $label, $show);
    }
    // Horaires : un seul bloc (grille, mode, texte libre et précision vont ensemble)
    $hb = fn(array $v): string => json_encode([$v['hours_mode'] ?? '', $n($v['hours_free'] ?? ''), $n($v['hours_note'] ?? ''), vib_hours_form_norm($v['h'] ?? [])]);
    $mineHours = $hb($mine) !== $hb($orig);
    if ($mineHours && $hb($fresh) !== $hb($orig) && $hb($fresh) !== $hb($mine)) $conflicts['horaires'] = (string) $fresh['hours_free'];
    foreach (['hours_mode', 'hours_free', 'hours_note', 'h'] as $k) $out[$k] = $mineHours ? ($mine[$k] ?? '') : $fresh[$k];
    if ($mineHours) $out['h'] = vib_hours_form_norm($out['h']);

    $out['social'] = [];
    foreach (array_keys(VIB_SOCIAL) as $k) {
        $v = $pick($orig['social'][$k] ?? '', $mine['social'][$k] ?? '', $fresh['social'][$k] ?? '', 'réseau ' . VIB_SOCIAL[$k][0], $fresh['social'][$k] ?? '');
        if ($n($v) !== '') $out['social'][$k] = $v;
    }
    return ['values' => $out, 'conflicts' => $conflicts];
}
