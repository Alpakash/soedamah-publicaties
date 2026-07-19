<?php
require __DIR__ . '/../app/bootstrap.php';

$book = book_find_by_slug((string) ($_GET['b'] ?? ($_POST['b'] ?? '')));
if ($book === null || !(int) $book['published'] || (int) $book['price_cents'] > 0) {
    redirect(url());
}
if (!book_orderable($book)) {
    redirect(url('boek.php?b=' . $book['slug']));
}

$error = '';
$order = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot: echte bezoekers laten dit veld leeg.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        redirect(url());
    }
    $email = trim((string) ($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Vul een geldig e-mailadres in.';
    } else {
        // Bestaande gratis bestelling voor dit adres hergebruiken in plaats van stapelen.
        $stmt = db()->prepare(
            "SELECT * FROM orders WHERE book_id = ? AND email = ? AND status = 'free' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$book['id'], $email]);
        $order = $stmt->fetch() ?: null;
        if ($order === null) {
            $order = order_create($book, 'free', $email);
            order_send_links($order);
            order_notify_admin($order);
        }
    }
}

$pageTitle = 'Gratis download: ' . $book['title'];
include APP_ROOT . '/app/templates/header.php';
?>
<?php if ($order !== null): ?>
  <div class="thanks">
    <h1>Veel leesplezier!</h1>
    <p>Je kunt <strong><?= e($book['title']) ?></strong> nu downloaden:</p>
    <p class="download-buttons">
      <?php if ($book['pdf_file'] !== ''): ?>
        <a class="btn btn-primary" href="<?= e(order_download_url($order, 'pdf')) ?>">Download PDF</a>
      <?php endif; ?>
      <?php if ($book['epub_file'] !== ''): ?>
        <a class="btn btn-primary" href="<?= e(order_download_url($order, 'epub')) ?>">Download EPUB</a>
      <?php endif; ?>
    </p>
    <p>De links zijn ook gemaild naar <strong><?= e($order['email']) ?></strong>.</p>
    <p><a href="<?= e(url()) ?>">← Terug naar alle publicaties</a></p>
  </div>
<?php else: ?>
  <div class="form-page">
    <h1>Gratis download</h1>
    <p>Vul je e-mailadres in en je ontvangt direct de downloadlink voor
       <strong><?= e($book['title']) ?></strong>.</p>
    <?php if ($error !== ''): ?>
      <p class="alert alert-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('gratis.php')) ?>" class="stacked-form">
      <input type="hidden" name="b" value="<?= e($book['slug']) ?>">
      <p class="hp-field" aria-hidden="true">
        <label>Laat dit veld leeg <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </p>
      <label for="email">E-mailadres</label>
      <input type="email" id="email" name="email" required
             value="<?= e((string) ($_POST['email'] ?? '')) ?>" placeholder="naam@voorbeeld.nl">
      <button type="submit" class="btn btn-primary">Stuur mij de downloadlink</button>
    </form>
    <p class="muted">We gebruiken je e-mailadres alleen om de downloadlink te sturen.</p>
    <p class="back-link"><a href="<?= e(url('boek.php?b=' . $book['slug'])) ?>">← Terug naar de publicatie</a></p>
  </div>
<?php endif; ?>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
