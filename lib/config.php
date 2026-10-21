<?php
/**
 * Configuration du back-office VIB, choisie selon l'environnement.
 *
 * Les données éditables (fiches acteurs, locaux) vivent HORS du dépôt Git et hors du dossier
 * public : un fichier modifié dans le dossier du site bloquerait les déploiements cPanel
 * (« uncommitted changes ») et serait écrasé ou en conflit au prochain git pull.
 *
 *   production : /home/villageiratybiar/vib-data
 *   test       : /home/villageiratybiar/vib-data-test
 *   poste local: <dépôt>/.local-data   (gitignoré)
 */

declare(strict_types=1);

function vib_env(): string {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if ($host === 'village-iraty-biarritz.fr' || $host === 'www.village-iraty-biarritz.fr') return 'prod';
    if ($host === 'test.village-iraty-biarritz.fr') return 'test';
    return 'local';   // CLI, serveur PHP intégré, tests
}

function vib_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $env = vib_env();
    $home = '/home/villageiratybiar';
    $dataDir = getenv('VIB_DATA_DIR') ?: match ($env) {
        'prod'  => $home . '/vib-data',
        'test'  => $home . '/vib-data-test',
        default => dirname(__DIR__) . '/.local-data',
    };
    $cfg = [
        'env'        => $env,
        'data_dir'   => $dataDir,
        'repo_dir'   => dirname(__DIR__),
        // Collections gérées par le moteur. Le fichier du dépôt sert de point de départ (migration).
        'collections' => [
            'entreprises' => ['seed' => dirname(__DIR__) . '/data/entreprises.json', 'key' => 'slug'],
            'locaux'      => ['seed' => dirname(__DIR__) . '/data/locaux.json', 'key' => 'slug'],
        ],
        // Cloudflare Access (porte d'entrée du back-office), renseigné à l'étape 1.
        'access' => [
            'team_domain' => getenv('CF_ACCESS_TEAM_DOMAIN') ?: '',   // ex. https://vib.cloudflareaccess.com
            'aud'         => getenv('CF_ACCESS_AUD') ?: '',           // Application Audience (AUD) Tag
        ],
    ];
    return $cfg;
}
