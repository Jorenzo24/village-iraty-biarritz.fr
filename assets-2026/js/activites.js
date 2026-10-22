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

  // Mesure anonyme de ce que cherchent les visiteurs (api/stat.php) : le mot final tapé et le nombre de
  // résultats, et les catégories choisies. Rien n'est envoyé pendant la frappe, seulement après une pause.
  const track = (data) => {
    if (!navigator.sendBeacon || navigator.webdriver) return;
    try { navigator.sendBeacon('/api/stat.php', new Blob([JSON.stringify(Object.assign({ p: '/activites' }, data))], { type: 'text/plain' })); } catch (e) { /* jamais bloquant */ }
  };
  let searchTimer = null;
  let lastSent = '';
  const trackSearch = () => {
    const term = query.trim().toLowerCase().replace(/\s+/g, ' ');
    if (term.length < 3 || term === lastSent) return;
    lastSent = term;
    const n = items.filter(li => li.style.display !== 'none').length;
    track({ e: 'q', t: term.slice(0, 40), n: n });
  };

  pills.forEach(pill => {
    pill.addEventListener('click', () => {
      if (pill.dataset.cat !== 'all' && pill.dataset.cat !== activeCat) track({ e: 'cat', t: pill.dataset.cat });
      pills.forEach(p => { p.classList.remove('is-active'); p.setAttribute('aria-pressed', 'false'); });
      pill.classList.add('is-active');
      pill.setAttribute('aria-pressed', 'true');
      activeCat = pill.dataset.cat;
      apply();
    });
  });

  if (search) {
    search.addEventListener('input', e => {
      query = e.target.value; apply();
      clearTimeout(searchTimer);
      searchTimer = setTimeout(trackSearch, 1200);
    });
    search.addEventListener('blur', () => { clearTimeout(searchTimer); trackSearch(); });
    search.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
  }

  if (openBtn) {
    openBtn.addEventListener('click', () => {
      openNow = !openNow;
      if (openNow) track({ e: 'cat', t: 'ouvert' });
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
