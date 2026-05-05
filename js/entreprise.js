// VIB — Page détail entreprise (chargée depuis data/entreprises.json)
document.addEventListener('DOMContentLoaded', async () => {
    const params = new URLSearchParams(window.location.search);
    const slug = params.get('slug');

    const heroTitle = document.getElementById('entity-name');
    const content = document.getElementById('entity-content');
    const notFound = document.getElementById('entity-not-found');

    if (!slug) {
        showNotFound();
        return;
    }

    try {
        const res = await fetch('data/entreprises.json');
        if (!res.ok) throw new Error('JSON load failed');
        const list = await res.json();
        const entity = list.find(e => e.slug === slug);

        if (!entity) {
            showNotFound();
            return;
        }

        renderEntity(entity);
    } catch (err) {
        console.error('[entreprise]', err);
        showNotFound();
    }

    function showNotFound() {
        heroTitle.textContent = 'Fiche introuvable';
        content.hidden = true;
        notFound.hidden = false;
        document.title = 'Fiche introuvable — VILLAGE Iraty-Biarritz';
    }

    function renderEntity(e) {
        // Title + meta
        document.getElementById('page-title').textContent = `${e.name} — VILLAGE Iraty-Biarritz`;
        const desc = e.description || `Découvrez ${e.name}, ${e.category_label.toLowerCase()} au VILLAGE Iraty-Biarritz.`;
        document.getElementById('page-description').setAttribute('content', desc);

        heroTitle.textContent = e.name;

        document.getElementById('entity-name-h2').textContent = e.name;
        document.getElementById('entity-category').textContent = e.category_label || '';
        document.getElementById('entity-address').textContent = e.address || '';

        // Description
        const descEl = document.getElementById('entity-description');
        if (e.description) {
            descEl.innerHTML = `<p>${e.description}</p>`;
        } else {
            descEl.innerHTML = `<p style="color:var(--c-text-muted);font-style:italic;">Description en cours de rédaction. Pour en savoir plus, n'hésitez pas à <a href="contact.html" style="color:var(--c-red);">nous contacter</a>.</p>`;
        }

        // Hours
        if (e.hours) {
            document.getElementById('entity-hours-block').hidden = false;
            document.getElementById('entity-hours').textContent = e.hours;
        }

        // Phone
        if (e.phone) {
            const block = document.getElementById('entity-phone-block');
            block.hidden = false;
            const link = document.getElementById('entity-phone');
            link.textContent = e.phone;
            link.href = 'tel:' + e.phone.replace(/\s/g, '');
        }

        // Website (rendu en bouton)
        if (e.website) {
            const block = document.getElementById('entity-website-block');
            block.hidden = false;
            const link = document.getElementById('entity-website');
            link.href = e.website;
            // Label : nom de domaine clean (ex: "biarritzburo.com")
            const domain = e.website.replace(/^https?:\/\//, '').replace(/\/$/, '').replace(/^www\./, '');
            const label = document.getElementById('entity-website-label');
            if (label) {
                label.textContent = domain.length > 30 ? 'Visiter le site web' : domain;
            }
        }

        // Email
        if (e.email) {
            const block = document.getElementById('entity-email-block');
            block.hidden = false;
            const link = document.getElementById('entity-email');
            link.textContent = e.email;
            link.href = 'mailto:' + e.email;
        }

        // Photos
        const mainPhotoEl = document.getElementById('entity-main-photo');
        const photosEl = document.getElementById('entity-photos');
        if (e.photos && e.photos.length > 0) {
            mainPhotoEl.innerHTML = `<img src="${e.photos[0]}" alt="${escape(e.name)}" loading="eager">`;
            if (e.photos.length > 1) {
                photosEl.innerHTML = e.photos.slice(1).map(p =>
                    `<div class="entity-detail__thumb"><img src="${p}" alt="${escape(e.name)}" loading="lazy"></div>`
                ).join('');
            }
        } else {
            // Pas de photos : placeholder coloré stylisé
            const slug = e.slug;
            mainPhotoEl.innerHTML = `<div class="entity-detail__placeholder">${escape(e.name)}</div>`;
        }

        content.hidden = false;
    }

    function escape(s) {
        const div = document.createElement('div');
        div.textContent = s || '';
        return div.innerHTML;
    }
});
