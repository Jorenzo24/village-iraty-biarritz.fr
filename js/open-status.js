// VIB — Détection "Ouvert / Fermé" à partir du champ `hours` des fiches.
// Expose window.OpeningStatus.{ getStatus, applyToCards }.
(function () {
    const DAY_INDEX = {
        lundi: 0, mardi: 1, mercredi: 2, jeudi: 3, vendredi: 4, samedi: 5, dimanche: 6,
        lun: 0, mar: 1, mer: 2, jeu: 3, ven: 4, sam: 5, dim: 6
    };
    const DAY_RE = /\b(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche|lun|mar|mer|jeu|ven|sam|dim)\b/g;

    function normalize(s) {
        return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    // Extrait toutes les heures rencontrées : "9h", "9h00", "09:00", "11H30"...
    function extractTimes(text) {
        const re = /(\d{1,2})\s*[h:](\d{0,2})/gi;
        const out = [];
        let m;
        while ((m = re.exec(text)) !== null) {
            const h = parseInt(m[1], 10);
            const minStr = m[2] || '';
            const min = minStr.length === 2 ? parseInt(minStr, 10) : 0;
            if (h > 24 || min > 59) continue;
            out.push(h * 60 + min);
        }
        return out;
    }

    function rangeDays(start, end) {
        const out = [];
        let d = start;
        for (let i = 0; i < 8; i++) {
            out.push(d);
            if (d === end) break;
            d = (d + 1) % 7;
        }
        return out;
    }

    // Identifie la liste de jours visés par un segment.
    function parseDaySpec(seg) {
        const t = normalize(seg);
        if (/\btous les jours\b/.test(t)) {
            const sauf = t.match(/sauf\s+(?:le\s+)?(\w+)/);
            if (sauf && DAY_INDEX[sauf[1]] !== undefined) {
                const ex = DAY_INDEX[sauf[1]];
                return [0, 1, 2, 3, 4, 5, 6].filter(d => d !== ex);
            }
            return [0, 1, 2, 3, 4, 5, 6];
        }

        const days = [];
        let m;
        DAY_RE.lastIndex = 0;
        while ((m = DAY_RE.exec(t)) !== null) {
            days.push({ index: DAY_INDEX[m[1]], pos: m.index, len: m[1].length });
        }
        if (days.length === 0) return null;
        if (days.length === 1) return [days[0].index];

        if (days.length === 2) {
            const between = t.slice(days[0].pos + days[0].len, days[1].pos);
            const isRange = (/\bau\b|[\-–—]/.test(between)) && !/\bet\b|,/.test(between);
            if (isRange) return rangeDays(days[0].index, days[1].index);
            return [days[0].index, days[1].index];
        }
        return days.map(d => d.index);
    }

    // Renvoie un tableau d'intervalles { day, start, end } ou null si non interprétable.
    function parseHours(text) {
        if (!text || typeof text !== 'string') return null;
        const norm = normalize(text);

        if (/24\s*h\s*\/\s*24/.test(norm)) {
            const out = [];
            for (let d = 0; d < 7; d++) out.push({ day: d, start: 0, end: 24 * 60 });
            return out;
        }
        if (/sur\s+rendez/.test(norm)) return null;

        const segments = text.split(/\s*·\s*|\n+/).map(s => s.trim()).filter(Boolean);
        const intervals = [];
        let parsedAny = false;

        for (const seg of segments) {
            const segNorm = normalize(seg);
            let days = parseDaySpec(seg);

            // Si pas de jour mais des heures : on suppose tous les jours.
            if (!days) {
                if (extractTimes(seg).length >= 2) days = [0, 1, 2, 3, 4, 5, 6];
                else continue;
            }

            if (/\bferme[es]*\b/.test(segNorm)) {
                parsedAny = true; // jour explicitement fermé
                continue;
            }

            const times = extractTimes(seg);
            if (times.length < 2) continue;

            for (let i = 0; i + 1 < times.length; i += 2) {
                for (const day of days) {
                    intervals.push({ day, start: times[i], end: times[i + 1] });
                }
            }
            parsedAny = true;
        }

        if (!parsedAny || intervals.length === 0) return null;
        return intervals;
    }

    // 'open' | 'closed' | null (null = horaires inconnus)
    function getStatus(text, now) {
        const intervals = parseHours(text);
        if (!intervals) return null;

        now = now || new Date();
        const jsDay = now.getDay();
        const todayIdx = jsDay === 0 ? 6 : jsDay - 1;
        const yIdx = (todayIdx + 6) % 7;
        const nowMin = now.getHours() * 60 + now.getMinutes();

        for (const iv of intervals) {
            if (iv.end > iv.start) {
                if (iv.day === todayIdx && nowMin >= iv.start && nowMin < iv.end) return 'open';
            } else {
                // Cross-midnight : ouvre iv.day à iv.start, ferme le lendemain à iv.end.
                if (iv.day === todayIdx && nowMin >= iv.start) return 'open';
                if (iv.day === yIdx && nowMin < iv.end) return 'open';
            }
        }
        return 'closed';
    }

    // Applique les badges aux cards de la page courante.
    async function applyToCards(jsonUrl) {
        const cards = document.querySelectorAll('.card');
        if (!cards.length) return;
        let list;
        try {
            const res = await fetch(jsonUrl || '/data/entreprises.json');
            list = await res.json();
        } catch (e) {
            return;
        }
        const bySlug = new Map(list.map(e => [e.slug, e]));

        cards.forEach(card => {
            const link = card.querySelector('a[href^="/acteur/"]');
            const badge = card.querySelector('.status');
            if (!link || !badge) return;
            const slug = link.getAttribute('href').replace(/^\/acteur\//, '').replace(/\/$/, '');
            const entry = bySlug.get(slug);
            if (!entry) { badge.remove(); return; }
            applyBadge(badge, entry.hours);
        });
    }

    function applyBadge(el, hours) {
        const status = getStatus(hours);
        el.classList.remove('status--open', 'status--closed');
        if (status === 'open') {
            el.classList.add('status--open');
            el.textContent = 'Ouvert';
            el.hidden = false;
        } else if (status === 'closed') {
            el.classList.add('status--closed');
            el.textContent = 'Fermé';
            el.hidden = false;
        } else {
            el.textContent = '';
            el.hidden = true;
        }
    }

    window.OpeningStatus = { parseHours, getStatus, applyToCards, applyBadge };

    // Auto-application sur toute page contenant des cards d'acteurs.
    document.addEventListener('DOMContentLoaded', () => {
        if (document.querySelector('.card a[href^="/acteur/"]')) applyToCards();
    });
})();
