// VIB — Page Activités : filtres par catégorie + recherche live
document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.tab');
    const cards = document.querySelectorAll('#cards-grid .card');
    const searchInput = document.getElementById('search-input');
    const searchBtn = document.getElementById('search-btn');
    const empty = document.getElementById('search-empty');

    let activeCat = 'all';
    let query = '';

    function normalize(s) {
        return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function applyFilter() {
        const q = normalize(query.trim());
        let visible = 0;
        cards.forEach(card => {
            const cat = card.dataset.cat;
            const name = normalize(card.dataset.name || '');
            const matchCat = activeCat === 'all' || cat === activeCat;
            const matchQuery = !q || name.includes(q);
            const show = matchCat && matchQuery;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        empty.hidden = visible > 0;
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('is-active'));
            tab.classList.add('is-active');
            activeCat = tab.dataset.cat;
            applyFilter();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            query = e.target.value;
            applyFilter();
        });
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilter();
            }
        });
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', () => {
            query = searchInput?.value || '';
            applyFilter();
        });
    }

    // Pré-filtre depuis URL ?cat=...
    const params = new URLSearchParams(window.location.search);
    const initCat = params.get('cat');
    if (initCat) {
        const matchTab = document.querySelector(`.tab[data-cat="${initCat}"]`);
        if (matchTab) matchTab.click();
    }
});
