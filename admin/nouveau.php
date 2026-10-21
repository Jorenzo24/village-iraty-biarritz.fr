<?php
/** Back-office VIB — ajout d'une fiche (acteur : nom + catégorie ; local : titre, surface, loyer). Le reste se complète sur la fiche. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();

$col = vib_admin_collection();
$isLocal = $col['c'] === 'locaux';
$store = new Store($col['c']);
$store->seedIfEmpty();
$name = trim((string) ($_POST['name'] ?? ''));
$cat = (string) ($_POST['category'] ?? '');
$surface = trim((string) ($_POST['surface'] ?? ''));
$price = trim((string) ($_POST['price_ht'] ?? ''));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    $sNum = vib_input_to_num($surface);
    $pNum = vib_input_to_num($price);
    if ($name === '') $error = $isLocal ? 'Indiquez le titre du local.' : 'Indiquez le nom de l’acteur.';
    elseif (!$isLocal && !isset(VIB_CATEGORIES[$cat])) $error = 'Choisissez une catégorie.';
    elseif ($isLocal && (!$sNum || !$pNum)) $error = 'Indiquez la surface et le loyer (nombres supérieurs à 0).';
    else {
        $same = array_filter($store->read()['items'], fn($it) => vib_fold($it['name']) === vib_fold($name));
        if ($same && empty($_POST['confirm_double'])) {
            $error = 'Une fiche porte déjà ce nom : « ' . reset($same)['name'] . ' ». Cochez la case ci-dessous si c’est bien une autre fiche.';
        } else {
            for ($try = 0; $try < 3; $try++) {
                $slug = vib_unique_slug($name, vib_taken_slugs($store, $col['prefix']));
                $item = $isLocal ? vib_local_new($slug, $name, $sNum, $pNum) : vib_actor_new($slug, $name, $cat);
                try {
                    $store->addItem($item, $user['email'], 'Ajout de la fiche « ' . $name . ' » (' . $slug . ')');
                    header('Location: ' . $col['edit'] . '?slug=' . rawurlencode($slug) . '&ok=nouveau', true, 303);
                    exit;
                } catch (StoreConflict) { /* identifiant pris entre-temps : on recalcule */ }
            }
            $error = 'Création impossible, réessayez.';
        }
    }
}

vib_admin_header($col['add'], $user, $col['nav']);
?>
  <p><a class="adm-back" href="<?= e($col['list']) ?>">← <?= e($col['title']) ?></a></p>
  <h1><?= e($col['add']) ?></h1>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form class="adm-form" method="post" action="/admin/nouveau.php">
    <input type="hidden" name="csrf" value="<?= e(vib_csrf_token($user['email'])) ?>">
    <input type="hidden" name="c" value="<?= e($col['c']) ?>">
    <section class="adm-card">
      <div class="adm-grid adm-grid--2">
        <div class="adm-field">
          <label for="f-name"><?= $isLocal ? 'Titre de l’annonce' : 'Nom' ?></label>
          <input type="text" id="f-name" name="name" value="<?= e($name) ?>" required autofocus<?= $isLocal ? ' placeholder="Lot 50 - Duplex commercial 90 m²"' : '' ?>>
        </div>
        <?php if ($isLocal): ?>
          <div class="adm-grid adm-grid--2">
            <div class="adm-field">
              <label for="f-surface">Surface (m²)</label>
              <input type="text" inputmode="decimal" id="f-surface" name="surface" value="<?= e($surface) ?>" required>
            </div>
            <div class="adm-field">
              <label for="f-price">Loyer HT / mois (€)</label>
              <input type="text" inputmode="decimal" id="f-price" name="price_ht" value="<?= e($price) ?>" required>
            </div>
          </div>
        <?php else: ?>
          <div class="adm-field">
            <label for="f-category">Catégorie</label>
            <select id="f-category" name="category" required>
              <option value="">Choisir…</option>
              <?php foreach (VIB_CATEGORIES as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $cat === $k ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
      </div>
      <?php if (str_starts_with($error, 'Une fiche porte déjà')): ?>
        <label class="adm-check"><input type="checkbox" name="confirm_double" value="1"> C’est bien une autre fiche, la créer</label>
      <?php endif; ?>
      <p class="adm-hint">L’adresse de la fiche (ex. <?= e($col['public']) ?><?= $isLocal ? 'lot-50-duplex-commercial-90-m2' : 'cafe-du-port' ?>) est calculée à partir du <?= $isLocal ? 'titre' : 'nom' ?> et ne change plus ensuite. Le reste se complète à l’étape suivante.</p>
    </section>
    <div class="adm-actions"><button type="submit" class="adm-btn">Créer la fiche</button></div>
  </form>
<?php vib_admin_footer();
