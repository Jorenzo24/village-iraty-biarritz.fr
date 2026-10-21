<?php
/**
 * Règles de saisie du back-office pour les fiches « locaux à louer ».
 * Même principe que lib/fields.php (acteurs) : seuls les champs du formulaire sont touchés,
 * et une valeur non modifiée est réécrite à l'identique (type numérique compris).
 */

declare(strict_types=1);

require_once __DIR__ . '/fields.php';

const VIB_LOCAL_LABELS = [
    'name' => 'titre', 'type' => 'type de local', 'address' => 'adresse', 'surface' => 'surface',
    'price_ht' => 'loyer', 'charges_ht' => 'charges', 'norm_pmr' => 'accès PMR', 'no_fees' => 'sans frais d’agence',
    'no_pas_de_porte' => 'sans pas-de-porte', 'description' => 'description', 'photos' => 'photos',
    'card_ref' => 'référence (carte)', 'card_title' => 'titre de la carte', 'card_sub' => 'accroche de la carte',
];
const VIB_LOCAL_FLAGS = ['norm_pmr' => 'Aux normes PMR', 'no_fees' => 'Sans frais d’agence', 'no_pas_de_porte' => 'Sans pas-de-porte'];

/** Nombre → saisie française : 1165.0 → « 1165 », 150.19 → « 150,19 ». */
function vib_num_to_input($n): string
{
    if (!is_int($n) && !is_float($n)) return '';
    $s = is_float($n) && floor($n) != $n ? number_format($n, 2, ',', '') : (string) (int) round($n);
    return $s;
}

/** Saisie française → nombre (« 1 165,50 », « 1165.5 », « 1 165 € ») ; null si illisible. */
function vib_input_to_num(string $s): ?float
{
    $s = preg_replace('/[\s\x{00A0}\x{202F}€]/u', '', trim($s));
    if ($s === '') return null;
    if (preg_match('/^\d+,\d{1,2}$/', $s)) $s = str_replace(',', '.', $s);
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) return null;
    return (float) $s;
}

function vib_local_form_values(array $l): array
{
    $v = [
        'name' => $l['name'] ?? '', 'type' => $l['type'] ?? '', 'address' => $l['address'] ?? '',
        'surface' => vib_num_to_input($l['surface'] ?? null), 'price_ht' => vib_num_to_input($l['price_ht'] ?? null),
        'charges_ht' => vib_num_to_input($l['charges_ht'] ?? null), 'description' => $l['description'] ?? '',
        'photos' => array_values($l['photos'] ?? []),
        'card_ref' => (string) ($l['card_ref'] ?? ''), 'card_title' => (string) ($l['card_title'] ?? ''), 'card_sub' => (string) ($l['card_sub'] ?? ''),
    ];
    foreach (VIB_LOCAL_FLAGS as $k => $_) $v[$k] = !empty($l[$k]) ? '1' : '';
    return $v;
}

/**
 * @return array{item: array, errors: array<string,string>, changed: list<string>}
 */
function vib_local_apply(array $cur, array $post): array
{
    $t = fn(string $k): string => trim(str_replace(["\r\n", "\r"], "\n", (string) ($post[$k] ?? '')));
    $new = $cur;
    $errors = [];

    $new['name'] = $t('name');
    if ($new['name'] === '') $errors['name'] = 'Le titre est obligatoire.';
    $new['type'] = $t('type');
    $new['address'] = $t('address');
    // La fiche affiche une phrase par ligne : les retours à la ligne saisis deviennent des espaces
    $new['description'] = trim(preg_replace('/\s*\n+\s*/u', ' ', $t('description')));

    // Textes de la carte (page « Louer un local ») : une ligne chacun ; vides = calculés depuis le titre et la description
    foreach (['card_ref', 'card_title', 'card_sub'] as $k) $new[$k] = trim(preg_replace('/\s+/u', ' ', $t($k)));
    if (mb_strlen($new['card_sub']) > 160) $errors['card_sub'] = 'Accroche de la carte trop longue (160 caractères au maximum).';

    // Nombres : valeur inchangée → valeur d'origine gardée telle quelle (1165.0 reste 1165.0)
    foreach (['surface' => 'La surface', 'price_ht' => 'Le loyer', 'charges_ht' => 'Les charges'] as $k => $label) {
        $raw = $t($k);
        $n = $raw === '' && $k === 'charges_ht' ? 0.0 : vib_input_to_num($raw);
        if ($n === null) { $errors[$k] = "$label : nombre attendu (ex. 1 165 ou 150,19)."; continue; }
        if ($k !== 'charges_ht' && $n <= 0) { $errors[$k] = "$label doit être supérieur à 0."; continue; }
        $old = $cur[$k] ?? null;
        if ((is_int($old) || is_float($old)) && abs($old - $n) < 0.001) continue;
        $new[$k] = $k === 'surface' && floor($n) == $n ? (int) $n : $n;
    }

    foreach (VIB_LOCAL_FLAGS as $k => $_) $new[$k] = !empty($post[$k]);

    if (array_key_exists('photos', $post)) {
        $known = $cur['photos'] ?? [];
        $photos = array_values(array_unique(array_filter(is_array($post['photos']) ? $post['photos'] : [], fn($p) => is_string($p) && $p !== '')));
        if (array_filter($photos, fn($p) => !in_array($p, $known, true) && !vib_is_upload($p, (string) $cur['slug'], 'locaux'))) $errors['photos'] = 'Photo inconnue : rechargez la page.';
        elseif (count($photos) > VIB_MAX_PHOTOS && count($photos) > count($known)) $errors['photos'] = 'Au maximum ' . VIB_MAX_PHOTOS . ' photos.';
        else $new['photos'] = $photos;
    }

    $changed = [];
    foreach (VIB_LOCAL_LABELS as $k => $label) if (($cur[$k] ?? null) !== ($new[$k] ?? null)) $changed[] = $label;
    return ['item' => $new, 'errors' => $errors, 'changed' => $changed];
}

/** Fiche modifiée par quelqu'un d'autre pendant la saisie : fusion champ par champ (cf. vib_form_merge). */
function vib_local_merge(array $orig, array $mine, array $fresh): array
{
    $out = $fresh;
    $conflicts = [];
    foreach (VIB_LOCAL_LABELS as $k => $label) {
        if ($k === 'photos') continue;
        $out[$k] = vib_merge_value($orig[$k] ?? '', $mine[$k] ?? '', $fresh[$k] ?? '', $label, (string) ($fresh[$k] ?? ''), $conflicts);
    }
    $out['photos'] = vib_merge_photos($orig, $mine, $fresh, $conflicts);
    return ['values' => $out, 'conflicts' => $conflicts];
}

/** Local vide, au format exact des fiches existantes. */
function vib_local_new(string $slug, string $name, float $surface, float $price): array
{
    return ['slug' => $slug, 'name' => $name, 'address' => '', 'surface' => floor($surface) == $surface ? (int) $surface : $surface,
            'price_ht' => $price, 'charges_ht' => 0.0, 'type' => '', 'norm_pmr' => false, 'no_fees' => true,
            'no_pas_de_porte' => true, 'description' => '', 'photos' => [],
            'card_ref' => '', 'card_title' => '', 'card_sub' => '', 'card_alt' => ''];
}
