<?php
/** Back-office VIB — page d'accueil (étape 1 : vérification de la chaîne d'accès et du coffre de données). */

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
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Back-office - VILLAGE Iraty-BIARRITZ</title>
<link rel="stylesheet" href="/assets-2026/css/design-system.css">
<style>
  body { margin: 0; background: var(--color-bg-alt); color: var(--color-text); font: 16px/1.5 var(--font-body); }
  main { max-width: 760px; margin: 0 auto; padding: var(--space-7) var(--space-4); display: grid; gap: var(--space-5); }
  h1 { font-family: var(--font-display); margin: 0; }
  .card { background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-5); }
  .muted { color: var(--color-text-muted); font-size: var(--fs-small); }
  .ok { color: var(--color-success); font-weight: 600; } .ko { color: var(--color-accent); font-weight: 600; }
</style>
</head>
<body>
<main>
  <h1>Back-office</h1>
  <p>Bonjour <strong><?= e($user['email']) ?></strong>.</p>
  <div class="card">
    <p class="muted">Environnement : <?= e(vib_env()) ?></p>
    <?php foreach ($status as $c => $st): ?>
      <p><strong><?= e($c) ?></strong> :
      <?php if ($st['ok']): ?>
        <span class="ok"><?= (int) $st['count'] ?> fiches</span>
        <span class="muted">— version <?= e($st['version']) ?><?= $st['last'] ? ', dernière modification le ' . e($st['last']['at']) . ' par ' . e($st['last']['author']) : '' ?></span>
      <?php else: ?>
        <span class="ko">erreur : <?= e($st['error']) ?></span>
      <?php endif; ?>
      </p>
    <?php endforeach; ?>
  </div>
  <p class="muted">Étape 1 : la porte d'entrée et le coffre des données fonctionnent. Les écrans de gestion arrivent aux étapes suivantes.</p>
</main>
</body>
</html>
