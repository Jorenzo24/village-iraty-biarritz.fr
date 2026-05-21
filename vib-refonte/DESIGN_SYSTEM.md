# DESIGN_SYSTEM.md — Village Iraty Biarritz

> ⚠️ **Avant de coder le CSS, capturer la maquette et exécuter l'extraction de tokens** (voir CLAUDE.md, étape 1). Les valeurs ci-dessous sont des **valeurs cibles à confirmer** depuis le rendu Vercel — certaines sont déduites du HTML public et peuvent nécessiter ajustement après inspection.

## 1. Couleurs

La maquette utilise une palette sobre cohérente avec l'identité "Pays Basque / village". À extraire et confirmer depuis la maquette via :

```javascript
// Dans capture-ref.mjs, ajouter :
const colors = await page.evaluate(() => {
  const elements = document.querySelectorAll('button, a, h1, h2, h3, section, header, footer');
  const palette = new Set();
  elements.forEach(el => {
    const cs = getComputedStyle(el);
    palette.add(cs.color);
    palette.add(cs.backgroundColor);
    palette.add(cs.borderColor);
  });
  return [...palette].filter(c => c && c !== 'rgba(0, 0, 0, 0)' && c !== 'rgb(0, 0, 0)');
});
```

### Palette à utiliser (variables CSS)

```css
:root {
  /* Couleurs principales — à AJUSTER après extraction */
  --color-bg: #FFFFFF;              /* fond principal */
  --color-bg-alt: #F7F5F1;          /* fond sections alternées (crème / beige clair) */
  --color-text: #1A1A1A;            /* texte principal */
  --color-text-muted: #6B6B6B;      /* texte secondaire */
  --color-accent: #2D5F3F;          /* vert basque / accent principal — À CONFIRMER */
  --color-accent-hover: #234A31;    /* hover du vert */
  --color-cta: #1A1A1A;             /* CTA principal (souvent noir sur la maquette) */
  --color-cta-text: #FFFFFF;
  --color-border: #E5E2DC;          /* bordures discrètes */
  --color-overlay: rgba(0, 0, 0, 0.4); /* overlay sur images du hero */
  
  /* États */
  --color-success: #2D7A4F;         /* badge "Disponible", "Ouvert" */
  --color-warning: #C97A2D;         /* badge "Fermé" */
}
```

**Action :** lancer `capture-ref.mjs`, ouvrir `desktop-full.png`, prélever les couleurs réelles (avec un picker ou en zoomant), et mettre à jour ces variables.

---

## 2. Typographie

### Polices détectées (depuis le HTML public)

La maquette utilise Next.js — les polices sont chargées via `next/font`. À identifier précisément :

```javascript
// Dans la capture, vérifier :
console.log([...document.fonts].map(f => `${f.family} ${f.weight}`));
```

**Hypothèse de travail** (à confirmer) : combinaison sans-serif moderne pour le texte + serif (display) pour les titres, type **Inter + Fraunces** ou similaire. Si la maquette utilise des polices Google Fonts, les charger via `<link>` (pas de framework de fonts).

### Hiérarchie typographique

```css
:root {
  /* Familles */
  --font-display: 'Fraunces', 'Times New Roman', serif;  /* À CONFIRMER */
  --font-body: 'Inter', -apple-system, system-ui, sans-serif;
  
  /* Tailles (mobile-first, ajustées en desktop via clamp ou media query) */
  --fs-hero: clamp(2.5rem, 6vw, 5rem);       /* H1 hero "Le village qui fait VIBrer Biarritz" */
  --fs-h1: clamp(2rem, 4vw, 3.5rem);          /* Titres de section "Ils font vibrer le Village" */
  --fs-h2: clamp(1.5rem, 2.5vw, 2.25rem);     /* Sous-titres */
  --fs-h3: clamp(1.125rem, 1.5vw, 1.375rem);  /* Titres cartes */
  --fs-body: 1rem;                            /* 16px */
  --fs-small: 0.875rem;                       /* 14px — labels, métadonnées */
  --fs-xs: 0.75rem;                           /* 12px — eyebrows ("VILLAGE IRATY BIARRITZ") */
  
  /* Poids */
  --fw-regular: 400;
  --fw-medium: 500;
  --fw-semibold: 600;
  --fw-bold: 700;
  
  /* Interlignages */
  --lh-tight: 1.1;       /* gros titres */
  --lh-snug: 1.25;       /* titres */
  --lh-normal: 1.5;      /* corps de texte */
  --lh-relaxed: 1.75;    /* paragraphes longs */
  
  /* Letter-spacing */
  --ls-tight: -0.02em;   /* gros titres serif */
  --ls-normal: 0;
  --ls-wide: 0.1em;      /* eyebrows en majuscules */
  --ls-wider: 0.2em;     /* labels très espacés */
}
```

