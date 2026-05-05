// VIB — Liste des articles (nos-articles)
document.addEventListener('DOMContentLoaded', async () => {
    const grid = document.getElementById('articles-grid');
    const empty = document.getElementById('articles-empty');

    try {
        const res = await fetch('data/articles.json');
        const articles = await res.json();

        if (!articles || articles.length === 0) {
            empty.hidden = false;
            return;
        }

        grid.innerHTML = articles.map(a => `
            <article class="news-card">
                <a href="article?slug=${a.slug}" class="news-card__media">
                    <img src="${a.image}" alt="${escape(a.title)}" loading="lazy">
                </a>
                <div class="news-card__body">
                    <p class="news-card__source">${formatDate(a.date)}</p>
                    <h2 class="news-card__title"><a href="article?slug=${a.slug}" style="color:inherit;">${escape(a.title)}</a></h2>
                    ${a.summary ? `<p style="font-size:0.9375rem;color:var(--c-text-soft);line-height:1.5;margin-bottom:1.25rem;flex:1;">${escape(a.summary).slice(0, 180)}${a.summary.length > 180 ? '…' : ''}</p>` : ''}
                    <a href="article?slug=${a.slug}" class="news-card__more">Lire l'article →</a>
                </div>
            </article>
        `).join('');
    } catch (err) {
        console.error('[articles]', err);
        empty.textContent = 'Erreur au chargement des articles.';
        empty.hidden = false;
    }

    function formatDate(d) {
        if (!d) return 'Article';
        const dt = new Date(d);
        if (isNaN(dt)) return 'Article';
        return dt.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function escape(s) {
        const div = document.createElement('div');
        div.textContent = s || '';
        return div.innerHTML;
    }
});
