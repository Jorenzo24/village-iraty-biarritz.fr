<?php
/**
 * Locaux à louer (/louer-un-local), fabriqué par le serveur depuis les fiches du coffre :
 * un local ajouté ou retiré dans le back-office apparaît ou disparaît ici. Cartes : lib/cards.php.
 */
declare(strict_types=1);
require __DIR__ . '/lib/cards.php';

$locaux = vib_public_items('locaux');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('Expires: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Louer un local à BIARRITZ - VILLAGE Iraty-Biarritz</title>
  <meta name="description" content="Locaux professionnels et commerciaux à louer au VILLAGE Iraty-Biarritz : sans frais d'agence, loyers maintenus 30 % sous le marché. Bureaux, commerces et espaces santé à BIARRITZ.">

  <!-- Open Graph -->
  <meta property="og:title" content="Louer un local à BIARRITZ - VILLAGE Iraty-Biarritz">
  <meta property="og:description" content="Bureaux, commerces et espaces santé à louer à BIARRITZ : sans frais d'agence, loyers 30 % sous le marché.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://village-iraty-biarritz.fr/louer-un-local">
  <meta property="og:site_name" content="VILLAGE Iraty-Biarritz">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:image" content="https://village-iraty-biarritz.fr/assets-2026/images/story-interieur.jpg">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">

  <link rel="canonical" href="https://village-iraty-biarritz.fr/louer-un-local">
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
      { "@type": "ListItem", "position": 2, "name": "Louer un local", "item": "https://village-iraty-biarritz.fr/louer-un-local" }
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
        <li><a href="/activites" class="nav__link">Activités</a></li>
        <li><a href="/louer-un-local" class="nav__link is-active" aria-current="page">Louer un local</a></li>
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

  <!-- ═══════════════ HERO (sans image) ═══════════════ -->
  <section class="page-hero page-hero--compact page-hero--solid" aria-labelledby="page-hero-title">
    <div class="page-hero__inner">
      <span class="page-hero__eyebrow">Immobilier d'entreprise</span>
      <h1 id="page-hero-title" class="page-hero__title">Louer un local</h1>
      <p class="page-hero__lead">Installez votre activité dans nos locaux professionnels et commerciaux à BIARRITZ : à prix doux, sans frais d'agence, des loyers maintenus 30 % sous le marché.</p>
    </div>
  </section>

  <!-- ═══════════════ ESPACES DISPONIBLES ═══════════════ -->
  <section class="espaces section section--cream" aria-labelledby="espaces-title">
    <div class="container">
      <header class="espaces__header">
        <div>
          <p class="eyebrow">Disponibilités</p>
          <h2 id="espaces-title" class="section-title">Vos espaces PRO disponibles</h2>
        </div>
        <a href="/contact?sujet=louer-local" class="btn btn--outline-dark espaces__cta-top">Demander un rendez-vous</a>
      </header>

      <ul class="espaces-grid">
<?php foreach ($locaux as $l): ?>
        <?= vib_card_local($l) ?>
<?php endforeach; ?>
<?php if (!$locaux): ?>
        <li><p class="section-lead">Tous nos locaux sont loués pour le moment. Contactez-nous pour être informé des prochaines disponibilités.</p></li>
<?php endif; ?>
      </ul>
    </div>
  </section>

  <!-- ═══════════════ ACCOMPAGNEMENT (intro) ═══════════════ -->
  <section class="section section--white" aria-labelledby="valeurs-title">
    <div class="container container--narrow text-center">
      <p class="eyebrow">Notre promesse</p>
      <h2 id="valeurs-title" class="section-title">Un accompagnement sans limite</h2>
      <p class="section-lead">Nous vous accompagnons pendant votre installation et durant toute la vie de votre activité en vous offrant un service client attentif de proximité.</p>
    </div>
  </section>

  <!-- ═══════════════ VALEURS / ENGAGEMENTS (bande pleine largeur) ═══════════════ -->
  <section class="values-engagements" aria-label="Nos valeurs et nos engagements">
    <div class="values-engagements__inner">
      <div class="values-engagements__col values-engagements__col--red">
        <h3 class="values-engagements__title">Nos valeurs</h3>
        <p>« Être moderne, innovant et pourtant favoriser l'art de vivre, c'est la réalité du VILLAGE Iraty-BIARRITZ. Le consommateur devient citoyen d'un VILLAGE où l'on prend le temps de vivre, de se parler, de communiquer, d'échanger et de partager avec les entrepreneurs et les commerçants, véritables acteurs de la vie de leur cité.</p>
        <p>Voilà ce qui nous fait <strong>VIBrer</strong> »</p>
      </div>
      <div class="values-engagements__divider" aria-hidden="true"><span></span></div>
      <div class="values-engagements__col values-engagements__col--light">
        <h3 class="values-engagements__title values-engagements__title--accent">Nos engagements</h3>
        <p>« Le VILLAGE Iraty-BIARRITZ est engagé aux côtés des entrepreneurs sur le plan financier. La société d'exploitation du VILLAGE poursuit sa politique de loyer modéré pour favoriser et agréger la microéconomie locale.</p>
        <p>L'objectif est de maintenir vos loyers de <strong>30 %</strong> en dessous des prix du marché locatif de BIARRITZ. »</p>
      </div>
    </div>
  </section>

  <!-- ═══════════════ CHOIX STRATÉGIQUE ═══════════════ -->
  <section class="section section--cream" aria-labelledby="strategie-title">
    <div class="container container--narrow">
      <p class="eyebrow">Pourquoi BIARRITZ</p>
      <h2 id="strategie-title" class="section-title">Un choix stratégique pour votre entreprise</h2>
      <div class="prose prose--lg">
        <p>Vous recherchez un local commercial à louer à BIARRITZ ? Que vous soyez commerçant, artisan ou entrepreneur, le choix de votre emplacement est une décision clé pour assurer la visibilité et la croissance de votre activité.</p>
        <h3>Un emplacement adapté à votre activité</h3>
        <p>BIARRITZ est une ville dynamique, offrant de nombreuses opportunités pour les entreprises. Avant de choisir votre local, il est essentiel de considérer plusieurs critères : la visibilité et l'accessibilité, la proximité avec votre clientèle cible, et la qualité des aménagements proposés.</p>
        <p>Le VILLAGE Iraty-BIARRITZ combine ces atouts dans un environnement pensé pour favoriser le développement économique local, avec une mixité d'activités unique sur la côte basque.</p>
      </div>
      <div class="strategie__cta">
        <a href="/contact?sujet=installer-entreprise" class="btn btn--primary btn--large">Installer mon entreprise</a>
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
</body>
</html>
