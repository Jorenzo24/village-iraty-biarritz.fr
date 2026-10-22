<?php
/**
 * Fiche acteur, rendue par le serveur : /acteur/<slug> (réécriture .htaccess → entreprise.php?slug=).
 * La page arrive complète (visiteurs, Google, IA, aperçus de partage). Seul le badge
 * « Ouvert / Fermé », qui dépend de l'heure du visiteur, est posé par le navigateur.
 */
declare(strict_types=1);
require __DIR__ . '/lib/render.php';

$slug = (string) ($_GET['slug'] ?? '');
$e = preg_match('/^[a-z0-9-]+$/', $slug) ? vib_find(vib_public_items('entreprises'), $slug) : null;
if (!$e && $slug !== '') {
    // Fiche retirée depuis le back-office : son adresse a pu être indexée → redirection permanente vers l'annuaire
    foreach ((new Store('entreprises'))->retired() as $r) {
        if (($r['item']['slug'] ?? null) === $slug) { header('Location: /activites', true, 301); exit; }
    }
}
if (!$e) http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
// Toujours revalider : une modification faite dans le back-office doit se voir au rechargement suivant
// (sans Expires posé ici, le .htaccess appliquerait le cache HTML d'une heure).
header('Cache-Control: no-cache');
header('Expires: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');

$url = $e ? 'https://village-iraty-biarritz.fr/acteur/' . $e['slug'] : 'https://village-iraty-biarritz.fr/activites';
$desc = $e ? ($e['description'] ?: 'Découvrez ' . $e['name'] . ', ' . mb_strtolower($e['category_label']) . ' au VILLAGE Iraty-BIARRITZ.') : 'Fiche acteur du VILLAGE Iraty-Biarritz, au cœur de BIARRITZ.';
$title = $e ? $e['name'] . ' - VILLAGE Iraty-BIARRITZ' : 'Fiche introuvable - VILLAGE Iraty-BIARRITZ';
$ogImage = ($e && !empty($e['photos'][0])) ? 'https://village-iraty-biarritz.fr' . $e['photos'][0] : 'https://village-iraty-biarritz.fr/assets-2026/images/hero-drone.jpg';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title id="page-title"><?= h($title) ?></title>
  <meta id="page-description" name="description" content="<?= ha($desc) ?>">
<?php if (!$e): ?>  <meta name="robots" content="noindex">
<?php endif; ?>

  <!-- Open Graph -->
  <meta property="og:title" content="<?= ha($e ? $e['name'] . ' - VILLAGE Iraty-Biarritz' : 'Acteur - VILLAGE Iraty-Biarritz') ?>">
  <meta property="og:description" content="<?= ha($e ? $desc : 'Découvrez cet acteur du VILLAGE Iraty-Biarritz, au cœur de BIARRITZ.') ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= ha($url) ?>">
  <meta property="og:site_name" content="VILLAGE Iraty-Biarritz">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:image" content="<?= ha($ogImage) ?>">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">

  <link rel="canonical" href="<?= ha($url) ?>">
<?php if ($e): ?>  <script type="application/ld+json"><?= vib_jsonld_actor($e, $url) ?></script>
<?php endif; ?>
  <link rel="icon" type="image/png" href="/assets-2026/images/logo/favicon.png">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/assets-2026/css/design-system.css?v=20260526a">
  <link rel="stylesheet" href="/assets-2026/css/styles.css?v=20260922b">
</head>
<body>

<a href="#main" class="skip-link">Aller au contenu</a>

<!-- ═══════════════ HEADER ═══════════════ -->
<header class="header" role="banner">
  <div class="header__inner">
    <a href="/" class="header__logo" aria-label="VILLAGE Iraty-Biarritz, Accueil">
      <img src="/assets-2026/images/logo/vib-full.svg" alt="VILLAGE Iraty-Biarritz">
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

  <!-- Hero acteur -->
  <section class="acteur-hero" id="entity-hero">
    <div class="container acteur-hero__inner">
      <a href="/activites" class="acteur-hero__back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Tous les acteurs
      </a>
      <h1 class="acteur-hero__title" id="entity-name"><?= h($e ? $e['name'] : 'Fiche introuvable') ?></h1>
    </div>
  </section>

  <!-- Contenu -->
  <section class="acteur-detail" id="entity-content"<?= $e ? '' : ' hidden' ?>>
