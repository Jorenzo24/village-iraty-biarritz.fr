<?php
/**
 * Back-office VIB — modification d'une fiche « local à louer ».
 * Enregistrement, conflits et historique : mécanique commune, lib/admin-fiche.php.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/admin-fiche.php';
$user = vib_admin_user();

$col = vib_admin_collection('locaux');
$store = new Store('locaux');
$store->seedIfEmpty();
$slug = (string) ($_POST['slug'] ?? $_GET['slug'] ?? '');
$item = vib_find($store->read()['items'], $slug);

if ($item === null) {
    http_response_code(404);
    vib_admin_header('Fiche introuvable', $user, 'locaux');
    echo '<p><a class="adm-back" href="/admin/locaux.php">← Tous les locaux</a></p><div class="adm-flash adm-flash--ko">Ce local n’existe pas (ou plus).</div>';
    vib_admin_footer();
    exit;
}

$st = vib_admin_edit($store, $col, $item, $user, 'vib_local_form_values', 'vib_local_apply', 'vib_local_merge', function (array $m): array {
    foreach (VIB_LOCAL_FLAGS as $k => $_) $m[$k] = ($_POST[$k] ?? '') !== '' ? '1' : '';   // case décochée = absente du formulaire
    $m['photos'] = array_values(array_filter(is_array($m['photos']) ? $m['photos'] : [], fn($p) => is_string($p) && $p !== ''));
    return $m;
});
['item' => $item, 'values' => $values, 'errors' => $errors] = $st;

$v = fn(string $k): string => e((string) ($values[$k] ?? ''));
$err = fn(string $k): string => isset($errors[$k]) ? '<p class="adm-error" id="err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k): string => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . e($k) . '"' : '';

vib_admin_header($item['name'], $user, 'locaux', true);
?>
  <p><a class="adm-back" href="/admin/locaux.php">← Tous les locaux</a></p>
  <div class="adm-head">
  <div>
    <h1><?= e($item['name']) ?></h1>
  </div>
    <div class="adm-head__actions">
      <a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e($col['public'] . rawurlencode($slug)) ?>" target="_blank" rel="noopener">Voir en ligne ↗</a>
      <a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e(vib_admin_url($col, 'retirer.php', ['slug' => $slug])) ?>">Dépublier</a>
      <a class="adm-btn adm-btn--ghost adm-btn--small adm-btn--warn" href="<?= e(vib_admin_url($col, 'supprimer.php', ['slug' => $slug])) ?>">Supprimer</a>
    </div>
  </div>

  <?php vib_admin_flash($col, $slug, vib_admin_last_change($store, $slug), $st['conflict'], $errors); ?>

  <div class="adm-edit">
  <form class="adm-form" method="post" action="/admin/local.php" id="adm-form" novalidate<?= $_SERVER['REQUEST_METHOD'] === 'POST' ? ' data-unsaved' : '' ?>>
    <?= vib_admin_hidden($col, $st, $user) ?>

    <section class="adm-card">
      <h2>Annonce</h2>
      <div class="adm-field">
        <label for="f-name">Titre</label>
        <input type="text" id="f-name" name="name" value="<?= $v('name') ?>" required<?= $inv('name') ?>>
        <?= $err('name') ?>
        <p class="adm-hint">Ex. : Lot 50 - Duplex commercial 90 m². C’est aussi le titre de la page et de l’aperçu quand l’annonce est partagée.</p>
      </div>
      <div class="adm-field">
        <label for="f-type">Type de local</label>
        <input type="text" id="f-type" name="type" value="<?= $v('type') ?>" placeholder="Bureau / Showroom / Atelier">
      </div>
      <div class="adm-field">
        <label for="f-address">Adresse</label>
        <input type="text" id="f-address" name="address" value="<?= $v('address') ?>">
      </div>
    </section>

    <section class="adm-card">
      <h2>Surface et prix</h2>
      <div class="adm-grid adm-grid--3">
        <div class="adm-field">
          <label for="f-surface">Surface (m²)</label>
          <input type="text" inputmode="decimal" id="f-surface" name="surface" value="<?= $v('surface') ?>"<?= $inv('surface') ?>>
          <?= $err('surface') ?>
        </div>
        <div class="adm-field">
          <label for="f-price">Loyer HT / mois (€)</label>
          <input type="text" inputmode="decimal" id="f-price" name="price_ht" value="<?= $v('price_ht') ?>"<?= $inv('price_ht') ?>>
          <?= $err('price_ht') ?>
        </div>
        <div class="adm-field">
          <label for="f-charges">Charges HT / mois (€)</label>
          <input type="text" inputmode="decimal" id="f-charges" name="charges_ht" value="<?= $v('charges_ht') ?>"<?= $inv('charges_ht') ?>>
          <?= $err('charges_ht') ?>
        </div>
      </div>
      <p class="adm-hint">Chiffres seuls, virgule pour les centimes (ex. 1165 ou 150,19). Charges à 0 ou vides : le bloc « charges » n’apparaît pas sur la fiche.</p>
      <div class="adm-flags">
        <?php foreach (VIB_LOCAL_FLAGS as $k => $label): ?>
          <label class="adm-check"><input type="checkbox" name="<?= e($k) ?>" value="1"<?= $values[$k] ? ' checked' : '' ?>> <?= e($label) ?></label>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="adm-card">
      <h2>Description</h2>
      <div class="adm-field">
        <label for="f-description" class="adm-visually-hidden">Description</label>
        <textarea id="f-description" name="description"><?= $v('description') ?></textarea>
        <p class="adm-hint">Sur la fiche, chaque phrase s’affiche sur sa propre ligne : rédigez des phrases courtes. Les retours à la ligne sont ignorés.</p>
      </div>
    </section>

    <section class="adm-card">
      <h2>Carte dans la liste des locaux</h2>
      <p class="adm-hint">Textes de la carte sur la page « Louer un local » (et sur l’accueil si le local y est mis en avant). Laissés vides, ils sont tirés du titre et de la description.</p>
      <div class="adm-grid adm-grid--2">
        <div class="adm-field">
          <label for="f-card-ref">Référence</label>
          <input type="text" id="f-card-ref" name="card_ref" value="<?= $v('card_ref') ?>" placeholder="Lot 50, Halle DARLA">
        </div>
        <div class="adm-field">
          <label for="f-card-title">Titre court</label>
          <input type="text" id="f-card-title" name="card_title" value="<?= $v('card_title') ?>" placeholder="Duplex commercial 90 m²">
        </div>
      </div>
      <div class="adm-field">
        <label for="f-card-sub">Accroche</label>
        <input type="text" id="f-card-sub" name="card_sub" value="<?= $v('card_sub') ?>" maxlength="160" placeholder="Rez-de-chaussée et premier étage, aménagé, normes PMR."<?= $inv('card_sub') ?>>
        <?= $err('card_sub') ?>
      </div>
    </section>

    <section class="adm-card" id="photos" data-slug="<?= e($slug) ?>" data-c="locaux">
      <h2>Photos</h2>
      <p class="adm-hint">La première photo est la couverture (en grand sur la fiche, et image d’aperçu quand l’annonce est partagée). Les changements ne sont visibles sur le site qu’après « Enregistrer ».</p>
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
      <p class="adm-muted" id="photo-none"<?= $values['photos'] ? ' hidden' : '' ?>>Aucune photo : la fiche affiche une image par défaut.</p>
      <?= $err('photos') ?>
      <label class="adm-btn adm-btn--ghost adm-upload">Ajouter des photos<input type="file" accept="image/jpeg,image/png,image/webp" multiple data-upload="photo" hidden></label>
      <p class="adm-hint" id="photo-status" role="status"></p>
    </section>

    <div class="adm-actions">
      <button type="submit" class="adm-btn">Enregistrer</button>
      <a class="adm-btn adm-btn--ghost" href="/admin/local.php?slug=<?= e(rawurlencode($slug)) ?>">Annuler</a>
      <span class="adm-dirty" id="adm-dirty" hidden>Modifications non enregistrées</span>
    </div>
  </form>
    <aside class="adm-live" aria-label="Aperçu de la carte sur la page Louer un local" data-live="local">
      <p class="adm-eyebrow">Aperçu sur « Louer un local »</p>
      <div class="adm-live__card">
        <div class="adm-live__media">
          <img id="pv-img" src="<?= e($values['photos'][0] ?? '/assets-2026/images/story-interieur.jpg') ?>" alt="" data-default="/assets-2026/images/story-interieur.jpg">
          <span class="adm-live__badge adm-live__badge--ok">● Disponible</span>
        </div>
        <div class="adm-live__body">
          <p class="adm-live__ref" id="pv-ref"></p>
          <h3 id="pv-title"></h3>
          <p class="adm-live__meta" id="pv-meta"></p>
          <p id="pv-sub"></p>
          <span class="adm-live__more">En savoir plus →</span>
        </div>
      </div>
    </aside>
  </div>

  <?php vib_admin_item_footer($store, $col, $slug); ?>
<?php vib_admin_footer('/admin/admin.js?v=20261022e');
