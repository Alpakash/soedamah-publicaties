<?php
require __DIR__ . '/../app/bootstrap.php';

$fromCart = !empty($_POST['mandje']) || (($_GET['bron'] ?? '') === 'mandje');

if ($fromCart) {
    $books = cart_books();
    if ($books === []) {
        redirect(url('mandje.php'));
    }
    $cancelUrl = url('mandje.php');
    $backUrl = $cancelUrl;
} else {
    $book = book_find_by_slug((string) ($_GET['b'] ?? ''));
    if ($book === null || !(int) $book['published']) {
        redirect(url());
    }
    if ((int) $book['price_cents'] <= 0) {
        redirect(url('gratis.php?b=' . $book['slug']));
    }
    if (!book_has_files($book)) {
        redirect(url('boek.php?b=' . $book['slug']));
    }
    $books = [$book];
    $cancelUrl = url('boek.php?b=' . $book['slug']);
    $backUrl = $cancelUrl;
}

try {
    $orders = [];
    $lineItems = [];
    foreach ($books as $item) {
        $orders[] = order_create($item, 'pending');
        $lineItem = [
            'quantity'   => 1,
            'price_data' => [
                'currency'     => 'eur',
                'unit_amount'  => (int) $item['price_cents'],
                'product_data' => ['name' => $item['title']],
            ],
        ];
        if ($item['cover_file'] !== '' && str_starts_with(base_url(), 'https://')) {
            $lineItem['price_data']['product_data']['images'] = [url('cover.php?b=' . $item['id'])];
        }
        $lineItems[] = $lineItem;
    }

    $params = [
        'mode'                => 'payment',
        'locale'              => 'nl',
        'client_reference_id' => (string) $orders[0]['id'],
        'metadata'            => [
            'order_ids' => implode(',', array_map(static fn (array $o): int => (int) $o['id'], $orders)),
        ],
        'line_items'          => $lineItems,
        // Stripe vervangt {CHECKOUT_SESSION_ID} zelf door het echte sessie-id.
        'success_url'         => url('bedankt.php') . '?sid={CHECKOUT_SESSION_ID}',
        'cancel_url'          => $cancelUrl,
    ];

    $session = stripe_request('POST', '/v1/checkout/sessions', $params);
    if (empty($session['id']) || empty($session['url'])) {
        throw new StripeError('Stripe gaf geen geldige checkout-sessie terug.');
    }
    foreach ($orders as $order) {
        order_set_session((int) $order['id'], (string) $session['id']);
    }
    redirect((string) $session['url']);
} catch (StripeError $e) {
    log_msg('Afrekenen mislukt: ' . $e->getMessage());
    http_response_code(502);
    $pageTitle = 'Betalen lukt even niet';
    include APP_ROOT . '/app/templates/header.php';
    ?>
    <div class="empty-state">
      <h1>Betalen lukt op dit moment niet</h1>
      <p>Er ging iets mis bij het starten van de betaling. Probeer het over een paar minuten opnieuw.</p>
      <p class="muted"><?= e($e->getMessage()) ?></p>
      <p><a class="btn btn-secondary" href="<?= e($backUrl) ?>">Terug</a></p>
    </div>
    <?php
    include APP_ROOT . '/app/templates/footer.php';
}
