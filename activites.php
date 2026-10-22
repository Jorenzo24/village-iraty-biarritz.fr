<?php
/**
 * Annuaire des acteurs (/activites), fabriqué par le serveur depuis les fiches du coffre :
 * cartes, compteurs et badges suivent le back-office. Rendu des cartes : lib/cards.php.
 */
declare(strict_types=1);
require __DIR__ . '/lib/cards.php';

$acteurs = vib_sort_actors(vib_public_items('entreprises'));
$n = count($acteurs);
$byCat = array_count_values(array_column($acteurs, 'category'));
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('Expires: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Activités - l'annuaire des acteurs du VILLAGE Iraty-Biarritz</title>
  <meta name="description" content="Les <?= $n ?> acteurs du VILLAGE Iraty-Biarritz : commerces, restaurants, santé, services, créateurs et sport au cœur de BIARRITZ. Filtrez par catégorie et voyez qui est ouvert.">

  <!-- Open Graph -->
  <meta property="og:title" content="Activités - l'annuaire des acteurs du VILLAGE Iraty-Biarritz">
  <meta property="og:description" content="Commerces, restaurants, santé, services, créateurs et sport : découvrez tous les acteurs du VILLAGE Iraty-Biarritz au cœur de BIARRITZ.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://village-iraty-biarritz.fr/activites">
  <meta property="og:site_name" content="VILLAGE Iraty-Biarritz">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:image" content="https://village-iraty-biarritz.fr/assets-2026/images/hero-drone.jpg">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Activités - l'annuaire des acteurs du VILLAGE Iraty-Biarritz">
  <meta name="twitter:description" content="Commerces, restaurants, santé, services, créateurs et sport : tous les acteurs du VILLAGE Iraty-Biarritz.">

  <link rel="canonical" href="https://village-iraty-biarritz.fr/activites">
  <link rel="icon" type="image/png" href="assets-2026/images/logo/favicon.png">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets-2026/css/design-system.css?v=20260526a">
  <link rel="stylesheet" href="assets-2026/css/styles.css?v=20260922b">

  <!-- JSON-LD BreadcrumbList -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
      { "@type": "ListItem", "position": 1, "name": "Accueil", "item": "https://village-iraty-biarritz.fr/" },
      { "@type": "ListItem", "position": 2, "name": "Activités", "item": "https://village-iraty-biarritz.fr/activites" }
    ]
  }
  </script>
</head>
<body>

<a href="#main" class="skip-link">Aller au contenu</a>

<!-- ═══════════════ HEADER ═══════════════ -->
<header class="header" role="banner">
  <div class="header__inner">
    <a href="/" class="header__logo" aria-label="VILLAGE Iraty-Biarritz, Accueil">
      <img src="assets-2026/images/logo/vib-full.svg" alt="VILLAGE Iraty-Biarritz">
    </a>

    <nav class="nav" aria-label="Navigation principale">
      <ul class="nav__list">
        <li><a href="/le-village" class="nav__link">Votre VILLAGE</a></li>
        <li><a href="/activites" class="nav__link is-active" aria-current="page">Activités</a></li>
        <li><a href="/louer-un-local" class="nav__link">Louer un local</a></li>
        <li><a href="/services" class="nav__link">Nos services</a></li>
      </ul>
    </nav>

    <div class="header__cta">
      <a href="/contact" class="btn btn--outline-light">Prendre un rendez-vous</a>
      <a href="/contact?sujet=installer-entreprise" class="btn btn--primary">Installer mon entreprise</a>
    </div>

    <button class="burger" id="burger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="mobile-nav">
      <span></span><span></span><span></span>
    </button>
  </div>

  <nav class="mobile-nav" id="mobile-nav" aria-label="Menu mobile">
    <ul class="mobile-nav__list">
      <li><a href="/le-village" class="mobile-nav__link">Votre VILLAGE</a></li>
      <li><a href="/activites" class="mobile-nav__link">Activités</a></li>
      <li><a href="/louer-un-local" class="mobile-nav__link">Louer un local</a></li>
      <li><a href="/services" class="mobile-nav__link">Nos services</a></li>
    </ul>
    <div class="mobile-nav__cta">
      <a href="/contact" class="btn btn--outline-dark">Prendre un rendez-vous</a>
      <a href="/contact?sujet=installer-entreprise" class="btn btn--primary">Installer mon entreprise</a>
    </div>
  </nav>
