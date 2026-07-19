<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Huidige tijd in UTC, zoals overal in de database opgeslagen. */
function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function log_msg(string $message): void
{
    @file_put_contents(
        DATA_DIR . '/app.log',
        gmdate('c') . ' ' . $message . "\n",
        FILE_APPEND | LOCK_EX
    );
}

function format_price(int $cents): string
{
    if ($cents <= 0) {
        return 'Gratis';
    }
    return "€\u{00A0}" . number_format($cents / 100, 2, ',', '.');
}

/**
 * Zet invoer als "12,50" of "12.50" om naar centen. Geeft -1 bij ongeldige invoer.
 */
function parse_price(string $input): int
{
    $s = trim(str_replace(['€', ' '], '', $input));
    if ($s === '') {
        return 0;
    }
    if (str_contains($s, ',')) {
        $s = str_replace('.', '', $s); // 1.250,50 -> 1250,50
        $s = str_replace(',', '.', $s);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
        // Geen komma, maar wel groepjes van precies drie cijfers na de punt(en):
        // dat is een duizendtalnotatie ("2.500" = tweeduizend vijfhonderd),
        // geen decimaal bedrag (een prijs heeft nooit 3 cijfers achter de komma).
        $s = str_replace('.', '', $s);
    }
    if (!is_numeric($s) || (float) $s < 0) {
        return -1;
    }
    return (int) round(((float) $s) * 100);
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $map = [
        'ä' => 'a', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
        'ß' => 'ss',
    ];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'publicatie';
}

function random_token(): string
{
    return bin2hex(random_bytes(20));
}

/** UTC-tijd uit de database tonen in Nederlandse tijd. */
function format_datetime(?string $utc): string
{
    if (!$utc) {
        return '-';
    }
    try {
        $dt = new DateTime($utc, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Amsterdam'));
        return $dt->format('d-m-Y H:i');
    } catch (Exception $e) {
        return $utc;
    }
}

/** Winkeltasje-icoon (Feather Icons "shopping-bag", MIT-licentie), inline zodat het meekleurt met tekst. */
function cart_icon_svg(): string
{
    return '<svg class="icon-cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/>'
        . '<path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
}

function format_date(?string $utc): string
{
    if (!$utc) {
        return '-';
    }
    try {
        $dt = new DateTime($utc, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Amsterdam'));
        return $dt->format('d-m-Y');
    } catch (Exception $e) {
        return $utc;
    }
}
