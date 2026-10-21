<?php
/**
 * Back-office VIB — modification d'une fiche acteur (textes, adresse, contacts, réseaux, horaires, photos).
 *
 * Enregistrement, conflits et historique : mécanique commune, lib/admin-fiche.php.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/admin-fiche.php';
$user = vib_admin_user();

$col = vib_admin_collection('entreprises');
$store = new Store('entreprises');
$store->seedIfEmpty();
$slug = (string) ($_POST['slug'] ?? $_GET['slug'] ?? '');
$item = vib_find($store->read()['items'], $slug);

if ($item === null) {
    http_response_code(404);
    vib_admin_header('Fiche introuvable', $user, 'acteurs');
    echo '<p><a class="adm-back" href="/admin/acteurs.php">← Tous les acteurs</a></p><div class="adm-flash adm-flash--ko">Cette fiche n’existe pas (ou plus).</div>';
    vib_admin_footer();
    exit;
}

$st = vib_admin_edit($store, $col, $item, $user, 'vib_actor_form_values', 'vib_actor_apply', 'vib_form_merge', function (array $m): array {
    if (!is_array($m['social'])) $m['social'] = [];
    $m['h'] = vib_hours_form_norm($m['h']);
    $m['photos'] = array_values(array_filter(is_array($m['photos']) ? $m['photos'] : [], fn($p) => is_string($p) && $p !== ''));
    if (!is_string($m['logo'])) $m['logo'] = '';
    $m['address_mode'] = ($_POST['address_mode'] ?? '') === 'libre' ? 'libre' : 'structuree';   // case décochée = absente du formulaire
    return $m;
});
['item' => $item, 'values' => $values, 'errors' => $errors] = $st;
$last = vib_admin_last_change($store, $slug);

$v = fn(string $k): string => e((string) ($values[$k] ?? ''));
$err = function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="adm-error" id="err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
};
$inv = fn(string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . e($k) . '"' : '';
$free = $values['address_mode'] === 'libre';

vib_admin_header($item['name'], $user, 'acteurs');
?>
  <p><a class="adm-back" href="/admin/acteurs.php">← Tous les acteurs</a></p>
  <div>
    <h1><?= e($item['name']) ?></h1>
    <p class="adm-muted"><?= e($item['category_label']) ?> · <a href="/acteur/<?= e(rawurlencode($slug)) ?>" target="_blank" rel="noopener">Voir la fiche en ligne ↗</a></p>
  </div>

  <?php vib_admin_flash($col, $slug, $last, $st['conflict'], $errors); ?>

  <form class="adm-form" method="post" action="/admin/acteur.php" id="adm-form" novalidate<?= $_SERVER['REQUEST_METHOD'] === 'POST' ? ' data-unsaved' : '' ?>>
    <?= vib_admin_hidden($col, $st, $user) ?>

    <section class="adm-card">
      <h2>Présentation</h2>
      <div class="adm-grid adm-grid--2">
        <div class="adm-field">
          <label for="f-name">Nom</label>
          <input type="text" id="f-name" name="name" value="<?= $v('name') ?>" required<?= $inv('name') ?>>
          <?= $err('name') ?>
        </div>
        <div class="adm-field">
          <label for="f-category">Catégorie</label>
          <select id="f-category" name="category"<?= $inv('category') ?>>
            <?php foreach (VIB_CATEGORIES as $k => $label): ?>
              <option value="<?= e($k) ?>"<?= $values['category'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $err('category') ?>
        </div>
      </div>
      <div class="adm-field">
        <label for="f-description">Description</label>
        <textarea id="f-description" name="description"><?= $v('description') ?></textarea>
        <p class="adm-hint">Un retour à la ligne = un nouveau paragraphe. Une ligne qui commence par « • » ou « - » devient une puce.</p>
      </div>
      <div class="adm-field">
        <label for="f-card-desc">Accroche de la carte (annuaire)</label>
        <input type="text" id="f-card-desc" name="card_desc" value="<?= $v('card_desc') ?>" maxlength="160" data-excerpt-from="f-description"<?= $inv('card_desc') ?>>
        <?= $err('card_desc') ?>
        <p class="adm-hint">Une phrase courte (100 caractères environ) sous le nom, sur la carte de l’annuaire. Vide : le début de la description est repris automatiquement. <button type="button" class="adm-linkbtn" data-excerpt="f-card-desc">Reprendre le début de la description</button></p>
      </div>
    </section>

    <section class="adm-card">
      <h2>Adresse</h2>
      <div id="addr-structured"<?= $free ? ' hidden' : '' ?>>
        <div class="adm-grid adm-grid--addr">
          <div class="adm-field">
            <label for="f-building">Bâtiment</label>
            <select id="f-building" name="building">
              <option value="">(aucun)</option>
              <?php foreach (VIB_BUILDINGS as $b): ?>
                <option<?= $values['building'] === $b ? ' selected' : '' ?>><?= e($b) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="adm-field">
            <label for="f-street">Numéro et rue</label>
            <input type="text" id="f-street" name="street" value="<?= $v('street') ?>" placeholder="10 rue des Mésanges">
          </div>
          <div class="adm-field">
            <label for="f-lot">Lot</label>
            <input type="text" id="f-lot" name="lot" value="<?= $v('lot') ?>" placeholder="24">
          </div>
        </div>
        <p class="adm-hint">Lot : « 24 », ou « 9 et 10 » pour plusieurs lots, ou « Bureau 2 ».</p>
        <p class="adm-preview">Adresse affichée : <span id="addr-preview"></span></p>
      </div>
      <div class="adm-field" id="addr-free"<?= $free ? '' : ' hidden' ?>>
        <label for="f-address-free">Adresse complète</label>
        <input type="text" id="f-address-free" name="address_free" value="<?= $v('address_free') ?>"<?= $inv('address') ?>>
        <p class="adm-hint">Format habituel : Halle DARLA - 10 rue des Mésanges - Lot n°24 - 64200 BIARRITZ</p>
      </div>
      <?= $err('address') ?>
      <label class="adm-check"><input type="checkbox" id="f-address-mode" name="address_mode" value="libre"<?= $free ? ' checked' : '' ?>> Saisir l’adresse librement</label>
    </section>

    <section class="adm-card">
      <h2>Contact</h2>
      <div class="adm-grid adm-grid--2">
        <div class="adm-field">
          <label for="f-phone">Téléphone</label>
          <input type="tel" id="f-phone" name="phone" value="<?= $v('phone') ?>" placeholder="05 59 00 00 00">
        </div>
        <div class="adm-field">
          <label for="f-email">E-mail</label>
          <input type="email" id="f-email" name="email" value="<?= $v('email') ?>"<?= $inv('email') ?>>
          <?= $err('email') ?>
        </div>
      </div>
      <div class="adm-field">
        <label for="f-website">Site web</label>
        <input type="url" id="f-website" name="website" value="<?= $v('website') ?>" placeholder="https://"<?= $inv('website') ?>>
        <?= $err('website') ?>
      </div>
      <p class="adm-hint">Un champ laissé vide n’apparaît pas sur la fiche.</p>
    </section>

    <section class="adm-card">
      <h2>Réseaux sociaux</h2>
      <div class="adm-grid adm-grid--2">
        <?php foreach (VIB_SOCIAL as $k => [$label]): ?>
          <div class="adm-field">
            <label for="f-social-<?= e($k) ?>"><?= e($label) ?></label>
            <input type="url" id="f-social-<?= e($k) ?>" name="social[<?= e($k) ?>]" value="<?= e((string) ($values['social'][$k] ?? '')) ?>" placeholder="https://"<?= $inv('social') ?>>
          </div>
        <?php endforeach; ?>
      </div>
      <?= $err('social') ?>
    </section>

    <section class="adm-card" id="hours">
      <h2>Horaires</h2>
      <?php $hm = $values['hours_mode']; ?>
      <div class="adm-modes" role="radiogroup" aria-label="Type d’horaires">
        <?php foreach (['grille' => 'Horaires d’ouverture', 'rdv' => 'Sur rendez-vous', 'aucun' => 'Non renseignés', 'libre' => 'Texte libre'] as $k => $label): ?>
          <label class="adm-mode"><input type="radio" name="hours_mode" value="<?= e($k) ?>"<?= $hm === $k ? ' checked' : '' ?>> <?= e($label) ?></label>
        <?php endforeach; ?>
      </div>
      <?= $err('hours') ?>

      <div class="adm-hours" data-hours-panel="grille"<?= $hm === 'grille' ? '' : ' hidden' ?>>
        <?php foreach (VIB_DAYS as $d => $day): $r = $values['h'][$d]; ?>
          <div class="adm-day<?= $r['open'] ? '' : ' is-closed' ?>" data-day="<?= $d ?>">
            <label class="adm-day__name"><input type="checkbox" name="h[<?= $d ?>][open]" value="1"<?= $r['open'] ? ' checked' : '' ?>> <?= e(ucfirst($day)) ?></label>
            <span class="adm-day__closed">Fermé</span>
            <span class="adm-day__slots">
              <span class="adm-slot"><input type="time" name="h[<?= $d ?>][s1]" value="<?= e($r['s1']) ?>" aria-label="<?= e(ucfirst($day)) ?>, ouverture"> à <input type="time" name="h[<?= $d ?>][e1]" value="<?= e($r['e1']) ?>" aria-label="<?= e(ucfirst($day)) ?>, fermeture"></span>
              <span class="adm-slot adm-slot--2">et <input type="time" name="h[<?= $d ?>][s2]" value="<?= e($r['s2']) ?>" aria-label="<?= e(ucfirst($day)) ?>, réouverture"> à <input type="time" name="h[<?= $d ?>][e2]" value="<?= e($r['e2']) ?>" aria-label="<?= e(ucfirst($day)) ?>, 2e fermeture"></span>
            </span>
            <?php if ($d < 6): ?><button type="button" class="adm-day__copy" data-copy="<?= $d ?>" title="Recopier sur <?= e(VIB_DAYS[$d + 1]) ?>">Recopier sur <?= e(VIB_DAYS[$d + 1]) ?> ↓</button><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <p class="adm-hint">Pause du midi : remplissez la 2e plage (« et … à … »), sinon laissez-la vide. Fermeture après minuit : indiquez simplement l’heure (ex. de 18:00 à 02:00).</p>
        <div class="adm-field">
          <label for="f-hours-note">Précision (facultatif)</label>
          <input type="text" id="f-hours-note" name="hours_note" value="<?= $v('hours_note') ?>" placeholder="Ex. : Le soir sur réservation">
        </div>
      </div>

      <div class="adm-field" data-hours-panel="libre"<?= $hm === 'libre' ? '' : ' hidden' ?>>
        <label for="f-hours-free">Horaires</label>
        <textarea id="f-hours-free" name="hours_free" class="adm-textarea--short"><?= $v('hours_free') ?></textarea>
        <p class="adm-hint">Une ligne par jour ou groupe de jours, ex. « Du lundi au vendredi de 9h à 18h ». À réserver aux cas que la grille ne permet pas : le badge « Ouvert / Fermé » peut ne pas s’afficher.</p>
      </div>

      <p class="adm-preview">Texte affiché sur la fiche : <span id="hours-preview" data-original="<?= e((string) $item['hours']) ?>"></span></p>
    </section>

    <section class="adm-card" id="photos" data-slug="<?= e($slug) ?>" data-c="entreprises">
      <h2>Photos</h2>
      <p class="adm-hint">La première photo est la couverture (en grand sur la fiche, et image d’aperçu quand la fiche est partagée). Les changements ne sont visibles sur le site qu’après « Enregistrer ».</p>
      <ul class="adm-photos" id="photo-list">
        <?php foreach ($values['photos'] as $src): ?>
          <li class="adm-photo">
            <img src="<?= e($src) ?>" alt="" loading="lazy">
            <input type="hidden" name="photos[]" value="<?= e($src) ?>">
            <span class="adm-photo__cover">Couverture</span>
            <span class="adm-photo__tools">
              <button type="button" data-photo="left" aria-label="Avancer">←</button>
              <button type="button" data-photo="right" aria-label="Reculer">→</button>
              <button type="button" data-photo="cover">En couverture</button>
              <button type="button" data-photo="remove">Retirer</button>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <input type="hidden" name="photos[]" value="" data-keep>
      <p class="adm-muted" id="photo-none"<?= $values['photos'] ? ' hidden' : '' ?>>Aucune photo : la fiche affiche le nom à la place.</p>
      <?= $err('photos') ?>
      <label class="adm-btn adm-btn--ghost adm-upload">Ajouter des photos<input type="file" accept="image/jpeg,image/png,image/webp" multiple data-upload="photo" hidden></label>
      <p class="adm-hint" id="photo-status" role="status"></p>

      <h2 class="adm-subtitle">Logo</h2>
      <div class="adm-logo" id="logo-box">
        <img id="logo-img" src="<?= e($values['logo']) ?>" alt=""<?= $values['logo'] ? '' : ' hidden' ?>>
        <span class="adm-muted" id="logo-none"<?= $values['logo'] ? ' hidden' : '' ?>>Aucun logo</span>
        <input type="hidden" name="logo" id="logo-input" value="<?= e($values['logo']) ?>">
        <label class="adm-btn adm-btn--ghost adm-upload"><?= $values['logo'] ? 'Remplacer' : 'Ajouter un logo' ?><input type="file" accept="image/jpeg,image/png,image/webp" data-upload="logo" hidden></label>
        <button type="button" class="adm-btn adm-btn--ghost" id="logo-remove"<?= $values['logo'] ? '' : ' hidden' ?>>Retirer</button>
      </div>
      <?= $err('logo') ?>
    </section>

    <div class="adm-actions">
      <button type="submit" class="adm-btn">Enregistrer</button>
      <a class="adm-btn adm-btn--ghost" href="/admin/acteur.php?slug=<?= e(rawurlencode($slug)) ?>">Annuler</a>
      <span class="adm-dirty" id="adm-dirty" hidden>Modifications non enregistrées</span>
    </div>
  </form>

  <?php vib_admin_item_footer($store, $col, $slug); ?>
<?php vib_admin_footer('/admin/admin.js?v=20261021f');
