# village-iraty-biarritz.fr

## Refonte 2026 : quasi terminée, on travaille en prod directe

**Le design 2026 est en production à la racine du repo.** Il n'y a plus de préproduction : le sous-dossier `vib-refonte/` ne contient plus que de la documentation, plus aucune page ni asset servi.

**État d'avancement :**
- ✅ **En prod (nouveau design `assets-2026/`)** : `index.html`, `le-village.html`, `activites.html` (annuaire), `entreprise.html` (fiche acteur `/acteur/<slug>`), `louer-un-local.html` + `local.html` (fiche local `/local/<slug>`), `services.html`, `a-propos.html`, `contact.html`, `nos-articles.html` + `article.html`, `faq.html`.
- ⏳ **Restant à refondre** (encore en ancien style `css/style.css`) : `regie-vib.html`, `mentions-legales.html`, `politique-confidentialite.html`.
- `404.html` est autonome (CSS inline, aucune feuille externe) et sans footer — le laisser tel quel.

**Workflow : on édite directement les fichiers à la racine, puis on vérifie sur l'URL live.**
1. Éditer le HTML à la racine + `assets-2026/`.
2. Bumper le `?v=` si `assets-2026/css/*.css` ou `assets-2026/js/*.js` a changé (voir Cache-busting plus bas).
3. Commit + push sur `main`, puis cliquer **Deploy HEAD Commit** dans cPanel.
4. Vérifier le rendu sur l'URL live (capture Playwright). Playwright est installé dans `vib-refonte/node_modules/` : un script qui l'importe doit être exécuté **depuis `vib-refonte/`**, sinon `ERR_MODULE_NOT_FOUND`.

**Documentation du chantier** (référence, pas du code) : [`vib-refonte/DESIGN_SYSTEM.md`](vib-refonte/DESIGN_SYSTEM.md), [`BRIEF_HOMEPAGE.md`](vib-refonte/BRIEF_HOMEPAGE.md), [`CONTENT_HOMEPAGE.md`](vib-refonte/CONTENT_HOMEPAGE.md), [`CLAUDE.md`](vib-refonte/CLAUDE.md) (méthode Playwright + maquette `https://vib-site.vercel.app/`). Ces fichiers décrivent l'ancien cycle de préprod sous `/vib-refonte/` — **il n'a plus cours**, mais le design system et les briefs restent valables.

⚠️ **Templates de fiche détail servis sur URL réécrite à 2 segments** (`entreprise.html` → `/acteur/<slug>`, `local.html` → `/local/<slug>`) : utiliser des **chemins d'assets ABSOLUS** (`/assets-2026/...`) — en relatif ils se résolvent en `/acteur/assets-2026/...` (404, page nue). Les pages à 1 segment (`/activites`, `/louer-un-local`, etc.) tolèrent le relatif. Les fiches détail posent aussi `canonical`/`og:*` dynamiquement par slug via leur JS (`entreprise.js`, `local.js`).

⚠️ **Fiches acteur/local rendues en JavaScript** : `entreprise.html` et `local.html` sont des coquilles vides remplies par JS depuis `data/*.json`. Googlebot exécute le JS et les indexe correctement, **mais les crawlers IA (GPTBot, ClaudeBot, PerplexityBot) ne l'exécutent pas** : pour eux ces pages sont vides. Tout balisage Schema.org injecté en JS y serait donc invisible. Une pré-génération HTML statique depuis `data/entreprises.json` reste à arbitrer.

---

## Hébergement
- **VPS** : Hetzner
- **Panneau** : cPanel
- **Username cPanel** : `villageiratybiar`
- **Deploy path** : `/home/villageiratybiar/public_html/`
- **Déploiement** : ⚠️ cPanel **n'exécute PAS `.cpanel.yml`** sur ce serveur, malgré la présence du fichier. Il fait un simple `git pull` de `main` dans `public_html/`, donc **l'URL live = le chemin dans le repo**. Le déploiement n'est **pas automatique au push** : il faut cliquer **Deploy HEAD Commit** dans cPanel › Git Version Control.

## Stack
HTML5 / CSS3 / JavaScript vanilla. Pas de framework, pas de build step. Les fichiers du repo sont copiés tels quels sur le serveur.

