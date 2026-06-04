# CLAUDE.md — Refonte Village Iraty Biarritz

## Contexte projet

Refonte du site **village-iraty-biarritz.fr** en HTML/CSS/JS pur (perspective CMS headless à moyen terme). On travaille section par section sur la homepage uniquement dans un premier temps, pour valider la méthode avant d'étendre aux autres pages.

**Stack imposée :**
- HTML5 sémantique
- CSS3 vanilla (custom properties, pas de framework type Tailwind/Bootstrap)
- JavaScript vanilla minimal (pas de framework)
- Hébergement cible : VPS Hetzner avec cPanel

**Pourquoi ce choix :** SEO/GEO maximal, performance native, simplicité de maintenance, aucun build complexe nécessaire.

---

## Maquette de référence

URL : **https://vib-site.vercel.app/**

Cette maquette est la cible visuelle à reproduire avec un haut niveau de fidélité. Elle n'est PAS à interpréter ou à "améliorer" — elle est à reproduire fidèlement.

**Quelques éléments du site live actuel (village-iraty-biarritz.fr) sont à réintégrer** — ils sont explicitement listés dans `BRIEF_HOMEPAGE.md` (storytelling, menu, double CTA, etc.).

---

## MÉTHODE DE TRAVAIL OBLIGATOIRE

Cette méthode est non-négociable. Elle existe pour garantir la fidélité visuelle à la maquette. Les erreurs classiques (rendu "qui ressemble vaguement", typographies génériques, espacements approximatifs) viennent toutes du fait de coder sans avoir vu la référence.

### Étape 1 — Capture de la maquette AVANT de coder

Avant d'écrire la moindre ligne de code pour une section :

```bash
# Installer Playwright si ce n'est pas déjà fait
npm install -D playwright
npx playwright install chromium
```

Puis capturer la maquette en desktop ET mobile :

```javascript
// scripts/capture-ref.mjs
import { chromium } from 'playwright';

const URL = 'https://vib-site.vercel.app/';
const OUT = './references';

const browser = await chromium.launch();

// Desktop
const ctxD = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const pD = await ctxD.newPage();
await pD.goto(URL, { waitUntil: 'networkidle' });
await pD.waitForTimeout(2000);
await pD.screenshot({ path: `${OUT}/desktop-full.png`, fullPage: true });
await pD.screenshot({ path: `${OUT}/desktop-hero.png`, fullPage: false });

// Extraction tokens
const tokens = await pD.evaluate(() => {
  const samples = {};
  for (const sel of ['h1', 'h2', 'h3', 'p', 'a', 'button', 'nav a']) {
    const el = document.querySelector(sel);
    if (!el) continue;
    const cs = getComputedStyle(el);
    samples[sel] = {
      fontFamily: cs.fontFamily, fontSize: cs.fontSize, fontWeight: cs.fontWeight,
      lineHeight: cs.lineHeight, letterSpacing: cs.letterSpacing,
      color: cs.color, backgroundColor: cs.backgroundColor,
    };
  }
  return {
    bodyFont: getComputedStyle(document.body).fontFamily,
    bodyColor: getComputedStyle(document.body).color,
    samples,
    fonts: [...document.fonts].map(f => `${f.family} ${f.weight} ${f.style}`),
  };
});
await import('fs').then(fs => fs.writeFileSync(`${OUT}/tokens.json`, JSON.stringify(tokens, null, 2)));

// Mobile
const ctxM = await browser.newContext({ viewport: { width: 390, height: 844 } });
const pM = await ctxM.newPage();
await pM.goto(URL, { waitUntil: 'networkidle' });
await pM.waitForTimeout(2000);
await pM.screenshot({ path: `${OUT}/mobile-full.png`, fullPage: true });

await browser.close();
console.log('Captures OK dans ./references/');
```

Lancer : `node scripts/capture-ref.mjs`

Puis **regarder les images** (`Read` sur desktop-full.png et mobile-full.png) avant de commencer à coder. Cette étape n'est pas optionnelle.

### Étape 2 — Implémentation section par section

Ne jamais essayer de coder toute la page d'un coup. Procéder section par section dans cet ordre :

1. Setup (HTML de base, reset CSS, design system en variables, fonts)
2. Header / Navigation
3. Hero
4. Stats (1200+ acteurs, etc.)
5. Annuaire (preview acteurs)
6. Avantages ("Pourquoi choisir le Village")
7. Espaces disponibles
8. Blog (actualités)
9. CTA final ("Prêt à faire partie de l'aventure ?")
10. Footer

Pour chaque section :
- Relire le brief correspondant dans `BRIEF_HOMEPAGE.md`
- Vérifier le contenu dans `CONTENT_HOMEPAGE.md`
- Coder la section
- **Re-capturer le rendu local** et le comparer à la référence (étape 3)

### Étape 3 — Boucle de vérification visuelle

> **Plus de serveur local.** On vérifie le rendu directement sur l'URL live de travail : la refonte
> est consultable sous `https://village-iraty-biarritz.fr/vib-refonte/`. cPanel n'exécute pas
> `.cpanel.yml` — il fait un `git pull` de `main` dans `public_html/`, donc l'URL = le chemin du repo.
> Cycle : éditer dans `vib-refonte/` → commit + push → cliquer **Deploy HEAD Commit** dans cPanel →
> capturer l'URL live. La homepage est en prod ; on reconstruit désormais les pages internes
> une par une sous `/vib-refonte/<page>.html` avant de les promouvoir en prod.

