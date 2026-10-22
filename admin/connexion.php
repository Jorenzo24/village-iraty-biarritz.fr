<?php
/** Back-office VIB — connexion (adresse e-mail + mot de passe). */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';

$retour = (string) ($_POST['retour'] ?? $_GET['retour'] ?? '/admin/');
if (!preg_match('#^/admin/[^\s]*$#', $retour) || str_starts_with($retour, '/admin/connexion')) $retour = '/admin/';
if (vib_session_user()) { header('Location: ' . $retour, true, 303); exit; }

$email = trim((string) ($_POST['email'] ?? ''));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_same_origin_or_die();
    try {
        vib_login($email, (string) ($_POST['password'] ?? ''));
        header('Location: ' . $retour, true, 303);
        exit;
    } catch (AuthError $ex) {
        $error = $ex->getMessage();
    }
}
$ok = (string) ($_GET['ok'] ?? '');

vib_auth_header('Connexion');
?>
  <h1>Connexion</h1>
  <?php if ($ok === 'deconnecte'): ?><div class="adm-flash adm-flash--ok" role="status">Vous êtes déconnecté.</div><?php endif; ?>
  <?php if ($ok === 'mdp'): ?><div class="adm-flash adm-flash--ok" role="status">Mot de passe enregistré. Vous pouvez vous connecter.</div><?php endif; ?>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form class="adm-form" method="post" action="/admin/connexion.php">
    <input type="hidden" name="retour" value="<?= e($retour) ?>">
    <div class="adm-field">
      <label for="f-email">Adresse e-mail</label>
      <input type="email" id="f-email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
    </div>
    <div class="adm-field">
      <label for="f-password">Mot de passe</label>
      <input type="password" id="f-password" name="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="adm-btn adm-auth__submit">Se connecter</button>
    <p class="adm-auth__alt"><a href="/admin/mot-de-passe.php">Mot de passe oublié ?</a></p>
  </form>
<?php vib_auth_footer();
