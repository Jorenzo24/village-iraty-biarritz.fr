<?php
/** Back-office VIB : ce qui distingue les fiches acteurs des fiches locaux dans les pages communes. */

declare(strict_types=1);

require_once __DIR__ . '/fields-locaux.php';

function vib_admin_collection(?string $c = null): array
{
    $c ??= (string) ($_POST['c'] ?? $_GET['c'] ?? 'entreprises');
    return match ($c) {
        'locaux' => ['c' => 'locaux', 'title' => 'Locaux à louer', 'nav' => 'locaux', 'one' => 'local', 'add' => 'Ajouter un local',
                     'public' => '/local/', 'gone' => '/louer-un-local', 'gone_label' => 'la page « Louer un local »', 'list_label' => 'la page « Louer un local »',
                     'edit' => '/admin/local.php', 'list' => '/admin/locaux.php', 'labels' => VIB_LOCAL_LABELS, 'prefix' => 'local',
                     'leave' => 'quand le local est loué, ou pour le mettre de côté'],
        default  => ['c' => 'entreprises', 'title' => 'Acteurs', 'nav' => 'acteurs', 'one' => 'acteur', 'add' => 'Ajouter un acteur',
                     'public' => '/acteur/', 'gone' => '/activites', 'gone_label' => 'l’annuaire', 'list_label' => 'l’annuaire',
                     'edit' => '/admin/acteur.php', 'list' => '/admin/acteurs.php', 'labels' => VIB_ACTOR_LABELS, 'prefix' => 'acteur',
                     'leave' => 'quand l’acteur a quitté le VILLAGE, ou pour mettre sa fiche de côté'],
    };
}

/** Lien vers une page commune, pour la bonne collection. */
function vib_admin_url(array $col, string $page, array $q = []): string
{
    return '/admin/' . $page . '?' . http_build_query(['c' => $col['c']] + $q);
}

/**
 * Ce qui manque à une fiche, du plus visible au moins visible (accueil du back-office).
 * Indicatif seulement : une fiche sans horaires peut l'être volontairement (atelier sur commande).
 * @return list<string>
 */
function vib_item_gaps(string $c, array $it): array
{
    $g = [];
    if (empty($it['photos'])) $g[] = 'photo';
    if (mb_strlen(trim((string) ($it['description'] ?? ''))) < 120) $g[] = 'description courte';
    if ($c === 'entreprises') {
        if (trim((string) ($it['hours'] ?? '')) === '') $g[] = 'horaires';
        if (($it['phone'] ?? '') === '' && ($it['email'] ?? '') === '') $g[] = 'contact';
    } else {
        if (trim((string) ($it['address'] ?? '')) === '') $g[] = 'adresse';
        if (trim((string) ($it['type'] ?? '')) === '') $g[] = 'type de local';
    }
    return $g;
}

/** Date lisible et relative : « à l’instant », « il y a 12 min », « aujourd’hui à 14:02 », « hier à 09:10 », « le 18/10 ». */
function vib_when(string $iso): string
{
    $t = strtotime($iso);
    if (!$t) return '';
    $d = time() - $t;
    if ($d < 60) return 'à l’instant';
    if ($d < 3600) return 'il y a ' . intdiv($d, 60) . ' min';
    if (date('Y-m-d', $t) === date('Y-m-d')) return 'aujourd’hui à ' . date('H:i', $t);
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) return 'hier à ' . date('H:i', $t);
    return 'le ' . date(date('Y', $t) === date('Y') ? 'd/m' : 'd/m/Y', $t) . ' à ' . date('H:i', $t);
}

/** « jeudi 22 octobre » */
function vib_date_fr(?int $t = null): string
{
    $t ??= time();
    $j = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'][(int) date('w', $t)];
    $m = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][(int) date('n', $t) - 1];
    return $j . ' ' . (int) date('j', $t) . ' ' . $m;
}

/**
 * Une entrée du journal, en clair : quoi, sur quelle fiche, et la nature de l'action (couleur de la pastille).
 * @param array<string,string> $names identifiant → nom actuel de la fiche
 * @return array{kind: string, slug: ?string, name: ?string, label: string}
 *   kind : add (vert), edit (gris), retire (rouge), restore (orange), system (technique, masqué à l'accueil)
 */
