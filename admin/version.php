<?php
/** Back-office VIB — ancienne version d'une fiche acteur : comparaison avec l'actuelle, et retour à cette version. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/fields.php';
$user = vib_admin_user();

$store = new Store('entreprises');
$slug = (string) ($_POST['slug'] ?? $_GET['slug'] ?? '');
$id = (string) ($_POST['id'] ?? $_GET['id'] ?? '');
$cur = vib_find($store->read()['items'], $slug);
$old = null; $entry = null;
foreach ($store->itemHistory($slug, 200) as $hv) if ($hv['entry']['id'] === $id) { $old = $hv['item']; $entry = $hv['entry']; break; }

if (!$cur || !$old) {
    vib_admin_header('Version introuvable', $user, 'acteurs');
    http_response_code(404);
    echo '<p><a class="adm-back" href="/admin/acteurs.php">← Tous les acteurs</a></p><div class="adm-flash adm-flash--ko">Version introuvable (ou fiche retirée).</div>';
    vib_admin_footer();
    exit;
}
$when = date('d/m/Y à H:i', strtotime($entry['at']));
$changed = [];
foreach (VIB_ACTOR_LABELS as $k => $label) if (($cur[$k] ?? null) != ($old[$k] ?? null) || ($k === 'social' && array_keys((array) ($cur[$k] ?? [])) !== array_keys((array) ($old[$k] ?? [])))) $changed[$k] = $label;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    if (!$changed) { header('Location: /admin/acteur.php?slug=' . rawurlencode($slug) . '&ok=0', true, 303); exit; }
    try {
        $store->updateItem($slug, (string) ($_POST['hash'] ?? ''), fn(array $c) => $old, $user['email'],
            'Fiche « ' . $old['name'] . ' » (' . $slug . ') : retour à la version du ' . $when . ' (' . implode(', ', $changed) . ')');
        header('Location: /admin/acteur.php?slug=' . rawurlencode($slug) . '&ok=restaure', true, 303);
        exit;
    } catch (StoreConflict $ex) {
        $error = $ex->getMessage();
        $cur = vib_find($store->read()['items'], $slug) ?? $cur;
    }
}

$show = function (string $k, array $it): string {
    $v = $it[$k] ?? '';
    if ($k === 'photos' || $k === 'logo') {
        $imgs = array_filter((array) $v);
        return $imgs ? '<span class="adm-thumbs">' . implode('', array_map(fn($s) => '<img src="' . e($s) . '" alt="">', $imgs)) . '</span>' : '<span class="adm-muted">(aucun)</span>';
    }
    if ($k === 'category') return e(VIB_CATEGORIES[$v] ?? $v);
    if (is_array($v)) $v = implode("\n", $v);
    return $v === '' ? '<span class="adm-muted">(vide)</span>' : nl2br(e($v));
};

vib_admin_header('Version du ' . $when, $user, 'acteurs');
?>
  <p><a class="adm-back" href="/admin/acteur.php?slug=<?= e(rawurlencode($slug)) ?>#historique">← Retour à la fiche</a></p>
  <div>
    <h1><?= e($cur['name']) ?> : version du <?= e($when) ?></h1>
    <p class="adm-muted"><?= ($entry['base'] ?? '') === '' ? 'Version de départ, à la mise en place du back-office.' : 'Enregistrée par ' . e($entry['author']) . '.' ?> <?= $changed ? 'Différences avec la version actuelle : ' . e(implode(', ', $changed)) . '.' : 'Identique à la version actuelle.' ?></p>
  </div>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>
  <div class="adm-card adm-compare">
    <div class="adm-compare__row adm-compare__head"><span></span><span>Cette version</span><span>Version actuelle</span></div>
    <?php foreach (VIB_ACTOR_LABELS as $k => $label): ?>
      <div class="adm-compare__row<?= isset($changed[$k]) ? ' is-diff' : '' ?>">
        <span class="adm-label"><?= e(ucfirst($label)) ?></span>
        <span><?= $show($k, $old) ?></span>
        <span><?= $show($k, $cur) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($changed): ?>
    <form method="post" action="/admin/version.php" class="adm-actions">
      <input type="hidden" name="csrf" value="<?= e(vib_csrf_token($user['email'])) ?>">
      <input type="hidden" name="slug" value="<?= e($slug) ?>">
      <input type="hidden" name="id" value="<?= e($id) ?>">
      <input type="hidden" name="hash" value="<?= e(Store::itemHash($cur)) ?>">
      <button type="submit" class="adm-btn">Revenir à cette version</button>
      <span class="adm-hint">La fiche reprend tout le contenu de gauche. Ce retour en arrière pourra lui-même être annulé.</span>
    </form>
  <?php endif; ?>
<?php vib_admin_footer();
