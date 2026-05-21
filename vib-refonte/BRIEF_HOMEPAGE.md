# BRIEF_HOMEPAGE.md — Spécifications section par section

> Ce document décrit le comportement attendu pour chaque section. Le **contenu** est dans `CONTENT_HOMEPAGE.md`. Les **tokens** dans `DESIGN_SYSTEM.md`. Le **rendu visuel** est la maquette Vercel : https://vib-site.vercel.app/

---

## Ordre d'implémentation recommandé

Coder dans cet ordre — pas autrement. Chaque section doit être validée visuellement avant de passer à la suivante.

1. Setup & Design System
2. Header / Navigation
3. Hero
4. Stats
5. Storytelling
6. Annuaire
7. Avantages
8. Espaces disponibles
9. Témoignages (placeholder)
10. Blog
11. CTA final
12. Footer

---

## 0. Setup global

**Fichiers à créer en premier :**

```
index.html
css/design-system.css   → toutes les variables de DESIGN_SYSTEM.md
css/styles.css          → styles des sections
js/main.js              → comportements (menu mobile, badges Ouvert/Fermé, etc.)
references/              → captures
scripts/capture-ref.mjs
scripts/capture-local.mjs
images/                  → assets
```

**`index.html` — squelette de base** :

```html
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Voir CONTENT_HOMEPAGE.md pour title, meta description, OG, JSON-LD -->
  
  <!-- Préchargement des fonts critiques -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- Fonts à confirmer après inspection de la maquette -->
  
  <link rel="stylesheet" href="css/design-system.css">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <header>...</header>
  <main>
    <section class="hero">...</section>
    <section class="stats">...</section>
    <!-- etc. -->
  </main>
  <footer>...</footer>
  <script src="js/main.js" defer></script>
</body>
</html>
```

---

## 1. Header / Navigation

**Structure desktop** (≥ 1024px) :
- Bandeau fixe en haut (`position: sticky; top: 0`), fond blanc avec léger blur quand on scroll (`backdrop-filter: blur(8px)`)
- 3 zones : logo à gauche · menu centré (ou à droite) · double CTA à droite
- Hauteur ~80px

**Structure mobile** (< 1024px) :
- Logo à gauche, burger à droite
- Au tap sur burger : panneau plein écran qui descend avec animation
- À l'intérieur : liens du menu en gros (font-size grande), puis les 2 CTA en bas

**Comportements** :
- Au scroll vers le bas : header reste visible, fond devient opaque (`background-color: rgba(255,255,255,0.95); backdrop-filter: blur(8px)`)
- Au scroll vers le haut depuis le hero : fond redevient transparent
- Lien actif : trait en bas du lien dans la couleur d'accent
- Hover sur lien : trait qui se dessine de gauche à droite (transition 250ms)
- Focus visible pour navigation clavier

**À inspecter sur la maquette** : la hauteur exacte du header, le comportement au scroll, la position exacte du logo dans le SVG/PNG.

**Accessibilité** :
- `<header>` avec `<nav aria-label="Navigation principale">`
- Burger : `<button aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="mobile-menu">`
- État `aria-expanded="true"` quand ouvert

---

## 2. Hero

**Structure** :
- Section pleine hauteur (`min-height: 100vh` ou `90vh`)
- Image de fond `hero-drone.jpg` en `object-fit: cover`, avec overlay sombre (`rgba(0,0,0,0.35)` à `0.5`) pour la lisibilité
- Contenu centré verticalement, aligné à gauche horizontalement (sur la maquette)
- Indicateur "Scroll" en bas, centré

**Hiérarchie verticale du contenu** :
1. Eyebrow `VILLAGE IRATY BIARRITZ` (petit, espacé, blanc translucide)
2. H1 énorme `Le village qui fait VIBrer Biarritz` (serif display, blanc)
3. Sous-titre 2 lignes (blanc, font-weight medium)
4. Double CTA côte à côte

**Mise en valeur de "VIB"** dans le H1 :

```html
<h1>Le village qui fait <span class="vib-highlight">VIB</span>rer Biarritz</h1>
```

```css
.vib-highlight {
  color: var(--color-accent);
  /* OU : font-style: italic; */
  /* OU : background: linear-gradient(...) avec mask-image */
}
```

À inspecter sur la maquette pour reproduire le style exact.

**Animation scroll indicator** :

```css
.scroll-indicator {
  position: absolute;
  bottom: var(--space-7);
  left: 50%;
  transform: translateX(-50%);
  color: white;
  font-size: var(--fs-xs);
  text-transform: uppercase;
  letter-spacing: var(--ls-wide);
}
.scroll-indicator::after {
  content: '';
  display: block;
  width: 1px;
  height: 40px;
  background: white;
  margin: var(--space-3) auto 0;
  animation: scroll-pulse 2s ease-in-out infinite;
  transform-origin: top;
}
@keyframes scroll-pulse {
  0%, 100% { transform: scaleY(0.3); opacity: 0.3; }
  50% { transform: scaleY(1); opacity: 1; }
}
```

