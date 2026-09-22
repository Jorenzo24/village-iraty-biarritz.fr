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
3. **Vérifier AVANT de pousser**, en local — un aller-retour de déploiement coûte cher (deploy manuel + purge CDN) :
   ```bash
   python3 -m http.server 8765 --bind 127.0.0.1   # depuis la racine du repo
   ```
   puis `http://127.0.0.1:8765/entreprise.html?slug=<slug>` (les URL réécrites `/acteur/<slug>`
   n'existent pas sans Apache, mais le formulaire `?slug=` que vise la réécriture, si).
   Indispensable pour tout ce qui est rendu en JS : c'est ce qui a permis de voir en une passe que
   `renderHours` cassait 3 fiches, et de tester le rendu à 2000/1280/390 px.
4. Commit + push sur `main`, puis cliquer **Deploy HEAD Commit** dans cPanel.
5. Vérifier le rendu sur l'URL live (capture Playwright). Playwright est installé dans `vib-refonte/node_modules/` : un script qui l'importe doit être exécuté **depuis `vib-refonte/`**, sinon `ERR_MODULE_NOT_FOUND` — y compris un script écrit ailleurs, donc le copier dans `vib-refonte/` avant de le lancer.

**Documentation du chantier** (référence, pas du code) : [`vib-refonte/DESIGN_SYSTEM.md`](vib-refonte/DESIGN_SYSTEM.md), [`BRIEF_HOMEPAGE.md`](vib-refonte/BRIEF_HOMEPAGE.md), [`CONTENT_HOMEPAGE.md`](vib-refonte/CONTENT_HOMEPAGE.md), [`CLAUDE.md`](vib-refonte/CLAUDE.md) (méthode Playwright + maquette `https://vib-site.vercel.app/`). Ces fichiers décrivent l'ancien cycle de préprod sous `/vib-refonte/` — **il n'a plus cours**, mais le design system et les briefs restent valables.

⚠️ **Templates de fiche détail servis sur URL réécrite à 2 segments** (`entreprise.html` → `/acteur/<slug>`, `local.html` → `/local/<slug>`) : utiliser des **chemins d'assets ABSOLUS** (`/assets-2026/...`) — en relatif ils se résolvent en `/acteur/assets-2026/...` (404, page nue). Les pages à 1 segment (`/activites`, `/louer-un-local`, etc.) tolèrent le relatif. Les fiches détail posent aussi `canonical`/`og:*` dynamiquement par slug via leur JS (`entreprise.js`, `local.js`).

⚠️ **Fiches acteur/local rendues en JavaScript** : `entreprise.html` et `local.html` sont des coquilles vides remplies par JS depuis `data/*.json`. Googlebot exécute le JS et les indexe correctement, **mais les crawlers IA (GPTBot, ClaudeBot, PerplexityBot) ne l'exécutent pas** : pour eux ces pages sont vides. Tout balisage Schema.org injecté en JS y serait donc invisible. Une pré-génération HTML statique depuis `data/entreprises.json` reste à arbitrer.

---

## Hébergement
- **VPS** : Hetzner
- **Panneau** : cPanel
- **Username cPanel** : `villageiratybiar`
- **Deploy path** : `/home/villageiratybiar/public_html/`
- **CDN** : ⚠️ **le site est derrière Cloudflare** (en proxy : les réponses portent `server: cloudflare`, `cf-cache-status`, `cf-ray`). Conséquence majeure sur les images et le cache — voir la section **Images**. Après un déploiement qui remplace des fichiers statiques, **purger le cache Cloudflare**, sinon l'ancienne version reste servie malgré le `git pull`.
- **Déploiement** : ⚠️ cPanel **n'exécute PAS `.cpanel.yml`** sur ce serveur, malgré la présence du fichier. Il fait un simple `git pull` de `main` dans `public_html/`, donc **l'URL live = le chemin dans le repo**. Le déploiement n'est **pas automatique au push** : il faut cliquer **Deploy HEAD Commit** dans cPanel › Git Version Control.
- **Vérifier le live** : toujours contourner le cache CDN avec un query string unique par requête (`?x=$RANDOM`) et lire `cf-cache-status`, sinon on teste le cache et pas le serveur.

## Stack
HTML5 / CSS3 / JavaScript vanilla. Pas de framework, pas de build step. Les fichiers du repo sont copiés tels quels sur le serveur.

## Structure
```
.
├── .cpanel.yml          # Présent mais NON exécuté par cPanel (cf. Hébergement)
├── .htaccess            # HTTPS, rewrites d'URL propres, cache, sécurité
├── robots.txt
├── sitemap.xml
├── llms.txt             # Carte du site pour les LLM (aucun effet mesurable attendu)
├── index.html           # Pages du site, servies en /<nom> via le catch-all .htaccess
├── le-village.html  activites.html  louer-un-local.html  services.html
├── a-propos.html    contact.html    nos-articles.html    faq.html
├── entreprise.html      # Template fiche acteur → /acteur/<slug>   (rendu JS)
├── local.html           # Template fiche local  → /local/<slug>    (rendu JS)
├── article.html         # Template article      → /<slug>          (rendu JS)
├── regie-vib.html  mentions-legales.html  politique-confidentialite.html  404.html
├── send.php             # Backend du formulaire de contact (SMTP, cf. .env)
├── data/                # Source de vérité du contenu data-driven
│   ├── entreprises.json #   90 acteurs
│   ├── locaux.json      #   locaux à louer
│   ├── articles.json    #   articles du blog
│   └── entreprises-a-integrer.json   # file d'attente, non servie
├── assets-2026/         # ✅ Design 2026 — utilisé par les pages refondues
│   ├── css/  design-system.css (tokens) + styles.css (composants)
│   ├── js/   main.js, activites.js, entreprise.js, local.js, article.js,
│   │         articles-list.js, open-status.js
│   └── images/          # jumeau .webp par image, SAUF les og:image (cf. Images)
├── css/style.css        # ⚠️ Ancien design — regie-vib + pages légales uniquement
├── js/                  # ⚠️ Mixte : voir l'avertissement ci-dessous
├── assets/              # Photos, fiches PDF, etc. (idem : jumeaux .webp)
│   ├── photos/_originaux_lourds/   # archive 51 Mo, servie par AUCUNE page — ne pas optimiser
│   └── og/og-image.jpg             # PAS de jumeau .webp — comme hero-drone.jpg,
│                                   # story-interieur.jpg, photos/drone-aerien.jpg
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
- **SVG inline** pour les icônes (permet de styler en CSS)
- **Jamais de hotlink** : toutes les images doivent être hébergées dans `assets/`
- **Lazy loading** : `loading="lazy"` sur les `<img>` hors viewport initial (déjà en place partout ; ne PAS le mettre sur le logo du header ni sur l'image de hero, qui sont above-the-fold)

⚠️ **Le WebP n'est PAS référencé dans le HTML — il est servi par négociation de contenu.**
Le HTML, le JS et `data/*.json` pointent tous vers le `.jpg`/`.png`. Un bloc de
[`.htaccess`](../.htaccess) réécrit la requête vers le `.webp` **jumeau** (même nom, même dossier)
quand le navigateur envoie `Accept: image/webp` et que le fichier existe :

```apache
RewriteCond %{HTTP_ACCEPT} image/webp
RewriteCond %{DOCUMENT_ROOT}/$1.webp -f
RewriteRule ^(.+)\.(jpe?g|png)$ /$1.webp [T=image/webp,E=accept:1,L]
```

Conséquences à connaître :
- **Ne jamais écrire `.webp` dans un `src`, ni de `<picture>`/`srcset`.** On référence le JPEG/PNG, point. C'est ce qui permet aux fiches `/acteur/<slug>` et `/local/<slug>`, dont les `<img>` sont construites en JS, d'en bénéficier aussi — un `<picture>` y serait impossible.
- **Toute nouvelle image doit avoir son jumeau `.webp`**, sinon elle est servie en JPEG à tout le monde (dégradation silencieuse, aucune erreur visible).

⚠️⚠️ **Le site est derrière Cloudflare, et Cloudflare IGNORE `Vary: Accept`.**
L'origine répond correctement (JPEG sans l'en-tête, WebP avec, `Vary: Accept` posé), mais
Cloudflare ne garde **qu'une seule variante par URL** : celle de la première requête après une
purge. En pratique c'est presque toujours le WebP, ensuite servi à *tout le monde* — y compris aux
navigateurs qui ne l'acceptent pas. Le repli JPEG n'est donc **pas** garanti en bout de chaîne.

Ce que ça impose :
- **Une image utilisée en `og:image`/`twitter:image` ne doit JAMAIS avoir de jumeau `.webp`** : c'est
  la seule façon de garantir qu'un scraper social reçoive du JPEG. Concernées à ce jour :
  `assets-2026/images/hero-drone.jpg` (9 pages), `assets-2026/images/story-interieur.jpg` (2),
  `assets/photos/drone-aerien.jpg`, `assets/og/og-image.jpg`. Avant d'ajouter une image d'aperçu,
  supprimer son `.webp` s'il existe.
- **Après tout remplacement d'image, purger le cache Cloudflare**, sinon l'ancienne version continue
  d'être servie (cache navigateur *et* CDN à 1 an). C'est ce qui s'est passé au déploiement d'août
  2026 : une partie des images restait à la version pré-optimisation.
- Piège de test : deux `curl` sur la même URL ne prouvent rien, le second tape le cache CF. Utiliser
  un query string **différent à chaque requête** et lire `cf-cache-status`.

Si le plan Cloudflare le permet, **activer Polish** (conversion WebP/AVIF au niveau CDN) serait plus
propre : il gère la négociation nativement et permettrait de supprimer et la règle `.htaccess` et
tous les jumeaux `.webp` du repo.

**Pipeline à appliquer à toute image ajoutée** (`magick`, `cwebp`, `exiftool`, `sips` sont installés) :

```bash
# 1. Redimensionner : 1400px max sur le grand côté (1920 pour un fond de hero,
#    600 pour un logo). -auto-orient règle l'EXIF, -strip purge les métadonnées.
magick in.jpg -auto-orient -resize '1400x1400>' -colorspace sRGB -strip \
       -sampling-factor 4:2:0 -interlace JPEG -quality 82 out.jpg
# 2. Générer le jumeau webp (-lossless pour un logo, lossy pour une photo)
cwebp -quiet -q 82 -m 6 -alpha_q 100 out.jpg -o out.webp
```

Repères : aucune image n'est jamais affichée plus grande que son conteneur (**il n'y a pas de
lightbox**), les cartes sont en `aspect-ratio: 4/3` sur ~400px. 1400px couvre donc le retina
partout. Vérifier `-colorspace sRGB` : deux logos étaient en **CMYK** et s'affichaient délavés
hors Safari.

### CSS
- Mobile-first (media queries `min-width`, pas `max-width`)
- **Toujours réutiliser les tokens de `assets-2026/css/design-system.css`** (`--color-accent` = #AC2C26 rouge basque, `--color-bg-alt` = #FBF8F4 crème, `--space-1..10`, `--radius-*`, `--fs-*`). Ne jamais coder une valeur en dur si un token existe. (Le vieux `css/style.css` a son propre reset, il ne concerne que les 3 pages en ancien style.)
- Composants dans `assets-2026/css/styles.css`, tokens dans `design-system.css`. Ne pas mélanger.

⚠️ **Alternance des fonds de section** : les sections de contenu alternent **crème / blanc** (`.section--cream` / `.section--white`), et font toutes **128px de padding** (`--section-py`). Deux sections de même fond qui se suivent produisent ~258px de vide continu sans frontière visible — ça se voit tout de suite et ça passe pour un bug de mise en page. **Vérifier le fond de la section précédente avant d'insérer une section.** Rythme actuel de l'accueil : stats (crème) → story (blanc) → annuaire (crème) → avantages (blanc) → espaces (crème) → blog (blanc) → FAQ (crème) → CTA final. Ne pas corriger un espacement jugé trop grand en réduisant le padding : c'est presque toujours un problème de fond, pas d'espacement.

### JavaScript
- Vanilla JS, pas de jQuery
- `defer` ou en fin de `<body>` (déjà fait)
- `DOMContentLoaded` pour le code qui touche au DOM

## Contenu data-driven : locaux & acteurs

Les **fiches détail** sont générées dynamiquement à partir de fichiers JSON, mais les **listes (cartes) sont codées en dur dans le HTML**. Quand on ajoute un local ou un acteur, il faut donc toucher **plusieurs endroits** (sinon la fiche existe mais n'apparaît dans aucune liste, ou inversement).

### Locaux à louer
- **Données** : [`data/locaux.json`](../data/locaux.json) — champs : `slug`, `name`, `address`, `surface` (nb), `price_ht` (nb), `charges_ht` (nb, 0 si aucune), `type`, `norm_pmr`/`no_fees`/`no_pas_de_porte` (bool → chips), `description`, `photos` (liste de chemins `/assets/...`, le 1er = cover).
- **Fiche détail** : `local.html` rendue par [`assets-2026/js/local.js`](../assets-2026/js/local.js). URL `/local/<slug>` (rewrite `.htaccess` → `local.html?slug=`). Si `photos` vide → placeholder auto.
- **Carte liste (en dur)** : ajouter un `<article class="local-card">` dans [`louer-un-local.html`](../louer-un-local.html) (grid `.locaux-grid`).
- ⚠️ **L'accueil affiche AUSSI 2 cartes de locaux en dur** (section « espaces », grid `.espaces-grid`), avec leur propre image dans `assets-2026/images/locaux/`. C'est un 4ᵉ endroit à toucher, facile à oublier : `grep -rn '<slug>' index.html louer-un-local.html data/locaux.json sitemap.xml` avant de conclure.
- **Sitemap** : ajouter `/local/<slug>`.
- **Photos** : `assets/photos/locaux/<slug>/` (dossier = slug), `cover.jpg` en premier, puis `photo-01.jpg`, `photo-02.jpg`… + jumeaux `.webp` (cf. section Images).
- `description` : `local.js` remplace chaque `. ` par un `<br>` — la description se rend donc **une phrase par ligne**. Rédiger en phrases courtes ; ni `\n` ni puces (contrairement à `entreprise.js`).
- `charges_ht: 0` **masque** le bloc charges sur la fiche (`local-charges-block`). Ne mettre 0 que s'il n'y a réellement aucune charge.

⚠️ **Quand un local est loué**, ne pas se contenter de le retirer : son URL `/local/<slug>` est
indexée et référencée au sitemap. Sans redirection, `local.js` ne trouve plus le slug et sert une
**fiche vide** (pas un 404). Ajouter une 301 vers `/louer-un-local` dans `.htaccess`, à côté des
autres redirections historiques. Exemple en place : `local/duplex-commercial-de-90mm2-ref-dar41`
(lot 38, loué en août 2026, remplacé par le lot 50).

### Acteurs (entreprises)
- **Données** : [`data/entreprises.json`](../data/entreprises.json) — champs : `slug`, `name`, `category`, `category_label`, `description`, `address`, `phone`, `email`, `website`, `hours`, `photos` (1er = cover), `logo`, `social` (objet). Champs vides = `""` ou `[]`/`{}` (tout est conditionnel côté JS). `address: ""` est supporté et ne laisse aucun trou visuel ; `hours: ""` masque le bloc horaires et le badge — cas typique de l'atelier sur commande sans boutique ouverte au public (`clairement-sucre`, septembre 2026).
- **Catégories** (`category` → `category_label`) : `commerces`→Commerces, `restaurants`→Restaurants & Bars, `sante`→Santé & soins, `services`→Services, `sport`→Sports & Loisirs, `entreprises`→Entreprises, `createurs`→Créateurs. (Immobilier/agences = `services`.)
- **Fiche détail** : `entreprise.html` rendue par [`assets-2026/js/entreprise.js`](../assets-2026/js/entreprise.js). URL `/acteur/<slug>`. `description` multi-paragraphes via `\n` (les lignes commençant par •/- deviennent des `<ul>`).
- **Carte liste (en dur)** : ajouter un `<li>` dans [`activites.html`](../activites.html) (grid `#acteurs-grid`). Markup réel — **pas** `class="card"` ni `#cards-grid`, que documentaient à tort les versions précédentes de ce fichier :
  ```html
  <article class="acteur-card" data-cat="<category>" data-name="<nom en minuscules> <category_label en minuscules>" data-hours="<format OSM, cf. Horaires>">
    <a href="/acteur/<slug>" class="acteur-card__media">
      <img src="/assets/photos/entreprises/<slug>/cover.jpg" alt="<NOM>, VILLAGE Iraty-Biarritz" loading="lazy">
      <span class="badge badge--category"><category_label></span>
      <span class="badge acteur-card__status" data-status hidden></span>
    </a>
    <div class="acteur-card__body">
      <h3 class="acteur-card__name"><NOM></h3>
      <p class="acteur-card__desc"><~100 car., tronqué par « … » si coupé></p>
      <a href="/acteur/<slug>" class="acteur-card__more">Voir la fiche →</a>
    </div>
  </article>
  ```
  Les cartes sont **triées alphabétiquement par nom**, toutes catégories confondues. Le filtre/recherche ([`assets-2026/js/activites.js`](../assets-2026/js/activites.js)) s'appuie sur `data-cat`/`data-name` ; `data-hours` est lu par `main.js`, pas par `activites.js`. Omettre `data-hours` si l'acteur n'a pas d'horaires : la carte n'aura pas de badge.
- ⚠️ **Compteurs en dur, 6 endroits à corriger à chaque ajout/suppression.** Dans [`activites.html`](../activites.html) : la `<meta name="description">`, le `page-hero__lead`, la pastille `data-cat="all"` et la pastille de la catégorie touchée. Dans [`llms.txt`](../llms.txt) : deux mentions du nombre d'acteurs. Le compteur `#acteur-count` sous les filtres, lui, est calculé en JS — il ne se corrige pas à la main et **trahit immédiatement une pastille fausse**.
- ⚠️ **L'accueil affiche AUSSI 6 cartes vedettes en dur** ([`index.html`](../index.html), grid `.acteurs-grid`), avec leur **propre image** dans `assets-2026/images/acteurs/<slug>.jpg` (jeu distinct de `assets/photos/entreprises/`). C'est un 5ᵉ endroit à toucher, facile à oublier. La grille fait 3 colonnes : **garder un multiple de 3**, sinon la dernière rangée est bancale. Réflexe : `grep -rn '<slug>' index.html activites.html data/entreprises.json sitemap.xml` avant de conclure.
- **Sitemap** : ajouter `/acteur/<slug>` (le fichier est trié alphabétiquement).
- **Photos** : `assets/photos/entreprises/<slug>/` (dossier = slug), `cover.jpg` + `logo.png|jpg`.
- ⚠️ **`photos[0]` sert d'`og:image`** à la fiche (`entreprise.js`). C'est en tension avec la règle Cloudflare de la section **Images** (une image d'aperçu ne doit pas avoir de jumeau `.webp`) : **80 des 88 covers en ont un**. Arbitrage assumé — supprimer un seul jumeau n'aiderait qu'une fiche tout en alourdissant la grille des 90 cartes. À trancher globalement, pas fiche par fiche.

⚠️ **Quand un acteur est retiré**, même piège que pour les locaux : son URL `/acteur/<slug>` est indexée
et au sitemap. Sans redirection, `entreprise.js` ne trouve plus le slug et sert une **fiche vide**
(pas un 404). Ajouter une 301 vers `/activites` dans `.htaccess`, **avant** la règle de réécriture
`^acteur/([a-z0-9\-]+)`. Exemples en place : `la-guinguette-des-sardines`, `lecho-du-silence`,
`wilau-proprete` (retirés en septembre 2026).

### Horaires : DEUX systèmes indépendants, à saisir deux fois

C'est le piège le moins évident de l'ajout d'un acteur : **les horaires existent dans deux formats
différents, dans deux fichiers différents, lus par deux scripts différents.** Oublier l'un des deux
ne casse rien visiblement — la fiche ou la carte perd juste son badge.

| | Fiche `/acteur/<slug>` | Cartes (accueil + `/activites`) |
|---|---|---|
| **Source** | champ `hours` de `entreprises.json` | attribut `data-hours` sur `.acteur-card` |
| **Format** | texte libre français | OSM : `Mo 09:00-17:00;Tu 09:00-17:00` |
| **Badge** | `assets-2026/js/open-status.js` | `assets-2026/js/main.js` |
| **Affichage** | `renderHours()` dans `entreprise.js` | — |

**Format du champ `hours`** (texte libre) : segments séparés par ` · `, plages multiples par jour
avec « et », plages d'horaires « de Xh à Yh ». Le passage minuit est géré (ex. `de 18h à 2h`).
- `Du mardi au vendredi de 10h à 12h et de 13h à 18h · Samedi de 10h à 18h`
- `Du lundi au mercredi de 9h à 15h · Du jeudi au vendredi de 9h à 15h et de 18h à 2h · Samedi de 18h à 2h`
- Vide ou `Sur rendez-vous` → pas de badge, et le bloc horaires de la fiche est masqué.

⚠️ **Le ` · ` sépare des segments, jamais un jour de sa valeur.** `Dimanche · Fermé` produit une
ligne « Dimanche » sans horaire **plus** une ligne « Fermé » orpheline. Écrire `Dimanche Fermé`.
(Coquille corrigée sur `centre-lazeo` en septembre 2026.)

**Rendu dans la colonne de la fiche** (`renderHours`) : la carte ne fait que **340 px**, la mise en
page est donc contrainte. Le libellé de jour est à gauche en gras, les horaires à droite, et :
- une suite de **3 jours consécutifs ou plus est compactée en plage** (`Lundi, mardi, mercredi,
  jeudi, vendredi` → `Lundi - Vendredi`) ;
- une liste **non consécutive est laissée telle quelle** (`Lundi - Mercredi - Vendredi` = lundi,
  mercredi et vendredi — le tiret y sépare des jours distincts, la compacter publierait un horaire faux) ;
- le libellé est `nowrap`, seule la colonne des horaires se replie ;
- chaque plage est insécable (`.hours-slot`), « de » introductif compris.

Ne pas « réparer » un affichage d'horaires en empilant la ligne : si une ligne déborde, c'est le
libellé qui est trop long, pas la mise en page qui est mauvaise. Empiler une ligne parmi des lignes
en vis-à-vis casse l'alignement de toute la colonne.

### ⚠️ Orientation EXIF des photos (piège iPhone)
Les photos prises au téléphone ont souvent un tag EXIF `Orientation=6` (« Rotate 90 CW ») : elles s'affichent droites dans certains contextes mais **de travers** ailleurs (aperçus OG, vieux navigateurs).

Le pipeline de la section **Images** règle ça tout seul : `-auto-orient` applique la rotation aux
pixels et `-strip` purge le tag. Si on traite une image hors pipeline, le faire à la main :
```bash
sips -r 90 photo.jpg                                  # redresse les pixels (90° CW pour orientation 6)
exiftool -Orientation=1 -n -overwrite_original photo.jpg
exiftool -Orientation -filename -T photo.jpg          # vérifier : doit afficher "Horizontal (normal)"
```
Dans tous les cas, contrôler visuellement le résultat (`Read` sur l'image) — un tag corrigé ne
garantit pas que les pixels sont dans le bon sens.

## Anti-spam du formulaire de contact

Trois couches dans [`send.php`](../send.php), posees en aout 2026 (3 a 5 spams crypto/jour avant).

1. **Cloudflare Turnstile** — widget `.cf-turnstile` dans `contact.html` (site key publique en dur),
   token verifie en cURL cote serveur (timeout 10 s) avant tout envoi. Echec → **403** + message FR.
2. **Honeypot** — champ `website` pousse hors ecran (`position:absolute;left:-9999px`,
   `tabindex="-1"`, `autocomplete="off"`). **Jamais `display:none`** : des bots le detectent.
3. **Filtrage de contenu** — rejet si URL, si mot-cle crypto (`token`, `USD`, `bitcoin`, `balance`...),
   ou si un message de plus de 20 caracteres ne contient aucun mot francais courant.

⚠️ **Les couches 2 et 3 repondent un faux succes** (HTTP 200 + « Message envoyé avec succès »)
sans envoyer de mail : le bot ne doit pas apprendre qu'il est filtre. Consequence directe : **un
faux positif est invisible cote visiteur**. C'est a ca que sert le log ci-dessous, a relire les
premieres semaines.

⚠️ **`TURNSTILE_SECRET_KEY` vit dans `.env`, qui est gitignore** : un `git pull` sur cPanel ne le
transporte pas. Il faut l'ajouter **a la main** dans `/home/villageiratybiar/public_html/.env`.
Si le secret est absent, `send.php` **n'applique pas** la couche 1 (le formulaire continue de
marcher, protege par les couches 2 et 3) et ecrit `turnstile_non_configure` dans le log — c'est
volontaire : mieux vaut un formulaire ouvert qu'un formulaire casse, mais ce n'est pas silencieux.
Meme logique si l'API Cloudflare est injoignable → `turnstile_indisponible`, on laisse passer.

**Log des rejets** : `logs/spam.log`, TSV `date · IP · raison · detail · extrait (100 car.)`.
Le dossier est protege par `logs/.htaccess` et le fichier par la regle `\.log$` du `.htaccess`
racine ; `*.log` est gitignore. Aucune rotation automatique — le purger de temps en temps.
L'IP journalisee est `CF-Connecting-IP` (le site est derriere Cloudflare, `REMOTE_ADDR` ne donne
que l'edge CF) ; le rate limit par IP utilise desormais la meme source, il etait jusqu'ici
inoperant puisqu'il regroupait tous les visiteurs derriere quelques IP Cloudflare.

Faux positifs a surveiller en priorite : **`balance`** (mot francais courant : « balance de
cuisine », un commercant du VILLAGE peut l'ecrire) et la **regle URL**, qui rejette un visiteur
qui donne l'adresse de son propre site.

## Cache-busting

**À chaque modification d'un CSS ou d'un JS**, il faut bumper le query string `?v=AAAAMMJJx` sur toutes les pages qui référencent le fichier touché — en pratique `assets-2026/css/*.css` et `assets-2026/js/*.js` pour les pages refondues, `css/style.css` et `js/main.js` pour les 3 pages restées en ancien style.

Ne concerne **que** les CSS/JS : le HTML est en cache 1 heure et `data/*.json` n'a pas de règle `Expires` (il n'est que compressé), donc une modif de contenu se voit sans bump.

⚠️ **Les images sont en cache navigateur 1 an et n'ont pas de `?v=`.** Remplacer une image en
gardant son nom ne rafraîchit donc rien chez un visiteur qui l'a déjà en cache. Pour un vrai
changement visuel (photo différente), **créer un nouveau nom de fichier**. C'est acceptable
uniquement quand l'image reste la même et que seul son poids change — c'est le cas de
l'optimisation d'août 2026 : les visiteurs récurrents gardent l'ancienne version lourde jusqu'à
expiration, les nouveaux profitent du gain immédiatement.

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

### État du balisage (2026-07-16)

- `LocalBusiness` sur `index.html`, `@id` = `https://village-iraty-biarritz.fr/#organization` — **le référencer par `@id` depuis les autres pages** plutôt que de le dupliquer (fait sur `contact.html` via `ContactPage.mainEntity`).
- `BreadcrumbList` sur toutes les pages clés. `FAQPage` sur `/faq` **uniquement** : les 5 questions reprises sur l'accueil sont volontairement non balisées (deux entités `FAQPage` sur le même contenu se desservent).
- ⚠️ **Le JSON-LD `FAQPage` doit rester le miroir exact des questions/réponses visibles.** Baliser du contenu absent de la page est sanctionné par Google. Script de contrôle utilisé : comparer les `name` des `mainEntity` aux `<summary class="faq-item__q">` et vérifier que chaque `acceptedAnswer.text` existe dans le texte rendu.
- ❌ **`Review` : absent, et à laisser absent tant qu'il n'y a pas de vrais témoignages clients.** Inventer des avis viole les guidelines Google (risque de pénalité manuelle sur le domaine) et Google n'affiche plus les avis auto-déclarés depuis 2019. Ne jamais fabriquer d'avis, de statistiques ou de citations, même si c'est démontré efficace.

### Dettes connues (non traitées)

#### ⏳ En attente d'action manuelle (au 18/08/2026)

Ces trois points sont **hors du repo** — aucun commit ne les résoudra, ils demandent un accès au
dashboard Cloudflare. Tant qu'ils ne sont pas faits, l'optimisation d'images d'août 2026 n'est que
partiellement effective en production.

1. **Déployer le commit `1505012`** (cPanel › Deploy HEAD Commit). Il supprime les jumeaux `.webp`
   des images d'aperçu — à faire **avant** la purge, sinon Cloudflare peut mettre en cache leur
   variante WebP et la servir aux scrapers sociaux.
2. **Purger le cache Cloudflare** (Caching › Purge Everything). Sans ça, une partie des images reste
   servie en version pré-optimisation : `hero-drone` 748 Ko, `story-halle` 979 Ko,
   `boulangerie-enneartz` 1 051 Ko, `last-modified` du 1er juin 2026, alors que l'origine sert bien
   les nouvelles.
3. **Arbitrer Cloudflare Polish** (Speed › Optimization) — dépend du plan souscrit, non vérifié.
   S'il est disponible, il gère conversion **et** négociation au niveau CDN : on pourrait alors
   supprimer la règle WebP de `.htaccess` **et** les 299 jumeaux `.webp` du repo, et le risque
   résiduel ci-dessous disparaîtrait.

**Risque résiduel assumé en attendant** : Cloudflare ignorant `Vary: Accept`, les navigateurs sans
support WebP (< 3 %, très vieux Safari/IE) peuvent recevoir du WebP sur les images de contenu. Les
images d'aperçu, elles, sont protégées (aucun jumeau `.webp`). Détail dans la section **Images**.


- **Meta descriptions des 90 fiches acteurs** : `assets-2026/js/entreprise.js:44` recopie `e.description` **sans troncature** → 41 % dépassent 160 caractères (max 1490), médiane 48. Correctif : couper à ~155 car. sur une frontière de mot.
- **`data/articles.json`** : deux articles (`gros-plan-sur-le-quartier-iraty…` et `le-forum-des-associations…`) partagent un `summary` **identique**, qui est de plus **hors sujet** sur le premier. Les autres `summary` sont des fragments pris en milieu d'article. `article.js:44` les sert tels quels en meta description.
- **Longueurs** : title de `index.html` = 77 car. (coupé vers 60) ; descriptions de `louer-un-local` (179), `faq` (174), `activites` (170) débordent.
- **Aucune meta description ne manque** : seule la 404 n'en a pas, et elle est en `noindex` — c'est normal.
- **Volume de texte** : `le-village` (279 mots), `louer-un-local` (440), `a-propos` (234), `regie-vib` (217), `services` (125) sont sous le seuil de 600 mots visé par le plan client. `contact` (69) et `nos-articles` (27) sont hors sujet pour ce critère — un formulaire et un index n'ont pas à être gonflés, et les 27 mots de `nos-articles` viennent du rendu JS, pas d'un manque de contenu.

⚠️ **Auditer le HTML au `grep` est piégeux** : les balises ont des attributs dans un ordre variable (`<title id="page-title">`, `<meta id="page-description" name="description" …>`). Un motif comme `<title>` ou `<meta name="description"` rate ces balises et fait conclure à tort qu'elles manquent. **Vérifier le rendu réel avec Playwright** (`page.title()`, `getAttribute`) plutôt que de se fier à un grep.

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
