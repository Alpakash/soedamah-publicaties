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
        // 'expired' hoort erbij: als een betaling toch nog binnenkomt nadat we een
        // bestelling als verlopen hadden gemarkeerd, wordt hij alsnog netjes betaald.
        if (!in_array($order['status'], ['pending', 'failed', 'expired'], true)) {
            continue;
        }
        $stmt = db()->prepare(
            'UPDATE orders SET status = ?, email = ?, paid_at = ?, expires_at = ?
             WHERE id = ? AND status IN (?, ?, ?)'
        );
        $stmt->execute([
            'paid',
            $email !== '' ? $email : $order['email'],
            now(),
            order_expiry_from_now(),
            $order['id'],
            'pending',
            'failed',
            'expired',
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
    // Adres dat een melding krijgt bij elke bestelling. Instelbaar via config
    // (admin_email); is dat leeg, dan valt hij terug op het vaste adres van de
    // verkoper, zodat er altijd een melding van een verkochte aankoop binnenkomt.
    $admin = (string) config('admin_email', '');
    if ($admin === '') {
        $admin = 'soedamah@gmail.com';
    }
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

/**
 * Markeert bestellingen die al langer dan $days dagen op betaling wachten als
 * 'expired' (verlopen). De Stripe-betaalsessie is dan allang vervallen, dus deze
 * bestelling wordt nooit meer vanzelf betaald. Zo blijft het overzicht overzichtelijk
 * en blijft 'wacht op betaling' niet eeuwig staan. Geeft het aantal gewijzigde regels.
 */
function orders_expire_stale(int $days = 3): int
{
    $cutoff = gmdate('Y-m-d H:i:s', time() - $days * 86400);
    $stmt = db()->prepare(
        "UPDATE orders SET status = 'expired' WHERE status = 'pending' AND created_at < ?"
    );
    $stmt->execute([$cutoff]);
    return $stmt->rowCount();
}

/**
 * Stuurt eenmalig een vriendelijke hulp-/herinneringsmail naar kopers die na
 * $afterHours uur nog steeds op betaling wachten (en vóór de bestelling verloopt).
 * Precies één mail per bestelling/mandje — geen spam (bijgehouden via reminder_sent_at).
 * Geeft het aantal verstuurde herinneringen terug.
 */
function orders_send_payment_reminders(int $afterHours = 24, int $beforeDays = 3): int
{
    $olderThan = gmdate('Y-m-d H:i:s', time() - $afterHours * 3600);   // minstens zo oud
    $notBefore = gmdate('Y-m-d H:i:s', time() - $beforeDays * 86400);  // maar nog niet verlopen
    $stmt = db()->prepare(
        "SELECT * FROM orders
         WHERE status = 'pending' AND email <> ''
           AND (reminder_sent_at IS NULL OR reminder_sent_at = '')
           AND created_at <= ? AND created_at >= ?
         ORDER BY id"
    );
    $stmt->execute([$olderThan, $notBefore]);
    $pending = $stmt->fetchAll();
    if ($pending === []) {
        return 0;
    }

    // Eén mandje kan meerdere bestellingen zijn: groepeer per checkout-sessie
    // (val terug op e-mailadres) zodat de koper één mail krijgt, niet één per titel.
    $groups = [];
    foreach ($pending as $order) {
        $session = (string) ($order['stripe_session_id'] ?? '');
        $key = $session !== '' ? 's:' . $session : 'e:' . strtolower((string) $order['email']);
        $groups[$key][] = $order;
    }

    $sent = 0;
    foreach ($groups as $group) {
        if (order_send_payment_reminder($group)) {
            $sent++;
        }
    }
    return $sent;
}

/** Verstuurt één herinneringsmail voor een groep openstaande bestellingen (één mandje). */
function order_send_payment_reminder(array $orders): bool
{
    $email = '';
    $buyerName = '';
    foreach ($orders as $order) {
        if ($email === '' && $order['email'] !== '') {
            $email = (string) $order['email'];
        }
        if ($buyerName === '' && !empty($order['name'])) {
            $buyerName = (string) $order['name'];
        }
    }
    if ($email === '') {
        return false;
    }

    $titles = [];
    $links = [];
    foreach ($orders as $order) {
        $titles[] = (string) $order['book_title'];
        $book = $order['book_id'] ? book_find((int) $order['book_id']) : null;
        if ($book !== null && !empty($book['slug'])) {
            $links[] = $order['book_title'] . ': ' . url('boek.php?b=' . $book['slug']);
        }
    }

    $lines = [];
    $lines[] = 'Beste ' . ($buyerName !== '' ? $buyerName : 'lezer') . ',';
    $lines[] = '';
    $lines[] = count($titles) === 1
        ? 'Je begon onlangs een bestelling van "' . $titles[0] . '" bij Publicaties van '
            . 'Lachman Soedamah, maar de betaling is nog niet afgerond.'
        : 'Je begon onlangs een bestelling bij Publicaties van Lachman Soedamah, maar de '
            . 'betaling is nog niet afgerond. Het ging om: ' . implode(', ', $titles) . '.';
    $lines[] = '';
    $lines[] = 'Wil je de bestelling alsnog afronden? Dat kan opnieuw via de shop'
        . ($links !== [] ? ':' : '.');
    if ($links !== []) {
        $lines[] = '';
        foreach ($links as $link) {
            $lines[] = $link;
        }
    }
    $lines[] = '';
    $lines[] = 'Lukt betalen niet — bijvoorbeeld omdat iDEAL vanuit het buitenland niet werkt? '
        . 'Beantwoord dan gerust deze e-mail, dan zoeken we samen naar een oplossing '
        . '(bijvoorbeeld betaling per creditcard).';
    $lines[] = '';
    $lines[] = 'Heb je inmiddels al betaald of geen interesse meer? Dan kun je deze mail negeren; '
        . 'je ontvangt hierover geen verdere berichten.';
    $lines[] = '';
    $lines[] = 'Met vriendelijke groet,';
    $lines[] = (string) config('mail_from_name', 'Lachman Soedamah');
    $lines[] = base_url();

    $subject = count($titles) === 1
        ? 'Je bestelling afronden: ' . $titles[0]
        : 'Je bestelling afronden bij Publicaties Soedamah';

    $ok = send_mail($email, $subject, implode("\n", $lines));
    if ($ok) {
        $stmt = db()->prepare('UPDATE orders SET reminder_sent_at = ? WHERE id = ?');
        foreach ($orders as $order) {
            $stmt->execute([now(), $order['id']]);
        }
    }
    return $ok;
}
