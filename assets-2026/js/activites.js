// VIB - Page Activités (refonte) : filtres par catégorie + recherche + "Ouvert maintenant".
// Les badges Ouvert/Fermé sont gérés par main.js (data-hours sur .acteur-card) ;
// ce filtre lit la classe .badge--open posée par main.js.
(function () {
  'use strict';

  const grid = document.getElementById('acteurs-grid');
  if (!grid) return;

  const items = Array.from(grid.children); // les <li> conteneurs
  const pills = Array.from(document.querySelectorAll('.filter-pill'));
  const search = document.getElementById('acteur-search');
  const openBtn = document.getElementById('filter-open');
  const countEl = document.getElementById('acteur-count');
  const emptyEl = document.getElementById('acteur-empty');

  let activeCat = 'all';
  let query = '';
  let openNow = false;

  const normalize = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  const isOpen = card => {
    const b = card.querySelector('[data-status]');
    return !!b && b.classList.contains('badge--open');
  };

  function apply() {
    const q = normalize(query.trim());
    let visible = 0;
    items.forEach(li => {
      const card = li.querySelector('.acteur-card');
      if (!card) return;
      const cat = card.dataset.cat || '';
      const name = normalize(card.dataset.name || '');
      const show = (activeCat === 'all' || cat === activeCat)
        && (!q || name.includes(q))
        && (!openNow || isOpen(card));
      li.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (emptyEl) emptyEl.hidden = visible > 0;
    if (countEl) {
      countEl.textContent = visible === items.length
        ? `${visible} acteurs`
        : `${visible} acteur${visible > 1 ? 's' : ''} sur ${items.length}`;
    }
  }

  pills.forEach(pill => {
    pill.addEventListener('click', () => {
      pills.forEach(p => { p.classList.remove('is-active'); p.setAttribute('aria-pressed', 'false'); });
      pill.classList.add('is-active');
      pill.setAttribute('aria-pressed', 'true');
      activeCat = pill.dataset.cat;
      apply();
    });
  });

  if (search) {
    search.addEventListener('input', e => { query = e.target.value; apply(); });
    search.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
  }

  if (openBtn) {
    openBtn.addEventListener('click', () => {
      openNow = !openNow;
      openBtn.classList.toggle('is-active', openNow);
      openBtn.setAttribute('aria-pressed', openNow ? 'true' : 'false');
      apply();
    });
    // main.js réévalue les badges chaque minute : garder le filtre "ouvert" à jour.
    setInterval(() => { if (openNow) apply(); }, 60 * 1000);
  }

  // Pré-filtre depuis l'URL ?cat=...
  const initCat = new URLSearchParams(location.search).get('cat');
  if (initCat) {
    const match = pills.find(p => p.dataset.cat === initCat);
    if (match) match.click();
  }

  apply();
})();
