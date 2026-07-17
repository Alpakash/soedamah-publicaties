<?php
require __DIR__ . '/../../app/bootstrap.php';
require APP_ROOT . '/app/auth.php';

admin_session_start();
if (admin_logged_in()) {
    redirect(url('admin/'));
}

$hash = setting_get('admin_password_hash');
$setupMode = ($hash === null);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($setupMode) {
        $pw1 = (string) ($_POST['password'] ?? '');
        $pw2 = (string) ($_POST['password2'] ?? '');
        if (strlen($pw1) < 10) {
            $error = 'Kies een wachtwoord van minimaal 10 tekens.';
        } elseif ($pw1 !== $pw2) {
            $error = 'De twee wachtwoorden zijn niet gelijk.';
        } else {
            setting_set('admin_password_hash', password_hash($pw1, PASSWORD_DEFAULT));
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            log_msg('Beheerwachtwoord ingesteld.');
            redirect(url('admin/'));
        }
    } else {
        $blocked = login_blocked_seconds();
        if ($blocked > 0) {
            $error = 'Te veel mislukte pogingen. Probeer het over ' . (int) ceil($blocked / 60) . ' minuten opnieuw.';
        } elseif (password_verify((string) ($_POST['password'] ?? ''), (string) $hash)) {
            login_register_success();
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            redirect(url('admin/'));
        } else {
            login_register_failure();
            $error = 'Onjuist wachtwoord.';
        }
    }
}

$pageTitle = $setupMode ? 'Beheer instellen' : 'Inloggen beheer';
include APP_ROOT . '/app/templates/header.php';
?>
<div class="form-page">
  <?php if ($setupMode): ?>
    <h1>Welkom! Stel het beheerwachtwoord in</h1>
    <p>Dit is de eerste keer dat het beheer wordt geopend. Kies een goed wachtwoord
       (minimaal 10 tekens) en bewaar het op een veilige plek.</p>
  <?php else: ?>
    <h1>Inloggen beheer</h1>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <p class="alert alert-error"><?= e($error) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('admin/login.php')) ?>" class="stacked-form">
    <?= csrf_field() ?>
    <label for="password">Wachtwoord</label>
    <input type="password" id="password" name="password" required autofocus
           autocomplete="<?= $setupMode ? 'new-password' : 'current-password' ?>">
    <?php if ($setupMode): ?>
      <label for="password2">Wachtwoord (nog een keer)</label>
      <input type="password" id="password2" name="password2" required autocomplete="new-password">
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">
      <?= $setupMode ? 'Wachtwoord instellen' : 'Inloggen' ?>
    </button>
  </form>
</div>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