<?php if ($e): ?>
    <div class="container">
      <div class="entity-grid">

        <aside class="entity-info">
          <div class="entity-info__logo" id="entity-logo-block"<?= $e['logo'] ? '' : ' hidden' ?>>
            <img id="entity-logo" src="<?= ha($e['logo']) ?>" alt="<?= $e['logo'] ? ha('Logo ' . $e['name']) : '' ?>">
          </div>

          <p class="entity-info__category" id="entity-category"><?= h($e['category_label'] ?? '') ?></p>
          <h2 class="entity-info__name" id="entity-name-h2"><?= h($e['name']) ?></h2>
          <p class="entity-info__address" id="entity-address"><?= h($e['address'] ?? '') ?></p>

          <div class="entity-info__block" id="entity-hours-block"<?= $e['hours'] ? '' : ' hidden' ?>>
            <p class="contact-info__label">Horaires <span id="entity-status" class="status" data-hours="<?= ha($e['hours']) ?>" hidden></span></p>
            <p class="entity-info__hours" id="entity-hours"><?= $e['hours'] ? vib_render_hours($e['hours']) : '' ?></p>
          </div>

          <div class="entity-info__block" id="entity-phone-block"<?= $e['phone'] ? '' : ' hidden' ?>>
            <p class="contact-info__label">Téléphone</p>
            <p><a id="entity-phone" href="<?= $e['phone'] ? ha('tel:' . preg_replace('/\s/u', '', $e['phone'])) : '#' ?>"><?= h($e['phone']) ?></a></p>
          </div>

          <div class="entity-info__block entity-info__block--cta" id="entity-website-block"<?= $e['website'] ? '' : ' hidden' ?>>
            <a id="entity-website" href="<?= $e['website'] ? ha($e['website']) : '#' ?>" target="_blank" rel="noopener" class="btn btn--primary entity-info__website">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              <span id="entity-website-label"><?= $e['website'] ? 'Site web' : 'Visiter le site web' ?></span>
            </a>
          </div>

          <div class="entity-info__block" id="entity-email-block"<?= $e['email'] ? '' : ' hidden' ?>>
            <p class="contact-info__label">Email</p>
            <p><a id="entity-email" href="<?= $e['email'] ? ha('mailto:' . $e['email']) : '#' ?>"><?= h($e['email']) ?></a></p>
          </div>

<?php $socialHtml = vib_render_social($e['social'] ?? []); ?>
          <div class="entity-info__social" id="entity-social-block"<?= $socialHtml !== '' ? '' : ' hidden' ?>>
            <p class="contact-info__label">Suivez-les</p>
            <ul class="entity-social__list" id="entity-social-list"><?= $socialHtml ?></ul>
          </div>
        </aside>

        <div class="entity-detail">
<?php $photos = $e['photos'] ?? []; ?>
          <div class="entity-detail__main-photo" id="entity-main-photo"><?php if ($photos): ?><img src="<?= ha($photos[0]) ?>" alt="<?= ha($e['name']) ?>" loading="eager"><?php else: ?><div class="entity-detail__placeholder"><?= h($e['name']) ?></div><?php endif; ?></div>
          <div class="entity-detail__description prose" id="entity-description"><?= $e['description'] ? vib_render_description($e['description']) : '<p style="color:var(--color-text-muted);font-style:italic;">Description en cours de rédaction. Pour en savoir plus, n\'hésitez pas à <a href="/contact" style="color:var(--color-accent);">nous contacter</a>.</p>' ?></div>
          <div class="entity-detail__photos" id="entity-photos"><?php foreach (array_slice($photos, 1) as $p): ?><div class="entity-detail__thumb"><img src="<?= ha($p) ?>" alt="<?= ha($e['name']) ?>" loading="lazy"></div><?php endforeach; ?></div>
        </div>

      </div>
    </div>
<?php endif; ?>
  </section>

  <!-- Fiche introuvable -->
  <section class="acteur-detail acteur-detail--empty" id="entity-not-found"<?= $e ? ' hidden' : '' ?>>
    <div class="container entity-notfound">
      <h2 class="entity-notfound__title">Fiche introuvable</h2>
      <p class="entity-notfound__lead">Désolé, l'acteur que vous cherchez n'a pas (encore) de fiche dédiée.</p>
      <div class="entity-notfound__actions">
        <a href="/activites" class="btn btn--outline-dark">← Voir tous les acteurs</a>
        <a href="/" class="btn btn--primary">Retour à l'accueil</a>
      </div>
    </div>
  </section>

</main>

<!-- ═══════════════ FOOTER ═══════════════ -->
<footer class="footer" role="contentinfo">
  <div class="container footer__inner">
    <div class="footer__brand">
      <a href="/" class="footer__logo" aria-label="VILLAGE Iraty-Biarritz, Accueil">
        <img src="/assets-2026/images/logo/vib-full.svg" alt="VILLAGE Iraty-Biarritz">
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
            <img src="/assets-2026/images/services/biarritz-box.jpeg" alt="BIARRITZ Box" loading="lazy">
          </a>
        </li>
        <li>
          <a href="https://biarritzburo.com" target="_blank" rel="noopener" aria-label="BIARRITZ Buro (nouvelle fenêtre)">
            <img src="/assets-2026/images/services/biarritz-buro.png" alt="BIARRITZ Buro" loading="lazy">
          </a>
        </li>
        <li>
          <a href="https://biarritzdomiciliation.com" target="_blank" rel="noopener" aria-label="BIARRITZ Domiciliation (nouvelle fenêtre)">
            <img src="/assets-2026/images/services/biarritz-domiciliation.png" alt="BIARRITZ Domiciliation" loading="lazy">
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

<script src="/assets-2026/js/main.js?v=20261022a" defer></script>
<script src="/assets-2026/js/open-status.js?v=20260622a" defer></script>
<script src="/assets-2026/js/entreprise.js?v=20261021a" defer></script>
</body>
</html>