function vib_log_describe(array $e, array $names = []): array
{
    $m = (string) ($e['message'] ?? '');
    $slug = $e['key'] ?? (preg_match('/\(([a-z0-9-]+)\)/', $m, $x) ? $x[1] : null);
    $quoted = preg_match('/« (.*) »/u', $m, $x) ? $x[1] : null;
    $name = ($slug !== null ? ($names[$slug] ?? null) : null) ?? $quoted;
    if (($e['base'] ?? '') === '' || str_starts_with($m, 'Import initial')) return ['kind' => 'system', 'slug' => null, 'name' => null, 'label' => 'Mise en place du back-office'];
    if (str_starts_with($m, 'Mise à jour du format')) return ['kind' => 'system', 'slug' => null, 'name' => null, 'label' => 'Mise à jour technique'];
    if (str_starts_with($m, 'Ajout de la fiche')) return ['kind' => 'add', 'slug' => $slug, 'name' => $name, 'label' => 'Nouvelle fiche'];
    if (str_starts_with($m, 'Réintégration de la fiche')) return ['kind' => 'add', 'slug' => $slug, 'name' => $name, 'label' => 'Republiée'];
    if (str_starts_with($m, 'Retrait de la fiche')) return ['kind' => 'retire', 'slug' => $slug, 'name' => $name, 'label' => 'Dépubliée'];
    if (str_starts_with($m, 'Suppression définitive')) return ['kind' => 'retire', 'slug' => $slug, 'name' => $name, 'label' => 'Supprimée définitivement'];
    if (preg_match('/ : retour à la version du (.+?) \((.*)\)$/u', $m, $x)) return ['kind' => 'restore', 'slug' => $slug, 'name' => $name, 'label' => 'Retour à la version du ' . $x[1]];
    if (preg_match('/^Fiche « .* » \([a-z0-9-]+\) : (.+)$/u', $m, $x)) return ['kind' => 'edit', 'slug' => $slug, 'name' => $name, 'label' => 'Modifié : ' . $x[1]];
    if (str_starts_with($m, 'Restauration')) return ['kind' => 'restore', 'slug' => null, 'name' => null, 'label' => 'Restauration générale'];
    return ['kind' => 'edit', 'slug' => $slug, 'name' => $name, 'label' => preg_replace('/ \([a-z0-9-]+\)/', '', $m)];
}

/** Auteur affiché : les vérifications automatiques apparaissent comme « Maintenance ». */
function vib_author_label(string $a): string
{
    if (str_starts_with($a, 'service:')) return 'Maintenance';
    if ($a === 'système') return 'Automatique';
    return $a;
}

/**
 * Fiches dépubliées (grisées, en bas des listes Acteurs et Locaux) : republier, ou supprimer définitivement.
 */
function vib_admin_unpublished(array $col, array $user): void
{
    $list = (new Store($col['c']))->retired(false);
    if (!$list) return;
    echo '<section class="adm-unpub" id="depubliees"><h2>Dépubliées <span class="adm-muted">' . count($list) . '</span></h2>'
       . '<p class="adm-hint">Invisibles sur le site, conservées telles quelles. « Republier » les remet en ligne en un clic.</p><ul class="adm-rows">';
    foreach ($list as $r) {
        $it = $r['item'];
        $img = !empty($it['photos']) ? '<img class="adm-rowcard__img" src="' . e($it['photos'][0]) . '" alt="" loading="lazy">'
            : '<span class="adm-rowcard__img adm-rowcard__ph" aria-hidden="true">' . e(mb_strtoupper(mb_substr((string) $it['name'], 0, 1))) . '</span>';
        echo '<li><div class="adm-rowcard adm-rowcard--off">' . $img
           . '<span class="adm-rowcard__main"><span class="adm-rowcard__name">' . e($it['name']) . '</span>'
           . '<span class="adm-rowcard__sub">Dépubliée ' . e(vib_when((string) $r['at'])) . ' par ' . e(vib_author_label((string) $r['author'])) . '</span></span>'
           . '<span class="adm-rowcard__side">'
           . '<form method="post" action="/admin/publier.php"><input type="hidden" name="csrf" value="' . e(vib_csrf_token($user['email'])) . '">'
           . '<input type="hidden" name="c" value="' . e($col['c']) . '"><input type="hidden" name="slug" value="' . e($it['slug']) . '">'
           . '<button type="submit" class="adm-btn adm-btn--small">Republier</button></form>'
           . '<a class="adm-btn adm-btn--ghost adm-btn--small" href="' . e(vib_admin_url($col, 'supprimer.php', ['slug' => $it['slug']])) . '">Supprimer…</a>'
           . '</span></div></li>';
    }
    echo '</ul></section>';
}
