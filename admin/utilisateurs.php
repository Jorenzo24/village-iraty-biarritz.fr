<?php
/**
 * Back-office VIB — comptes (administrateurs seulement) : inviter, renvoyer l'invitation, désactiver, supprimer.
 * Personne ne choisit le mot de passe d'un autre : la personne invitée le choisit elle-même via son lien.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/admin.php';
require __DIR__ . '/../lib/collections.php';
$user = vib_admin_user();
vib_require_admin($user);

$msg = ''; $error = ''; $link = '';
$roles = ['editeur' => 'Gestion des fiches', 'admin' => 'Administrateur (fiches et comptes)'];

$invite = function (string $email) use (&$msg, &$link): void {
    $t = vib_issue_token($email, 72 * 3600);
    if (!$t) return;
    $url = vib_site_url() . '/admin/mot-de-passe.php?jeton=' . $t;
    $ok = vib_mail($email, 'Votre accès au back-office du VILLAGE Iraty-BIARRITZ',
        "Bonjour,\n\nUn accès au back-office du site du VILLAGE Iraty-BIARRITZ a été créé pour vous.\nPour choisir votre mot de passe, ouvrez ce lien (valable 3 jours) :\n\n$url\n\nVous pourrez ensuite vous connecter sur " . vib_site_url() . "/admin/ avec votre adresse e-mail.\n");
    $msg = $ok ? "Invitation envoyée à $email." : "L’e-mail n’a pas pu partir. Transmettez ce lien à $email (valable 3 jours) :";
    if (!$ok) $link = $url;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    vib_csrf_check($user);
    $target = vib_norm_email((string) ($_POST['email'] ?? ''));
    $act = (string) ($_POST['action'] ?? '');
    try {
        if ($act === 'ajouter') {
            vib_user_add($target, (string) ($_POST['name'] ?? ''), (string) ($_POST['role'] ?? 'editeur'));
            $invite($target);
        } elseif ($target === $user['email'] && $act !== 'inviter') {
            $error = 'Vous ne pouvez pas modifier votre propre compte ici (passez par « Mon compte »).';
        } elseif ($act === 'inviter') {
            $invite($target);
        } elseif ($act === 'desactiver' || $act === 'reactiver') {
            vib_users(fn($all) => array_map(fn($x) => $x['email'] === $target ? ['disabled' => $act === 'desactiver'] + $x : $x, $all));
            $msg = $act === 'desactiver' ? "Compte $target désactivé : il ne peut plus se connecter." : "Compte $target réactivé.";
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
$btn = fn(string $email, string $action, string $label, string $cls = 'adm-btn--ghost') =>
    '<form method="post" action="/admin/utilisateurs.php"><input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="email" value="' . e($email) . '">'
    . '<input type="hidden" name="action" value="' . e($action) . '"><button type="submit" class="adm-btn ' . $cls . ' adm-btn--small">' . e($label) . '</button></form>';

vib_admin_header('Utilisateurs', $user, '');
?>
  <div>
    <h1>Utilisateurs</h1>
    <p class="adm-muted">Les personnes qui peuvent se connecter au back-office. Chacune choisit son mot de passe via le lien reçu par e-mail.</p>
  </div>
  <?php if ($msg): ?><div class="adm-flash adm-flash--ok" role="status"><?= e($msg) ?><?php if ($link): ?><br><code class="adm-code"><?= e($link) ?></code><?php endif; ?></div><?php endif; ?>
  <?php if ($error): ?><div class="adm-flash adm-flash--ko" role="alert"><?= e($error) ?></div><?php endif; ?>

  <ul class="adm-rows">
    <?php foreach ($users as $u): $self = $u['email'] === $user['email']; ?>
      <li><div class="adm-rowcard<?= !empty($u['disabled']) ? ' adm-rowcard--off' : '' ?>">
        <span class="adm-rowcard__img adm-rowcard__ph" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(($u['name'] ?? '') ?: $u['email'], 0, 1))) ?></span>
        <span class="adm-rowcard__main">
          <span class="adm-rowcard__name"><?= e(($u['name'] ?? '') ?: $u['email']) ?><?= $self ? ' (vous)' : '' ?></span>
          <span class="adm-rowcard__sub"><?= e($u['email']) ?> · <?= e($roles[$u['role'] ?? 'editeur'] ?? '') ?> ·
            <?= !empty($u['disabled']) ? 'désactivé' : (empty($u['hash']) ? 'invitation en attente' : (isset($u['last_login']) ? 'dernière connexion ' . e(vib_when($u['last_login'])) : 'jamais connecté')) ?></span>
        </span>
        <span class="adm-rowcard__side">
          <?php if (!$self): ?>
            <?php if (empty($u['hash']) && empty($u['disabled'])) echo $btn($u['email'], 'inviter', 'Renvoyer l’invitation'); ?>
            <?= !empty($u['disabled']) ? $btn($u['email'], 'reactiver', 'Réactiver') : $btn($u['email'], 'desactiver', 'Désactiver') ?>
            <?= $btn($u['email'], 'supprimer', 'Supprimer', 'adm-btn--ghost adm-btn--warn') ?>
          <?php endif; ?>
        </span>
      </div></li>
    <?php endforeach; ?>
  </ul>

  <form class="adm-form" method="post" action="/admin/utilisateurs.php">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="ajouter">
    <section class="adm-card">
      <h2>Inviter une personne</h2>
      <div class="adm-grid adm-grid--2">
        <div class="adm-field"><label for="f-name">Nom</label><input type="text" id="f-name" name="name" required></div>
        <div class="adm-field"><label for="f-email">Adresse e-mail</label><input type="email" id="f-email" name="email" required></div>
      </div>
      <div class="adm-field">
        <label for="f-role">Accès</label>
        <select id="f-role" name="role"><?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
      </div>
      <p class="adm-hint">La personne reçoit un e-mail avec un lien pour choisir son mot de passe (valable 3 jours).</p>
      <button type="submit" class="adm-btn">Envoyer l’invitation</button>
    </section>
  </form>
<?php vib_admin_footer();
