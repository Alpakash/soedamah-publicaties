<?php
declare(strict_types=1);

/**
 * Eigen, privacyvriendelijke bezoekersstatistieken.
 *
 * - Server-side gemeten: geen JavaScript, geen cookies, geen externe dienst
 *   (past binnen de strikte Content-Security-Policy en vereist geen cookiebanner).
 * - Bezoekers worden per dag geteld via een hash van IP + browser met een
 *   dagelijks wisselend geheim. Het IP-adres zelf wordt nooit opgeslagen en
 *   bezoekers zijn na die dag niet meer herleidbaar of te volgen.
 * - Bekende zoekmachine-bots en linkvoorbeelden (WhatsApp, Facebook) tellen niet mee.
 */

/** Registreert één paginaweergave. Mag nooit een pagina laten sneuvelen. */
function analytics_track(): void
{
    try {
        if (PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            return;
        }
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if (str_contains($script, '/admin/')) {
            return;
        }
        $base = basename($script);
        if (in_array($base, ['webhook.php', 'cover.php', 'download.php'], true)) {
            return;
        }
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '' || preg_match(
            '/bot|crawl|spider|slurp|curl|wget|python|scrapy|httpclient|monitor|lighthouse|headless'
            . '|preview|facebookexternalhit|whatsapp|telegram|skype|pingdom|uptime/i',
            $ua
        )) {
            return;
        }

        $page = analytics_page_label($base);
        $stmt = db()->prepare(
            'INSERT INTO page_views (day, page, visitor_hash, referrer_host, created_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            gmdate('Y-m-d'),
            $page,
            analytics_visitor_hash($ua),
            analytics_referrer_host(),
            now(),
        ]);

        // Heel af en toe oude regels opruimen zodat de database klein blijft.
        if (random_int(1, 100) === 1) {
            db()->prepare('DELETE FROM page_views WHERE day < ?')
                ->execute([gmdate('Y-m-d', time() - 400 * 86400)]);
        }
    } catch (Throwable $e) {
        // Statistieken mogen nooit de site breken; stilzwijgend overslaan.
    }
}

/** Korte, herkenbare paginanaam, met de slug erbij voor boek/artikel/gratis. */
function analytics_page_label(string $scriptBase): string
{
    $slug = static function (string $param): string {
        $v = (string) ($_GET[$param] ?? '');
        return preg_match('/^[A-Za-z0-9_-]{1,200}$/', $v) ? $v : '';
    };
    switch ($scriptBase) {
        case 'index.php':
            return 'home';
        case 'boek.php':
            return 'boek:' . $slug('b');
        case 'artikel.php':
            return 'artikel:' . $slug('a');
        case 'artikelen.php':
            return 'artikelen';
        case 'gratis.php':
            return 'gratis:' . $slug('b');
        case 'mandje.php':
            return 'mandje';
        case 'afrekenen.php':
            return 'afrekenen';
        case 'bedankt.php':
            return 'bedankt';
        case 'voorwaarden.php':
            return 'voorwaarden';
        default:
            return preg_replace('/\.php$/', '', $scriptBase) ?? $scriptBase;
    }
}

/**
 * Anonieme dagelijkse bezoekerscode: hash van IP + browser met een geheim dat
 * elke dag wisselt. Zelfde bezoeker op dezelfde dag = zelfde code (voor het
 * tellen van unieke bezoekers), maar over dagen heen niet te volgen.
 */
function analytics_visitor_hash(string $ua): string
{
    $today = gmdate('Y-m-d');
    if (setting_get('stats_salt_day') !== $today) {
        setting_set('stats_salt', bin2hex(random_bytes(16)));
        setting_set('stats_salt_day', $today);
    }
    $salt = (string) setting_get('stats_salt', '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return substr(hash('sha256', $salt . '|' . $ip . '|' . $ua), 0, 32);
}

/** Alleen de domeinnaam van een externe verwijzer (bijv. "google.com"), anders ''. */
function analytics_referrer_host(): string
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref === '') {
        return '';
    }
    $host = strtolower((string) (parse_url($ref, PHP_URL_HOST) ?? ''));
    if ($host === '') {
        return '';
    }
    $own = strtolower((string) (parse_url(base_url(), PHP_URL_HOST) ?? ''));
    if ($host === $own || str_starts_with($host, 'www.') && substr($host, 4) === $own) {
        return '';
    }
    return substr($host, 0, 150);
}

/** Nette weergavenaam voor een pagina-code uit de statistieken. */
function analytics_page_display(string $page): string
{
    if ($page === 'home') {
        return 'Homepagina';
    }
    if ($page === 'artikelen') {
        return 'Artikelenoverzicht';
    }
    if ($page === 'mandje') {
        return 'Winkelmandje';
    }
    if ($page === 'afrekenen') {
        return 'Afrekenen';
    }
    if ($page === 'bedankt') {
        return 'Bedankt (na betaling)';
    }
    if ($page === 'voorwaarden') {
        return 'Algemene voorwaarden';
    }
    foreach (['boek' => 'Boek', 'artikel' => 'Artikel', 'gratis' => 'Gratis download'] as $prefix => $label) {
        if (str_starts_with($page, $prefix . ':')) {
            $slug = substr($page, strlen($prefix) + 1);
            return $label . ': ' . ($slug !== '' ? $slug : 'onbekend');
        }
    }
    return ucfirst($page);
}
