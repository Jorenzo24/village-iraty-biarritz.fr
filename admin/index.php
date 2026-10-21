<?php
/** Back-office VIB — page d’accueil : accès aux rubriques et état du coffre de données. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
$user = vib_admin_user();

$status = [];
foreach (array_keys(vib_config()['collections']) as $c) {
    try {
        $s = new Store($c);
        $seeded = $s->seedIfEmpty();
        $r = $s->read();
        $last = $s->history(1)[0] ?? null;
        $status[$c] = ['ok' => true, 'count' => count($r['items']), 'version' => $r['version'], 'seeded' => $seeded, 'last' => $last];
    } catch (Throwable $ex) {
        $status[$c] = ['ok' => false, 'error' => $ex->getMessage()];
    }
}

if (($_GET['format'] ?? '') === 'json') {   // utilisé par les tests automatiques
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['user' => $user, 'env' => vib_env(), 'collections' => $status], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
vib_admin_header('Accueil', $user, 'accueil');
$st = $status['entreprises'];
?>
  <h1>Bonjour</h1>
  <div class="adm-tiles">
    <a class="adm-card adm-tile" href="/admin/acteurs.php">
      <strong>Acteurs</strong>
      <span class="adm-muted"><?= $st['ok'] ? (int) $st['count'] . ' fiches · modifier une fiche' : 'indisponible' ?></span>
    </a>
    <a class="adm-card adm-tile" href="/admin/locaux.php">
      <strong>Locaux à louer</strong>
      <span class="adm-muted"><?= $status['locaux']['ok'] ? (int) $status['locaux']['count'] . ' locaux · modifier une annonce' : 'indisponible' ?></span>
    </a>
  </div>
  <div class="adm-card">
    <h2>État des données</h2>
    <?php foreach ($status as $c => $st): ?>
      <p class="adm-muted"><strong><?= e($c) ?></strong> :
      <?php if ($st['ok']): ?>
        <?= (int) $st['count'] ?> fiches<?= $st['last'] ? ', dernière modification le ' . e(date('d/m/Y à H:i', strtotime($st['last']['at']))) . ' par ' . e($st['last']['author']) : '' ?>.
      <?php else: ?>
        <span class="adm-error">erreur : <?= e($st['error']) ?></span>
      <?php endif; ?>
      </p>
    <?php endforeach; ?>
  </div>
<?php vib_admin_footer();
