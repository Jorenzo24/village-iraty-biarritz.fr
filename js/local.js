// VIB — Page détail local (chargée depuis data/locaux.json)
document.addEventListener('DOMContentLoaded', async () => {
    const params = new URLSearchParams(window.location.search);
    const slug = params.get('slug');

    const content = document.getElementById('local-content');
    const notFound = document.getElementById('local-not-found');

    if (!slug) {
        showNotFound();
        return;
    }

    try {
        const res = await fetch('data/locaux.json');
        if (!res.ok) throw new Error('JSON load failed');
        const list = await res.json();
        const local = list.find(l => l.slug === slug);

        if (!local) {
            showNotFound();
            return;
        }

        renderLocal(local);
    } catch (err) {
        console.error('[local]', err);
        showNotFound();
    }

    function showNotFound() {
        content.hidden = true;
        notFound.hidden = false;
    }

    function renderLocal(l) {
        // Title + meta
        document.title = `${l.name} — VILLAGE Iraty-Biarritz`;
        document.getElementById('page-description').setAttribute(
            'content',
            `${l.name} à louer au VILLAGE Iraty-Biarritz. ${l.surface} m², ${l.price_ht} € HT/mois.`
        );

        document.getElementById('local-name').textContent = l.name;
        document.getElementById('local-address').innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="display:inline;vertical-align:middle;color:var(--c-red);" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            ${escape(l.address)}
        `;

        document.getElementById('local-price').textContent = formatPrice(l.price_ht);
        document.getElementById('local-surface').textContent = l.surface + ' m²';

        // Bouton RDV avec slug
        const rdvBtn = document.getElementById('local-rdv-btn');
        rdvBtn.href = `contact?sujet=louer-local&local=${encodeURIComponent(l.slug)}`;

        // Caractéristiques (chips)
        const features = [];
        if (l.no_fees) features.push('✓ Sans frais d\'agence');
        if (l.no_pas_de_porte) features.push('✓ Pas de pas-de-porte');
        if (l.norm_pmr) features.push('✓ Normes PMR');
        if (l.type) features.push('✓ ' + l.type);
        document.getElementById('local-features').innerHTML = features.map(f =>
            `<span class="local-detail__chip">${escape(f)}</span>`
        ).join('');

        // Charges
        if (l.charges_ht && l.charges_ht > 0) {
            document.getElementById('local-charges-block').hidden = false;
            document.getElementById('local-charges').textContent = formatPrice(l.charges_ht);
        }

        // Description
        const descEl = document.getElementById('local-description');
        if (l.description) {
            descEl.innerHTML = `<h3>Description</h3><p>${escape(l.description).replace(/\. /g, '.<br>')}</p>`;
        }

        // Photos
        const mainPhotoEl = document.getElementById('local-main-photo');
        const thumbsEl = document.getElementById('local-thumbs');
        if (l.photos && l.photos.length > 0) {
            mainPhotoEl.innerHTML = `<img src="${l.photos[0]}" alt="${escape(l.name)}" loading="eager" id="local-main-img">`;
            thumbsEl.innerHTML = l.photos.map((p, i) =>
                `<button class="local-detail__thumb${i === 0 ? ' is-active' : ''}" data-src="${p}" type="button">
                    <img src="${p}" alt="Vue ${i + 1}" loading="lazy">
                </button>`
            ).join('');

            // Click on thumb → swap main image
            thumbsEl.querySelectorAll('.local-detail__thumb').forEach(thumb => {
                thumb.addEventListener('click', () => {
                    const src = thumb.dataset.src;
                    document.getElementById('local-main-img').src = src;
                    thumbsEl.querySelectorAll('.local-detail__thumb').forEach(t =>
                        t.classList.toggle('is-active', t === thumb));
                });
            });
        } else {
            // Fallback : placeholder rouge avec surface
            mainPhotoEl.innerHTML = `
                <div class="local-detail__placeholder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <line x1="3" y1="9" x2="21" y2="9"/>
                        <line x1="9" y1="3" x2="9" y2="21"/>
                    </svg>
                    <p>${l.surface} m²</p>
                    <small>Photos prochainement</small>
                </div>`;
        }

        content.hidden = false;
    }

    function formatPrice(p) {
        return p.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' € HT';
    }

    function escape(s) {
        const div = document.createElement('div');
        div.textContent = s || '';
        return div.innerHTML;
    }
});
