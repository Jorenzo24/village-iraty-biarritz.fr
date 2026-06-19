// VIB - main.js
// Comportements : header au scroll, menu mobile, badges Ouvert/Fermé,
// année automatique dans le footer.

(function () {
  'use strict';

  const header = document.querySelector('.header');
  const burger = document.querySelector('.burger');
  const mobileNav = document.querySelector('.mobile-nav');
  const SCROLL_THRESHOLD = 20;

  /* ---------- Header au scroll ---------- */
  function updateHeader() {
    if (!header) return;
    if (window.scrollY > SCROLL_THRESHOLD) {
      header.classList.add('is-scrolled');
    } else {
      header.classList.remove('is-scrolled');
    }
  }
  window.addEventListener('scroll', updateHeader, { passive: true });
  updateHeader();

  /* ---------- Menu mobile ---------- */
  if (burger && mobileNav) {
    burger.addEventListener('click', () => {
      const open = mobileNav.classList.toggle('is-open');
      header.classList.toggle('is-menu-open', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    });
    // Fermer en cliquant un lien
    mobileNav.querySelectorAll('a').forEach((a) => {
      a.addEventListener('click', () => {
        mobileNav.classList.remove('is-open');
        header.classList.remove('is-menu-open');
        burger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      });
    });
  }

  /* ---------- Année automatique ---------- */
  const year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();

  /* ---------- Badges Ouvert / Fermé (cartes acteurs) ----------
     Format data-hours : "Mo-Sa 09:00-19:00" ou
                        "Mo-Fr 07:30-18:00;Sa 08:00-12:00" (plusieurs jours)
                        "Mo-Sa 11:30-14:30,18:30-22:00"    (plusieurs créneaux/jour)
     Codes jours : Mo=1, Tu=2, We=3, Th=4, Fr=5, Sa=6, Su=0
  */
  const DAY_CODES = { Su: 0, Mo: 1, Tu: 2, We: 3, Th: 4, Fr: 5, Sa: 6 };

  function parseDayRange(token) {
    // ex: "Mo-Fr" -> [1,2,3,4,5] ; "Sa" -> [6]
    if (token.includes('-')) {
      const [a, b] = token.split('-');
      const da = DAY_CODES[a], db = DAY_CODES[b];
      if (da == null || db == null) return [];
      const out = [];
      let i = da;
      // Loop forward, wrap if needed (e.g. Sa-Mo)
      for (let n = 0; n < 7; n++) {
        out.push(i);
        if (i === db) break;
        i = (i + 1) % 7;
      }
      return out;
    }
    return DAY_CODES[token] != null ? [DAY_CODES[token]] : [];
  }

  function toMinutes(hhmm) {
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
  }

  function isOpenAt(hoursStr, date) {
    if (!hoursStr) return null;
    const day = date.getDay();
    const now = date.getHours() * 60 + date.getMinutes();
    const groups = hoursStr.split(';');
    for (const g of groups) {
      const m = g.trim().match(/^(\S+)\s+(.+)$/);
      if (!m) continue;
      const days = parseDayRange(m[1]);
      if (!days.includes(day)) continue;
      const intervals = m[2].split(',');
      for (const iv of intervals) {
        const [start, end] = iv.split('-').map(s => toMinutes(s.trim()));
        // Gérer les créneaux qui traversent minuit (ex: 18:00-00:30)
        if (end < start) {
          if (now >= start || now < end) return true;
        } else if (now >= start && now < end) {
          return true;
        }
      }
    }
    return false;
  }

  function updateStatusBadges() {
    const now = new Date();
    document.querySelectorAll('.acteur-card').forEach((card) => {
      const slot = card.querySelector('[data-status]');
      if (!slot) return;
      const open = isOpenAt(card.dataset.hours, now);
      if (open === null) {
        slot.hidden = true;
        return;
      }
      slot.hidden = false;
      slot.textContent = open ? 'Ouvert' : 'Fermé';
      slot.classList.toggle('badge--open', open);
      slot.classList.toggle('badge--closed', !open);
    });
  }
  updateStatusBadges();
  // Re-vérifier toutes les minutes pour les visites longues
  setInterval(updateStatusBadges, 60 * 1000);
})();
