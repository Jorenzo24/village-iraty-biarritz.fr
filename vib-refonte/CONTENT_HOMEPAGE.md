# CONTENT_HOMEPAGE.md — Textes définitifs

> **Règle :** Claude Code ne doit JAMAIS inventer ou modifier un texte. Tous les textes définitifs sont ici. Si un texte manque (ex: témoignage à venir), un placeholder explicite est indiqué.

---

## Meta & SEO

```html
<title>Village Iraty Biarritz — Locaux commerciaux, coworking et commerces à Biarritz</title>
<meta name="description" content="Plus de 100 commerces, restaurants, espaces de coworking et services au cœur de Biarritz. Découvrez le Village Iraty-Biarritz et installez votre activité.">
<meta name="keywords" content="village iraty biarritz, local commercial biarritz, coworking biarritz, commerce biarritz, bureau à louer biarritz, zone commerciale biarritz">

<!-- Open Graph -->
<meta property="og:title" content="Village Iraty Biarritz — Le village qui fait vibrer Biarritz">
<meta property="og:description" content="Plus de 100 acteurs, commerces et services dans la zone la plus dynamique de Biarritz.">
<meta property="og:type" content="website">
<meta property="og:url" content="https://village-iraty-biarritz.fr">
<meta property="og:site_name" content="Village Iraty Biarritz">
<meta property="og:locale" content="fr_FR">

<!-- Twitter -->
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="Village Iraty Biarritz — Le village qui fait vibrer Biarritz">
<meta name="twitter:description" content="Plus de 100 acteurs, commerces et services dans la zone la plus dynamique de Biarritz.">
```

---

## Header / Navigation

**Logo** : `images/logo-vib-header.png` (alt: "Village Iraty Biarritz")

**Menu principal** (4 entrées — arbitrage du PDF, on remplace "Contact" par "Services") :

| Label | URL |
|---|---|
| Le Village | `/le-village` |
| Activités | `/activites` |
| Louer un local | `/louer-un-local` |
| Services | `/services` |

**Double CTA dans le header** (arbitrage du PDF — plus orienté conversion que "S'installer" seul) :
- **CTA primaire** : "Installer mon entreprise" → `/louer-un-local`
- **CTA secondaire** : "Prendre rendez-vous" → `/contact`

Sur mobile : burger menu, les CTA apparaissent en bas du menu déroulé.

---

## Section 1 — Hero

**Eyebrow** : `VILLAGE IRATY BIARRITZ`

