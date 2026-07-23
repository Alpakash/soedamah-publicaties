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
require __DIR__ . '/articles.php';
require __DIR__ . '/html_sanitizer.php';
require __DIR__ . '/cart.php';
require __DIR__ . '/stripe.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/orders.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/analytics.php';

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

/**
 * Nette, leesbare URL's voor de publieke pagina's (zonder .php-extensie of
 * ?-parameter). De bijbehorende herschrijfregels staan in public/.htaccess;
 * de oude ...php?slug-URL's blijven daarnaast gewoon werken.
 */
function book_url(string $slug): string
{
    return url('boek/' . rawurlencode($slug));
}

function article_url(string $slug): string
{
    return url('artikel/' . rawurlencode($slug));
}

function book_free_url(string $slug): string
{
    return url('gratis/' . rawurlencode($slug));
}

function articles_url(): string
{
    return url('artikelen');
}

/**
 * URL voor een statisch bestand, met de wijzigingsdatum als versienummer
 * zodat browsers na een update nooit een oude versie uit hun cache tonen.
 */
function asset_url(string $path): string
{
    $file = APP_ROOT . '/public/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url($path) . '?v=' . $version;
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Elke pagina toont mogelijk persoonlijke staat (winkelmandje, sessie) via een cookie,
    // dus nooit laten cachen door de browser of een cachende reverse proxy (bijv. Plesk/nginx).
    // Pagina's die wél cachebaar zijn (cover.php) overschrijven dit expliciet met hun eigen header.
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    // frame-src staat alleen de privacyvriendelijke video-embeds toe die de artikel-editor
    // produceert (zie app/html_sanitizer.php voor de bijbehorende whitelist bij het opslaan).
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-src https://www.youtube-nocookie.com https://player.vimeo.com; base-uri 'self'; frame-ancestors 'self'");
}

/** Zorgt dat een map bestaat, voor het eerste gebruik van een uploadlocatie. */
function ensure_dir(string $path): void
{
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

// Bezoekersstatistieken (privacyvriendelijk, zonder cookies) — alleen voor de
// publieke pagina's; beheer, webhook en bestandsdownloads tellen niet mee.
analytics_track();