### Règles typographiques

- Les **eyebrows** ("VILLAGE IRATY BIARRITZ", "Annuaire", "Avantages", "Immobilier", "Blog", "Rejoignez-nous") sont en MAJUSCULES, petite taille (`--fs-xs`), letter-spacing large (`--ls-wide` ou `--ls-wider`), couleur `--color-text-muted` ou `--color-accent`.
- Le **H1 hero** met en valeur "VIB" (lettres B vert ou couleur d'accent). À reproduire avec `<span class="vib-highlight">VIB</span>rer`.
- Les **titres de section** ("Ils font vibrer le Village", "Pourquoi choisir le Village") sont en serif display, gros, alignés à gauche.

---

## 3. Espacements

Système basé sur 4px / 8px.

```css
:root {
  --space-1: 0.25rem;   /* 4px */
  --space-2: 0.5rem;    /* 8px */
  --space-3: 0.75rem;   /* 12px */
  --space-4: 1rem;      /* 16px */
  --space-5: 1.5rem;    /* 24px */
  --space-6: 2rem;      /* 32px */
  --space-7: 3rem;      /* 48px */
  --space-8: 4rem;      /* 64px */
  --space-9: 6rem;      /* 96px */
  --space-10: 8rem;     /* 128px */
  
  /* Padding vertical des sections */
  --section-py-mobile: var(--space-7);   /* 48px */
  --section-py-desktop: var(--space-10); /* 128px */
  
  /* Container */
  --container-max: 1280px;
  --container-px-mobile: var(--space-4);  /* 16px */
  --container-px-desktop: var(--space-7); /* 48px */
}
```

---

## 4. Layout & container

```css
.container {
  width: 100%;
  max-width: var(--container-max);
  margin-inline: auto;
  padding-inline: var(--container-px-mobile);
}

@media (min-width: 768px) {
  .container {
    padding-inline: var(--container-px-desktop);
  }
}
```

### Breakpoints

```css
/* Mobile first — utiliser uniquement min-width */
/* sm */  @media (min-width: 640px)  { ... }
/* md */  @media (min-width: 768px)  { ... }
/* lg */  @media (min-width: 1024px) { ... }
/* xl */  @media (min-width: 1280px) { ... }
```

### Grilles fréquentes

- **Cartes acteurs (annuaire)** : 1 col mobile, 2 cols tablet, 3 cols desktop
- **Avantages** : layout asymétrique avec images alternées (à inspecter sur la maquette)
- **Espaces disponibles** : 1 col mobile, 2 cols tablet, 4 cols desktop
- **Blog** : 1 col mobile, 3 cols desktop

---

## 5. Border-radius & ombres

```css
:root {
  --radius-sm: 4px;       /* badges, petits éléments */
  --radius-md: 8px;       /* boutons, inputs */
  --radius-lg: 12px;      /* cartes */
  --radius-xl: 24px;      /* grands blocs, images hero */
  --radius-full: 9999px;  /* pills, avatars */
  
  /* Ombres — la maquette est plutôt plate, ombres discrètes */
  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.04);
  --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.06);
  --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.08);
  --shadow-hover: 0 12px 32px rgba(0, 0, 0, 0.1);
}
```

À confirmer via inspection de `.acteur-card` sur la maquette.

---

## 6. Transitions & animations

```css
:root {
  --transition-fast: 150ms ease;
  --transition-base: 250ms ease;
  --transition-slow: 400ms ease;
  --easing-smooth: cubic-bezier(0.4, 0, 0.2, 1);
}

/* Pattern par défaut sur les cartes au hover */
.card {
  transition: transform var(--transition-base), box-shadow var(--transition-base);
}
.card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-hover);
}
```

**Hero scroll indicator** : la maquette affiche un "Scroll" animé en bas du hero — animation `translateY` infinie sur un chevron ou un trait vertical. À reproduire en CSS pur (pas de Lottie ni JS).

---

## 7. Composants types

### Bouton primaire (CTA)

```css
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-5);
  background: var(--color-cta);
  color: var(--color-cta-text);
  font-weight: var(--fw-medium);
  font-size: var(--fs-body);
  border: none;
  border-radius: var(--radius-md);
  text-decoration: none;
  transition: background var(--transition-fast);
  cursor: pointer;
}
.btn-primary:hover { background: var(--color-accent-hover); }
```

### Bouton secondaire (outline)

```css
.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-5);
  background: transparent;
  color: var(--color-text);
  font-weight: var(--fw-medium);
  border: 1px solid var(--color-text);
  border-radius: var(--radius-md);
  text-decoration: none;
  transition: all var(--transition-fast);
}
.btn-secondary:hover {
  background: var(--color-text);
  color: var(--color-bg);
}
```

### Carte (acteur, local, article)

```css
.card {
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  transition: transform var(--transition-base), box-shadow var(--transition-base);
}
.card__image {
  aspect-ratio: 4 / 3;
  object-fit: cover;
  width: 100%;
}
.card__body { padding: var(--space-5); }
.card__category {
  font-size: var(--fs-xs);
  text-transform: uppercase;
  letter-spacing: var(--ls-wide);
  color: var(--color-text-muted);
  margin-bottom: var(--space-2);
}
.card__title {
  font-family: var(--font-display);
  font-size: var(--fs-h3);
  margin: 0 0 var(--space-2);
}
.card__meta {
  font-size: var(--fs-small);
  color: var(--color-text-muted);
}
```

### Badge "Disponible" / "Ouvert"

```css
.badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: var(--space-1) var(--space-3);
  background: var(--color-success);
  color: white;
  font-size: var(--fs-xs);
  font-weight: var(--fw-medium);
  border-radius: var(--radius-full);
  text-transform: uppercase;
  letter-spacing: var(--ls-wide);
}
.badge--closed { background: var(--color-warning); }
```

---

## 8. Reset CSS minimal

```css
*, *::before, *::after { box-sizing: border-box; }
* { margin: 0; }
html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }
body {
  font-family: var(--font-body);
  font-size: var(--fs-body);
  line-height: var(--lh-normal);
  color: var(--color-text);
  background: var(--color-bg);
  -webkit-font-smoothing: antialiased;
}
img, picture, video, canvas, svg { display: block; max-width: 100%; }
input, button, textarea, select { font: inherit; }
a { color: inherit; text-decoration: none; }
ul, ol { list-style: none; padding: 0; }
button { background: none; border: none; cursor: pointer; }
:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```

---

## 9. Checklist avant de finaliser le design system

- [ ] Couleurs extraites de la maquette (pas devinées) et reportées dans `:root`
- [ ] Polices identifiées précisément, chargées via `<link>` Google Fonts ou auto-hébergées
- [ ] Tokens d'espacement testés sur au moins 3 sections (hero, cartes, footer)
- [ ] Border-radius et ombres inspectés sur les cartes réelles de la maquette
- [ ] Hover states de la maquette reproduits (carte qui se soulève, lien qui se souligne, etc.)
- [ ] Variables utilisées **partout** dans le CSS — `grep -E "#[0-9a-fA-F]{3,6}" css/` ne doit rien retourner en dehors de `design-system.css`
