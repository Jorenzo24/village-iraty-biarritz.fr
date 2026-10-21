<?php
/** Back-office VIB — ajout d'une fiche acteur (nom + catégorie ; le reste se complète ensuite sur la fiche). */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/fields.php';
$user = vib_admin_user();

$store = new Store('entreprises');
$store->seedIfEmpty();
$name = trim((string) ($_POST['name'] ?? ''));
$cat = (string) ($_POST['category'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    if ($name === '') $error = 'Indiquez le nom de l’acteur.';
    elseif (!isset(VIB_CATEGORIES[$cat])) $error = 'Choisissez une catégorie.';
    else {
        $same = array_filter($store->read()['items'], fn($it) => vib_fold($it['name']) === vib_fold($name));
        if ($same && empty($_POST['confirm_double'])) {
            $error = 'Une fiche porte déjà ce nom : « ' . reset($same)['name'] . ' ». Cochez la case ci-dessous si c’est bien un autre acteur.';
        } else {
            // Identifiant calculé et enregistré sous le même verrou : deux ajouts simultanés ne peuvent pas prendre le même
            for ($try = 0; $try < 3; $try++) {
                $slug = vib_unique_slug($name, vib_taken_slugs($store));
                try {
                    $store->addItem(vib_actor_new($slug, $name, $cat), $user['email'], 'Ajout de la fiche « ' . $name . ' » (' . $slug . ')');
                    header('Location: /admin/acteur.php?slug=' . rawurlencode($slug) . '&ok=nouveau', true, 303);
                    exit;
                } catch (StoreConflict) { /* identifiant pris entre-temps : on recalcule */ }
            }
            $error = 'Création impossible, réessayez.';
        }
    }
}

vib_admin_header('Ajouter un acteur', $user, 'acteurs');
?>
  <p><a class="adm-back" href="/admin/acteurs.php">← Tous les acteurs</a></p>
  <h1>Ajouter un acteur</h1>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form class="adm-form" method="post" action="/admin/nouveau.php">
    <input type="hidden" name="csrf" value="<?= e(vib_csrf_token($user['email'])) ?>">
    <section class="adm-card">
      <div class="adm-grid adm-grid--2">
        <div class="adm-field">
          <label for="f-name">Nom</label>
          <input type="text" id="f-name" name="name" value="<?= e($name) ?>" required autofocus>
        </div>
        <div class="adm-field">
          <label for="f-category">Catégorie</label>
          <select id="f-category" name="category" required>
            <option value="">Choisir…</option>
            <?php foreach (VIB_CATEGORIES as $k => $label): ?>
              <option value="<?= e($k) ?>"<?= $cat === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php if (str_starts_with($error, 'Une fiche porte déjà')): ?>
        <label class="adm-check"><input type="checkbox" name="confirm_double" value="1"> C’est bien un autre acteur, créer la fiche</label>
      <?php endif; ?>
      <p class="adm-hint">L’adresse de la fiche (ex. /acteur/cafe-du-port) est calculée à partir du nom et ne change plus ensuite. Description, adresse, horaires et photos se complètent à l’étape suivante.</p>
    </section>
    <div class="adm-actions"><button type="submit" class="adm-btn">Créer la fiche</button></div>
  </form>
<?php vib_admin_footer();
