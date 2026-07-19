<?php
require __DIR__ . '/../app/bootstrap.php';

$sessionId = (string) ($_GET['sid'] ?? '');
if ($sessionId === '' || strlen($sessionId) > 255) {
    redirect(url());
}

$orders = orders_find_by_session($sessionId);
$allDone = static function (array $orders): bool {
    if ($orders === []) {
        return false;
    }
    foreach ($orders as $order) {
        if (!in_array($order['status'], ['paid', 'free'], true)) {
            return false;
        }
    }
    return true;
};

$state = 'processing';
try {
    if ($allDone($orders)) {
        $state = 'paid';
    } else {
        $session = stripe_request('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId));
        if ($orders === []) {
            // Vangnet: bestellingen opzoeken via de metadata van de sessie.
            foreach (explode(',', (string) ($session['metadata']['order_ids'] ?? '')) as $id) {
                $order = order_find((int) $id);
                if ($order !== null) {
                    $orders[] = $order;
                }
            }
        }
        if ($orders !== [] && ($session['payment_status'] ?? '') === 'paid') {
            $email = (string) ($session['customer_details']['email'] ?? '');
            $orders = orders_mark_paid($orders, $email);
            $state = 'paid';
        }
    }
} catch (StripeError $e) {
    log_msg('Bedankt-pagina: controle bij Stripe mislukt: ' . $e->getMessage());
    if ($allDone($orders)) {
        $state = 'paid';
    }
}

if ($state === 'paid') {
    // Gekochte boeken uit het mandje halen (als ze erin zaten).
    $boughtIds = array_map(static fn (array $o): int => (int) $o['book_id'], $orders);
    $remaining = array_diff(cart_ids(), $boughtIds);
    if (count($remaining) !== count(cart_ids())) {
        cart_store($remaining);
    }
}

$buyerEmail = '';
foreach ($orders as $order) {
    if ($order['email'] !== '') {
        $buyerEmail = $order['email'];
        break;
    }
}

$pageTitle = $state === 'paid' ? 'Bedankt voor je aankoop' : 'Betaling wordt verwerkt';
?>
<?php if ($state !== 'paid'): ?>
<?php
// De pagina ververst zichzelf tot de betaling (bijv. iDEAL) is bevestigd.
$metaRefresh = 6;
include APP_ROOT . '/app/templates/header.php';
?>
  <div class="empty-state">
    <h1>Je betaling wordt verwerkt…</h1>
    <p>Dit duurt meestal maar een paar seconden. Deze pagina ververst zichzelf automatisch.</p>
    <p>Zodra de betaling bevestigd is, verschijnen hier je downloadlinks.
       Je ontvangt ze dan ook per e-mail.</p>
  </div>
<?php else: ?>
<?php include APP_ROOT . '/app/templates/header.php'; ?>
  <div class="thanks">
    <h1>Bedankt voor je <?= count($orders) === 1 && $orders[0]['status'] === 'free' ? 'download' : 'aankoop' ?>!</h1>
    <?php $anyDigitalOrder = false; ?>
    <?php foreach ($orders as $order): ?>
      <?php $book = $order['book_id'] ? book_find((int) $order['book_id']) : null; ?>
      <?php if ($book === null) { continue; } ?>
      <div class="thanks-item">
        <p><strong><?= e($book['title']) ?></strong></p>
        <?php if (book_is_physical($book)): ?>
          <p class="muted">Dit is een gedrukte uitgave. We versturen het boek per post naar het
             adres dat je bij het afrekenen hebt opgegeven.</p>
        <?php else: ?>
          <?php $anyDigitalOrder = true; ?>
          <p class="download-buttons">
            <?php if ($book['pdf_file'] !== ''): ?>
              <a class="btn btn-primary" href="<?= e(order_download_url($order, 'pdf')) ?>">Download PDF</a>
            <?php endif; ?>
            <?php if ($book['epub_file'] !== ''): ?>
              <a class="btn btn-primary" href="<?= e(order_download_url($order, 'epub')) ?>">Download EPUB</a>
            <?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($buyerEmail !== ''): ?>
      <p>Een bevestiging<?= $anyDigitalOrder ? ' met downloadlinks' : '' ?> is ook gemaild naar
         <strong><?= e($buyerEmail) ?></strong>.</p>
    <?php endif; ?>
    <?php if ($anyDigitalOrder): ?>
      <p class="muted">De downloadlinks zijn <?= (int) config('download_days', 90) ?> dagen geldig;
         deze uitgave is voor persoonlijk gebruik.</p>
    <?php endif; ?>
    <p><a href="<?= e(url()) ?>">← Terug naar alle publicaties</a></p>
  </div>
<?php endif; ?>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
