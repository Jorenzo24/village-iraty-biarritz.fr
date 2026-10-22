<?php
/**
 * Back-office VIB — mot de passe oublié (demande d'un lien) et choix du mot de passe (lien reçu par e-mail,
 * oubli ou invitation). La réponse à une demande est la même que le compte existe ou non.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';

$tok = (string) ($_POST['jeton'] ?? $_GET['jeton'] ?? '');
$error = '';
$sent = false;

if ($tok !== '') {
    $u = vib_token_user($tok);
    if ($u && $_SERVER['REQUEST_METHOD'] === 'POST') {
        vib_same_origin_or_die();
        $pw = (string) ($_POST['password'] ?? '');
        $error = (string) vib_password_problem($pw, (string) ($_POST['confirm'] ?? ''));
        if ($error === '') {
            vib_set_password($u['email'], $pw);
            header('Location: /admin/connexion.php?ok=mdp', true, 303);
            exit;
        }
    }
    vib_auth_header('Choisir un mot de passe');
    if (!$u): ?>
  <h1>Lien expiré</h1>
  <div class="adm-flash adm-flash--ko" role="alert">Ce lien n’est plus valable (déjà utilisé, ou trop ancien).</div>
  <p class="adm-auth__alt"><a href="/admin/mot-de-passe.php">Recevoir un nouveau lien</a></p>
<?php else: ?>
  <h1><?= empty($u['hash']) ? 'Bienvenue' : 'Nouveau mot de passe' ?></h1>
  <p class="adm-muted">Compte <strong><?= e($u['email']) ?></strong>. Choisissez votre mot de passe (<?= VIB_PW_MIN ?> caractères minimum).</p>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form class="adm-form" method="post" action="/admin/mot-de-passe.php">
    <input type="hidden" name="jeton" value="<?= e($tok) ?>">
    <input type="text" name="username" value="<?= e($u['email']) ?>" autocomplete="username" hidden>
    <div class="adm-field">
      <label for="f-password">Mot de passe</label>
      <input type="password" id="f-password" name="password" autocomplete="new-password" minlength="<?= VIB_PW_MIN ?>" required autofocus>
    </div>
    <div class="adm-field">
      <label for="f-confirm">Confirmer le mot de passe</label>
      <input type="password" id="f-confirm" name="confirm" autocomplete="new-password" minlength="<?= VIB_PW_MIN ?>" required>
    </div>
    <button type="submit" class="adm-btn adm-auth__submit">Enregistrer</button>
  </form>
<?php endif;
    vib_auth_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_same_origin_or_die();
    $email = vib_norm_email((string) ($_POST['email'] ?? ''));
    // Limite les demandes (même compteur que les échecs de connexion) ; la réponse ne dit jamais si le compte existe
    if (vib_throttle($email) === 0) {
        vib_throttle($email, true);
        $t = vib_issue_token($email, 3600);
        if ($t) vib_mail($email, 'Back-office VIB : nouveau mot de passe',
            "Bonjour,\n\nPour choisir un nouveau mot de passe pour le back-office du VILLAGE Iraty-BIARRITZ, ouvrez ce lien (valable 1 heure) :\n\n"
            . vib_site_url() . '/admin/mot-de-passe.php?jeton=' . $t . "\n\nSi vous n’êtes pas à l’origine de cette demande, ignorez ce message : votre mot de passe actuel reste valable.\n");
    }
    $sent = true;
}

vib_auth_header('Mot de passe oublié');
?>
  <h1>Mot de passe oublié</h1>
  <?php if ($sent): ?>
    <div class="adm-flash adm-flash--ok" role="status">Si un compte existe pour cette adresse, un e-mail vient d’être envoyé avec un lien pour choisir un nouveau mot de passe (valable 1 heure).</div>
    <p class="adm-auth__alt"><a href="/admin/connexion.php">Retour à la connexion</a></p>
  <?php else: ?>
    <p class="adm-muted">Indiquez votre adresse e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
    <form class="adm-form" method="post" action="/admin/mot-de-passe.php">
      <div class="adm-field">
        <label for="f-email">Adresse e-mail</label>
        <input type="email" id="f-email" name="email" autocomplete="username" required autofocus>
      </div>
      <button type="submit" class="adm-btn adm-auth__submit">Recevoir le lien</button>
      <p class="adm-auth__alt"><a href="/admin/connexion.php">Retour à la connexion</a></p>
    </form>
  <?php endif; ?>
<?php vib_auth_footer();
