<?php
/**
 * Back-office VIB : mécanique commune des pages de modification d'une fiche (acteur, local).
 *
 *  - le formulaire porte l'empreinte de la fiche affichée : si quelqu'un l'a modifiée entre-temps, RIEN
 *    n'est enregistré ; les deux saisies sont fusionnées champ par champ à l'écran, l'utilisateur vérifie ;
 *  - seuls les champs du formulaire sont touchés ;
 *  - après enregistrement, redirection (un rechargement de page ne renvoie pas le formulaire).
 */

declare(strict_types=1);

require_once __DIR__ . '/collections.php';

/**
 * Traite l'envoi du formulaire (ou prépare l'affichage). Redirige et termine si l'enregistrement réussit.
 * @param callable $formValues fiche → valeurs du formulaire
 * @param callable $apply      (fiche, $_POST) → ['item', 'errors', 'changed']
 * @param callable $merge      (orig, mine, fresh) → ['values', 'conflicts']
 * @param callable $normMine   valeurs envoyées → valeurs normalisées (cases décochées, listes…)
 * @return array{item: array, values: array, orig: array, hash: string, errors: array, conflict: ?array}
 */
function vib_admin_edit(Store $store, array $col, array $item, array $user, callable $formValues, callable $apply, callable $merge, callable $normMine): array
{
    $slug = $item['slug'];
    $values = $formValues($item);
    $st = ['item' => $item, 'values' => $values, 'orig' => $values, 'hash' => Store::itemHash($item), 'errors' => [], 'conflict' => null];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $st;

    vib_csrf_check($user);
    $sentOrig = json_decode((string) base64_decode((string) ($_POST['orig'] ?? ''), true), true);
    if (is_array($sentOrig) && !array_diff_key($values, $sentOrig)) $st['orig'] = $sentOrig;
    $mine = $normMine(array_merge($values, array_intersect_key($_POST, $values)));
    $st['values'] = $mine;

    $onConflict = function (array $fresh) use (&$st, $mine, $formValues, $merge) {
        $m = $merge($st['orig'], $mine, $formValues($fresh));
        [$st['values'], $st['conflict']] = [$m['values'], $m['conflicts']];
        $st['orig'] = $formValues($fresh);
        $st['hash'] = Store::itemHash($fresh);
        $st['item'] = $fresh;
    };

    if ((string) ($_POST['hash'] ?? '') !== $st['hash']) {
        $onConflict($item);
        return $st;
    }
    $r = $apply($item, $_POST);
    $st['errors'] = $r['errors'];
    if ($r['errors']) return $st;
    $back = $col['edit'] . '?slug=' . rawurlencode($slug);
    if (!$r['changed']) { header('Location: ' . $back . '&ok=0', true, 303); exit; }
    try {
        $store->updateItem($slug, $st['hash'], fn(array $cur) => $apply($cur, $_POST)['item'], $user['email'],
            'Fiche « ' . $r['item']['name'] . ' » (' . $slug . ') : ' . implode(', ', $r['changed']));
        header('Location: ' . $back . '&ok=1', true, 303);
        exit;
    } catch (StoreConflict) {
        $fresh = vib_find($store->read()['items'], $slug);
        if ($fresh === null) { http_response_code(409); exit('Cette fiche vient d’être retirée.'); }
        $onConflict($fresh);
        return $st;
    }
}

/** Dernière modification enregistrée de cette fiche. */
function vib_admin_last_change(Store $store, string $slug): ?array
{
    foreach ($store->history(200) as $h) {
        if (($h['key'] ?? null) === $slug || str_contains((string) $h['message'], '(' . $slug . ')')) return $h;
    }
    return null;
}