**Performance** :
- Image hero en WebP avec fallback JPG
- `<img loading="eager">` (above the fold)
- Préchargement : `<link rel="preload" as="image" href="images/hero-drone.webp">`

---

## 3. Stats

**Structure** :
- Section avec fond `--color-bg-alt` (crème)
- Padding vertical généreux
- Grille 2x2 mobile / 4 colonnes desktop
- Chaque stat : grand chiffre (h2 / serif display) + label (petit, muted)

**Animation au scroll** (optionnel mais bel effet) :
- Compteur qui s'incrémente quand la section entre dans le viewport (`IntersectionObserver`)
- ⚠️ Le chiffre final DOIT être dans le HTML (pas seulement en JS) pour le GEO. L'animation est purement visuelle.

```html
<div class="stat" data-target="1200">
  <div class="stat__value">1 200<span>+</span></div>
  <div class="stat__label">Acteurs</div>
</div>
```

```javascript
// L'animation lit data-target, le HTML reste lisible sans JS
```

---

## 4. Storytelling "Une histoire de vibrations"

**Structure** :
- Layout 2 colonnes desktop (texte + image) ou centré (selon style maquette)
- Sur mobile : pleine largeur, texte au-dessus image en-dessous
- Padding vertical important pour aérer

**À noter** : section absente de la maquette Vercel mais demandée par le PDF. À insérer entre Stats et Annuaire. Style à harmoniser avec le reste — utiliser le même type de eyebrow + H2 + paragraphes que les autres sections.

---

## 5. Annuaire (preview acteurs)

**Structure** :
- Eyebrow + H2 + description (alignés à gauche)
- Grille de 6 cartes : 1 col mobile / 2 cols tablet / 3 cols desktop
- CTA centré en bas

**Carte acteur** :

```html
<article class="acteur-card">
  <div class="acteur-card__image">
    <img src="..." alt="..." loading="lazy">
    <span class="badge badge--success">Ouvert</span>
  </div>
  <div class="acteur-card__body">
    <div class="acteur-card__category">Restaurants</div>
    <h3 class="acteur-card__name">MAISON VISHNU</h3>
    <p class="acteur-card__description">Plats indiens & sri-lankais à emporter</p>
    <p class="acteur-card__address">Halles DARLA, local 61, 16 rue des Mésanges, 64200 Biarritz</p>
  </div>
</article>
```

**Badge Ouvert/Fermé** :
- Position absolue sur l'image, en haut à droite
- Pour l'instant statique (5 ouverts, 1 fermé en démo)
- Câblage dynamique plus tard (Priorité 3 du PDF — UX enrichie) :

```javascript
// js/main.js — squelette pour plus tard
function updateBadgeStatus(card, hours) {
  // hours = { mon: [9, 18], tue: [9, 18], ... }
  const now = new Date();
  const day = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][now.getDay()];
  const hour = now.getHours();
  const [open, close] = hours[day] || [];
  const isOpen = hour >= open && hour < close;
  const badge = card.querySelector('.badge');
  badge.textContent = isOpen ? 'Ouvert' : 'Fermé';
  badge.classList.toggle('badge--success', isOpen);
  badge.classList.toggle('badge--closed', !isOpen);
}
```

**Hover** : carte qui se soulève de 4px, ombre qui s'intensifie, image qui zoome légèrement (`scale(1.02)` sur l'image avec `overflow: hidden` sur le conteneur).

---

## 6. Avantages "Pourquoi choisir le Village"

**Structure** :
- 3 blocs en layout asymétrique (à inspecter sur la maquette pour reproduire fidèlement)
- Sur desktop : alternance image gauche / image droite (effet zigzag)
- Sur mobile : empilés, image en haut texte en bas

**Inspection à faire** : la maquette utilise probablement une grille CSS avec des cellules qui se chevauchent ou un layout en 2 colonnes inversées par bloc. Capturer et reproduire l'agencement exact.

---

## 7. Espaces disponibles

**Structure** :
- Header de section avec H2 à gauche et CTA "Voir tous les espaces" à droite (alignés sur la même ligne baseline)
- Grille de 4 cartes : 1 col mobile / 2 cols tablet / 4 cols desktop
- Carte plus compacte que les fiches acteurs

**Carte local** :

