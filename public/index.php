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

<section class="trust-row">
  <div class="trust-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 11 5 5 5-5"/><path d="M5 21h14"/></svg>
    <h3>Direct downloaden</h3>
    <p>Na betaling staan je bestanden meteen klaar — en je krijgt de links ook per e-mail.</p>
  </div>
  <div class="trust-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 3 8.2 7 10 4-1.8 7-5.5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
    <h3>Veilig betalen</h3>
    <p>Afrekenen met iDEAL of creditcard, via het beveiligde betaalplatform Stripe.</p>
  </div>
  <div class="trust-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="13" height="16" rx="2"/><path d="M19 7h1a1 1 0 0 1 1 1v10a2 2 0 0 1-2 2h-6"/><path d="M7 9h5M7 13h5"/></svg>
    <h3>Voor elk apparaat</h3>
    <p>PDF voor computer en tablet, EPUB voor je e-reader — waar beschikbaar krijg je beide.</p>
  </div>
  <div class="trust-item">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-4.6-9.5-9A5.4 5.4 0 0 1 12 6.5 5.4 5.4 0 0 1 21.5 12C19 16.4 12 21 12 21Z"/></svg>
    <h3>Rechtstreeks van de auteur</h3>
    <p>Geen tussenhandel: je aankoop steunt het schrijfwerk van Lachman Soedamah direct.</p>
  </div>
</section>

<section id="over-de-auteur" class="author-band">
  <div class="author-photo">
    <img src="<?= e(url('assets/auteur.jpg')) ?>" alt="Portret van mr. dr. Lachman Soedamah">
  </div>
  <div class="author-text">
    <h2 class="section-title">Over de auteur</h2>
    <p><strong>Mr. dr. Lachman Soedamah</strong> studeerde rechten aan de Anton de Kom
       Universiteit van Suriname en is sinds 1988 advocaat in Amsterdam. Wat begon bij de
       Rechtswinkel Migranten en in de Bijlmer groeide uit tot
       <a href="https://soedamah.nl">Soedamah Advocaten</a> — een kantoor dat al meer dan
       35 jaar opkomt voor mensen die hun recht zoeken.</p>
    <p>In 2014 promoveerde hij aan de Open Universiteit op <em>Suriname compleet?</em>,
       een volkenrechtelijke studie naar de grensgeschillen van Suriname met Guyana en
       Frans-Guyana. Zijn werk beweegt zich sindsdien op het snijvlak van recht,
       geschiedenis en samenleving: de staatkundige toekomst van Suriname, de Hindostaanse
       emancipatie en de betekenis van integer leiderschap.</p>
    <blockquote class="author-quote">
      <p>„Leiderschap wordt uiteindelijk niet beoordeeld op afkomst, maar op integriteit.
         Niet op retoriek, maar op resultaten."</p>
      <cite>— Lachman Soedamah</cite>
    </blockquote>
    <p>Zijn boeken en essays verschijnen op deze pagina rechtstreeks van de auteur;
       zijn artikelen lees je op <a href="https://soedamah.nl/publicaties">soedamah.nl/publicaties</a>.</p>
  </div>
</section>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
