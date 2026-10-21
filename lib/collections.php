<?php
/** Back-office VIB : ce qui distingue les fiches acteurs des fiches locaux dans les pages communes. */

declare(strict_types=1);

require_once __DIR__ . '/fields-locaux.php';

function vib_admin_collection(?string $c = null): array
{
    $c ??= (string) ($_POST['c'] ?? $_GET['c'] ?? 'entreprises');
    return match ($c) {
        'locaux' => ['c' => 'locaux', 'title' => 'Locaux à louer', 'nav' => 'locaux', 'one' => 'local', 'add' => 'Ajouter un local',
                     'public' => '/local/', 'gone' => '/louer-un-local', 'gone_label' => 'la page « Louer un local »',
                     'edit' => '/admin/local.php', 'list' => '/admin/locaux.php', 'labels' => VIB_LOCAL_LABELS, 'prefix' => 'local',
                     'leave' => 'quand le local est loué'],
        default  => ['c' => 'entreprises', 'title' => 'Acteurs', 'nav' => 'acteurs', 'one' => 'acteur', 'add' => 'Ajouter un acteur',
                     'public' => '/acteur/', 'gone' => '/activites', 'gone_label' => 'l’annuaire',
                     'edit' => '/admin/acteur.php', 'list' => '/admin/acteurs.php', 'labels' => VIB_ACTOR_LABELS, 'prefix' => 'acteur',
                     'leave' => 'quand l’acteur a quitté le VILLAGE'],
    };
}

/** Lien vers une page commune, pour la bonne collection. */
function vib_admin_url(array $col, string $page, array $q = []): string
{
    return '/admin/' . $page . '?' . http_build_query(['c' => $col['c']] + $q);
}