```html
<article class="local-card">
  <span class="badge badge--success">Disponible</span>
  <p class="local-card__ref">REF-OCC71</p>
  <h3 class="local-card__title">Local commercial en duplex</h3>
  <p class="local-card__meta">
    <span>472 m²</span><span aria-hidden="true">·</span><span>4 930€/mois HT</span>
  </p>
  <a href="/louer-un-local" class="local-card__cta">
    En savoir plus
    <span aria-hidden="true">→</span>
  </a>
</article>
```

**Hover** : flèche du CTA qui se déplace de quelques px vers la droite (`translateX(4px)`).

---

## 8. Témoignages (placeholder)

**Structure** :
- Grille 3 colonnes desktop / 1 colonne mobile
- 3 cartes placeholder avec texte `[À COLLECTER — verbatim acteur]`
- Carte témoignage : photo ronde + verbatim en italique + nom & activité

⚠️ Ne pas oublier d'ajouter un commentaire HTML visible pour rappeler que la section est à remplir :

```html
<!-- TODO : Témoignages à collecter auprès des acteurs (Priorité 3 PDF — UX enrichie) -->
```

---

## 9. Blog (actualités)

**Structure** :
- Grille 3 colonnes desktop / 1 colonne mobile
- Carte article : image en haut + date + h3 + extrait + lien "Lire l'article →"
- Image en aspect-ratio 16/9 ou 4/3 (à inspecter)
- Date affichée en eyebrow style

**Hover** : titre qui passe en couleur d'accent + flèche du CTA qui se déplace.

---

## 10. CTA final "Prêt à faire partie de l'aventure ?"

**Structure** :
- Section pleine largeur, image de fond `village-sunset.png` avec overlay sombre
- Texte centré, blanc
- Padding vertical très généreux (utiliser `--space-10`)
- Double CTA centré (boutons en mode "outline blanc" pour ressortir sur fond sombre)

---

## 11. Footer

**Structure** :
- Fond très sombre (`--color-text` ou noir)
- Padding vertical important
- 4 colonnes desktop : logo+intro · Navigation · Contact · Services associés
- Sur mobile : empilées
- Ligne de séparation discrète avant le copyright
- Copyright + liens légaux en bas, alignés sur la même ligne en desktop

**Logos services associés** (recommandation PDF — IMPORTANT pour le GEO) :
- Affichés sur fond clair (cartouche blanc avec padding) pour les faire ressortir
- **Cliquables** vers les sites respectifs (ouvrent dans le même onglet OU nouvel onglet ? — choisir nouvel onglet `target="_blank" rel="noopener"` pour ne pas perdre le visiteur)
- Hover : légère opacité 0.8

```html
<div class="footer__services">
  <h4>Services associés</h4>
  <div class="footer__services-logos">
    <a href="https://www.biarritz-box.fr" target="_blank" rel="noopener" aria-label="Biarritz Box (nouvelle fenêtre)">
      <img src="images/logo-btz-box.jpg" alt="Biarritz Box" loading="lazy">
    </a>
    <!-- etc -->
  </div>
</div>
```

---

## Checklist finale avant livraison

### Visuel
- [ ] Rendu local capturé et comparé à la référence pour desktop ET mobile
- [ ] Aucun écart visible majeur (typo, couleur, espacement, alignement)
- [ ] Tous les hover/focus states reproduits
- [ ] Animations fluides (scroll indicator, hover cards, etc.)

### Contenu
- [ ] Tous les textes proviennent de `CONTENT_HOMEPAGE.md` (pas de lorem)
- [ ] JSON-LD `LocalBusiness` présent dans le `<head>`
- [ ] Meta title, description, OG complets
- [ ] Storytelling "Une histoire de vibrations" intégré
- [ ] Badges "Ouvert/Fermé" présents sur les fiches acteurs
- [ ] Logos services associés cliquables en footer

### Technique
- [ ] HTML5 valide (passer au W3C validator)
- [ ] Aucun framework CSS/JS utilisé
- [ ] Toutes les couleurs/polices/espacements passent par les variables CSS
- [ ] Images en WebP avec fallback, `loading="lazy"` sous la ligne de flottaison
- [ ] `<title>`, alt-text, aria-labels présents
- [ ] Lighthouse : Performance > 90, Accessibilité > 95, SEO 100

### Mobile
- [ ] Testé sur 375px, 390px, 414px (iPhone), 360px (Android)
- [ ] Burger menu fonctionne, états aria corrects
- [ ] Pas de scroll horizontal
- [ ] Touch targets ≥ 44px (boutons, liens)

### Préparation suite (autres pages)
- [ ] Design system robuste, variables couvrent tous les cas
- [ ] Composants réutilisables identifiés (cartes, boutons, badges)
- [ ] Structure de fichiers claire pour extension
