/* Back-office VIB : confort de saisie. Tout est contrôlé à nouveau par le serveur ; sans ce script, tout fonctionne. */
document.addEventListener('DOMContentLoaded', () => {
  const fold = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/œ/g, 'oe').replace(/æ/g, 'ae').replace(/’/g, "'");

  // --- Graphiques des statistiques : infobulle immédiate au survol (ou au toucher) d'une colonne
  const initChart = (box) => {
    const tip = box.querySelector('.adm-tip');
    const svg = box.querySelector('svg');
    let current = null;
    const show = (g) => {
      if (current) current.classList.remove('is-hover');
      current = g;
      g.classList.add('is-hover');
      tip.innerHTML = '';
      const day = document.createElement('strong'); day.textContent = g.dataset.day;
      const v = document.createElement('span'); v.textContent = g.dataset.v;
      const c = document.createElement('span'); c.textContent = g.dataset.c;
      tip.append(day, v, c);
      tip.hidden = false;
      const b = g.querySelector('.adm-chart__hit').getBoundingClientRect();
      const r = box.getBoundingClientRect();
      const x = Math.min(Math.max(b.left + b.width / 2 - r.left, tip.offsetWidth / 2), r.width - tip.offsetWidth / 2);
      tip.style.left = x + 'px';
      const top = g.querySelector('.adm-chart__v').getBoundingClientRect().top;   // au-dessus de la colonne
      tip.style.top = Math.max(top - r.top - 8, tip.offsetHeight - 40) + 'px';
    };
    const hide = () => { tip.hidden = true; if (current) current.classList.remove('is-hover'); current = null; };
    svg.addEventListener('pointerover', (ev) => { const g = ev.target.closest('g[data-day]'); if (g) show(g); });
    svg.addEventListener('pointerdown', (ev) => { const g = ev.target.closest('g[data-day]'); if (g) show(g); });
    box.addEventListener('pointerleave', (ev) => { if (ev.pointerType === 'mouse') hide(); });
  };
  document.querySelectorAll('.adm-chartbox').forEach(initChart);

  // Jour / Semaine / Mois / Année : seul le graphique est rechargé, la page ne bouge pas
  document.addEventListener('click', async (ev) => {
    const a = ev.target.closest('.adm-seg a');
    const card = a && a.closest('#graphique');
    if (!card || ev.metaKey || ev.ctrlKey) return;
    ev.preventDefault();
    try {
      const html = await (await fetch(a.href, { credentials: 'same-origin' })).text();
      const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('graphique');
      if (!fresh) { location.href = a.href; return; }
      card.replaceWith(fresh);
      fresh.querySelectorAll('.adm-chartbox').forEach(initChart);
      history.replaceState(null, '', a.href);
      // Les liens de période gardent le regroupement choisi
      const g = new URL(a.href).searchParams.get('g');
      document.querySelectorAll('.adm-period a, .adm-period form').forEach((el) => {
        if (el.tagName === 'A') { const u = new URL(el.href); u.searchParams.set('g', g); el.href = u; }
        else { let i = el.querySelector('input[name=g]'); if (!i) { i = document.createElement('input'); i.type = 'hidden'; i.name = 'g'; el.prepend(i); } i.value = g; }
      });
    } catch (e) { location.href = a.href; }
  });

  // --- Liste des acteurs : recherche, catégorie, « à compléter »
  const list = document.getElementById('adm-list');
  if (list) {
    const q = document.getElementById('adm-q');
    const none = document.getElementById('adm-none');
    const pills = [...document.querySelectorAll('.adm-pill[data-cat]')];
    const todoBtn = document.querySelector('.adm-pill[data-todo]');
    const rows = [...list.querySelectorAll('li[data-search]')];
    let cat = '';
    const apply = () => {
      const words = fold(q.value).split(/\s+/).filter(Boolean);
      const todo = todoBtn && todoBtn.getAttribute('aria-pressed') === 'true';
      let shown = 0;
      rows.forEach((li) => {
        const ok = (!cat || li.dataset.cat === cat) && (!todo || li.hasAttribute('data-todo')) && words.every((w) => li.dataset.search.includes(w));
        li.hidden = !ok;
        if (ok) shown++;
      });
      none.hidden = shown > 0;
    };
    pills.forEach((b) => b.addEventListener('click', () => {
      cat = b.dataset.cat;
      pills.forEach((x) => { const on = x === b; x.classList.toggle('is-active', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      apply();
    }));
    todoBtn?.addEventListener('click', () => {
      const on = todoBtn.getAttribute('aria-pressed') !== 'true';
      todoBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
      todoBtn.classList.toggle('is-active', on);
      apply();
    });
    q.addEventListener('input', apply);
    apply();   // le navigateur peut avoir conservé la saisie (retour arrière)
  }

  // --- Formulaire d'une fiche
  const form = document.getElementById('adm-form');
  if (!form) return;

  // Aperçu de l'adresse : même règle que vib_address_compose() (lib/fields.php)
  const $ = (id) => document.getElementById(id);
  const preview = $('addr-preview');
  if (preview) {   // fiches acteurs seulement (adresse structurée)
    const compose = () => {
      let lot = $('f-lot').value.trim();
      if (lot) {
        if (/^Bureau\s/u.test(lot)) { /* tel quel */ }
        else if (/\set\s|,|\s-\s|-/u.test(lot)) lot = 'Lots ' + lot.replace(/\s*-\s*/gu, ' et ');
        else lot = 'Lot n°' + lot;
      }
      const parts = [$('f-building').value.trim(), $('f-street').value.trim(), lot].filter(Boolean);
      preview.textContent = parts.length ? parts.join(' - ') + ' - 64200 BIARRITZ' : '(aucune adresse)';
    };
    ['f-building', 'f-street', 'f-lot'].forEach((id) => $(id).addEventListener('input', compose));
    compose();

    $('f-address-mode').addEventListener('change', (ev) => {
      const free = ev.target.checked;
      // En passant en saisie libre, partir de l'adresse composée plutôt que d'un champ vide
      if (free && !$('f-address-free').value.trim() && preview.textContent !== '(aucune adresse)') $('f-address-free').value = preview.textContent;
      $('addr-structured').hidden = free;
      $('addr-free').hidden = !free;
    });
  }

  // Accroche de carte : reprendre le début de la description (même règle que vib_card_excerpt_auto, lib/cards.php)
  document.querySelectorAll('[data-excerpt]').forEach((btn) => btn.addEventListener('click', () => {
    const input = $(btn.dataset.excerpt);
    let s = $(input.dataset.excerptFrom).value.replace(/\n\s*[•\-]\s*/g, ' ').replace(/\s+/g, ' ').trim();
    if (s.length > 100) { s = s.slice(0, 100); if (s.includes(' ')) s = s.slice(0, s.lastIndexOf(' ')); s = s.replace(/[\s,;:.\-–]+$/, '') + '…'; }
    input.value = s;
    form.dispatchEvent(new Event('input'));
  }));

  // --- Horaires : même règles que lib/hours.php (aperçu seulement, le serveur recalcule)
  const hoursBox = $('hours');
  if (hoursBox) {
    const DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    const hPreview = $('hours-preview');
    const field = (d, k) => form.elements[`h[${d}][${k}]`];
    const toMin = (v, isEnd) => {
      const m = /^(\d{1,2}):(\d{2})$/.exec(v || '');
      if (!m) return null;
      const n = +m[1] * 60 + +m[2];
      return isEnd && n === 0 ? 1440 : n;
    };
    const fmt = (n) => Math.floor(n / 60) + 'h' + (n % 60 ? String(n % 60).padStart(2, '0') : '');
    const readGrid = () => DAYS.map((_, d) => {
      if (!field(d, 'open').checked) return [];
      const out = [];
      for (const i of [1, 2]) {
        const a = toMin(field(d, 's' + i).value, false), b = toMin(field(d, 'e' + i).value, true);
        if (a !== null && b !== null) out.push([a, b]);
      }
      return out;
    });
    const compose = (grid, note) => {
      const slots = (s) => s.map(([a, b]) => `de ${fmt(a)} à ${fmt(b)}`).join(' et ');
      const segs = [];
      for (let d = 0; d < 7; d++) {
        if (!grid[d].length) continue;
        let e = d;
        while (e < 6 && JSON.stringify(grid[e + 1]) === JSON.stringify(grid[d])) e++;
        const name = DAYS[d][0].toUpperCase() + DAYS[d].slice(1);
        segs.push((e - d === 6 ? 'Tous les jours' : e === d ? name : `Du ${DAYS[d]} au ${DAYS[e]}`) + ' ' + slots(grid[d]));
        d = e;
      }
      if (note.trim()) segs.push(note.trim());
      return segs.join(' · ');
    };
    const mode = () => form.querySelector('input[name=hours_mode]:checked')?.value;
    const initialGrid = JSON.stringify(readGrid()), initialNote = $('f-hours-note').value.trim();
    const gridText = () => (JSON.stringify(readGrid()) === initialGrid && $('f-hours-note').value.trim() === initialNote && hPreview.dataset.original)
      ? hPreview.dataset.original : compose(readGrid(), $('f-hours-note').value);
    const update = () => {
      const m = mode();
      hoursBox.querySelectorAll('[data-hours-panel]').forEach((p) => { p.hidden = p.dataset.hoursPanel !== m; });
      DAYS.forEach((_, d) => hoursBox.querySelector(`[data-day="${d}"]`).classList.toggle('is-closed', !field(d, 'open').checked));
      const text = m === 'aucun' ? '' : m === 'rdv' ? 'Sur rendez-vous' : m === 'libre' ? $('f-hours-free').value.trim().replace(/\s*\n+\s*/g, ' · ') : gridText();
      hPreview.textContent = text || '(aucun horaire : le bloc n’apparaît pas sur la fiche)';
    };
    hoursBox.addEventListener('input', update);
    hoursBox.addEventListener('change', (ev) => {
      // Passage en texte libre : partir des horaires de la grille plutôt que d'un texte périmé
      if (ev.target.name === 'hours_mode' && ev.target.value === 'libre' && JSON.stringify(readGrid()) !== initialGrid && readGrid().some((s) => s.length)) {
        $('f-hours-free').value = compose(readGrid(), $('f-hours-note').value);
      }
      update();
    });
    hoursBox.querySelectorAll('[data-copy]').forEach((btn) => btn.addEventListener('click', () => {
      const d = +btn.dataset.copy;
      field(d + 1, 'open').checked = field(d, 'open').checked;
      for (const k of ['s1', 'e1', 's2', 'e2']) field(d + 1, k).value = field(d, k).value;
      update();
      form.dispatchEvent(new Event('input'));   // indicateur « non enregistré »
    }));
    update();
  }

  // --- Photos et logo : réduites dans le navigateur (le serveur refuse au-delà de 2 Mo), puis envoyées.
  // L'envoi ne modifie pas la fiche : elle change au clic sur « Enregistrer ».
  const photosBox = $('photos');
  let uploading = 0;
  if (photosBox) {
    const list = $('photo-list'), status = $('photo-status');
    const changed = () => { $('photo-none').hidden = list.children.length > 0; form.dispatchEvent(new Event('input')); };
    list.addEventListener('click', (ev) => {
      const btn = ev.target.closest('[data-photo]');
      if (!btn) return;
      const li = btn.closest('li');
      const act = btn.dataset.photo;
      if (act === 'left' && li.previousElementSibling) li.previousElementSibling.before(li);
      if (act === 'right' && li.nextElementSibling) li.nextElementSibling.after(li);
      if (act === 'cover') list.prepend(li);
      if (act === 'remove') li.remove();
      changed();
    });

    const shrink = async (file, kind) => {
      const max = kind === 'logo' ? 600 : 1400;
      let bmp;
      try { bmp = await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch {
        if (file.size < 1.9e6) return file;   // format que le navigateur ne sait pas lire : le serveur tranchera
        throw new Error('Format non pris en charge : envoyez une photo JPEG ou PNG.');
      }
      const scale = Math.min(1, max / Math.max(bmp.width, bmp.height));
      const c = document.createElement('canvas');
      c.width = Math.round(bmp.width * scale); c.height = Math.round(bmp.height * scale);
      const ctx = c.getContext('2d');
      if (kind !== 'logo') { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height); }
      ctx.drawImage(bmp, 0, 0, c.width, c.height);
      return new Promise((ok) => c.toBlob(ok, kind === 'logo' ? 'image/png' : 'image/jpeg', 0.88));
    };
    const send = async (file, kind) => {
      const blob = await shrink(file, kind);
      const fd = new FormData();
      fd.append('csrf', form.elements.csrf.value);
      fd.append('slug', photosBox.dataset.slug);
      fd.append('c', photosBox.dataset.c || 'entreprises');
      fd.append('kind', kind);
      fd.append('file', blob, kind === 'logo' ? 'logo.png' : 'photo.jpg');
      const res = await fetch('/admin/upload.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      let data = null;
      try { data = await res.json(); } catch { /* réponse non JSON : session expirée, etc. */ }
      if (!res.ok || !data?.ok) throw new Error(data?.error || 'Envoi impossible (session expirée ?) : rechargez la page.');
      return data.path;
    };

    photosBox.querySelectorAll('input[data-upload]').forEach((input) => input.addEventListener('change', async () => {
      const kind = input.dataset.upload;
      const files = [...input.files];
      input.value = '';
      const errors = [];
      uploading++;
      for (const [i, file] of files.entries()) {
        status.textContent = `Envoi ${files.length > 1 ? `${i + 1} / ${files.length} ` : ''}en cours…`;
        let li = null;
        if (kind === 'photo') {
          li = list.querySelector('li')?.cloneNode(true) || (() => {
            const t = document.createElement('li'); t.className = 'adm-photo';
            t.innerHTML = '<img alt=""><input type="hidden" name="photos[]"><span class="adm-photo__cover">Couverture</span><span class="adm-photo__tools"><button type="button" data-photo="left" aria-label="Avancer">←</button><button type="button" data-photo="right" aria-label="Reculer">→</button><button type="button" data-photo="cover">En couverture</button><button type="button" data-photo="remove">Retirer</button></span>';
            return t;
          })();
          li.classList.add('is-uploading');
          li.querySelector('img').src = URL.createObjectURL(file);
          li.querySelector('input').value = '';
          list.append(li);
        }
        try {
          const path = await send(file, kind);
          if (kind === 'photo') { li.querySelector('img').src = path; li.querySelector('input').value = path; li.classList.remove('is-uploading'); }
          else { $('logo-img').src = path; $('logo-img').hidden = false; $('logo-none').hidden = true; $('logo-remove').hidden = false; $('logo-input').value = path; }
        } catch (err) {
          if (li) li.remove();
          errors.push(`${file.name} : ${err.message}`);
        }
        changed();
      }
      uploading--;
      status.textContent = errors.length ? errors.join(' ') : 'Envoyé. Cliquez sur « Enregistrer » pour publier.';
    }));
    $('logo-remove')?.addEventListener('click', () => {
      $('logo-input').value = ''; $('logo-img').hidden = true; $('logo-none').hidden = false; $('logo-remove').hidden = true;
      changed();
    });
  }

  // --- Aperçu en direct de la carte (annuaire / louer un local), mêmes règles que lib/cards.php
  const live = document.querySelector('.adm-live');
  if (live) {
    const val = (id) => ($(id)?.value || '').trim();
    const excerpt = (txt) => {
      let s = txt.replace(/\n\s*[•\-]\s*/g, ' ').replace(/\s+/g, ' ').trim();
      if (s.length > 100) { s = s.slice(0, 100); if (s.includes(' ')) s = s.slice(0, s.lastIndexOf(' ')); s = s.replace(/[\s,;:.\-–]+$/, '') + '…'; }
      return s;
    };
    const cover = () => document.querySelector('#photo-list input[name="photos[]"]')?.closest('li')?.querySelector('img')?.getAttribute('src') || '';
    const num = (v) => { const n = parseFloat(String(v).replace(/[\s\u00a0\u202f€]/g, '').replace(',', '.')); return isNaN(n) ? null : n; };
    const draw = () => {
      if (live.dataset.live === 'actor') {
        const name = val('f-name');
        $('pv-name').textContent = name || 'Nom de l’acteur';
        const sel = $('f-category'); $('pv-cat').textContent = sel.options[sel.selectedIndex]?.text || '';
        $('pv-desc').textContent = val('f-card-desc') || excerpt($('f-description').value);
        const src = cover();
        $('pv-img').hidden = !src; if (src) $('pv-img').src = src;
        $('pv-ph').hidden = !!src;
        $('pv-ph').textContent = name.split(/[\s'-]+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
      } else {
        const [ref, title] = (() => { const n = val('f-name'); const i = n.indexOf(' - '); return i < 0 ? ['', n] : [n.slice(0, i), n.slice(i + 3)]; })();
        $('pv-ref').textContent = val('f-card-ref') || ref;
        $('pv-title').textContent = val('f-card-title') || title || 'Titre du local';
        const surf = num(val('f-surface')), price = num(val('f-price'));
        const fmt = (n) => n === null ? '…' : n.toLocaleString('fr-FR', { minimumFractionDigits: Number.isInteger(n) ? 0 : 2, maximumFractionDigits: 2 });
        $('pv-meta').textContent = `${fmt(surf)} m² · ${fmt(price)} € /mois HT`;
        const d = $('f-description').value.trim(); const m = d.match(/^(.+?[.!?])(\s|$)/);
        $('pv-sub').textContent = val('f-card-sub') || (m ? m[1] : excerpt(d));
        $('pv-img').src = cover() || $('pv-img').dataset.default;
      }
    };
    form.addEventListener('input', draw);
    form.addEventListener('change', draw);
    const plist = $('photo-list');
    if (plist) new MutationObserver(draw).observe(plist, { childList: true, subtree: true, attributes: true, attributeFilter: ['src', 'value'] });
    draw();
  }

  // Prévenir avant de quitter une saisie non enregistrée
  const snapshot = () => new URLSearchParams(new FormData(form)).toString();
  // Formulaire réaffiché après un refus (erreur, conflit) : la saisie à l'écran n'est pas enregistrée
  const initial = form.hasAttribute('data-unsaved') ? null : snapshot();
  const dirty = $('adm-dirty');
  let submitting = false;
  const refresh = () => { dirty.hidden = snapshot() === initial; };
  form.addEventListener('input', refresh);
  form.addEventListener('change', refresh);
  refresh();
  window.addEventListener('beforeunload', (ev) => {
    if (!submitting && snapshot() !== initial) { ev.preventDefault(); ev.returnValue = ''; }
  });
  form.addEventListener('submit', (ev) => {
    if (uploading) { ev.preventDefault(); alert('Une image est en cours d’envoi : attendez la fin avant d’enregistrer.'); return; }
    submitting = true;
    form.querySelector('button[type=submit]').disabled = true;   // pas de double envoi
  });
  // Retour arrière vers une page gardée en mémoire par le navigateur : bouton de nouveau utilisable
  window.addEventListener('pageshow', () => { submitting = false; form.querySelector('button[type=submit]').disabled = false; });
});
