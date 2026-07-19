<?php
require __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'add' && $id > 0) {
        $book = book_find($id);
        if ($book !== null && (int) $book['published'] === 1
            && (int) $book['price_cents'] > 0 && book_orderable($book)) {
            cart_store(array_merge(cart_ids(), [$id]));
        }
    } elseif ($action === 'remove' && $id > 0) {
        cart_store(array_diff(cart_ids(), [$id]));
    }
    redirect(url('mandje.php'));
}

$books = cart_books();
// Cookie opschonen als er boeken offline zijn gehaald.
if (count($books) !== count(cart_ids())) {
    cart_store(array_map(static fn (array $b): int => (int) $b['id'], $books));
}

$pageTitle = 'Winkelmandje';
include APP_ROOT . '/app/templates/header.php';
?>
<div class="cart-page">
  <h1>Winkelmandje</h1>

  <?php if ($books === []): ?>
    <div class="empty-state">
      <p>Je mandje is leeg.</p>
      <p><a class="btn btn-secondary" href="<?= e(url()) ?>">Bekijk de publicaties</a></p>
    </div>
  <?php else: ?>
    <div class="cart-list">
      <?php foreach ($books as $book): ?>
        <div class="cart-row">
          <?php if ($book['cover_file'] !== ''): ?>
            <a href="<?= e(url('boek.php?b=' . $book['slug'])) ?>">
              <img class="cart-thumb" src="<?= e(url('cover.php?b=' . $book['id'])) ?>"
                   alt="Omslag van <?= e($book['title']) ?>">
            </a>
          <?php endif; ?>
          <div class="cart-row-info">
            <a class="cart-row-title" href="<?= e(url('boek.php?b=' . $book['slug'])) ?>"><?= e($book['title']) ?></a>
            <span class="formats"><?= e(book_formats_label($book)) ?></span>
          </div>
          <span class="cart-row-price"><?= e(format_price((int) $book['price_cents'])) ?></span>
          <form method="post" action="<?= e(url('mandje.php')) ?>" class="inline-form">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="id" value="<?= (int) $book['id'] ?>">
            <button type="submit" class="cart-remove" aria-label="Verwijder <?= e($book['title']) ?> uit het mandje">×</button>
          </form>
        </div>
      <?php endforeach; ?>
      <div class="cart-row cart-total-row">
        <span class="cart-row-info"><strong>Totaal</strong></span>
        <span class="cart-row-price"><strong><?= e(format_price(cart_total($books))) ?></strong></span>
        <span class="cart-remove-spacer"></span>
      </div>
      <p class="field-hint">Prijzen zijn inclusief 9% btw.</p>
    </div>

    <div class="form-actions">
      <a class="btn btn-primary btn-large" href="<?= e(url('afrekenen.php')) ?>">Afrekenen</a>
      <a class="btn btn-secondary" href="<?= e(url()) ?>">Verder kijken</a>
    </div>
  <?php endif; ?>
</div>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
