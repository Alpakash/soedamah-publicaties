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

/**
 * Beste inschatting van het IP-adres van de bezoeker. Deze site draait achter
 * Plesk/nginx, dat REMOTE_ADDR correct doorgeeft; er wordt bewust geen
 * X-Forwarded-For-header vertrouwd (die is door de bezoeker zelf te vervalsen).
 */
function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Aantal seconden dat inloggen vanaf dit IP-adres nog geblokkeerd is (0 = niet geblokkeerd). */
function login_blocked_seconds(): int
{
    $stmt = db()->prepare('SELECT blocked_until FROM login_attempts WHERE ip = ?');
    $stmt->execute([client_ip()]);
    $until = (int) $stmt->fetchColumn();
    return $until > time() ? $until - time() : 0;
}

function login_register_failure(): void
{
    $ip = client_ip();
    $stmt = db()->prepare('SELECT count, blocked_until FROM login_attempts WHERE ip = ?');
    $stmt->execute([$ip]);
    $row = $stmt->fetch();
    $count = ((int) ($row['count'] ?? 0)) + 1;
    $blockedUntil = (int) ($row['blocked_until'] ?? 0);
    if ($count >= LOGIN_MAX_ATTEMPTS) {
        $blockedUntil = time() + LOGIN_BLOCK_SECONDS;
        $count = 0;
        log_msg('Inloggen beheer geblokkeerd voor ' . $ip . ' na te veel mislukte pogingen.');
    }
    db()->prepare(
        'INSERT INTO login_attempts (ip, count, blocked_until, updated_at) VALUES (?, ?, ?, ?)
         ON CONFLICT(ip) DO UPDATE SET count = excluded.count, blocked_until = excluded.blocked_until, updated_at = excluded.updated_at'
    )->execute([$ip, $count, $blockedUntil, now()]);

    // Oude, niet-geblokkeerde rijen opruimen zodat de tabel niet blijft groeien.
    db()->exec("DELETE FROM login_attempts WHERE blocked_until = 0 AND updated_at < datetime('now', '-1 day')");
}

function login_register_success(): void
{
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]);
}
