<?php
/** Back-office VIB — accueil : actions courantes, chiffres clés, dernières modifications, fiches à compléter. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();

$status = [];
$items = [];
$log = [];
foreach (['entreprises', 'locaux'] as $c) {
    try {
        $s = new Store($c);
        $seeded = $s->seedIfEmpty();
        $r = $s->read();
        $items[$c] = $r['items'];
        $last = $s->history(1)[0] ?? null;
        $status[$c] = ['ok' => true, 'count' => count($r['items']), 'version' => $r['version'], 'seeded' => $seeded, 'last' => $last];
        foreach ($s->history(40) as $e) $log[] = ['c' => $c] + $e;
    } catch (Throwable $ex) {
        $status[$c] = ['ok' => false, 'error' => $ex->getMessage()];
        $items[$c] = [];
    }
}

if (($_GET['format'] ?? '') === 'json') {   // utilisé par les tests automatiques
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['user' => $user, 'env' => vib_env(), 'collections' => $status], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Dernières modifications, acteurs et locaux confondus (hors opérations techniques du système)
$log = array_filter($log, fn($e) => ($e['author'] ?? '') !== 'système');
usort($log, fn($a, $b) => strcmp($b['at'], $a['at']));
$log = array_slice($log, 0, 8);
$names = [];
foreach ($items as $c => $list) foreach ($list as $it) $names[$c][$it['slug']] = $it['name'];

// Fiches à compléter : les plus incomplètes d'abord
$todo = [];
foreach ($items as $c => $list) foreach ($list as $it) {
    $g = vib_item_gaps($c, $it);
    if ($g) $todo[] = ['c' => $c, 'it' => $it, 'gaps' => $g];
}
usort($todo, fn($a, $b) => count($b['gaps']) <=> count($a['gaps']) ?: strcmp($a['it']['name'], $b['it']['name']));
$cats = array_count_values(array_column($items['entreprises'], 'category'));

vib_admin_header('Accueil', $user, 'accueil', true);
?>
  <section class="adm-hero">
    <div>
      <p class="adm-eyebrow"><?= e(vib_date_fr()) ?></p>
      <h1>Bonjour</h1>
      <p class="adm-muted">Les fiches du VILLAGE, à jour sur le site dès l’enregistrement.</p>
    </div>
    <div class="adm-hero__actions">
      <a class="adm-btn" href="/admin/nouveau.php?c=entreprises">+ Ajouter un acteur</a>
      <a class="adm-btn adm-btn--ghost" href="/admin/nouveau.php?c=locaux">+ Ajouter un local</a>
      <a class="adm-btn adm-btn--ghost" href="/" target="_blank" rel="noopener">Voir le site ↗</a>
    </div>
  </section>

  <div class="adm-stats">
    <a class="adm-stat" href="/admin/acteurs.php">
      <span class="adm-stat__label">Acteurs</span>
      <span class="adm-stat__value"><?= count($items['entreprises']) ?></span>
      <span class="adm-stat__meta">dans <?= count($cats) ?> catégories</span>
    </a>
    <a class="adm-stat" href="/admin/locaux.php">
      <span class="adm-stat__label">Locaux à louer</span>
      <span class="adm-stat__value"><?= count($items['locaux']) ?></span>
      <span class="adm-stat__meta"><?= $items['locaux'] ? e(vib_num_to_input(min(array_column($items['locaux'], 'surface'))) . ' à ' . vib_num_to_input(max(array_column($items['locaux'], 'surface'))) . ' m²') : 'Aucun local disponible' ?></span>
    </a>
    <a class="adm-stat<?= $todo ? ' adm-stat--todo' : '' ?>" href="#a-completer">
      <span class="adm-stat__label">Fiches à compléter</span>
      <span class="adm-stat__value"><?= count($todo) ?></span>
      <span class="adm-stat__meta"><?= $todo ? 'Photo, description, horaires…' : 'Tout est complet' ?></span>
    </a>
  </div>

  <div class="adm-dash">
    <section class="adm-card">
      <div class="adm-card__head">
        <h2>Dernières modifications</h2>
        <a class="adm-more" href="/admin/historique.php">Tout l’historique →</a>
      </div>
      <?php if (!$log): ?>
        <p class="adm-muted">Aucune modification pour l’instant. Elles apparaîtront ici, avec leur auteur.</p>
      <?php else: ?>
        <ol class="adm-feed">
          <?php foreach ($log as $en):
            $c = $en['c'];
            $k = $en['key'] ?? (preg_match('/\(([a-z0-9-]+)\)/', (string) $en['message'], $m) ? $m[1] : null);
            $msg = preg_replace(['/^Fiche « .* » \([a-z0-9-]+\) : /u', '/ \([a-z0-9-]+\)/'], ['', ''], (string) $en['message']);
            $name = $k !== null ? ($names[$c][$k] ?? null) : null;
            $href = $name !== null ? vib_admin_collection($c)['edit'] . '?slug=' . rawurlencode($k) : null; ?>
            <li>
              <span class="adm-feed__dot adm-feed__dot--<?= e($c) ?>" aria-hidden="true"></span>
              <div>
                <p><?php if ($href): ?><a href="<?= e($href) ?>"><strong><?= e($name) ?></strong></a> · <?= e($msg) ?><?php else: ?><?= e($msg) ?><?php endif; ?></p>
                <p class="adm-feed__meta"><?= e(vib_when((string) $en['at'])) ?> · <?= e($en['author']) ?></p>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>

    <section class="adm-card" id="a-completer">
      <div class="adm-card__head">
        <h2>Fiches à compléter</h2>
        <span class="adm-muted"><?= count($todo) ?></span>
      </div>
      <?php if (!$todo): ?>
        <p class="adm-muted">Toutes les fiches ont une photo, une description et leurs informations principales.</p>
      <?php else: ?>
        <ul class="adm-todo">
          <?php foreach (array_slice($todo, 0, 12) as $t): $it = $t['it']; ?>
            <li>
              <a href="<?= e(vib_admin_collection($t['c'])['edit'] . '?slug=' . rawurlencode($it['slug'])) ?>">
                <?php if (!empty($it['photos'])): ?><img src="<?= e($it['photos'][0]) ?>" alt="" loading="lazy"><?php else: ?><span class="adm-todo__ph" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($it['name'], 0, 1))) ?></span><?php endif; ?>
                <span class="adm-todo__name"><?= e($it['name']) ?></span>
                <span class="adm-todo__gaps"><?php foreach ($t['gaps'] as $g): ?><span class="adm-chip"><?= e($g) ?></span><?php endforeach; ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if (count($todo) > 12): ?><p class="adm-muted">… et <?= count($todo) - 12 ?> autres.</p><?php endif; ?>
        <p class="adm-hint">Indicatif : une fiche sans horaires peut l’être volontairement (activité sur rendez-vous ou sur commande).</p>
      <?php endif; ?>
    </section>
  </div>

  <details class="adm-tech">
    <summary>État technique</summary>
    <?php foreach ($status as $c => $st): ?>
      <p class="adm-muted"><strong><?= e($c) ?></strong> :
        <?= $st['ok'] ? (int) $st['count'] . ' fiches, version ' . e($st['version']) . ($st['last'] ? ', dernière écriture ' . e(vib_when((string) $st['last']['at'])) : '') : '<span class="adm-error">erreur : ' . e($st['error']) . '</span>' ?>
      </p>
    <?php endforeach; ?>
    <p class="adm-muted">Environnement : <?= e(vib_env()) ?></p>
  </details>
<?php vib_admin_footer();
