<?php
/**
 * Back-office VIB — comptes : ajouter, changer un mot de passe, envoyer un lien, supprimer.
 * Pas de rôles : toute personne connectée gère tous les comptes (équipe restreinte et de confiance).
 */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();

$msg = ''; $error = ''; $link = '';

/** Envoie un lien pour choisir son mot de passe ; en cas d'échec de l'e-mail, le lien s'affiche pour être transmis. */
$sendLink = function (string $email, bool $invite) use (&$msg, &$link): void {
    $t = vib_issue_token($email, $invite ? 72 * 3600 : 24 * 3600);
    if (!$t) return;
    $url = vib_site_url() . '/admin/mot-de-passe.php?jeton=' . $t;
    $ok = vib_mail($email, $invite ? 'Votre accès au back-office du VILLAGE Iraty-BIARRITZ' : 'Back-office VIB : choisir un nouveau mot de passe',
        "Bonjour,\n\n" . ($invite ? "Un accès au back-office du site du VILLAGE Iraty-BIARRITZ a été créé pour vous.\n" : '')
        . "Pour choisir votre mot de passe, ouvrez ce lien (valable " . ($invite ? '3 jours' : '24 heures') . ") :\n\n$url\n\n"
        . "Vous pourrez ensuite vous connecter sur " . vib_site_url() . "/admin/ avec votre adresse e-mail.\n");
    $msg = $ok ? "E-mail envoyé à $email." : "L’e-mail n’a pas pu partir. Transmettez ce lien à $email (valable " . ($invite ? '3 jours' : '24 heures') . ') :';
    if (!$ok) $link = $url;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    $target = vib_norm_email((string) ($_POST['email'] ?? ''));
    $act = (string) ($_POST['action'] ?? '');
    $pw = (string) ($_POST['password'] ?? '');
    try {
        if ($act === 'ajouter') {
            if ($pw !== '' && ($p = vib_password_problem($pw, (string) ($_POST['confirm'] ?? '')))) throw new AuthError($p);
            vib_user_add($target, (string) ($_POST['name'] ?? ''), 'admin');
            if ($pw !== '') { vib_set_password($target, $pw); $msg = "Compte $target créé, avec le mot de passe que vous avez choisi. La personne pourra le changer dans « Mon compte »."; }
            else $sendLink($target, true);
        } elseif ($target === $user['email']) {
            $error = 'Pour votre propre compte, passez par « Mon compte ».';
        } elseif (!vib_user_find($target)) {
            $error = 'Ce compte n’existe plus.';
        } elseif ($act === 'mot-de-passe') {
            if ($p = vib_password_problem($pw, (string) ($_POST['confirm'] ?? ''))) throw new AuthError($p);
            vib_set_password($target, $pw);
            $msg = "Mot de passe de $target modifié. Ses sessions ouvertes ont été fermées.";
        } elseif ($act === 'lien') {
            $sendLink($target, empty(vib_user_find($target)['hash']));
        } elseif ($act === 'supprimer') {
            vib_users(fn($all) => array_values(array_filter($all, fn($x) => $x['email'] !== $target)));
            $msg = "Compte $target supprimé.";
        }
    } catch (AuthError $ex) {
        $error = $ex->getMessage();
    }
}
$users = vib_users();
$csrf = vib_csrf_token($user['email']);
$hidden = fn(string $email, string $action) => '<input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="email" value="' . e($email) . '"><input type="hidden" name="action" value="' . e($action) . '">';

