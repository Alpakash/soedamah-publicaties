<?php
declare(strict_types=1);

function order_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    return $order ?: null;
}

/** Alle bestellingen die bij één checkout-sessie horen (mandje = meerdere). */
function orders_find_by_session(string $sessionId): array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE stripe_session_id = ? ORDER BY id');
    $stmt->execute([$sessionId]);
    return $stmt->fetchAll();
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
function order_create(
    array $book,
    string $status,
    string $email = '',
    string $name = '',
    ?string $sessionId = null,
    string $shippingAddress = '',
    string $consentAt = '',
    bool $withdrawalWaived = false
): array {
    $stmt = db()->prepare(
        'INSERT INTO orders
            (book_id, book_title, email, name, stripe_session_id, amount_cents, status,
             token, expires_at, created_at, paid_at, shipping_address, consent_at, withdrawal_waived)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $book['id'],
        $book['title'],
        $email,
        $name,
        $sessionId,
        (int) $book['price_cents'],
        $status,
        random_token(),
        order_expiry_from_now(),
        now(),
        $status === 'free' ? now() : null,
        $shippingAddress,
        $consentAt,
        $withdrawalWaived ? 1 : 0,
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
 * Markeert de bestellingen van één betaling als betaald en stuurt daarna
 * één gecombineerde downloadmail plus één beheerdersmelding.
 * Idempotent: al betaalde bestellingen worden niet opnieuw verwerkt.
 */
function orders_mark_paid(array $orders, string $email): array
{
    $newlyPaid = false;
    foreach ($orders as $order) {
        if (!in_array($order['status'], ['pending', 'failed'], true)) {
            continue;
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
        if ($stmt->rowCount() > 0) {
            $newlyPaid = true;
        }
    }

    $fresh = [];
    foreach ($orders as $order) {
        $found = order_find((int) $order['id']);
        if ($found !== null) {
            $fresh[] = $found;
        }
    }
    if ($newlyPaid) {
        orders_send_links($fresh);
        orders_notify_admin($fresh);
    }
    return $fresh;
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
 * Stuurt één e-mail met de downloadlinks van deze bestellingen (één keer;
 * daarna alleen na expliciet opnieuw versturen vanuit het beheer).
 */
function orders_send_links(array $orders): bool
{
    $email = '';
    $buyerName = '';
    foreach ($orders as $order) {
        if ($email === '' && $order['email'] !== '') {
            $email = $order['email'];
        }
        if ($buyerName === '' && !empty($order['name'])) {
            $buyerName = $order['name'];
        }
    }
    if ($email === '') {
        return false;
    }

    $unsent = [];
    foreach ($orders as $order) {
        if (empty($order['email_sent_at']) && in_array($order['status'], ['paid', 'free'], true)) {
            $book = $order['book_id'] ? book_find((int) $order['book_id']) : null;
            if ($book !== null) {
                $unsent[] = [$order, $book];
            }
        }
    }
    if ($unsent === []) {
        return false;
    }

    $isFree = count($unsent) === 1 && $unsent[0][0]['status'] === 'free';
    $anyPhysical = false;
    $anyDigital = false;
    foreach ($unsent as [, $book]) {
        if (book_is_physical($book)) {
            $anyPhysical = true;
        } else {
            $anyDigital = true;
        }
    }

    $lines = [];
    $lines[] = 'Beste ' . ($buyerName !== '' ? $buyerName : 'lezer') . ',';
    $lines[] = '';
    if (count($unsent) === 1) {
        $intro = $isFree
            ? 'Bedankt voor je interesse in "' . $unsent[0][1]['title'] . '".'
            : 'Bedankt voor je aankoop van "' . $unsent[0][1]['title'] . '".';
        $lines[] = $anyPhysical
            ? $intro . ' We versturen dit boek per post naar het door jou opgegeven adres.'
            : $intro . ' Je kunt de publicatie downloaden via onderstaande link(s):';
    } elseif ($anyPhysical) {
        $lines[] = 'Bedankt voor je aankoop.'
            . ($anyDigital ? ' De digitale publicatie(s) kun je downloaden via onderstaande link(s);' : '')
            . ' de gedrukte uitgave versturen we per post naar het door jou opgegeven adres.';
    } else {
        $lines[] = 'Bedankt voor je aankoop. Je kunt de publicaties downloaden via onderstaande links:';
    }
    foreach ($unsent as [$order, $book]) {
        $lines[] = '';
        if (count($unsent) > 1) {
            $lines[] = $book['title'];
        }
        if (book_is_physical($book)) {
            $lines[] = 'Gedrukte uitgave (hardcover) — wordt per post verzonden.';
        } else {
            if ($book['pdf_file'] !== '') {
                $lines[] = 'PDF:  ' . order_download_url($order, 'pdf');
            }
            if ($book['epub_file'] !== '') {
                $lines[] = 'EPUB: ' . order_download_url($order, 'epub');
            }
        }
    }
    $lines[] = '';
    if ($anyDigital) {
        $lines[] = 'De downloadlink(s) zijn ' . (int) config('download_days', 90) . ' dagen geldig; de uitgaven zijn voor persoonlijk gebruik.';
    }
    $lines[] = $anyPhysical
        ? 'Vragen over je bestelling? Beantwoord dan deze e-mail.'
        : 'Lukt het downloaden niet? Beantwoord dan deze e-mail.';
    $lines[] = '';
    $lines[] = 'Met vriendelijke groet,';
    $lines[] = (string) config('mail_from_name', 'Lachman Soedamah');
    $lines[] = base_url();

    $subject = count($unsent) === 1
        ? ($anyPhysical ? 'Je bestelling: ' . $unsent[0][1]['title'] : 'Je download: ' . $unsent[0][1]['title'])
        : ($anyPhysical ? 'Je bestelling' : 'Je downloads');
    $ok = send_mail($email, $subject, implode("\n", $lines));
    if ($ok) {
        $stmt = db()->prepare('UPDATE orders SET email_sent_at = ? WHERE id = ?');
        foreach ($unsent as [$order, $book]) {
            $stmt->execute([now(), $order['id']]);
        }
    }
    return $ok;
}

/** Wrapper voor één losse bestelling (gratis download, opnieuw versturen). */
function order_send_links(array $order): bool
{
    return orders_send_links([$order]);
}

function orders_notify_admin(array $orders): void
{
    $admin = (string) config('admin_email', '');
    if ($orders === []) {
        return;
    }
    $total = 0;
    $itemLines = [];
    $email = '';
    $name = '';
    $shippingAddress = '';
    foreach ($orders as $order) {
        $total += (int) $order['amount_cents'];
        $itemLines[] = '- ' . $order['book_title'] . ' (' . format_price((int) $order['amount_cents']) . ')';
        if ($email === '' && $order['email'] !== '') {
            $email = $order['email'];
        }
        if ($name === '' && !empty($order['name'])) {
            $name = $order['name'];
        }
        if ($shippingAddress === '' && !empty($order['shipping_address'])) {
            $shippingAddress = (string) $order['shipping_address'];
        }
    }
    $koper = trim($name . ($email !== '' ? ' <' . $email . '>' : ''));
    $body = "Er is een nieuwe bestelling binnengekomen.\n\n"
        . implode("\n", $itemLines) . "\n\n"
        . 'Totaal: ' . format_price($total) . "\n"
        . 'Koper:  ' . ($koper !== '' ? $koper : 'onbekend') . "\n"
        . ($shippingAddress !== '' ? "\nVerzendadres (per post):\n" . $shippingAddress . "\n" : '')
        . "\nBekijk alle bestellingen: " . url('admin/bestellingen.php') . "\n";
    $subject = count($orders) === 1
        ? 'Nieuwe bestelling: ' . $orders[0]['book_title']
        : 'Nieuwe bestelling (' . count($orders) . ' publicaties)';

    if ($shippingAddress !== '') {
        // Bestellingen met een verzendadres gaan naar wie het boek daadwerkelijk post,
        // met de gewone beheerder in de CC.
        $shippingTo = (string) config('shipping_notify_email', '');
        $to = $shippingTo !== '' ? $shippingTo : $admin;
        $cc = ($admin !== '' && $admin !== $to) ? $admin : '';
        if ($to !== '') {
            send_mail($to, $subject, $body, $cc);
        }
        return;
    }

    if ($admin !== '') {
        send_mail($admin, $subject, $body);
    }
}

/** Wrapper voor één losse bestelling (gratis download). */
function order_notify_admin(array $order): void
{
    orders_notify_admin([$order]);
}