## Structure
```
.
├── .cpanel.yml          # Présent mais NON exécuté par cPanel (cf. Hébergement)
├── .htaccess            # HTTPS, rewrites d'URL propres, cache, sécurité
├── robots.txt
├── sitemap.xml
├── index.html           # Pages du site, servies en /<nom> via le catch-all .htaccess
├── le-village.html  activites.html  louer-un-local.html  services.html
├── a-propos.html    contact.html    nos-articles.html    faq.html
├── entreprise.html      # Template fiche acteur → /acteur/<slug>   (rendu JS)
├── local.html           # Template fiche local  → /local/<slug>    (rendu JS)
├── article.html         # Template article      → /<slug>          (rendu JS)
├── regie-vib.html  mentions-legales.html  politique-confidentialite.html  404.html
├── send.php             # Backend du formulaire de contact (SMTP, cf. .env)
├── data/                # Source de vérité du contenu data-driven
│   ├── entreprises.json #   92 acteurs
│   ├── locaux.json      #   locaux à louer
│   ├── articles.json    #   articles du blog
│   └── entreprises-a-integrer.json   # file d'attente, non servie
├── assets-2026/         # ✅ Design 2026 — utilisé par les pages refondues
│   ├── css/  design-system.css (tokens) + styles.css (composants)
│   ├── js/   main.js, activites.js, entreprise.js, local.js, article.js,
│   │         articles-list.js, open-status.js
│   └── images/
├── css/style.css        # ⚠️ Ancien design — regie-vib + pages légales uniquement
├── js/                  # ⚠️ Mixte : voir l'avertissement ci-dessous
├── assets/              # Photos, fiches PDF, etc.
└── vib-refonte/         # Documentation du chantier uniquement (+ node_modules Playwright)
```

