<?php
require __DIR__ . '/../../app/bootstrap.php';
require APP_ROOT . '/app/auth.php';

admin_session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $_SESSION = [];
    session_destroy();
}
redirect(url('admin/login.php'));
