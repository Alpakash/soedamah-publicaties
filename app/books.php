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

function book_orderable(array $book): bool
{
    return book_has_files($book) && (int) $book['in_stock'] === 1;
}

function book_formats_label(array $book): string
{
    $formats = [];
    if ($book['pdf_file'] !== '') {
        $formats[] = 'PDF';
    }
    if ($book['epub_file'] !== '') {
        $formats[] = 'EPUB';
    }
    return $formats ? implode(' · ', $formats) : 'Verschijnt binnenkort';
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
