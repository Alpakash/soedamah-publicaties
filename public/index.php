<?php
require __DIR__ . '/../app/bootstrap.php';

$books = books_published();
$pageTitle = 'Boeken en publicaties';
$metaDescription = 'Boeken en publicaties van Lachman Soedamah, direct te downloaden als PDF of EPUB.';
include APP_ROOT . '/app/templates/header.php';
?>
<section class="intro">
  <h1>Publicaties</h1>
  <p>Boeken van Lachman Soedamah, direct te downloaden als PDF of EPUB.
     Betalen gaat veilig via iDEAL of creditcard.</p>
</section>

<?php if (!$books): ?>
  <div class="empty-state">
    <p>Binnenkort verschijnen hier de publicaties van Lachman Soedamah.</p>
    <p>Kom snel nog eens terug, of lees alvast de artikelen op
       <a href="https://soedamah.nl/publicaties">soedamah.nl</a>.</p>
  </div>
<?php else: ?>
  <div class="book-grid">
    <?php foreach ($books as $book): ?>
      <a class="book-card" href="<?= e(url('boek.php?b=' . $book['slug'])) ?>">
        <?php if ($book['cover_file'] !== ''): ?>
          <img class="book-cover" src="<?= e(url('cover.php?b=' . $book['id'])) ?>"
               alt="Omslag van <?= e($book['title']) ?>" loading="lazy">
        <?php else: ?>
          <span class="book-cover cover-fallback"><span><?= e($book['title']) ?></span></span>
        <?php endif; ?>
        <span class="book-card-body">
          <strong class="book-title"><?= e($book['title']) ?></strong>
          <?php if ($book['subtitle'] !== ''): ?>
            <span class="book-subtitle"><?= e($book['subtitle']) ?></span>
          <?php endif; ?>
          <span class="book-meta">
            <span class="price"><?= e(format_price((int) $book['price_cents'])) ?></span>
            <span class="formats"><?= e(book_formats_label($book)) ?></span>
          </span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
