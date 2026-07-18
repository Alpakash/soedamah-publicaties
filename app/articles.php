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