vib_admin_header('Utilisateurs', $user, '');
?>
  <div>
    <h1>Utilisateurs</h1>
    <p class="adm-muted">Les personnes qui peuvent se connecter au back-office.</p>
  </div>
  <?php if ($msg): ?><div class="adm-flash adm-flash--ok" role="status"><?= e($msg) ?><?php if ($link): ?><br><code class="adm-code"><?= e($link) ?></code><?php endif; ?></div><?php endif; ?>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>

  <ul class="adm-rows">
    <?php foreach ($users as $u): $self = $u['email'] === $user['email']; ?>
      <li><div class="adm-rowcard adm-user">
        <span class="adm-rowcard__img adm-rowcard__ph" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(($u['name'] ?? '') ?: $u['email'], 0, 1))) ?></span>
        <span class="adm-rowcard__main">
          <span class="adm-rowcard__name"><?= e(($u['name'] ?? '') ?: $u['email']) ?><?= $self ? ' (vous)' : '' ?></span>
          <span class="adm-rowcard__sub"><?= e($u['email']) ?> ·
            <?= empty($u['hash']) ? 'mot de passe pas encore choisi' : (isset($u['last_login']) ? 'dernière connexion ' . e(vib_when($u['last_login'])) : 'jamais connecté') ?></span>
        </span>
        <span class="adm-rowcard__side">
          <?php if ($self): ?>
            <a class="adm-btn adm-btn--ghost adm-btn--small" href="/admin/compte.php">Mon compte</a>
          <?php else: ?>
            <details class="adm-pop">
              <summary class="adm-btn adm-btn--ghost adm-btn--small">Changer le mot de passe</summary>
              <form method="post" action="/admin/utilisateurs.php" class="adm-pop__body">
                <?= $hidden($u['email'], 'mot-de-passe') ?>
                <input type="text" name="username" value="<?= e($u['email']) ?>" autocomplete="off" hidden>
                <label>Nouveau mot de passe<input type="password" name="password" autocomplete="new-password" minlength="<?= VIB_PW_MIN ?>" required></label>
                <label>Confirmer<input type="password" name="confirm" autocomplete="new-password" minlength="<?= VIB_PW_MIN ?>" required></label>
                <button type="submit" class="adm-btn adm-btn--small">Enregistrer</button>
              </form>
            </details>
            <form method="post" action="/admin/utilisateurs.php"><?= $hidden($u['email'], 'lien') ?><button type="submit" class="adm-btn adm-btn--ghost adm-btn--small"><?= empty($u['hash']) ? 'Renvoyer l’invitation' : 'Envoyer un lien' ?></button></form>
            <form method="post" action="/admin/utilisateurs.php" onsubmit="return confirm('Supprimer le compte <?= e($u['email']) ?> ?')"><?= $hidden($u['email'], 'supprimer') ?><button type="submit" class="adm-btn adm-btn--ghost adm-btn--small adm-btn--warn">Supprimer</button></form>
          <?php endif; ?>
        </span>
      </div></li>
    <?php endforeach; ?>
  </ul>

  <form class="adm-form" method="post" action="/admin/utilisateurs.php">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="ajouter">
    <section class="adm-card">
      <h2>Ajouter un compte</h2>
      <div class="adm-grid adm-grid--2">
        <div class="adm-field"><label for="f-name">Nom</label><input type="text" id="f-name" name="name" required></div>
        <div class="adm-field"><label for="f-email">Adresse e-mail</label><input type="email" id="f-email" name="email" autocomplete="off" required></div>
      </div>
      <div class="adm-grid adm-grid--2">
        <div class="adm-field"><label for="f-pw">Mot de passe <span class="adm-hint">(facultatif)</span></label><input type="password" id="f-pw" name="password" autocomplete="new-password" minlength="<?= VIB_PW_MIN ?>"></div>
        <div class="adm-field"><label for="f-pw2">Confirmer</label><input type="password" id="f-pw2" name="confirm" autocomplete="new-password" minlength="<?= VIB_PW_MIN ?>"></div>
      </div>
      <p class="adm-hint">Sans mot de passe, la personne reçoit un e-mail pour choisir le sien (lien valable 3 jours). Avec un mot de passe, le compte est utilisable tout de suite : transmettez-le-lui, elle pourra le changer dans « Mon compte ».</p>
      <button type="submit" class="adm-btn">Ajouter le compte</button>
    </section>
  </form>
<?php vib_admin_footer();
