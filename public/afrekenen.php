<?php
require __DIR__ . '/../app/bootstrap.php';

$books = cart_books();
if ($books === []) {
    redirect(url('mandje.php'));
}

$errors = [];
$naam = trim((string) ($_POST['naam'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot: echte bezoekers laten dit veld leeg.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        redirect(url());
    }
    if ($naam === '') {
        $errors[] = 'Vul je naam in.';
    } elseif (mb_strlen($naam) > 150) {
        $errors[] = 'Die naam is te lang.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Vul een geldig e-mailadres in.';
    }

    if (!$errors) {
        try {
            $orders = [];
            $lineItems = [];
            foreach ($books as $item) {
                $orders[] = order_create($item, 'pending', $email, $naam);
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
                'customer_email'      => $email,
                'client_reference_id' => (string) $orders[0]['id'],
                'metadata'            => [
                    'order_ids' => implode(',', array_map(static fn (array $o): int => (int) $o['id'], $orders)),
                ],
                'line_items'          => $lineItems,
                // Stripe vervangt {CHECKOUT_SESSION_ID} zelf door het echte sessie-id.
                'success_url'         => url('bedankt.php') . '?sid={CHECKOUT_SESSION_ID}',
                'cancel_url'          => url('mandje.php'),
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
            $errors[] = 'Betalen lukt op dit moment niet. Probeer het over een paar minuten opnieuw.';
        }
    }
}

$pageTitle = 'Afrekenen';
include APP_ROOT . '/app/templates/header.php';
?>
<div class="form-page">
  <h1>Afrekenen</h1>

  <div class="cart-summary">
    <?php foreach ($books as $book): ?>
      <div class="cart-summary-row">
        <span><?= e($book['title']) ?></span>
        <span><?= e(format_price((int) $book['price_cents'])) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="cart-summary-row cart-summary-total">
      <span>Totaal</span>
      <span><?= e(format_price(cart_total($books))) ?></span>
    </div>
  </div>

  <?php foreach ($errors as $error): ?>
    <p class="alert alert-error"><?= e($error) ?></p>
  <?php endforeach; ?>

  <form method="post" action="<?= e(url('afrekenen.php')) ?>" class="stacked-form">
    <p class="hp-field" aria-hidden="true">
      <label>Laat dit veld leeg <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </p>
    <label for="naam">Naam</label>
    <input type="text" id="naam" name="naam" required maxlength="150" autocomplete="name" value="<?= e($naam) ?>">
    <label for="email">E-mailadres</label>
    <input type="email" id="email" name="email" required autocomplete="email"
           value="<?= e($email) ?>" placeholder="naam@voorbeeld.nl">
    <p class="field-hint">Op dit e-mailadres ontvang je de downloadlinks.</p>
    <button type="submit" class="btn btn-primary btn-large">Doorgaan naar betalen</button>
  </form>
  <p class="back-link"><a href="<?= e(url('mandje.php')) ?>">← Terug naar mandje</a></p>
</div>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
