<?php
require __DIR__ . '/../app/bootstrap.php';

$books = books_published();
$featured = $books[0] ?? null;
$isNewBook = static function (array $book): bool {
    return book_has_files($book)
        && (strtotime($book['created_at'] . ' UTC') > time() - 45 * 86400);
};

$pageTitle = 'Boeken en essays van Lachman Soedamah';
$metaDescription = 'Boeken en essays van mr. dr. Lachman Soedamah — over Suriname, het volkenrecht en de Hindostaanse gemeenschap. Direct te downloaden als PDF of EPUB.';
if ($featured !== null && $featured['cover_file'] !== '') {
    $ogImage = url('cover.php?b=' . $featured['id']);
}
include APP_ROOT . '/app/templates/header.php';
?>
<section class="hero">
  <div class="hero-text">
    <p class="kicker">Boeken &amp; essays · rechtstreeks van de auteur</p>
    <h1>Publicaties van<br>Lachman Soedamah</h1>
    <p class="hero-lead">Over Suriname en het volkenrecht, de erfenis van de Hindostaanse
       contractarbeid en de vraag wat integer leiderschap betekent.
       Direct te downloaden als PDF of EPUB.</p>
    <p class="hero-actions">
      <a class="btn btn-primary btn-large" href="#publicaties">Bekijk de publicaties</a>
      <a class="btn btn-ghost" href="#over-de-auteur">Over de auteur</a>
    </p>
  </div>
  <div class="hero-visual">
    <?php if ($featured !== null): ?>
      <a class="hero-book" href="<?= e(url('boek.php?b=' . $featured['slug'])) ?>">
        <span class="cover-frame">
          <?php if (!book_has_files($featured)): ?>
            <span class="badge-floating">Binnenkort</span>
          <?php elseif (!book_orderable($featured)): ?>
            <span class="badge-floating">Niet op voorraad</span>
          <?php elseif ($isNewBook($featured)): ?>
            <span class="badge-floating">Nieuw</span>
          <?php endif; ?>
          <?php if ($featured['cover_file'] !== ''): ?>
            <img class="book-cover" src="<?= e(url('cover.php?b=' . $featured['id'])) ?>"
                 alt="Omslag van <?= e($featured['title']) ?>">
          <?php else: ?>
            <span class="book-cover cover-fallback"><span><?= e($featured['title']) ?></span></span>
          <?php endif; ?>
        </span>
        <span class="hero-book-caption">
          <strong><?= e($featured['title']) ?></strong>
          <span><?= book_has_files($featured)
              ? e(format_price((int) $featured['price_cents'])) . ' · ' . e(book_formats_label($featured))
              : 'Verschijnt binnenkort' ?></span>
        </span>
      </a>
    <?php else: ?>
      <span class="cover-frame portrait-frame">
        <img class="portrait" src="<?= e(url('assets/auteur.jpg')) ?>" alt="Portret van Lachman Soedamah">
      </span>
      <p class="hero-book-caption"><strong>mr. dr. Lachman Soedamah</strong>
        <span>advocaat &amp; auteur</span></p>
    <?php endif; ?>
  </div>
</section>

<section id="publicaties" class="shop-section">
  <h2 class="section-title">Alle publicaties</h2>
  <?php if (!$books): ?>
    <div class="empty-state">
      <p><strong>De eerste publicatie verschijnt hier binnenkort.</strong></p>
      <p>Lees in de tussentijd de artikelen van Lachman Soedamah op
         <a href="https://soedamah.nl/publicaties">soedamah.nl</a>.</p>
    </div>
  <?php else: ?>
    <div class="book-grid">
      <?php foreach ($books as $book): ?>
        <a class="book-card" href="<?= e(url('boek.php?b=' . $book['slug'])) ?>">
          <span class="cover-frame">
            <?php if (!book_has_files($book)): ?>
              <span class="badge-floating">Binnenkort</span>
            <?php elseif (!book_orderable($book)): ?>
              <span class="badge-floating">Niet op voorraad</span>
            <?php elseif ($isNewBook($book)): ?>
              <span class="badge-floating">Nieuw</span>
            <?php endif; ?>
            <?php if ($book['cover_file'] !== ''): ?>
              <img class="book-cover" src="<?= e(url('cover.php?b=' . $book['id'])) ?>"
                   alt="Omslag van <?= e($book['title']) ?>" loading="lazy">
            <?php else: ?>
              <span class="book-cover cover-fallback"><span><?= e($book['title']) ?></span></span>
            <?php endif; ?>
          </span>
          <span class="book-card-body">
            <strong class="book-title"><?= e($book['title']) ?></strong>
            <?php if ($book['subtitle'] !== ''): ?>
              <span class="book-subtitle"><?= e($book['subtitle']) ?></span>
            <?php endif; ?>
            <span class="book-meta">
              <?php if (book_has_files($book)): ?>
                <span class="price"><?= e(format_price((int) $book['price_cents'])) ?></span>
              <?php endif; ?>
              <span class="formats"><?= e(book_formats_label($book)) ?></span>
            </span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section id="over-de-auteur" class="author-band">
  <div class="author-photo">
    <img src="<?= e(url('assets/auteur.jpg')) ?>" alt="Portret van mr. dr. Lachman Soedamah">
  </div>
  <div class="author-text">
    <h2 class="section-title">Over de auteur</h2>
    <p><strong>Mr. dr. Lachman Soedamah</strong> is advocaat, auteur en onderzoeker. Sinds 1988
       is hij werkzaam als advocaat in Amsterdam en richtte hij in 1999
       <a href="https://soedamah.nl">Soedamah Advocaten</a> op. Daarnaast promoveerde hij aan
       de Open Universiteit op een studie naar de Surinaamse grensgeschillen vanuit het
       internationaal recht.</p>
    <p>Naast zijn juridische praktijk zet hij zich al decennialang in voor maatschappelijke
       vraagstukken op het gebied van recht, migratie, democratie en de Surinaamse diaspora.
       Hij publiceert regelmatig over staatsrechtelijke en maatschappelijke thema's en draagt
       actief bij aan het publieke debat over de toekomst van Suriname. Voor zijn langdurige
       maatschappelijke verdiensten is hij zowel door Suriname als door Nederland onderscheiden.</p>
    <p>Met <em>Diasporavisie 2050 – Van territoriale staat naar mondiale Surinaamse natie</em>
       presenteert Lachman Soedamah een vernieuwende visie op de rol van de Surinaamse diaspora
       als strategische partner in de ontwikkeling van Suriname.</p>
    <blockquote class="author-quote">
      <p>„Leiderschap wordt uiteindelijk niet beoordeeld op afkomst, maar op integriteit.
         Niet op retoriek, maar op resultaten."</p>
      <cite>— Lachman Soedamah</cite>
    </blockquote>
  </div>
</section>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
