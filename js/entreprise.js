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

        // Logo (encart dédié)
        if (e.logo) {
            const logoBlock = document.getElementById('entity-logo-block');
            logoBlock.hidden = false;
            const logoImg = document.getElementById('entity-logo');
            logoImg.src = e.logo;
            logoImg.alt = `Logo ${e.name}`;
        }

        // Description : multi-paragraphes + détection de listes à puces
        const descEl = document.getElementById('entity-description');
        if (e.description) {
            descEl.innerHTML = renderDescription(e.description);
        } else {
            descEl.innerHTML = `<p style="color:var(--c-text-muted);font-style:italic;">Description en cours de rédaction. Pour en savoir plus, n'hésitez pas à <a href="contact" style="color:var(--c-red);">nous contacter</a>.</p>`;
        }

        // Hours : un jour par ligne
        if (e.hours) {
            document.getElementById('entity-hours-block').hidden = false;
            document.getElementById('entity-hours').innerHTML = renderHours(e.hours);
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
            const label = document.getElementById('entity-website-label');
            if (label) label.textContent = 'Site web';
        }

        // Email
        if (e.email) {
            const block = document.getElementById('entity-email-block');
            block.hidden = false;
            const link = document.getElementById('entity-email');
            link.textContent = e.email;
            link.href = 'mailto:' + e.email;
        }

        // Réseaux sociaux
        const social = e.social || {};
        const socialEntries = Object.entries(social).filter(([k, v]) => v);
        if (socialEntries.length > 0) {
            const block = document.getElementById('entity-social-block');
            const list = document.getElementById('entity-social-list');
            const SOCIAL_ICONS = {
                facebook:  '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>',
                instagram: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
                linkedin:  '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.36V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13zm1.78 13.02H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0z"/></svg>',
                tiktok:    '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5.8 20.1a6.34 6.34 0 0 0 10.86-4.43V8.79a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.84-.22z"/></svg>',
                twitter:   '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>',
            };
            const LABELS = {
                facebook: 'Facebook', instagram: 'Instagram', linkedin: 'LinkedIn',
                tiktok: 'TikTok', twitter: 'Twitter / X',
            };
            list.innerHTML = socialEntries.map(([kind, url]) =>
                `<li><a href="${escape(url)}" target="_blank" rel="noopener" aria-label="${LABELS[kind] || kind}" title="${LABELS[kind] || kind}">${SOCIAL_ICONS[kind] || ''}</a></li>`
            ).join('');
            block.hidden = false;
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

    /**
     * Rendu des horaires : split sur " · " ou retours à la ligne, un jour par ligne.
     * Si une ligne contient un nom de jour suivi de plages, on aligne via <strong>.
     */
    function renderHours(text) {
        const DAY_RE = /^(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)\b/i;
        const items = text.split(/\s*·\s*|\n+/).map(s => s.trim()).filter(Boolean);
        return items.map(item => {
            const m = item.match(DAY_RE);
            if (m) {
                const day = m[1];
                const rest = item.slice(m[0].length).trim();
                return `<span class="hours-row"><strong>${escape(day)}</strong> <span>${escape(rest || 'Fermé')}</span></span>`;
            }
            return `<span class="hours-row">${escape(item)}</span>`;
        }).join('');
    }

    /**
     * Rendu enrichi d'une description multi-paragraphes :
     * - Lignes commençant par ● • · ou - (avec/sans espace) → <ul><li>
     * - Si la ligne précédente se termine par ":" et est suivie de bullets,
     *   elle reste un <p> introductif (le ":" est conservé).
     * - Lignes vides ignorées.
     */
    function renderDescription(text) {
        const isBullet = (line) => /^[\s]*[●•·\-–—][\s]*\S/.test(line);
        const stripBullet = (line) => line.replace(/^[\s]*[●•·\-–—][\s]*/, '').trim();

        const lines = text.split(/\n+/).map(l => l.trim()).filter(Boolean);
        const out = [];
        let listBuf = [];

        const flushList = () => {
            if (listBuf.length) {
                out.push(`<ul>${listBuf.map(li => `<li>${escape(li)}</li>`).join('')}</ul>`);
                listBuf = [];
            }
        };

        for (const line of lines) {
            if (isBullet(line)) {
                listBuf.push(stripBullet(line));
            } else {
                flushList();
                out.push(`<p>${escape(line)}</p>`);
            }
        }
        flushList();

        return out.join('');
    }
});
