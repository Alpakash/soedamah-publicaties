<?php
declare(strict_types=1);

function article_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    return $article ?: null;
}

function article_find_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM articles WHERE slug = ?');
    $stmt->execute([$slug]);
    $article = $stmt->fetch();
    return $article ?: null;
}

function articles_published(): array
{
    return db()->query(
        'SELECT * FROM articles WHERE published = 1 ORDER BY article_date DESC, id DESC'
    )->fetchAll();
}

function articles_all(): array
{
    return db()->query(
        'SELECT * FROM articles ORDER BY article_date DESC, id DESC'
    )->fetchAll();
}

function article_unique_slug(string $title, int $excludeId = 0): string
{
    $base = slugify($title);
    $slug = $base;
    $i = 2;
    while (true) {
        $stmt = db()->prepare('SELECT id FROM articles WHERE slug = ? AND id != ?');
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

/** Is dit artikel eerder in de media verschenen (toont de badge)? */
function article_in_media(array $article): bool
{
    return (int) ($article['in_media'] ?? 0) === 1;
}

/** Moet de datum bij dit artikel verborgen blijven? */
function article_hide_date(array $article): bool
{
    return (int) ($article['hide_date'] ?? 0) === 1;
}

/**
 * HTML-blokje met de verwijzing naar het oorspronkelijke media-artikel, of ''
 * als er geen (geldige) link is. Alleen http(s)-links worden geaccepteerd.
 */
function article_source_html(array $article): string
{
    $url = trim((string) ($article['source_url'] ?? ''));
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return '';
    }
    $name = trim((string) ($article['source_name'] ?? ''));
    $intro = $name !== ''
        ? 'Dit artikel verscheen eerder in ' . e($name) . '.'
        : 'Dit artikel verscheen eerder in de media.';
    return '<p class="article-source">' . $intro
        . ' <a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
        . 'Lees het oorspronkelijke artikel →</a></p>';
}

/** Korte samenvatting afleiden uit de opgemaakte tekst, voor overzicht en meta-omschrijving. */
function article_excerpt_from_body(string $bodyHtml, int $maxLength = 220): string
{
    // Vóór het strippen een spatie invoegen waar blokelementen op elkaar volgden,
    // anders plakken twee alinea's/koppen aan elkaar vast ("...landHet kleine...").
    $withBreaks = (string) preg_replace('#</(p|h2|h3|li|blockquote)>|<br\s*/?>#i', ' ', $bodyHtml);
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($withBreaks)) ?? '');
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $maxLength) {
        return $text;
    }
    $truncated = mb_substr($text, 0, $maxLength);
    $lastSpace = mb_strrpos($truncated, ' ');
    if ($lastSpace !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }
    return $truncated . '…';
}
