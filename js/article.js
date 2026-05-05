// VIB — Page article individuelle
document.addEventListener('DOMContentLoaded', async () => {
    const params = new URLSearchParams(window.location.search);
    const slug = params.get('slug');

    const wrapper = document.getElementById('article-wrapper');
    const notFound = document.getElementById('article-not-found');

    if (!slug) {
        showNotFound();
        return;
    }

    try {
        const res = await fetch('data/articles.json');
        const articles = await res.json();
        const article = articles.find(a => a.slug === slug);

        if (!article) {
            showNotFound();
            return;
        }

        renderArticle(article);
    } catch (err) {
        console.error('[article]', err);
        showNotFound();
    }

    function showNotFound() {
        wrapper.hidden = true;
        notFound.hidden = false;
        document.title = 'Article introuvable — VILLAGE Iraty-Biarritz';
    }

    function renderArticle(a) {
        document.title = `${a.title} — VILLAGE Iraty-Biarritz`;
        document.getElementById('page-description').setAttribute(
            'content', a.summary || a.title.slice(0, 160)
        );

        document.getElementById('article-title').textContent = a.title;
        document.getElementById('article-date').textContent = formatDate(a.date);

        const img = document.getElementById('article-image');
        if (a.image) {
            img.src = a.image;
            img.alt = a.title;
        } else {
            img.parentElement.style.display = 'none';
        }

        const body = document.getElementById('article-body');
        body.innerHTML = a.content_html || '<p>Contenu en cours de mise à jour.</p>';

        wrapper.hidden = false;
    }

    function formatDate(d) {
        if (!d) return '';
        const dt = new Date(d);
        if (isNaN(dt)) return '';
        return dt.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
    }
});
