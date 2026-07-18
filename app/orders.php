<?php
declare(strict_types=1);

function order_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function order_find_by_session(string $sessionId): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE stripe_session_id = ?');
    $stmt->execute([$sessionId]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function order_find_by_token(string $token): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE token = ?');
    $stmt->execute([$token]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function order_expiry_from_now(): string
{
    $days = max(1, (int) config('download_days', 90));
    return gmdate('Y-m-d H:i:s', time() + 86400 * $days);
}

/**
 * Maakt een bestelling aan. Status: 'pending' (wacht op betaling) of 'free'.
 */
function order_create(array $book, string $status, string $email = '', ?string $sessionId = null): array
{
    $stmt = db()->prepare(
        'INSERT INTO orders
            (book_id, book_title, email, stripe_session_id, amount_cents, status,
             token, expires_at, created_at, paid_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $book['id'],
        $book['title'],
        $email,
        $sessionId,
        (int) $book['price_cents'],
        $status,
        random_token(),
        order_expiry_from_now(),
        now(),
        $status === 'free' ? now() : null,
    ]);
    $order = order_find((int) db()->lastInsertId());
    if ($order === null) {
        throw new RuntimeException('Bestelling kon niet worden aangemaakt.');
    }
    return $order;
}

function order_set_session(int $orderId, string $sessionId): void
{
    $stmt = db()->prepare('UPDATE orders SET stripe_session_id = ? WHERE id = ?');
    $stmt->execute([$sessionId, $orderId]);
}

/**
 * Markeert een bestelling als betaald en stuurt de downloadmail.
 * Idempotent: een al betaalde bestelling wordt niet nog een keer verwerkt.
 */
function order_mark_paid(array $order, string $email): array
{
    if (!in_array($order['status'], ['pending', 'failed'], true)) {
        return $order;
    }
    $stmt = db()->prepare(
        'UPDATE orders SET status = ?, email = ?, paid_at = ?, expires_at = ?
         WHERE id = ? AND status IN (?, ?)'
    );
    $stmt->execute([
        'paid',
        $email !== '' ? $email : $order['email'],
        now(),
        order_expiry_from_now(),
        $order['id'],
        'pending',
        'failed',
    ]);
    $order = order_find((int) $order['id']) ?? $order;
    order_send_links($order);
    order_notify_admin($order);
    return $order;
}

function order_mark_failed(array $order): void
{
    $stmt = db()->prepare('UPDATE orders SET status = ? WHERE id = ? AND status = ?');
    $stmt->execute(['failed', $order['id'], 'pending']);
}

function order_download_url(array $order, string $format): string
{
    return url('download.php?t=' . $order['token'] . '&f=' . $format);
}

/**
 * Stuurt de e-mail met downloadlinks (één keer; daarna alleen na expliciet
 * opnieuw versturen vanuit het beheer).
 */
function order_send_links(array $order): bool
{
    if ($order['email'] === '' || !empty($order['email_sent_at'])) {
        return false;
    }
    $book = $order['book_id'] ? book_find((int) $order['book_id']) : null;
    if ($book === null) {
        return false;
    }

    $lines = [];
    $lines[] = 'Beste lezer,';
    $lines[] = '';
    $lines[] = $order['status'] === 'free'
        ? 'Bedankt voor je interesse in "' . $book['title'] . '". Je kunt de publicatie downloaden via onderstaande link(s):'
        : 'Bedankt voor je aankoop van "' . $book['title'] . '". Je kunt het boek downloaden via onderstaande link(s):';
    $lines[] = '';
    if ($book['pdf_file'] !== '') {
        $lines[] = 'PDF:  ' . order_download_url($order, 'pdf');
    }
    if ($book['epub_file'] !== '') {
        $lines[] = 'EPUB: ' . order_download_url($order, 'epub');
    }
    $lines[] = '';
    $lines[] = 'De link is ' . (int) config('download_days', 90) . ' dagen geldig; de uitgave is voor persoonlijk gebruik.';
    $lines[] = 'Lukt het downloaden niet? Beantwoord dan deze e-mail.';
    $lines[] = '';
    $lines[] = 'Met vriendelijke groet,';
    $lines[] = (string) config('mail_from_name', 'Lachman Soedamah');
    $lines[] = base_url();

    $ok = send_mail($order['email'], 'Je download: ' . $book['title'], implode("\n", $lines));
    if ($ok) {
        $stmt = db()->prepare('UPDATE orders SET email_sent_at = ? WHERE id = ?');
        $stmt->execute([now(), $order['id']]);
    }
    return $ok;
}

function order_notify_admin(array $order): void
{
    $admin = (string) config('admin_email', '');
    if ($admin === '') {
        return;
    }
    $body = "Er is een nieuwe bestelling binnengekomen.\n\n"
        . 'Publicatie: ' . $order['book_title'] . "\n"
        . 'Bedrag:     ' . format_price((int) $order['amount_cents']) . "\n"
        . 'Koper:      ' . ($order['email'] !== '' ? $order['email'] : 'onbekend') . "\n"
        . 'Status:     ' . ($order['status'] === 'free' ? 'gratis download' : 'betaald') . "\n\n"
        . 'Bekijk alle bestellingen: ' . url('admin/bestellingen.php') . "\n";
    send_mail($admin, 'Nieuwe bestelling: ' . $order['book_title'], $body);
}
