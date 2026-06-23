// VIB - Liste des articles (nos-articles) - markup refonte (.news-card)
document.addEventListener('DOMContentLoaded', async () => {
    const grid = document.getElementById('articles-grid');
    const empty = document.getElementById('articles-empty');

    try {
        const res = await fetch('/data/articles.json');
        const articles = await res.json();

        if (!articles || articles.length === 0) {
            empty.hidden = false;
            return;
        }

        grid.innerHTML = articles.map(a => {
            const summary = a.summary
                ? `<p class="news-card__excerpt">${escape(a.summary).slice(0, 180)}${a.summary.length > 180 ? '…' : ''}</p>`
                : '';
            return `
            <li>
              <article class="news-card">
                <a href="/${a.slug}" class="news-card__media">
                  <img src="${a.image}" alt="${escape(a.title)}" loading="lazy">
                </a>
                <div class="news-card__body">
                  <time class="news-card__date"${a.date ? ` datetime="${a.date}"` : ''}>${formatDate(a.date)}</time>
                  <h2 class="news-card__title"><a href="/${a.slug}">${escape(a.title)}</a></h2>
                  ${summary}
                  <a href="/${a.slug}" class="news-card__more">Lire l'article <span aria-hidden="true">→</span></a>
                </div>
              </article>
            </li>`;
        }).join('');
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