Après chaque section/page codée et déployée, capturer le rendu live et comparer :

```javascript
// scripts/capture-local.mjs
import { chromium } from 'playwright';

// URL live de travail (et non plus localhost) ; cibler la page travaillée
const URL = 'https://village-iraty-biarritz.fr/vib-refonte/';
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const p = await ctx.newPage();
await p.goto(URL, { waitUntil: 'networkidle' });
await p.screenshot({ path: './references/local-full.png', fullPage: true });
await browser.close();
```

**Comparer visuellement** `references/desktop-full.png` (référence) et `references/local-full.png` (ton rendu live). Lire les deux images, identifier les écarts (typo, espacements, couleurs, alignements, hover states), et corriger jusqu'à ce que le rendu soit fidèle.

Ne pas considérer une section comme "terminée" tant que le rendu n'est pas visuellement très proche de la référence.

### Étape 4 — Inspection des détails

Pour les détails fins (radius, ombres, transitions), inspecter directement la maquette via Playwright :

```javascript
const detail = await page.evaluate(() => {
  const card = document.querySelector('.acteur-card, [class*="card"]');
  if (!card) return null;
  const cs = getComputedStyle(card);
  return {
    borderRadius: cs.borderRadius,
    boxShadow: cs.boxShadow,
    padding: cs.padding,
    transition: cs.transition,
  };
});
```

---

## RÈGLES STRICTES

### ✅ Ce qu'il faut faire

- **Toujours capturer la maquette avant de coder une section**
- Utiliser les variables CSS définies dans `DESIGN_SYSTEM.md` (jamais de valeurs en dur)
- Garder le HTML sémantique (`<header>`, `<main>`, `<section>`, `<article>`, `<nav>`, `<footer>`)
- Mobile-first (media queries `min-width`)
- Tous les textes définitifs viennent de `CONTENT_HOMEPAGE.md` — pas de lorem ipsum, pas de placeholder
- Préparer le terrain GEO/SEO dès maintenant : JSON-LD `LocalBusiness` dans le `<head>`, balises meta complètes, alt-text sur toutes les images, structure de titres cohérente
- Performance native : `loading="lazy"` sur les images sous la ligne de flottaison, WebP si possible, pas de JS bloquant
- Accessibilité de base : contrastes AA, focus visibles, aria-labels sur les boutons icônes

### ❌ Ce qu'il ne faut PAS faire

- **Ne pas coder sans avoir capturé et regardé la maquette**
- Ne pas inventer du contenu (tout est dans `CONTENT_HOMEPAGE.md`)
- Ne pas "améliorer" la maquette (couleurs, polices, layouts) — la reproduire fidèlement
- Ne pas utiliser de framework CSS (Tailwind, Bootstrap) ni de framework JS (React, Vue)
- Ne pas mettre tout en un seul fichier — séparer `index.html`, `css/styles.css`, `css/design-system.css`, `js/main.js`
- Ne pas oublier les hover states / focus states (visibles sur la maquette)
- Ne pas utiliser de couleurs en dur dans le CSS (toujours via variables)
- Ne pas faire de "creative interpretation" sur des éléments non spécifiés — demander à la place

### En cas de doute

Si quelque chose n'est pas clair (un comportement, un espacement, un état au survol), **inspecter la maquette via Playwright** plutôt que d'inventer. Si l'inspection ne suffit pas, poser la question.

---

## Structure du repo attendue

```
.
├── CLAUDE.md                    # Ce fichier
├── DESIGN_SYSTEM.md             # Tokens du design (couleurs, typo, etc.)
├── CONTENT_HOMEPAGE.md          # Tous les textes définitifs
├── BRIEF_HOMEPAGE.md            # Brief section par section
├── references/                  # Captures de la maquette + rendus locaux
│   ├── desktop-full.png
│   ├── desktop-hero.png
│   ├── mobile-full.png
│   ├── tokens.json
│   └── local-full.png           # ton rendu (régénéré à chaque itération)
├── scripts/
│   ├── capture-ref.mjs
│   └── capture-local.mjs
├── index.html
├── css/
│   ├── design-system.css        # Variables : couleurs, fonts, spacings, radius
│   └── styles.css               # Styles des sections
├── js/
│   └── main.js
└── images/                      # Assets locaux
```

---

## Priorités du PDF de synthèse à intégrer dès la homepage

Trois axes du plan d'action (détaillés dans la synthèse) à intégrer dès la homepage :

1. **GEO / IA génératives** : JSON-LD `LocalBusiness` + `FAQPage` dans le head, contenu factuel dense (chiffres, faits), entité VIB clairement définie (nom officiel, adresse, téléphone, dates clés).
2. **SEO** : meta description unique optimisée, balises sémantiques propres, sitemap.xml à prévoir, images en WebP avec `loading="lazy"`, alt-text descriptifs.
3. **UX enrichie** : fiches acteurs avec indicateur "Ouvert / Fermé" dynamique, section témoignages à prévoir (placeholder pour l'instant si pas de contenu), maillage interne vers les services associés (Biarritz Box, Biarritz Buro, Biarritz Domiciliation) en footer cliquable.
