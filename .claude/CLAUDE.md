# village-iraty-biarritz.fr

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