/** Messages en tête de fiche : enregistré, créée, restaurée, conflit, erreurs. */
function vib_admin_flash(array $col, string $slug, ?array $last, ?array $conflict, array $errors): void
{
    $pub = '<a href="' . e($col['public'] . rawurlencode($slug)) . '" target="_blank" rel="noopener">';
    $ok = $_SERVER['REQUEST_METHOD'] !== 'POST' ? (string) ($_GET['ok'] ?? '') : '';
    $box = fn(string $html, bool $good = true) => print('<div class="adm-flash adm-flash--' . ($good ? 'ok" role="status"' : 'ko" role="alert"') . '>' . $html . "</div>\n");
    if ($ok === '1') $box('<strong>Modifications enregistrées.</strong> ' . ($last ? 'Champs modifiés : ' . e(preg_replace('/^.*\) : /u', '', $last['message'])) . '. ' : '') . 'Elles sont déjà visibles sur ' . $pub . 'la fiche en ligne</a>.');
    elseif ($ok === 'nouveau') $box('<strong>Fiche créée</strong> et déjà en ligne, à l’adresse ' . $pub . e($col['public'] . $slug) . '</a>, avec sa carte dans ' . e($col['list_label']) . '. Complétez-la ci-dessous puis enregistrez.');
    elseif ($ok === 'restaure') $box('<strong>Version restaurée.</strong> La fiche a repris son contenu d’alors, déjà visible sur ' . $pub . 'la fiche en ligne</a>. Ce retour en arrière est lui-même annulable depuis l’historique ci-dessous.');
    elseif ($ok === 'reintegre') $box('<strong>Fiche réintégrée</strong>, telle qu’elle était au moment du retrait. Elle est de nouveau visible sur ' . $pub . 'le site</a>.');
    elseif ($ok === '0') $box('Aucune modification : la fiche était déjà à jour.');

    if ($conflict !== null) {
        $html = '<strong>Rien n’a encore été enregistré :</strong> '
            . ($last ? e($last['author']) . ' a modifié cette fiche le ' . e(date('d/m/Y à H:i', strtotime($last['at']))) : 'cette fiche a été modifiée')
            . ' pendant votre saisie. Ses modifications ont été reprises dans le formulaire, avec les vôtres.';
        if ($conflict) {
            $html .= '<br>Vous avez tous les deux modifié ' . (count($conflict) > 1 ? 'les mêmes champs' : 'le même champ') . ' ; c’est votre version qui est affichée. Sa version :<ul>';
            foreach ($conflict as $label => $val) $html .= '<li><strong>' . e($label) . '</strong> : ' . e($val !== '' ? mb_strimwidth($val, 0, 160, '…') : '(vide)') . '</li>';
            $html .= '</ul>';
        }
        $box($html . ' Vérifiez, puis cliquez sur « Enregistrer ».', false);
    } elseif ($errors) {
        $box('<strong>Rien n’a été enregistré</strong>, merci de corriger :<ul>' . implode('', array_map(fn($m) => '<li>' . e($m) . '</li>', $errors)) . '</ul>', false);
    }
}

/** Champs cachés communs à tous les formulaires de fiche. */
function vib_admin_hidden(array $col, array $st, array $user): string
{
    return '<input type="hidden" name="csrf" value="' . e(vib_csrf_token($user['email'])) . '">'
        . '<input type="hidden" name="c" value="' . e($col['c']) . '">'
        . '<input type="hidden" name="slug" value="' . e($st['item']['slug']) . '">'
        . '<input type="hidden" name="hash" value="' . e($st['hash']) . '">'
        . '<input type="hidden" name="orig" value="' . e(base64_encode(json_encode($st['orig'], JSON_UNESCAPED_UNICODE))) . '">';
}

/** Historique de la fiche (bas de page) + lien de retrait. */
function vib_admin_item_footer(Store $store, array $col, string $slug): void
{
    $hist = $store->itemHistory($slug, 15);
    echo '<section class="adm-card" id="historique"><h2>Historique de cette fiche</h2>';
    if (!$hist) echo '<p class="adm-muted">Aucune version enregistrée.</p>';
    else {
        echo '<ol class="adm-history">';
        foreach ($hist as $i => $hv) {
            $en = $hv['entry'];
            $d = vib_log_describe($en);
            echo '<li><span><strong>' . e(date('d/m/Y à H:i', strtotime($en['at']))) . '</strong> · ' . e(vib_author_label((string) $en['author'])) . '</span>'
               . '<span class="adm-muted">' . e($d['label']) . '</span>';
            if ($i === 0) echo '<span class="adm-tag">version actuelle</span>';
            elseif ($hv['item']) echo '<a href="' . e(vib_admin_url($col, 'version.php', ['slug' => $slug, 'id' => $en['id']])) . '">Voir et revenir à cette version</a>';
            echo '</li>';
        }
        echo '</ol>';
    }
    echo '</section>';
    echo '<p class="adm-muted"><a href="' . e(vib_admin_url($col, 'retirer.php', ['slug' => $slug])) . '">Retirer cette fiche du site…</a></p>';
}
