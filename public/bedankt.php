<?php
require __DIR__ . '/../app/bootstrap.php';

$sessionId = (string) ($_GET['sid'] ?? '');
if ($sessionId === '' || strlen($sessionId) > 255) {
    redirect(url());
}

$order = order_find_by_session($sessionId);
$state = 'processing';

try {
    if ($order !== null && in_array($order['status'], ['paid', 'free'], true)) {
        $state = 'paid';
    } else {
        $session = stripe_request('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId));
        if ($order === null) {
            // Vangnet: bestelling alsnog aanmaken op basis van de sessie-metadata.
            $bookId = (int) ($session['metadata']['book_id'] ?? 0);
            $book = $bookId > 0 ? book_find($bookId) : null;
            if ($book !== null) {
                $order = order_create($book, 'pending', '', $sessionId);
            }
        }
        if ($order !== null && ($session['payment_status'] ?? '') === 'paid') {
            $email = (string) ($session['customer_details']['email'] ?? '');
            $order = order_mark_paid($order, $email);
            $state = 'paid';
        }
    }
} catch (StripeError $e) {
    log_msg('Bedankt-pagina: controle bij Stripe mislukt: ' . $e->getMessage());
}

$book = ($order !== null && $order['book_id']) ? book_find((int) $order['book_id']) : null;
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
    <h1>Bedankt voor je <?= $order['status'] === 'free' ? 'download' : 'aankoop' ?>!</h1>
    <?php if ($book !== null): ?>
      <p>Je kunt <strong><?= e($book['title']) ?></strong> nu downloaden:</p>
      <p class="download-buttons">
        <?php if ($book['pdf_file'] !== ''): ?>
          <a class="btn btn-primary" href="<?= e(order_download_url($order, 'pdf')) ?>">Download PDF</a>
        <?php endif; ?>
        <?php if ($book['epub_file'] !== ''): ?>
          <a class="btn btn-primary" href="<?= e(order_download_url($order, 'epub')) ?>">Download EPUB</a>
        <?php endif; ?>
      </p>
      <?php if ($order['email'] !== ''): ?>
        <p>De links zijn ook gemaild naar <strong><?= e($order['email']) ?></strong>.</p>
      <?php endif; ?>
      <p class="muted">De links zijn <?= (int) config('download_days', 90) ?> dagen geldig;
         deze uitgave is voor persoonlijk gebruik.</p>
    <?php else: ?>
      <p>Je betaling is ontvangen. De downloadlinks zijn per e-mail verstuurd.</p>
    <?php endif; ?>
    <p><a href="<?= e(url()) ?>">← Terug naar alle publicaties</a></p>
  </div>
<?php endif; ?>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
