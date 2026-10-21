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
