# Kit de refonte — Village Iraty Biarritz (Homepage)

Ce kit contient tout ce dont Claude Code a besoin pour produire une refonte fidèle à la maquette **https://vib-site.vercel.app/**, sur la **homepage uniquement** (validation de la méthode avant extension aux autres pages).

## Comment utiliser ce kit

1. **Placer les 5 fichiers à la racine du projet** :
   - `CLAUDE.md` — règles + méthode (à lire en premier par Claude Code)
   - `DESIGN_SYSTEM.md` — tokens (couleurs, typo, espacements)
   - `CONTENT_HOMEPAGE.md` — tous les textes définitifs
   - `BRIEF_HOMEPAGE.md` — brief section par section
   - `README.md` — ce fichier

2. **Démarrer Claude Code dans le dossier projet** :
   ```bash
   cd ~/projets/vib-refonte
   claude
   ```

3. **Premier prompt à donner à Claude Code** :
   > Lis `CLAUDE.md` en entier, puis `DESIGN_SYSTEM.md`, `CONTENT_HOMEPAGE.md` et `BRIEF_HOMEPAGE.md`. Confirme-moi que tu as compris la méthode (capture de la maquette obligatoire avant de coder, boucle de vérification visuelle section par section). Ne code rien pour l'instant.

4. **Deuxième prompt** :
   > Crée le script `scripts/capture-ref.mjs` décrit dans `CLAUDE.md`, exécute-le, puis ouvre `references/desktop-full.png` et `references/mobile-full.png` pour analyser la maquette. Décris-moi ce que tu vois : couleurs dominantes, typographie, structure des sections, hover states que tu peux deviner. Ne code toujours rien.

5. **Troisième prompt** (une fois l'analyse validée) :
   > Maintenant, set up le projet : `index.html` squelette, `css/design-system.css` avec les tokens (ajuste les valeurs estimées dans `DESIGN_SYSTEM.md` avec ce que tu as réellement vu sur la maquette), `css/styles.css` avec reset + container, `js/main.js` vide. Confirme avant d'écrire le code.

6. **Ensuite, prompt par section**, dans l'ordre du `BRIEF_HOMEPAGE.md` :
   > Code la section Header. Re-capture le rendu local avec `scripts/capture-local.mjs` après et compare visuellement à la référence. Liste les écarts et corrige.

## Pourquoi cette méthode

Le risque principal avec Claude Code, quand on lui donne juste une URL de maquette, c'est qu'il **interprète** au lieu de **reproduire**. Les écarts typiques :
- Typographie générique (système au lieu de la vraie police)
- Espacements approximatifs
- Couleurs proches mais pas identiques
- Animations/hover states ignorés
- "Améliorations" non demandées

Ce kit résout ça en imposant :
1. Une **capture obligatoire** de la maquette avant de coder
2. Un **design system explicite** pour éviter les valeurs en dur "à l'œil"
3. Un **brief section par section** qui détaille les comportements
4. Une **boucle de vérification visuelle** (capture du rendu local + comparaison à la référence)

## Contenu intégré du PDF de synthèse

Les arbitrages clés du PDF de synthèse VIB sont déjà intégrés dans `CONTENT_HOMEPAGE.md` et `BRIEF_HOMEPAGE.md` :
- Menu à 4 entrées avec "Services" (pas "Contact")
- Double CTA "Installer mon entreprise" + "Prendre rendez-vous"
- Storytelling "Une histoire de vibrations" réintégré
- Bloc "Aide au financement / Coworking / Offre sur mesure" structuré
- Statistiques rendues en HTML (pas en JS) — pour le GEO
- Badges "Ouvert / Fermé" sur les fiches acteurs
- Logos services associés cliquables en footer
- JSON-LD `LocalBusiness` dans le `<head>`
- Section témoignages prévue (placeholder)
- Prix HT en HTML brut

## Limites de ce kit

- **Couleurs et polices exactes** : non-confirmées (le sandbox n'a pas pu charger la maquette). Claude Code devra les extraire lui-même via Playwright lors de la capture. Les valeurs dans `DESIGN_SYSTEM.md` sont des estimations.
- **Layout des sections complexes** (Avantages asymétriques, Hero) : à reproduire en inspectant la maquette, pas depuis ce kit.
- **Témoignages** : placeholder à remplir avec collecte ultérieure.
- **URLs des services associés** (Biarritz Box, etc.) : à confirmer.

## Après validation de la homepage

Une fois la homepage validée, étendre aux 4 autres pages avec la même méthode :
- `/le-village`
- `/activites`
- `/louer-un-local`
- `/services`
- `/contact`

Chaque page aura son propre `BRIEF_PAGENAME.md` et `CONTENT_PAGENAME.md`. Le `CLAUDE.md` et `DESIGN_SYSTEM.md` restent partagés.
