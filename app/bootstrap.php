<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');

error_reporting(E_ALL);
ini_set('log_errors', '1');
// Alleen bij de ingebouwde PHP-testserver fouten tonen; op de echte server nooit.
ini_set('display_errors', PHP_SAPI === 'cli-server' ? '1' : '0');

date_default_timezone_set('Europe/Amsterdam');

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/books.php';
require __DIR__ . '/stripe.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/orders.php';

/**
 * @return mixed
 */
function config(?string $key = null, $default = null)
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<h1>Configuratie ontbreekt</h1>'
                . '<p>Kopieer <code>app/config.example.php</code> naar <code>app/config.php</code> '
                . 'en vul de gegevens in. Zie <code>docs/INSTALLATIE-PLESK.md</code>.</p>';
            exit;
        }
        $config = require $file;
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}

function base_url(): string
{
    $configured = rtrim((string) config('base_url', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host;
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; base-uri 'self'; frame-ancestors 'self'");
}
