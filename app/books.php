<?php
declare(strict_types=1);

function book_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM books WHERE id = ?');
    $stmt->execute([$id]);
    $book = $stmt->fetch();
    return $book ?: null;
}

function book_find_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM books WHERE slug = ?');
    $stmt->execute([$slug]);
    $book = $stmt->fetch();
    return $book ?: null;
}

function books_published(): array
{
    return db()->query(
        'SELECT * FROM books WHERE published = 1 ORDER BY sort_order DESC, id DESC'
    )->fetchAll();
}

function books_all(): array
{
    return db()->query(
        'SELECT * FROM books ORDER BY sort_order DESC, id DESC'
    )->fetchAll();
}

function book_has_files(array $book): bool
{
    return $book['pdf_file'] !== '' || $book['epub_file'] !== '';
}

function book_is_physical(array $book): bool
{
    return (int) ($book['is_physical'] ?? 0) === 1;
}

/** Heeft deze publicatie iets om te leveren: digitale bestanden, of een fysieke uitgave die per post gaat? */
function book_has_deliverable(array $book): bool
{
    return book_is_physical($book) || book_has_files($book);
}

function book_orderable(array $book): bool
{
    return book_has_deliverable($book) && (int) $book['in_stock'] === 1;
}

function book_formats_label(array $book): string
{
    if (book_is_physical($book)) {
        return 'Hardcover';
    }
    $formats = [];
    if ($book['pdf_file'] !== '') {
        $formats[] = 'PDF';
    }
    if ($book['epub_file'] !== '') {
        $formats[] = 'EPUB';
    }
    return $formats ? implode(' · ', $formats) : 'Nog niet beschikbaar';
}

/** Toont het boek de "Nieuw"-badge? Uitgezet als de beheerder dat expliciet heeft uitgevinkt
 *  (bijv. een oudere titel die nu pas aan de shop is toegevoegd). */
function book_is_new(array $book): bool
{
    return book_has_deliverable($book)
        && (int) ($book['hide_new_badge'] ?? 0) === 0
        && (strtotime($book['created_at'] . ' UTC') > time() - 45 * 86400);
}

function book_unique_slug(string $title, int $excludeId = 0): string
{
    $base = slugify($title);
    $slug = $base;
    $i = 2;
    while (true) {
        $stmt = db()->prepare('SELECT id FROM books WHERE slug = ? AND id != ?');
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}
