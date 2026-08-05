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
$metaDescription = article_excerpt_from_body($book['description'], 155);
$canonicalUrl = book_url($book['slug']);
if ($book['cover_file'] !== '') {
    $ogImage = url('cover.php?b=' . $book['id']);
}
$isFree = (int) $book['price_cents'] <= 0;
include APP_ROOT . '/app/templates/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(url()) ?>">Publicaties</a> <span>/</span> <?= e($book['title']) ?></nav>
<article class="book-detail">
  <div class="book-detail-cover">
    <?php if ($book['cover_file'] !== ''): ?>
      <a class="cover-zoom" href="#omslag" aria-label="Bekijk de omslag groter">
        <span class="cover-frame">
          <img class="book-cover" src="<?= e(url('cover.php?b=' . $book['id'])) ?>"
               alt="Omslag van <?= e($book['title']) ?>">
        </span>
      </a>
    <?php else: ?>
      <span class="cover-frame">
        <span class="book-cover cover-fallback"><span><?= e($book['title']) ?></span></span>
      </span>
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
      <?php if (!$isFree): ?>
        <p class="buy-note">Prijs is incl. btw<?= book_is_physical($book) ? ' en verzendkosten' : '' ?>.</p>
      <?php endif; ?>
      <?php if (!book_has_deliverable($book)): ?>
        <p class="muted">Deze publicatie is nog niet te bestellen.</p>
      <?php elseif (!book_orderable($book)): ?>
        <p class="muted">Deze publicatie is tijdelijk niet op voorraad.</p>
      <?php elseif ($isFree): ?>
        <a class="btn btn-primary" href="<?= e(book_free_url($book['slug'])) ?>">Gratis downloaden</a>
        <p class="buy-note">Je ontvangt de downloadlink direct en per e-mail.</p>
      <?php else: ?>
        <?php if (in_array((int) $book['id'], cart_ids(), true)): ?>
          <a class="btn btn-primary btn-with-icon" href="<?= e(url('mandje.php')) ?>">
            <?= cart_icon_svg() ?> Bekijk mandje
          </a>
        <?php else: ?>
          <form method="post" action="<?= e(url('mandje.php')) ?>" class="inline-form">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="id" value="<?= (int) $book['id'] ?>">
            <button type="submit" class="btn btn-primary">Kopen</button>
          </form>
        <?php endif; ?>
        <?php if (book_is_physical($book)): ?>
          <p class="buy-note">Dit is een gedrukte uitgave; we versturen het boek per post naar
             het adres dat je bij het afrekenen opgeeft.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="book-description">
      <?= $book['description'] ?>
    </div>

    <?php
    // Presentatie en specificaties worden hieronder, ná de beschrijving en over
    // de volle breedte getoond. Het specificatieblok bouwen we hier één keer op,
    // zodat het zowel met als zonder presentatie op dezelfde plek eindigt.
    $slides = book_slides($book);
    $specsHtml = book_specs_html((string) ($book['specs'] ?? ''));
    $specsBlock = $specsHtml === '' ? '' :
        '<section class="book-specs" aria-label="Specificaties">'
        . '<h2 class="book-specs-title">Specificaties</h2>' . $specsHtml
        . '</section>';
    ?>
    <?php if ($slides === []) { echo $specsBlock; } // zonder presentatie: specs blijven in de kolom ?>
  </div>
</article>

<?php if ($slides !== []): ?>
<section class="deck" aria-label="Presentatie bij <?= e($book['title']) ?>">
  <h2 class="deck-title">Van staat naar natie, in beeld</h2>
  <p class="deck-lead">Blader door de dia's voor een voorproefje van de visie.</p>
  <div class="deck-viewer" data-deck>
    <div class="deck-stage">
      <?php foreach ($slides as $i => $src): ?>
        <img class="deck-slide<?= $i === 0 ? ' is-active' : '' ?>"
             src="<?= e($src) ?>"
             alt="Dia <?= $i + 1 ?> van <?= count($slides) ?>"
             draggable="false"<?= $i === 0 ? '' : ' loading="lazy"' ?>>
      <?php endforeach; ?>
    </div>
    <div class="deck-controls">
      <button type="button" class="deck-btn deck-prev" aria-label="Vorige dia">&lsaquo;</button>
      <p class="deck-counter" aria-live="polite"><span class="deck-current">1</span> / <?= count($slides) ?></p>
      <button type="button" class="deck-btn deck-next" aria-label="Volgende dia">&rsaquo;</button>
      <button type="button" class="deck-btn deck-full" aria-label="Volledig scherm">&#x2922;</button>
    </div>
  </div>
</section>
<script src="<?= e(asset_url('assets/slides.js')) ?>" defer></script>
<?= $specsBlock // ná de presentatie, over de volle breedte ?>
<?php endif; ?>

<aside class="author-mini">
  <img src="<?= e(url('assets/LSoedamah.jpeg')) ?>" alt="Portret van Lachman Soedamah">
  <div>
    <p class="author-mini-name">Over de auteur</p>
    <p><strong>Mr. dr. Lachman Soedamah</strong> is advocaat in Amsterdam en promoveerde op
       <em>Suriname compleet?</em>, een volkenrechtelijke studie naar de Surinaamse
       grensgeschillen. Hij schrijft over Suriname, recht, mens en maatschappij.
       <a href="<?= e(url('#over-de-auteur')) ?>">Lees meer →</a></p>
  </div>
</aside>
<p class="back-link"><a href="<?= e(url()) ?>">← Alle publicaties</a></p>

<?php if ($book['cover_file'] !== ''): ?>
<a class="lightbox" id="omslag" href="#!" aria-label="Sluit de vergrote omslag">
  <img src="<?= e(url('cover.php?b=' . $book['id'])) ?>" alt="Omslag van <?= e($book['title']) ?>">
</a>
<?php endif; ?>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
