<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }
    foreach (['/uploads', '/uploads/books', '/uploads/covers'] as $dir) {
        if (!is_dir(DATA_DIR . $dir)) {
            @mkdir(DATA_DIR . $dir, 0755, true);
        }
    }

    try {
        $pdo = new PDO('sqlite:' . DATA_DIR . '/shop.sqlite', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Databasefout: controleer of de map "data" schrijfbaar is en of de PHP-extensie pdo_sqlite aanstaat.');
    }
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    db_migrate($pdo);
    return $pdo;
}

function db_migrate(PDO $pdo): void
{
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS settings (
    name  TEXT PRIMARY KEY,
    value TEXT NOT NULL
)
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS books (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    slug        TEXT UNIQUE NOT NULL,
    title       TEXT NOT NULL,
    subtitle    TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    price_cents INTEGER NOT NULL DEFAULT 0,
    cover_file  TEXT NOT NULL DEFAULT '',
    pdf_file    TEXT NOT NULL DEFAULT '',
    epub_file   TEXT NOT NULL DEFAULT '',
    published   INTEGER NOT NULL DEFAULT 0,
    in_stock    INTEGER NOT NULL DEFAULT 1,
    is_physical INTEGER NOT NULL DEFAULT 0,
    hide_new_badge INTEGER NOT NULL DEFAULT 0,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL
)
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS orders (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    book_id           INTEGER,
    book_title        TEXT NOT NULL DEFAULT '',
    email             TEXT NOT NULL DEFAULT '',
    name              TEXT NOT NULL DEFAULT '',
    stripe_session_id TEXT,
    amount_cents      INTEGER NOT NULL DEFAULT 0,
    status            TEXT NOT NULL DEFAULT 'pending',
    token             TEXT UNIQUE,
    downloads_pdf     INTEGER NOT NULL DEFAULT 0,
    downloads_epub    INTEGER NOT NULL DEFAULT 0,
    expires_at        TEXT,
    email_sent_at     TEXT,
    shipping_address  TEXT NOT NULL DEFAULT '',
    created_at        TEXT NOT NULL,
    paid_at           TEXT
)
SQL);

    // Migratie voor bestaande databases: het winkelmandje laat meerdere
    // bestellingen dezelfde checkout-sessie delen, dus de UNIQUE-beperking
    // op stripe_session_id moet weg (SQLite vereist daarvoor een herbouw).
    $tableSql = (string) $pdo->query(
        "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'orders'"
    )->fetchColumn();
    if (str_contains($tableSql, 'stripe_session_id TEXT UNIQUE')) {
        $pdo->exec('BEGIN IMMEDIATE');
        $pdo->exec(<<<SQL
CREATE TABLE orders_new (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    book_id           INTEGER,
    book_title        TEXT NOT NULL DEFAULT '',
    email             TEXT NOT NULL DEFAULT '',
    stripe_session_id TEXT,
    amount_cents      INTEGER NOT NULL DEFAULT 0,
    status            TEXT NOT NULL DEFAULT 'pending',
    token             TEXT UNIQUE,
    downloads_pdf     INTEGER NOT NULL DEFAULT 0,
    downloads_epub    INTEGER NOT NULL DEFAULT 0,
    expires_at        TEXT,
    email_sent_at     TEXT,
    created_at        TEXT NOT NULL,
    paid_at           TEXT
)
SQL);
        $pdo->exec(
            'INSERT INTO orders_new
                (id, book_id, book_title, email, stripe_session_id, amount_cents, status,
                 token, downloads_pdf, downloads_epub, expires_at, email_sent_at, created_at, paid_at)
             SELECT id, book_id, book_title, email, stripe_session_id, amount_cents, status,
                 token, downloads_pdf, downloads_epub, expires_at, email_sent_at, created_at, paid_at
             FROM orders'
        );
        $pdo->exec('DROP TABLE orders');
        $pdo->exec('ALTER TABLE orders_new RENAME TO orders');
        $pdo->exec('COMMIT');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_session ON orders (stripe_session_id)');

    // Migratie voor bestaande databases: naamveld voor het afrekenformulier.
    $orderColumns = $pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('name', $orderColumns, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN name TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('shipping_address', $orderColumns, true)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_address TEXT NOT NULL DEFAULT ''");
    }

    // Migratie voor bestaande databases: voorraadstatus en fysieke uitgaven per publicatie.
    $bookColumns = $pdo->query('PRAGMA table_info(books)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('in_stock', $bookColumns, true)) {
        $pdo->exec('ALTER TABLE books ADD COLUMN in_stock INTEGER NOT NULL DEFAULT 1');
    }
    if (!in_array('is_physical', $bookColumns, true)) {
        $pdo->exec('ALTER TABLE books ADD COLUMN is_physical INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('hide_new_badge', $bookColumns, true)) {
        $pdo->exec('ALTER TABLE books ADD COLUMN hide_new_badge INTEGER NOT NULL DEFAULT 0');
    }

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS articles (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    slug         TEXT UNIQUE NOT NULL,
    title        TEXT NOT NULL,
    excerpt      TEXT NOT NULL DEFAULT '',
    body_html    TEXT NOT NULL DEFAULT '',
    cover_file   TEXT NOT NULL DEFAULT '',
    published    INTEGER NOT NULL DEFAULT 0,
    article_date TEXT NOT NULL,
    created_at   TEXT NOT NULL,
    updated_at   TEXT NOT NULL
)
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS login_attempts (
    ip            TEXT PRIMARY KEY,
    count         INTEGER NOT NULL DEFAULT 0,
    blocked_until INTEGER NOT NULL DEFAULT 0,
    updated_at    TEXT NOT NULL
)
SQL);
}

function setting_get(string $name, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE name = ?');
    $stmt->execute([$name]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function setting_set(string $name, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (name, value) VALUES (?, ?)
         ON CONFLICT(name) DO UPDATE SET value = excluded.value'
    );
    $stmt->execute([$name, $value]);
}