</header>

<main id="main">

  <!-- ═══════════════ HERO DE PAGE ═══════════════ -->
  <section class="page-hero page-hero--compact" aria-labelledby="page-hero-title">
    <div class="page-hero__bg">
      <img src="assets-2026/images/story-halle.jpg" alt="Intérieur de la Halle d'Iraty et ses commerces, VILLAGE Iraty-Biarritz" loading="eager" fetchpriority="high">
      <div class="page-hero__overlay"></div>
    </div>
    <div class="page-hero__inner">
      <span class="page-hero__eyebrow">L'annuaire du VILLAGE</span>
      <h1 id="page-hero-title" class="page-hero__title">Activités</h1>
      <p class="page-hero__lead"><?= $n ?> commerces, restaurants, professionnels de santé, créateurs et services réunis au même endroit. Trouvez le vôtre, et voyez en un coup d'œil qui est ouvert.</p>
    </div>
  </section>

  <!-- ═══════════════ ANNUAIRE ═══════════════ -->
  <section class="section section--cream annuaire-page" aria-label="Annuaire des acteurs">
    <div class="container">

      <div class="annuaire-toolbar">
        <div class="annuaire-toolbar__top">
          <div class="annuaire-search">
            <svg class="annuaire-search__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <input type="search" id="acteur-search" placeholder="Rechercher un commerce, une catégorie…" aria-label="Rechercher un acteur">
          </div>
          <button class="filter-toggle" id="filter-open" type="button" aria-pressed="false">
            <span class="filter-toggle__dot" aria-hidden="true"></span>Ouvert maintenant
          </button>
        </div>
        <div class="filter-pills" role="group" aria-label="Filtrer par catégorie">
          <button class="filter-pill is-active" data-cat="all" type="button" aria-pressed="true">Tous <span class="filter-pill__count"><?= $n ?></span></button>
          <button class="filter-pill" data-cat="restaurants" type="button" aria-pressed="false">Restaurants & Bars <span class="filter-pill__count"><?= (int) ($byCat["restaurants"] ?? 0) ?></span></button>
          <button class="filter-pill" data-cat="commerces" type="button" aria-pressed="false">Commerces <span class="filter-pill__count"><?= (int) ($byCat["commerces"] ?? 0) ?></span></button>
          <button class="filter-pill" data-cat="sante" type="button" aria-pressed="false">Santé & soins <span class="filter-pill__count"><?= (int) ($byCat["sante"] ?? 0) ?></span></button>
          <button class="filter-pill" data-cat="services" type="button" aria-pressed="false">Services <span class="filter-pill__count"><?= (int) ($byCat["services"] ?? 0) ?></span></button>
          <button class="filter-pill" data-cat="sport" type="button" aria-pressed="false">Sports & Loisirs <span class="filter-pill__count"><?= (int) ($byCat["sport"] ?? 0) ?></span></button>
          <button class="filter-pill" data-cat="createurs" type="button" aria-pressed="false">Créateurs <span class="filter-pill__count"><?= (int) ($byCat["createurs"] ?? 0) ?></span></button>
          <button class="filter-pill" data-cat="entreprises" type="button" aria-pressed="false">Entreprises <span class="filter-pill__count"><?= (int) ($byCat["entreprises"] ?? 0) ?></span></button>
        </div>
      </div>

      <p class="annuaire-count" id="acteur-count" aria-live="polite"></p>

      <ul class="acteurs-grid acteurs-grid--page" id="acteurs-grid">
<?php foreach ($acteurs as $e): ?>
        <?= vib_card_actor($e) ?>
