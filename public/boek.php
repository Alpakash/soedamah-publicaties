<?php
require __DIR__ . '/../app/bootstrap.php';

$book = book_find_by_slug((string) ($_GET['b'] ?? ''));
if ($book === null || !(int) $book['published']) {
    http_response_code(404);
    $pageTitle = 'Niet gevonden';
    include APP_ROOT . '/app/templates/header.php';
    echo '<div class="empty-state"><p>Deze publicatie bestaat niet (meer).</p>'
        . '<p><a class="btn btn-secondary" href="' . e(url()) . '">Terug naar het overzicht</a></p></div>';
    include APP_ROOT . '/app/templates/footer.php';
    exit;
}

$pageTitle = $book['title'];
$metaDescription = mb_substr(trim(preg_replace('/\s+/u', ' ', $book['description']) ?? ''), 0, 155);
if ($book['cover_file'] !== '') {
    $ogImage = url('cover.php?b=' . $book['id']);
}
$isFree = (int) $book['price_cents'] <= 0;
include APP_ROOT . '/app/templates/header.php';
?>
<article class="book-detail">
  <div class="book-detail-cover">
    <?php if ($book['cover_file'] !== ''): ?>
      <img class="book-cover" src="<?= e(url('cover.php?b=' . $book['id'])) ?>"
           alt="Omslag van <?= e($book['title']) ?>">
    <?php else: ?>
      <span class="book-cover cover-fallback"><span><?= e($book['title']) ?></span></span>
    <?php endif; ?>
  </div>
  <div class="book-detail-info">
    <h1><?= e($book['title']) ?></h1>
    <?php if ($book['subtitle'] !== ''): ?>
      <p class="book-detail-subtitle"><?= e($book['subtitle']) ?></p>
    <?php endif; ?>
    <p class="book-detail-author">door Lachman Soedamah</p>

    <div class="buy-box">
      <p class="buy-price"><?= e(format_price((int) $book['price_cents'])) ?>
        <span class="formats"><?= e(book_formats_label($book)) ?></span>
      </p>
      <?php if (!book_has_files($book)): ?>
        <p class="muted">Deze publicatie verschijnt binnenkort en is nog niet te bestellen.</p>
      <?php elseif ($isFree): ?>
        <a class="btn btn-primary" href="<?= e(url('gratis.php?b=' . $book['slug'])) ?>">Gratis downloaden</a>
        <p class="buy-note">Je ontvangt de downloadlink direct en per e-mail.</p>
      <?php else: ?>
        <a class="btn btn-primary" href="<?= e(url('afrekenen.php?b=' . $book['slug'])) ?>">Nu kopen</a>
        <p class="buy-note">Veilig betalen via iDEAL of creditcard (Stripe).<br>
           Direct downloaden na betaling; je ontvangt de link ook per e-mail.</p>
      <?php endif; ?>
    </div>

    <div class="book-description">
      <?= text_to_html($book['description']) ?>
    </div>
  </div>
</article>
<p class="back-link"><a href="<?= e(url()) ?>">← Alle publicaties</a></p>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