**H1** : `Le village qui fait VIBrer Biarritz`
(Les lettres "VIB" sont visuellement mises en valeur — couleur d'accent ou italique)

**Sous-titre** : 
```
100+ acteurs · Commerces · Coworking · Restaurants · Services
Au cœur de la zone la plus dynamique du Pays Basque.
```

**CTA primaire** : "Installer mon entreprise" → `/louer-un-local`
**CTA secondaire** : "Prendre rendez-vous" → `/contact`

> Note : on remplace les CTA de la maquette ("Découvrir le Village" + "Installer mon entreprise") par le double CTA du site live ("Installer mon entreprise" + "Prendre rendez-vous"), plus business — arbitrage du PDF.

**Indicateur de scroll** : texte "Scroll" + animation (chevron descendant ou trait vertical pulsant)

**Image de fond** : `images/hero-drone.jpg` (vue aérienne du Village) avec overlay sombre léger pour la lisibilité du texte. Alt : "Vue aérienne du Village Iraty Biarritz"

---

## Section 2 — Statistiques

> ⚠️ **Important pour le GEO** : ces statistiques DOIVENT être rendues directement dans le HTML (pas chargées en JS), pour être indexables par les LLMs.

4 chiffres clés :

| Valeur | Label |
|---|---|
| 1 200+ | Acteurs |
| 3 000+ | Visiteurs / jour |
| 100+ | Commerces |
| 20 ans | D'existence |

Layout : grille 2x2 mobile, 4 colonnes desktop.

---

## Section 3 — Storytelling "Une histoire de vibrations"

> 🔄 **À réintégrer depuis le site live** (arbitrage du PDF — contenu narratif unique citable par les IA, absent de la maquette Vercel).

**Eyebrow** : `NOTRE HISTOIRE`

**H2** : `Une histoire de vibrations`

**Texte** (placeholder à valider avec l'équipe VIB — récupérer le texte exact depuis le site live) :

```
Depuis 2003, le Village Iraty Biarritz fait battre le cœur économique du Pays Basque.
Plus qu'une zone commerciale, c'est un écosystème vivant où artisans, créateurs,
restaurateurs et entrepreneurs partagent la même énergie.

Notre mission : préserver la diversité, soutenir l'entrepreneuriat local,
et offrir un cadre accessible à ceux qui font vibrer Biarritz au quotidien.

20 ans plus tard, plus de 100 commerces et services y ont trouvé leur place,
et chaque année, de nouveaux acteurs viennent enrichir l'aventure.
```

**Action** : récupérer le texte exact depuis `village-iraty-biarritz.fr` avant publication. Le bloc doit faire **environ 600 mots minimum** (recommandation du PDF pour la richesse sémantique GEO) — à compléter avec l'équipe.

---

## Section 4 — Annuaire (preview acteurs)

**Eyebrow** : `ANNUAIRE`

**H2** : `Ils font vibrer le Village`

**Description** :
```
Artisans, restaurateurs, créateurs et entrepreneurs partagent la même énergie
dans un espace unique au cœur de Biarritz.
```

**6 fiches acteurs en preview** (les mêmes que sur la maquette pour démarrer — les vraies données viendront du futur CMS) :

| Catégorie | Nom | Description | Adresse |
|---|---|---|---|
| Restaurants | MAISON VISHNU | Plats indiens & sri-lankais à emporter | Halles DARLA, local 61, 16 rue des Mésanges, 64200 Biarritz |
| Santé & soins | INOVIE AXBIO | Laboratoire d'analyses médicales | Halle DARLA, local 14, 12 rue des Mésanges, 64200 Biarritz |
| Services | WILAU PROPRETÉ | Nettoyage industriel | Halle DARLA, local 26, 10 rue des Mésanges, 64200 Biarritz |
| Commerces | 2P GOURMANDS | Épicerie épicurienne | Halles DARLA, local 13, 12 rue des Mésanges, 64200 Biarritz |
| Santé & soins | LAZEO BIARRITZ | Épilation laser & soins esthétiques | Halle DARLA, local 67, 16 rue des Mésanges, 64200 Biarritz |
| Commerces | LE VIOLON DANS L'ÂME | Atelier de lutherie | Halle DARLA, local 30, 10 rue des Mésanges, 64200 Biarritz |

**Sur chaque fiche** :
- Image (4:3) — utiliser les images de la maquette comme placeholder
- Badge catégorie en haut
- 🆕 **Badge "Ouvert" / "Fermé"** (recommandation PDF — UX enrichie) — à câbler sur les horaires plus tard, pour l'instant on peut afficher "Ouvert" en dur sur 5/6 et "Fermé" sur 1/6 comme démo
- Nom (h3)
- Description (1 ligne)
- Adresse (texte petit, muted)
- Hover : carte qui se soulève légèrement

**CTA bas de section** : "Découvrir les 93 acteurs" → `/activites`

---

## Section 5 — Avantages "Pourquoi choisir le Village"

**Eyebrow** : `AVANTAGES`

**H2** : `Pourquoi choisir le Village`

**3 avantages** (avec images, layout alterné gauche/droite sur desktop) :

### 1. Loyers accessibles
```
30% en dessous du marché biarrot. La SEBI maintient des loyers modérés
pour favoriser la diversité et l'entrepreneuriat local. À partir de 590€/mois.
```
Image : `images/facade-darla.jpg` (alt: "Façade du Village Iraty")

### 2. Emplacement stratégique
```
Aéroport international à 5 min, gare TGV à 10 min, autoroute A63.
Desservi par 5 lignes de bus. Un carrefour d'accessibilité unique au Pays Basque.
```
Image : `images/hero-drone.jpg` (alt: "Vue aérienne du Village Iraty")

### 3. Accompagnement sur mesure
```
Aide au financement, espaces de coworking, conseils personnalisés.
Chaque entrepreneur bénéficie d'un suivi adapté à son projet.
```
Image : `images/coworking-space.png` (alt: "Espace de coworking au Village Iraty")

> Note : ce trio "Aide au financement / Coworking / Offre sur mesure" est explicitement validé par le PDF comme structurant l'offre de manière claire.

---

## Section 6 — Espaces disponibles (immobilier)

**Eyebrow** : `IMMOBILIER`

**H2** : `Espaces disponibles`

**CTA en haut à droite** : "Voir tous les espaces" → `/louer-un-local`

**4 locaux en preview** (les mêmes que sur la maquette pour démarrer) :

| Référence | Titre | Surface | Prix |
|---|---|---|---|
| REF-OCC71 | Local commercial en duplex | 472 m² | 4 930€/mois HT |
| REF-DAR42BIS | Espace professionnel | 44 m² | 590€/mois HT |
| REF-ALD21 | Local activité médicale | 42 m² | 906,10€/mois HT |
| REF-DAR38 | Local commercial duplex | 90 m² | 1 165€/mois HT |

**Sur chaque carte** :
- Badge "Disponible" (vert) en haut
- Référence en petit, muted
- Titre (h3)
- Surface · Prix HT/mois
- CTA "En savoir plus" → `/louer-un-local`

> ⚠️ **GEO** : ces prix doivent être en HTML brut (pas en JS) pour être lisibles par les LLMs.

---

## Section 7 — Témoignages

> 🆕 **À ajouter** (recommandation PDF — totalement absent des deux versions actuelles).

**Eyebrow** : `ILS NOUS FONT CONFIANCE`

**H2** : `Témoignages d'acteurs du Village`

**Placeholder** : prévoir le markup pour 3 témoignages (carrousel ou grille), à remplir avec l'équipe VIB après collecte.

Structure attendue par témoignage :
- Photo de l'acteur (rond, 80px)
- Verbatim ("Depuis que je suis installé au Village...")
- Nom + activité ("Marie Dupont — Maison Vishnu")
- Note (optionnel : 5 étoiles)

En attendant la vraie collecte, mettre 3 placeholders explicites avec texte `[À COLLECTER]` pour ne pas oublier.

---

## Section 8 — Blog (actualités)

**Eyebrow** : `BLOG`

**H2** : `Actualités du Village`

**3 articles** (les mêmes que sur la maquette pour démarrer) :

| Date | Titre | Extrait | Image |
|---|---|---|---|
| 20 août 2025 | Pourquoi de plus en plus de professionnels s'installent en zones d'activités | Accessibilité, flexibilité, coûts maîtrisés : les zones d'activités séduisent une nouvelle génération d'entrepreneurs. | `village-sunset.png` |
| 4 août 2025 | Les meilleurs endroits pour une virée shopping à Biarritz | Des ruelles du centre-ville aux ateliers du Village Iraty, Biarritz regorge de destinations shopping. | `boutique-commerce.png` |
| 5 septembre 2024 | Gros plan sur le quartier Iraty de Biarritz et son marché immobilier | Le quartier Iraty se transforme en pôle d'activités dynamique, porté par une association fédératrice. | `restaurant-basque.png` |

CTA par carte : "Lire l'article" → `/blog/[slug]`

---

## Section 9 — CTA final "Prêt à faire partie de l'aventure ?"

**Image de fond** : `images/village-sunset.png` avec overlay sombre

**Eyebrow** : `REJOIGNEZ-NOUS`

**H2** : `Prêt à faire partie de l'aventure ?`

**Sous-titre** :
```
Des espaces professionnels à partir de 590€/mois dans la zone la plus dynamique de Biarritz.
```

**CTA primaire** : "Nous contacter" → `/contact`
**CTA secondaire** : "Voir les locaux disponibles" → `/louer-un-local`

---

## Footer

**Logo footer** : `images/logo-vib-footer.png`

**Texte d'intro** :
```
Plus de 100 acteurs qui font vibrer Biarritz depuis 2003.
Commerces, restaurants, coworking et services.
```

### Colonne "Navigation"
- Le Village → `/le-village`
- Activités → `/activites`
- Louer un local → `/louer-un-local`
- Services → `/services`
- Contact → `/contact`

### Colonne "Contact"
- Adresse : `4 Rue des Mésanges, 64200 Biarritz`
- Téléphone : `05 59 23 93 10` (tel:0559239310)
- Email : `contact@village-iraty-biarritz.fr`

### Colonne "Services associés" — 🔄 LOGOS CLIQUABLES (recommandation PDF)

> Les 3 logos doivent pointer vers les sites respectifs (maillage interne sémantique pour les LLMs) :

- `logo-btz-box.jpg` — **Biarritz Box** → https://www.biarritz-box.fr (URL à confirmer)
- `logo-biarritz-bureaux.png` — **Biarritz Buro** → https://www.biarritz-buro.fr (URL à confirmer)
- `logo-biarritz-domiciliation.png` — **Biarritz Domiciliation** → https://www.biarritz-domiciliation.fr (URL à confirmer)

### Bas de footer

```
© 2026 Village Iraty-Biarritz · Tous droits réservés
```

Liens : Mentions légales → `/mentions-legales` · Confidentialité → `/confidentialite`

---

## JSON-LD à intégrer dans le `<head>` (Priorité 1 du PDF — GEO)

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "@id": "https://village-iraty-biarritz.fr/#organization",
  "name": "Village Iraty Biarritz",
  "alternateName": "VIB",
  "description": "Plus de 100 commerces, restaurants, espaces de coworking et services au cœur de Biarritz. Zone d'activités gérée par la SEBI, loyers 30% sous le marché biarrot, à partir de 590€/mois.",
  "url": "https://village-iraty-biarritz.fr",
  "telephone": "+33559239310",
  "email": "contact@village-iraty-biarritz.fr",
  "foundingDate": "2003",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "4 Rue des Mésanges",
    "postalCode": "64200",
    "addressLocality": "Biarritz",
    "addressRegion": "Pyrénées-Atlantiques",
    "addressCountry": "FR"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 43.4632,
    "longitude": -1.5460
  },
  "areaServed": {
    "@type": "City",
    "name": "Biarritz"
  },
  "priceRange": "€€",
  "openingHoursSpecification": [
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
      "opens": "09:00",
      "closes": "18:00"
    }
  ]
}
</script>
```

> Coordonnées GPS et horaires à confirmer avec l'équipe VIB.

---

## llms.txt à créer à la racine (Priorité 1 du PDF)

Fichier `/llms.txt` (markdown) — voir Priorité 1 du plan d'action. À générer dans un second temps, mais à prévoir.
