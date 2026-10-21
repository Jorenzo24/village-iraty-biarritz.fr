<?php
/**
 * Fiche local à louer, rendue par le serveur : /local/<slug> (réécriture .htaccess → local.php?slug=).
 * Seul le changement de photo au clic sur une vignette reste en JavaScript (local.js).
 */
declare(strict_types=1);
require __DIR__ . '/lib/render.php';

$slug = (string) ($_GET['slug'] ?? '');
$l = preg_match('/^[a-z0-9-]+$/', $slug) ? vib_find(vib_public_items('locaux'), $slug) : null;
if (!$l) http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
// Toujours revalider : une modification faite dans le back-office doit se voir au rechargement suivant
// (sans Expires posé ici, le .htaccess appliquerait le cache HTML d'une heure).
header('Cache-Control: no-cache');
header('Expires: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');

$url = $l ? 'https://village-iraty-biarritz.fr/local/' . $l['slug'] : 'https://village-iraty-biarritz.fr/louer-un-local';
$desc = $l ? $l['name'] . ' à louer au VILLAGE Iraty-BIARRITZ. ' . vib_jsnum($l['surface']) . ' m², ' . vib_jsnum($l['price_ht']) . ' € HT/mois.' : 'Local commercial à louer au VILLAGE Iraty-Biarritz, au cœur de BIARRITZ.';
$photos = $l['photos'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title id="page-title"><?= h($l ? $l['name'] . ' - VILLAGE Iraty-BIARRITZ' : 'Local à louer - VILLAGE Iraty-Biarritz') ?></title>
  <meta id="page-description" name="description" content="<?= ha($desc) ?>">
<?php if (!$l): ?>  <meta name="robots" content="noindex">
<?php endif; ?>

  <!-- Open Graph -->
  <meta property="og:title" content="<?= ha($l ? $l['name'] . ' - VILLAGE Iraty-Biarritz' : 'Local à louer - VILLAGE Iraty-Biarritz') ?>">
  <meta property="og:description" content="<?= ha($l ? $desc : 'Local professionnel à louer au VILLAGE Iraty-Biarritz, sans frais d\'agence.') ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= ha($url) ?>">
  <meta property="og:site_name" content="VILLAGE Iraty-Biarritz">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:image" content="<?= ha($photos ? 'https://village-iraty-biarritz.fr' . $photos[0] : 'https://village-iraty-biarritz.fr/assets-2026/images/story-interieur.jpg') ?>">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">

  <link rel="canonical" href="<?= ha($url) ?>">
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

  <!-- Hero local -->
  <section class="acteur-hero" id="local-hero">
    <div class="container acteur-hero__inner">
      <a href="/louer-un-local" class="acteur-hero__back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Tous les locaux disponibles
      </a>
      <h1 class="acteur-hero__title" id="local-name"><?= h($l ? $l['name'] : 'Local introuvable') ?></h1>
    </div>
  </section>

  <!-- Contenu -->
  <section class="acteur-detail" id="local-content"<?= $l ? '' : ' hidden' ?>>
<?php if ($l): ?>
    <div class="container">
      <div class="local-detail">

        <div class="local-detail__gallery">
          <div class="local-detail__main-photo" id="local-main-photo"><?php if ($photos): ?><img src="<?= ha($photos[0]) ?>" alt="<?= ha($l['name']) ?>" loading="eager" id="local-main-img"><?php else: ?>
                <div class="local-detail__placeholder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <line x1="3" y1="9" x2="21" y2="9"/>
                        <line x1="9" y1="3" x2="9" y2="21"/>
                    </svg>
                    <p><?= h(vib_jsnum($l['surface'])) ?> m²</p>
                    <small>Photos prochainement</small>
                </div><?php endif; ?></div>
          <div class="local-detail__thumbs" id="local-thumbs"><?php foreach ($photos as $i => $p): ?><button class="local-detail__thumb<?= $i === 0 ? ' is-active' : '' ?>" data-src="<?= ha($p) ?>" type="button">
                    <img src="<?= ha($p) ?>" alt="Vue <?= $i + 1 ?>" loading="lazy">
                </button><?php endforeach; ?></div>
        </div>

        <aside class="local-detail__info">
          <span class="badge badge--available local-detail__badge"><span class="badge__dot"></span>Disponible</span>
          <p class="local-detail__address" id="local-address">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="display:inline;vertical-align:middle;color:var(--color-accent);" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= h($l['address']) ?>
        </p>

          <div class="local-detail__price-box">
            <div>
              <p class="local-detail__price-label">Loyer / mois</p>
              <p class="local-detail__price" id="local-price"><?= h(vib_price($l['price_ht'])) ?></p>
            </div>
            <div>
              <p class="local-detail__price-label">Superficie</p>
              <p class="local-detail__surface" id="local-surface"><?= h(vib_jsnum($l['surface'])) ?> m²</p>
            </div>
          </div>

          <a href="<?= ha('/contact?sujet=louer-local&local=' . rawurlencode($l['slug'])) ?>" id="local-rdv-btn" class="btn btn--primary local-detail__rdv">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Prendre rendez-vous pour ce local
          </a>

          <?php $features = array_filter([!empty($l['no_fees']) ? "✓ Sans frais d'agence" : null, !empty($l['no_pas_de_porte']) ? '✓ Pas de pas-de-porte' : null, !empty($l['norm_pmr']) ? '✓ Normes PMR' : null, !empty($l['type']) ? '✓ ' . $l['type'] : null]); ?>
          <div class="local-detail__features" id="local-features"><?php foreach ($features as $f): ?><span class="local-detail__chip"><?= h($f) ?></span><?php endforeach; ?></div>

          <div class="local-detail__charges" id="local-charges-block"<?= !empty($l['charges_ht']) && $l['charges_ht'] > 0 ? '' : ' hidden' ?>>
            <p class="contact-info__label">Charges mensuelles HT</p>
            <p id="local-charges"><?= !empty($l['charges_ht']) && $l['charges_ht'] > 0 ? h(vib_price($l['charges_ht'])) : '' ?></p>
          </div>
        </aside>

      </div>

      <div class="local-detail__description prose" id="local-description"><?php if ($l['description']): ?><h3>Description</h3><p><?= str_replace('. ', '.<br>', h($l['description'])) ?></p><?php endif; ?></div>
    </div>
<?php endif; ?>
  </section>

  <!-- Local introuvable -->
  <section class="acteur-detail acteur-detail--empty" id="local-not-found"<?= $l ? ' hidden' : '' ?>>
    <div class="container entity-notfound">
      <h2 class="entity-notfound__title">Local introuvable</h2>
      <p class="entity-notfound__lead">Désolé, ce local n'est plus disponible ou n'existe pas.</p>
      <div class="entity-notfound__actions">
        <a href="/louer-un-local" class="btn btn--outline-dark">← Voir tous les locaux</a>
        <a href="/contact" class="btn btn--primary">Nous contacter</a>
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

<script src="/assets-2026/js/main.js?v=20260526a" defer></script>
<script src="/assets-2026/js/local.js?v=20261021a" defer></script>
</body>
</html>
