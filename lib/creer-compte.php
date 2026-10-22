<?php
/**
 * Création d'un compte du back-office en ligne de commande (premier compte administrateur).
 * Ne demande AUCUN mot de passe : affiche un lien à usage unique (72 h) pour le choisir soi-même.
 *
 *   sudo -u villageiratybiar php /home/villageiratybiar/public_html/lib/creer-compte.php \
 *        prod|test adresse@exemple.fr "Prénom Nom" [admin|editeur]
 *
 * (Dossier lib/ interdit au web : ce fichier ne peut être exécuté que depuis le serveur.)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
[$_, $env, $email, $name, $role] = $argv + [null, '', '', '', 'admin'];
$hosts = ['prod' => 'village-iraty-biarritz.fr', 'test' => 'test.village-iraty-biarritz.fr'];
if (!isset($hosts[$env]) || $email === '') {
    fwrite(STDERR, "Usage : php creer-compte.php prod|test adresse@exemple.fr \"Prénom Nom\" [admin|editeur]\n");
    exit(1);
}
$_SERVER['HTTP_HOST'] = $hosts[$env];   // choisit le bon dossier de données (vib-data ou vib-data-test)
require __DIR__ . '/auth.php';

try {
    if (!vib_user_find($email)) vib_user_add($email, $name, $role);
    $t = vib_issue_token($email, 72 * 3600);
    echo "Compte : " . vib_norm_email($email) . "\nOuvrez ce lien pour choisir votre mot de passe (valable 3 jours) :\n"
       . 'https://' . $hosts[$env] . '/admin/mot-de-passe.php?jeton=' . $t . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
