<?php
declare(strict_types=1);

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('sp_admin');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function admin_logged_in(): bool
{
    admin_session_start();
    return !empty($_SESSION['admin']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect(url('admin/login.php'));
    }
}

function csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    admin_session_start();
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        exit('Ongeldige of verlopen sessie. Ga terug en probeer het opnieuw.');
    }
}

const LOGIN_MAX_ATTEMPTS = 8;
const LOGIN_BLOCK_SECONDS = 900;

/** Aantal seconden dat inloggen nog geblokkeerd is (0 = niet geblokkeerd). */
function login_blocked_seconds(): int
{
    $raw = setting_get('login_throttle');
    if ($raw === null) {
        return 0;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return 0;
    }
    $until = (int) ($data['blocked_until'] ?? 0);
    return $until > time() ? $until - time() : 0;
}

function login_register_failure(): void
{
    $raw = setting_get('login_throttle');
    $data = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    $count = (int) ($data['count'] ?? 0) + 1;
    $blockedUntil = (int) ($data['blocked_until'] ?? 0);
    if ($count >= LOGIN_MAX_ATTEMPTS) {
        $blockedUntil = time() + LOGIN_BLOCK_SECONDS;
        $count = 0;
        log_msg('Inloggen beheer geblokkeerd na te veel mislukte pogingen.');
    }
    setting_set('login_throttle', (string) json_encode(['count' => $count, 'blocked_until' => $blockedUntil]));
}

function login_register_success(): void
{
    setting_set('login_throttle', (string) json_encode(['count' => 0, 'blocked_until' => 0]));
}
