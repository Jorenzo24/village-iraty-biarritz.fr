<?php
/**
 * Back-office VIB — modification d'une fiche acteur (textes, adresse, contacts, réseaux).
 *
 * Sécurités :
 *  - le formulaire porte l'empreinte de la fiche affichée : si quelqu'un l'a modifiée entre-temps,
 *    l'enregistrement est refusé, et la saisie est conservée à l'écran (rien n'est perdu) ;
 *  - seuls les champs du formulaire sont touchés : horaires, photos et logo restent tels quels ;
 *  - après enregistrement, redirection (un rechargement de page ne renvoie pas le formulaire).
 */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/fields.php';
$user = vib_admin_user();

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

$values = vib_actor_form_values($item);
$hash = Store::itemHash($item);
$errors = [];
$conflict = null;   // fiche modifiée entre-temps : champs modifiés des deux côtés (libellé → valeur de l'autre personne)
$orig = $values;    // valeurs à l'ouverture du formulaire : permettent de savoir ce que l'utilisateur a touché

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    $sentOrig = json_decode((string) base64_decode((string) ($_POST['orig'] ?? ''), true), true);
    if (is_array($sentOrig) && !array_diff_key($values, $sentOrig)) $orig = $sentOrig;
    $mine = array_merge($values, array_intersect_key($_POST, $values));
    if (!is_array($mine['social'])) $mine['social'] = [];
    $mine['h'] = vib_hours_form_norm($mine['h']);
    $mine['address_mode'] = ($_POST['address_mode'] ?? '') === 'libre' ? 'libre' : 'structuree';   // case décochée = absente du formulaire
    $values = $mine;

    // Fiche modifiée entre-temps : fusion champ par champ, RIEN n'est enregistré, l'utilisateur vérifie
    $onConflict = function (array $fresh) use (&$values, &$orig, &$conflict, &$hash, $mine) {
        $m = vib_form_merge($orig, $mine, vib_actor_form_values($fresh));
        [$values, $conflict] = [$m['values'], $m['conflicts']];
        $orig = vib_actor_form_values($fresh);
        $hash = Store::itemHash($fresh);
    };

    if ((string) ($_POST['hash'] ?? '') !== $hash) {
        $onConflict($item);
    } else {
        $r = vib_actor_apply($item, $_POST);
        $errors = $r['errors'];
        if (!$errors && !$r['changed']) {
            header('Location: /admin/acteur.php?slug=' . rawurlencode($slug) . '&ok=0', true, 303);
            exit;
        }
        if (!$errors) {
            try {
                $store->updateItem($slug, $hash, fn(array $cur) => vib_actor_apply($cur, $_POST)['item'], $user['email'],
                    'Fiche « ' . $r['item']['name'] . ' » (' . $slug . ') : ' . implode(', ', $r['changed']));
                header('Location: /admin/acteur.php?slug=' . rawurlencode($slug) . '&ok=1', true, 303);
                exit;
            } catch (StoreConflict $ex) {
                $item = vib_find($store->read()['items'], $slug);
                if ($item === null) { http_response_code(409); exit('Cette fiche vient d’être retirée.'); }
                $onConflict($item);
            }
        }
    }
}

// Dernière modification de cette fiche (affichée après enregistrement et en bas de page)
$last = null;
foreach ($store->history(200) as $h) {
    if (str_contains((string) $h['message'], '(' . $slug . ')')) { $last = $h; break; }
}

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

  <?php if (($_GET['ok'] ?? '') === '1' && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
    <div class="adm-flash adm-flash--ok" role="status"><strong>Modifications enregistrées.</strong>
      <?= $last ? 'Champs modifiés : ' . e(preg_replace('/^.*\) : /u', '', $last['message'])) . '.' : '' ?>
      Elles sont déjà visibles sur <a href="/acteur/<?= e(rawurlencode($slug)) ?>" target="_blank" rel="noopener">la fiche en ligne</a>.</div>
  <?php elseif (($_GET['ok'] ?? '') === '0' && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
    <div class="adm-flash adm-flash--ok" role="status">Aucune modification : la fiche était déjà à jour.</div>
  <?php endif; ?>

  <?php if ($conflict !== null): ?>
    <div class="adm-flash adm-flash--ko" role="alert"><strong>Rien n’a encore été enregistré :</strong>
      <?= $last ? e($last['author']) . ' a modifié cette fiche le ' . e(date('d/m/Y à H:i', strtotime($last['at']))) : 'cette fiche a été modifiée' ?> pendant votre saisie.
      Ses modifications ont été reprises dans le formulaire, avec les vôtres.
      <?php if ($conflict): ?>
        <br>Vous avez tous les deux modifié <?= count($conflict) > 1 ? 'les mêmes champs' : 'le même champ' ?> ; c’est votre version qui est affichée. Sa version :
        <ul><?php foreach ($conflict as $label => $val): ?><li><strong><?= e($label) ?></strong> : <?= e($val !== '' ? mb_strimwidth($val, 0, 160, '…') : '(vide)') ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      Vérifiez, puis cliquez sur « Enregistrer ».
    </div>
  <?php elseif ($errors): ?>
    <div class="adm-flash adm-flash--ko" role="alert"><strong>Rien n’a été enregistré</strong>, merci de corriger :
      <ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form class="adm-form" method="post" action="/admin/acteur.php" id="adm-form" novalidate<?= $_SERVER['REQUEST_METHOD'] === 'POST' ? ' data-unsaved' : '' ?>>
    <input type="hidden" name="csrf" value="<?= e(vib_csrf_token($user['email'])) ?>">
    <input type="hidden" name="slug" value="<?= e($slug) ?>">
    <input type="hidden" name="hash" value="<?= e($hash) ?>">
    <input type="hidden" name="orig" value="<?= e(base64_encode(json_encode($orig, JSON_UNESCAPED_UNICODE))) ?>">

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

    <section class="adm-card">
      <h2>Photos et logo</h2>
      <p class="adm-hint">Modifiables dans une prochaine version du back-office.</p>
      <?php if ($item['photos'] || $item['logo']): ?>
        <div class="adm-thumbs">
          <?php foreach (array_filter(array_merge($item['photos'], [$item['logo']])) as $src): ?>
            <img src="<?= e($src) ?>" alt="" loading="lazy">
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="adm-muted">Aucune photo.</p>
      <?php endif; ?>
    </section>

    <div class="adm-actions">
      <button type="submit" class="adm-btn">Enregistrer</button>
      <a class="adm-btn adm-btn--ghost" href="/admin/acteur.php?slug=<?= e(rawurlencode($slug)) ?>">Annuler</a>
      <span class="adm-dirty" id="adm-dirty" hidden>Modifications non enregistrées</span>
    </div>
  </form>

  <p class="adm-muted">
    <?= $last ? 'Dernière modification le ' . e(date('d/m/Y à H:i', strtotime($last['at']))) . ' par ' . e($last['author']) . '.' : 'Aucune modification depuis la mise en place du back-office.' ?>
    Le nom et la catégorie de la carte dans l’annuaire seront reliés à cette fiche dans une prochaine version.
  </p>
<?php vib_admin_footer('/admin/admin.js?v=20261021b');
