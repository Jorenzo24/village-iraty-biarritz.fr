# village-iraty-biarritz.fr

## ⚠️ Chantier actif : refonte (homepage en prod, pages internes en cours)

Une refonte est en cours dans le sous-dossier [`vib-refonte/`](vib-refonte/). Le dossier est **tracké et commité** sur `main` (la homepage refonte est déjà en production à la racine du site).

**Avant de répondre à toute question liée à la refonte, lire [`vib-refonte/CLAUDE.md`](vib-refonte/CLAUDE.md)** — il contient la méthode imposée (capture Playwright de la maquette `https://vib-site.vercel.app/` avant chaque section, boucle de vérification visuelle, etc.).

**Fichiers actifs de la refonte :**
- [`vib-refonte/index.html`](vib-refonte/index.html)
- [`vib-refonte/assets-2026/css/styles.css`](vib-refonte/assets-2026/css/styles.css) + [`design-system.css`](vib-refonte/assets-2026/css/design-system.css)
- [`vib-refonte/assets-2026/js/main.js`](vib-refonte/assets-2026/js/main.js)
- Captures de référence et rendus locaux : [`vib-refonte/references/`](vib-refonte/references/)
- Scripts Playwright : [`vib-refonte/scripts/`](vib-refonte/scripts/)

**Workflow de travail : on bosse directement sur l'URL live `/vib-refonte/`, plus de serveur local.**

La homepage refonte est déjà en production (racine du site). On reconstruit maintenant **les pages internes une par une**, consultables sous `https://village-iraty-biarritz.fr/vib-refonte/`, jusqu'à validation avant promotion en prod (racine).

**État d'avancement de la refonte (au fil de l'eau) :**
- ✅ **Promues en prod** : `index.html` (homepage), `le-village.html`, `activites.html` (annuaire), `entreprise.html` (fiche acteur `/acteur/<slug>`), `louer-un-local.html` (liste) + `local.html` (fiche local `/local/<slug>`), `services.html` (fiches pratiques PDF).
- ⏳ **Restant à refondre** (encore en ancien style `/css/style.css`) : `a-propos.html`, `contact.html`, `nos-articles.html` + `article.html`, `regie-vib.html`.

⚠️ **Templates de fiche détail servis sur URL réécrite à 2 segments** (`entreprise.html` → `/acteur/<slug>`, `local.html` → `/local/<slug>`) : utiliser des **chemins d'assets ABSOLUS** (`/assets-2026/...`) — en relatif ils se résolvent en `/acteur/assets-2026/...` (404, page nue). Les pages à 1 segment (`/activites`, `/louer-un-local`, etc.) tolèrent le relatif. Les fiches détail posent aussi `canonical`/`og:*` dynamiquement par slug via leur JS (`entreprise.js`, `local.js`).

⚠️ **Modèle de déploiement (important)** : cPanel **n'exécute PAS `.cpanel.yml`** sur ce serveur. Il fait un simple `git pull` de `main` dans `public_html/`, donc **l'URL live = le chemin dans le repo** (`vib-refonte/index.html` → `…/vib-refonte/`). Le déploiement se déclenche **manuellement** via cPanel › Git Version Control › **Deploy HEAD Commit**.

Cycle pour chaque page interne :
1. Éditer les fichiers dans [`vib-refonte/`](vib-refonte/) (HTML + `assets-2026/`).
2. Commit + push sur `main`, puis cliquer **Deploy HEAD Commit** dans cPanel.
3. Vérifier le rendu **sur l'URL live** `https://village-iraty-biarritz.fr/vib-refonte/<page>.html` (capture Playwright sur l'URL live, pas sur localhost).

`/vib-refonte/` est exclu de l'indexation (`Disallow: /vib-refonte/` dans `robots.txt` + `<meta name="robots" content="noindex,nofollow,…">` dans chaque page) pour ne pas dupliquer la prod.

Le site **live** (décrit dans la suite de ce fichier) reste à la racine du repo. Ne pas confondre les deux : la refonte ne touche **aucun** fichier hors de `vib-refonte/`.

---

## Hébergement
- **VPS** : Hetzner
- **Panneau** : cPanel
- **Username cPanel** : `villageiratybiar`
- **Deploy path** : `/home/villageiratybiar/public_html/`
- **Déploiement** : via `.cpanel.yml` (cPanel > Git Version Control, déclenché à chaque push sur `main`)

## Stack
HTML5 / CSS3 / JavaScript vanilla. Pas de framework, pas de build step. Les fichiers du repo sont copiés tels quels sur le serveur.

## Structure
```
.
├── .cpanel.yml          # Script de déploiement cPanel
├── .htaccess            # HTTPS, redirections, cache, sécurité
├── .gitignore
├── robots.txt
├── sitemap.xml
├── index.html
├── 404.html
├── css/
│   └── style.css
├── js/
│   └── main.js
└── assets/              # Images, fonts, favicon, etc.
```

## Conventions de marque (retours client — valables sur TOUT le site)

Règles imposées par le client, à appliquer sur **toutes les pages** (live + refonte), y compris les pages futures :

- **« VILLAGE » toujours en capitales** dès que le mot apparaît dans du texte visible (titres, baselines, nav « Votre VILLAGE », footer, corps, méta, alt/aria). Ne **jamais** toucher aux URLs/chemins/slugs (`/le-village`, `village-iraty-biarritz.fr`, noms de fichiers) ni aux sélecteurs.
- **« zone » → « quartier »** quand le mot décrit le VIB (ex. « la zone la plus dynamique » → « le quartier le plus dynamique », accorder l'article : « de la zone » → « du quartier »). **Exception** : ne pas toucher au terme générique/au sujet d'article « zones d'activités » ni à son slug d'URL.
- **Exception slogan** : sur la page « Le village » le titre reste **« Un village qui VIBre »** (`village` en minuscules, **VIB** en capitales). C'est le seul endroit où `village` reste en minuscules.

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

- **`main`** = branche de production. Chaque push sur `main` déclenche un déploiement cPanel automatique
- **Jamais de push direct sur `main`** pour les modifs non-triviales : créer une branche `feature/xxx` ou `fix/xxx`, puis PR
- **Commits** : messages clairs en français ou anglais, présent de l'indicatif (`Ajoute la section contact`, `Fix typo dans le footer`)
- **Déploiement** : push sur `main` → cPanel exécute `.cpanel.yml` → fichiers copiés dans `public_html/`. Vérifier le déploiement dans cPanel > Git Version Control > Pull or Deploy

## Sécurité

- HTTPS forcé via `.htaccess` (redirection 301 HTTP → HTTPS)
- Headers de sécurité : `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`
- Listing des répertoires désactivé
- Accès aux fichiers sensibles (`.env`, `.htaccess`, `.cpanel.yml`, `CLAUDE.md`...) bloqué
- Jamais de credentials en dur dans le repo. Utiliser les variables d'environnement cPanel ou un fichier `.env` (déjà ignoré par `.gitignore`)
