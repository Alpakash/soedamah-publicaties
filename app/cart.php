<?php
declare(strict_types=1);

const CART_COOKIE = 'sp_mandje';
const CART_MAX = 10;

/** Boek-id's uit het mandje-cookie (gededupliceerd, begrensd). */
function cart_ids(): array
{
    $raw = (string) ($_COOKIE[CART_COOKIE] ?? '');
    $ids = [];
    foreach (explode(',', $raw) as $part) {
        $id = (int) $part;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return array_slice(array_values(array_unique($ids)), 0, CART_MAX);
}

function cart_store(array $ids): void
{
    $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, CART_MAX);
    $value = implode(',', $ids);
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(CART_COOKIE, $value, [
        'expires'  => $ids !== [] ? time() + 7 * 86400 : time() - 3600,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // Zelfde request meteen de nieuwe stand laten zien.
    if ($ids !== []) {
        $_COOKIE[CART_COOKIE] = $value;
    } else {
        unset($_COOKIE[CART_COOKIE]);
    }
}

/** Alleen boeken die echt te koop zijn: online, betaald, op voorraad én leverbaar (bestanden of fysiek). */
function cart_books(): array
{
    $books = [];
    foreach (cart_ids() as $id) {
        $book = book_find($id);
        if ($book !== null
            && (int) $book['published'] === 1
            && (int) $book['price_cents'] > 0
            && book_orderable($book)
        ) {
            $books[] = $book;
        }
    }
    return $books;
}

function cart_total(array $books): int
{
    $total = 0;
    foreach ($books as $book) {
        $total += (int) $book['price_cents'];
    }
    return $total;
}