<?php endforeach; ?>
      </ul>

      <p class="annuaire-empty" id="acteur-empty" hidden>Aucun acteur ne correspond à votre recherche. Essayez d'autres mots-clés ou une autre catégorie.</p>
    </div>
  </section>

  <!-- ═══════════════ CTA FINAL ═══════════════ -->
  <section class="cta-final cta-final--solid" aria-labelledby="cta-title">
    <div class="container cta-final__inner">
      <p class="eyebrow cta-final__eyebrow">Rejoignez-nous</p>
      <h2 id="cta-title" class="cta-final__title">Vous êtes une entreprise&nbsp;?</h2>
      <p class="cta-final__lead">Rejoignez les 1 200 acteurs qui font vibrer le VILLAGE Iraty-Biarritz. Locaux disponibles, accompagnement sur-mesure et loyers maîtrisés.</p>
      <div class="cta-final__cta">
        <a href="/contact?sujet=installer-entreprise" class="btn btn--primary">Installer mon entreprise</a>
        <a href="/louer-un-local" class="btn btn--outline-light">Voir les locaux disponibles</a>
      </div>
    </div>
  </section>

</main>

<!-- ═══════════════ FOOTER ═══════════════ -->
<footer class="footer" role="contentinfo">
  <div class="container footer__inner">
    <div class="footer__brand">
      <a href="/" class="footer__logo" aria-label="VILLAGE Iraty-Biarritz, Accueil">
        <img src="assets-2026/images/logo/vib-full.svg" alt="VILLAGE Iraty-Biarritz">
      </a>
      <p class="footer__tagline">Plus de 1 200 acteurs qui font vibrer BIARRITZ depuis 2005.<br>Commerces, restaurants, coworking et services.</p>
    </div>

    <div class="footer__col">
      <h2 class="footer__heading">Navigation</h2>
      <ul class="footer__list">
        <li><a href="/le-village">Votre VILLAGE</a></li>
        <li><a href="/activites">Activités</a></li>
        <li><a href="/louer-un-local">Louer un local</a></li>
        <li><a href="/services">Nos services</a></li>
        <li><a href="/faq">FAQ</a></li>
        <li><a href="/contact">Contact</a></li>
      </ul>
    </div>

    <div class="footer__col">
      <h2 class="footer__heading">Contact</h2>
      <ul class="footer__list footer__contact">
        <li>
          <span class="footer__label">Adresse</span>
          <address>4 Rue des Mésanges,<br>64200 BIARRITZ</address>
        </li>
        <li>
          <span class="footer__label">Téléphone</span>
          <a href="tel:+33559239310">05 59 23 93 10</a>
        </li>
        <li>
          <span class="footer__label">Email</span>
          <a href="mailto:contact@v-i-b.fr">contact@v-i-b.fr</a>
        </li>
      </ul>
    </div>

    <div class="footer__col">
      <h2 class="footer__heading">Services associés</h2>
      <ul class="footer__services">
        <li>
          <a href="https://biarritz-box.com" target="_blank" rel="noopener" aria-label="BIARRITZ Box (nouvelle fenêtre)">
            <img src="assets-2026/images/services/biarritz-box.jpeg" alt="BIARRITZ Box" loading="lazy">
          </a>
        </li>
        <li>
          <a href="https://biarritzburo.com" target="_blank" rel="noopener" aria-label="BIARRITZ Buro (nouvelle fenêtre)">
            <img src="assets-2026/images/services/biarritz-buro.png" alt="BIARRITZ Buro" loading="lazy">
          </a>
        </li>
        <li>
          <a href="https://biarritzdomiciliation.com" target="_blank" rel="noopener" aria-label="BIARRITZ Domiciliation (nouvelle fenêtre)">
            <img src="assets-2026/images/services/biarritz-domiciliation.png" alt="BIARRITZ Domiciliation" loading="lazy">
          </a>
        </li>
      </ul>
    </div>
  </div>

  <div class="container footer__bottom">
    <span>&copy; <span id="year">2026</span> VILLAGE Iraty-Biarritz · Tous droits réservés</span>
    <span class="footer__legal">
      <a href="/mentions-legales">Mentions légales</a>
      <span aria-hidden="true">·</span>
      <a href="/politique-confidentialite">Politique de confidentialité</a>
    </span>
  </div>
</footer>

<script src="assets-2026/js/main.js?v=20261022a" defer></script>
<script src="assets-2026/js/activites.js?v=20260619b" defer></script>
</body>
</html>
