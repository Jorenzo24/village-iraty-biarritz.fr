/* Back-office VIB : confort de saisie. Tout est contrôlé à nouveau par le serveur ; sans ce script, tout fonctionne. */
document.addEventListener('DOMContentLoaded', () => {
  const fold = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/œ/g, 'oe').replace(/æ/g, 'ae').replace(/’/g, "'");

  // --- Liste des acteurs : recherche + catégorie
  const list = document.getElementById('adm-list');
  if (list) {
    const q = document.getElementById('adm-q');
    const cat = document.getElementById('adm-cat');
    const none = document.getElementById('adm-none');
    const rows = [...list.querySelectorAll('li[data-search]')];
    const apply = () => {
      const words = fold(q.value).split(/\s+/).filter(Boolean);
      let shown = 0;
      rows.forEach((li) => {
        const ok = (!cat.value || li.dataset.cat === cat.value) && words.every((w) => li.dataset.search.includes(w));
        li.hidden = !ok;
        if (ok) shown++;
      });
      none.hidden = shown > 0;
    };
    q.addEventListener('input', apply);
    cat.addEventListener('change', apply);
    apply();   // le navigateur peut avoir conservé la saisie (retour arrière)
  }

  // --- Formulaire d'une fiche
  const form = document.getElementById('adm-form');
  if (!form) return;

  // Aperçu de l'adresse : même règle que vib_address_compose() (lib/fields.php)
  const $ = (id) => document.getElementById(id);
  const preview = $('addr-preview');
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
  form.addEventListener('submit', () => {
    submitting = true;
    form.querySelector('button[type=submit]').disabled = true;   // pas de double envoi
  });
  // Retour arrière vers une page gardée en mémoire par le navigateur : bouton de nouveau utilisable
  window.addEventListener('pageshow', () => { submitting = false; form.querySelector('button[type=submit]').disabled = false; });
});