⚠️ **Deux jeux d'assets coexistent — vérifier ce que la page charge AVANT d'éditer.**
- Les pages refondues chargent `assets-2026/`. `css/style.css` ne sert plus qu'à `regie-vib.html`, `mentions-legales.html` et `politique-confidentialite.html`.
- **`js/` est un piège** : la plupart de ses fichiers sont des doublons périmés de `assets-2026/js/` (éditer `js/entreprise.js` n'a aucun effet, `entreprise.html` charge `/assets-2026/js/entreprise.js`) — **sauf `js/contact.js`, qui est bien vivant** : il n'existe pas dans `assets-2026/` et `contact.html`, pourtant refondue, le charge depuis `/js/contact.js`. Il câble le formulaire en AJAX vers `send.php`.
- Réflexe : `grep -o '<script src="[^"]*"' <page>.html` avant toute modif de JS.

## Conventions de marque (retours client — valables sur TOUT le site)

Règles imposées par le client, à appliquer sur **toutes les pages** (live + refonte), y compris les pages futures :

- **« VILLAGE » toujours en capitales** dès que le mot apparaît dans du texte visible (titres, baselines, nav « Votre VILLAGE », footer, corps, méta, alt/aria). Ne **jamais** toucher aux URLs/chemins/slugs (`/le-village`, `village-iraty-biarritz.fr`, noms de fichiers) ni aux sélecteurs.
- **« zone » → « quartier »** quand le mot décrit le VIB (ex. « la zone la plus dynamique » → « le quartier le plus dynamique », accorder l'article : « de la zone » → « du quartier »). **Exception** : ne pas toucher au terme générique/au sujet d'article « zones d'activités » ni à son slug d'URL.
- **Exception slogan** : sur la page « Le village » le titre reste **« Un village qui VIBre »** (`village` en minuscules, **VIB** en capitales). C'est le seul endroit où `village` reste en minuscules.
- **« vib » surligné en rouge dans un gros titre = « VIB » en capitales.** Quand le radical `vib` est mis en avant via le span rouge (`<span class="hero__highlight">`) dans un titre (hero / section-title), il s'écrit **VIB** (ex. « Une histoire de **VIB**rations », « Le VILLAGE qui fait **VIB**rer BIARRITZ »). Jamais `vib` minuscule dans ce span.

Balayage sûr déjà utilisé : `perl -CSD -Mutf8 -i -pe 's/(?<![-\w])([Vv]illage)(?!-)/VILLAGE/g'` (exclut `le-village`/`village-iraty`), à lancer sur toute nouvelle page.

## Conventions de code

### HTML
- **Lang** : `<html lang="fr">` partout
- **Mobile-first** : penser le CSS pour mobile d'abord, puis adapter au desktop
- **Chemins relatifs uniquement** : `css/style.css`, jamais `/css/style.css`. Sinon le site ne fonctionne pas en `file://` ou en sous-dossier
- **Accessibilité** : `alt` obligatoire sur toutes les images. `<button>` plutôt que `<div onclick>`
- **Sémantique** : utiliser `<header>`, `<main>`, `<nav>`, `<article>`, `<section>`, `<footer>`

### Images
- **WebP** par défaut, JPEG/PNG en fallback si nécessaire
- **SVG inline** pour les icônes (permet de styler en CSS)
- **Jamais de hotlink** : toutes les images doivent être hébergées dans `assets/`
- **Lazy loading** : `loading="lazy"` sur les `<img>` hors viewport initial

### CSS
- Reset déjà fait dans `css/style.css`
- Mobile-first (media queries `min-width`, pas `max-width`)
- Variables CSS (`:root { --color-primary: ... }`) pour les couleurs/spacings réutilisés

### JavaScript
- Vanilla JS, pas de jQuery
- `defer` ou en fin de `<body>` (déjà fait)
- `DOMContentLoaded` pour le code qui touche au DOM

## Contenu data-driven : locaux & acteurs

Les **fiches détail** sont générées dynamiquement à partir de fichiers JSON, mais les **listes (cartes) sont codées en dur dans le HTML**. Quand on ajoute un local ou un acteur, il faut donc toucher **plusieurs endroits** (sinon la fiche existe mais n'apparaît dans aucune liste, ou inversement).

### Locaux à louer
- **Données** : [`data/locaux.json`](../data/locaux.json) — champs : `slug`, `name`, `address`, `surface` (nb), `price_ht` (nb), `charges_ht` (nb, 0 si aucune), `type`, `norm_pmr`/`no_fees`/`no_pas_de_porte` (bool → chips), `description`, `photos` (liste de chemins `/assets/...`, le 1er = cover).
- **Fiche détail** : `local.html` rendue par [`js/local.js`](../js/local.js). URL `/local/<slug>` (rewrite `.htaccess` → `local.html?slug=`). Si `photos` vide → placeholder auto.
- **Carte liste (en dur)** : ajouter un `<article class="local-card">` dans [`louer-un-local.html`](../louer-un-local.html) (grid `.locaux-grid`).
- **Sitemap** : ajouter `/local/<slug>`.
- **Photos** : `assets/photos/locaux/<slug>/` (dossier = slug), `cover.jpg` en premier.

### Acteurs (entreprises)
- **Données** : [`data/entreprises.json`](../data/entreprises.json) — champs : `slug`, `name`, `category`, `category_label`, `description`, `address`, `phone`, `email`, `website`, `hours`, `photos` (1er = cover), `logo`, `social` (objet). Champs vides = `""` ou `[]`/`{}` (tout est conditionnel côté JS).
- **Catégories** (`category` → `category_label`) : `commerces`→Commerces, `restaurants`→Restaurants & Bars, `sante`→Santé & soins, `services`→Services, `sport`→Sports & Loisirs, `entreprises`→Entreprises, `createurs`→Créateurs. (Immobilier/agences = `services`.)
- **Fiche détail** : `entreprise.html` rendue par [`js/entreprise.js`](../js/entreprise.js). URL `/acteur/<slug>`. `description` multi-paragraphes via `\n` (les lignes commençant par •/- deviennent des `<ul>`).
- **Carte liste (en dur)** : ajouter un `<article class="card" data-cat="<category>" data-name="<nom en minuscules>">` dans [`activites.html`](../activites.html) (grid `#cards-grid`). Le filtre/recherche JS ([`js/activites.js`](../js/activites.js)) s'appuie sur `data-cat`/`data-name`. Badge statut : `<span class="status" hidden></span>` (rempli au chargement).
- **Sitemap** : ajouter `/acteur/<slug>`.
- **Photos** : `assets/photos/entreprises/<slug>/` (dossier = slug), `cover.jpg` + `logo.png|jpg`.

### Horaires (champ `hours`) → badge « Ouvert / Fermé »
Texte libre parsé par [`js/open-status.js`](../js/open-status.js). Format : segments séparés par ` · `, plages multiples par jour avec « et », plages d'horaires « de Xh à Yh ». Le passage minuit est géré (ex. `de 18h à 2h`). Exemples :
- `Du mardi au vendredi de 10h à 12h et de 13h à 18h · Samedi de 10h à 18h`
- `Du lundi au mercredi de 9h à 15h · Du jeudi au vendredi de 9h à 15h et de 18h à 2h · Samedi de 18h à 2h`
- Vide ou `Sur rendez-vous` → pas de badge.

### ⚠️ Orientation EXIF des photos (piège iPhone)
Les photos prises au téléphone ont souvent un tag EXIF `Orientation=6` (« Rotate 90 CW ») : elles s'affichent droites dans certains contextes mais **de travers** ailleurs (aperçus OG, vieux navigateurs). **Toujours redresser physiquement + purger l'EXIF** avant de committer :
```bash
sips -r 90 photo.jpg                                  # redresse les pixels (90° CW pour orientation 6)
exiftool -Orientation=1 -n -overwrite_original photo.jpg
exiftool -Orientation -filename -T photo.jpg          # vérifier : doit afficher "Horizontal (normal)"
```
Vérifier le sens réel avec `exiftool -Orientation <f>` avant de redresser, puis contrôler visuellement (Read sur l'image). `sips`/`exiftool` sont dispos sur la machine.

## Cache-busting

**À chaque modification de `css/style.css` ou `js/main.js`**, il faut bumper le query string `?v=AAAAMMJJx` dans `index.html` (et toutes les pages qui référencent ces fichiers).

```html
<link rel="stylesheet" href="css/style.css?v=20260505a">
<script src="js/main.js?v=20260505a"></script>
```

**Pourquoi** : `.htaccess` met un cache navigateur de **1 mois** sur les CSS/JS. Sans bump du query string, les visiteurs récurrents servent l'ancienne version pendant 30 jours et ne voient pas les modifications.

**Format** :
- `AAAA` = année (4 chiffres)
- `MM` = mois (2 chiffres)
- `JJ` = jour (2 chiffres)
- `x` = lettre `a`, `b`, `c`... pour différencier plusieurs modifs dans la même journée

Exemple : trois modifs le 12 mai 2026 → `?v=20260512a`, puis `?v=20260512b`, puis `?v=20260512c`.

## SEO

- **`<title>`** unique et descriptif sur chaque page (max ~60 caractères)
- **`<meta name="description">`** unique sur chaque page (~150-160 caractères)
- **Open Graph** complet : `og:title`, `og:description`, `og:image`, `og:url`, `og:type`, `og:locale=fr_FR`, `og:site_name`
- **Twitter Cards** : `twitter:card=summary_large_image` + title/description/image
- **Schema.org** (JSON-LD) selon le contenu : `LocalBusiness`, `Article`, `BreadcrumbList`, `Organization`...
- **Canonical** : `<link rel="canonical">` sur chaque page
- **Sitemap** : ajouter chaque nouvelle page dans `sitemap.xml` avec `lastmod` à jour
- **Robots** : `robots.txt` autorise tout par défaut. Ajouter `<meta name="robots" content="noindex">` sur les pages à exclure (404, espaces privés)

## Git

- **`main`** = branche de production. Le push **ne déploie pas tout seul** (voir Hébergement).
- **Commits** : messages clairs en français ou anglais, présent de l'indicatif (`Ajoute la section contact`, `Fix typo dans le footer`)
- **Déploiement** : push sur `main`, puis cliquer **Deploy HEAD Commit** dans cPanel › Git Version Control. cPanel fait un `git pull`, il n'exécute pas `.cpanel.yml`. Vérifier ensuite le rendu sur l'URL live.
- **Pratique en vigueur** : la refonte se fait en prod directe sur `main` (cf. commits `Refonte (prod direct) : …`). Pour un chantier risqué ou long, préférer une branche `feature/xxx` / `fix/xxx` puis une PR.

## Sécurité

- HTTPS forcé via `.htaccess` (redirection 301 HTTP → HTTPS)
- Headers de sécurité : `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`
- Listing des répertoires désactivé
- Accès aux fichiers sensibles (`.env`, `.htaccess`, `.cpanel.yml`, `CLAUDE.md`...) bloqué
- Jamais de credentials en dur dans le repo. Utiliser les variables d'environnement cPanel ou un fichier `.env` (déjà ignoré par `.gitignore`)
