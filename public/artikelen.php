<?php
require __DIR__ . '/../app/bootstrap.php';

$articles = articles_published();
$pageTitle = 'Artikelen';
$metaDescription = 'Artikelen en beschouwingen van Lachman Soedamah over Suriname, het volkenrecht en de Hindostaanse gemeenschap.';
$canonicalUrl = articles_url();
include APP_ROOT . '/app/templates/header.php';
?>
<section class="intro">
  <h1>Artikelen</h1>
  <p>Beschouwingen over Suriname, recht, mens en maatschappij.</p>
</section>

<?php if (!$articles): ?>
  <div class="empty-state">
    <p>Hier verschijnen binnenkort artikelen.</p>
  </div>
<?php else: ?>
  <div class="article-grid">
    <?php foreach ($articles as $article): ?>
      <a class="article-card" href="<?= e(article_url($article['slug'])) ?>">
        <?php if ($article['cover_file'] !== ''): ?>
          <img class="article-cover" src="<?= e(url('uploads/articles/' . $article['cover_file'])) ?>"
               alt="" loading="lazy">
        <?php endif; ?>
        <span class="article-card-body">
          <span class="article-date"><?= e(format_date($article['article_date'])) ?></span>
          <strong class="article-title"><?= e($article['title']) ?></strong>
          <?php $excerpt = $article['excerpt'] !== '' ? $article['excerpt'] : article_excerpt_from_body($article['body_html']); ?>
          <?php if ($excerpt !== ''): ?>
            <span class="article-excerpt"><?= e($excerpt) ?></span>
          <?php endif; ?>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
