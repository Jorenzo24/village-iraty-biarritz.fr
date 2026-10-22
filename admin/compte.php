<?php
/** Back-office VIB — mon compte : changer son mot de passe. */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
$user = vib_admin_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    $u = vib_user_find($user['email']);
    $pw = (string) ($_POST['password'] ?? '');
    if (!$u || !password_verify((string) ($_POST['current'] ?? ''), (string) $u['hash'])) $error = 'Mot de passe actuel incorrect.';
    else $error = (string) vib_password_problem($pw, (string) ($_POST['confirm'] ?? ''));
    if ($error === '') {
        vib_set_password($user['email'], $pw);
        $_SESSION['since'] = time();   // cette session reste ouverte, les autres sont fermées
        header('Location: /admin/compte.php?ok=1', true, 303);
        exit;
    }
}

vib_admin_header('Mon compte', $user, '');
?>
  <div>
    <h1>Mon compte</h1>
    <p class="adm-muted"><?= e($user['name'] ?: $user['email']) ?> · <?= e($user['email']) ?></p>
  </div>
  <?php if (($_GET['ok'] ?? '') === '1'): ?><div class="adm-flash adm-flash--ok" role="status">Mot de passe modifié. Vos autres sessions ouvertes (autre ordinateur, téléphone) ont été fermées.</div><?php endif; ?>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form class="adm-form" method="post" action="/admin/compte.php">
    <input type="hidden" name="csrf" value="<?= e(vib_csrf_token($user['email'])) ?>">
    <input type="text" name="username" value="<?= e($user['email']) ?>" autocomplete="username" hidden>
    <section class="adm-card">
      <h2>Changer de mot de passe</h2>
      <div class="adm-field">
        <label for="f-current">Mot de passe actuel</label>
        <input type="password" id="f-current" name="current" autocomplete="current-password" required>
      </div>
      <div class="adm-grid adm-grid--2">
        <div class="adm-field">
          <label for="f-password">Nouveau mot de passe</label>
          <input type="password" id="f-password" name="password" autocomplete="new-password" minlength="<?= VIB_PW_MIN ?>" required>
        </div>
        <div class="adm-field">
          <label for="f-confirm">Confirmer</label>
          <input type="password" id="f-confirm" name="confirm" autocomplete="new-password" minlength="<?= VIB_PW_MIN ?>" required>
        </div>
      </div>
      <p class="adm-hint"><?= VIB_PW_MIN ?> caractères minimum. Une phrase courte est plus sûre et plus facile à retenir qu’un mot compliqué.</p>
    </section>
    <div class="adm-actions"><button type="submit" class="adm-btn">Enregistrer</button></div>
  </form>
<?php vib_admin_footer();
