<?php
require __DIR__ . '/../app/bootstrap.php';

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

try {
    $order = order_create($book, 'pending');

    $params = [
        'mode'                 => 'payment',
        'locale'               => 'nl',
        'client_reference_id'  => (string) $order['id'],
        'metadata'             => [
            'order_id' => (string) $order['id'],
            'book_id'  => (string) $book['id'],
        ],
        'line_items'           => [[
            'quantity'   => 1,
            'price_data' => [
                'currency'     => 'eur',
                'unit_amount'  => (int) $book['price_cents'],
                'product_data' => ['name' => $book['title']],
            ],
        ]],
        // Stripe vervangt {CHECKOUT_SESSION_ID} zelf door het echte sessie-id.
        'success_url'          => url('bedankt.php') . '?sid={CHECKOUT_SESSION_ID}',
        'cancel_url'           => url('boek.php?b=' . $book['slug']),
    ];
    if ($book['cover_file'] !== '' && str_starts_with(base_url(), 'https://')) {
        $params['line_items'][0]['price_data']['product_data']['images'] = [url('cover.php?b=' . $book['id'])];
    }

    $session = stripe_request('POST', '/v1/checkout/sessions', $params);
    if (empty($session['id']) || empty($session['url'])) {
        throw new StripeError('Stripe gaf geen geldige checkout-sessie terug.');
    }
    order_set_session((int) $order['id'], (string) $session['id']);
    redirect((string) $session['url']);
} catch (StripeError $e) {
    log_msg('Afrekenen mislukt voor boek ' . $book['id'] . ': ' . $e->getMessage());
    http_response_code(502);
    $pageTitle = 'Betalen lukt even niet';
    include APP_ROOT . '/app/templates/header.php';
    ?>
    <div class="empty-state">
      <h1>Betalen lukt op dit moment niet</h1>
      <p>Er ging iets mis bij het starten van de betaling. Probeer het over een paar minuten opnieuw.</p>
      <p class="muted"><?= e($e->getMessage()) ?></p>
      <p><a class="btn btn-secondary" href="<?= e(url('boek.php?b=' . $book['slug'])) ?>">Terug naar het boek</a></p>
    </div>
    <?php
    include APP_ROOT . '/app/templates/footer.php';
}
