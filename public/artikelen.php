<?php
require __DIR__ . '/../app/bootstrap.php';

$articles = articles_published();
$pageTitle = 'Artikelen';
$metaDescription = 'Artikelen en beschouwingen van Lachman Soedamah over Suriname, recht, mens en maatschappij.';
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
      <?php
        $hasCover = $article['cover_file'] !== '';
        $showDate = !article_hide_date($article);
        $inMedia = article_in_media($article);
        $hasMeta = $showDate || $inMedia;
        $excerpt = $article['excerpt'] !== '' ? $article['excerpt'] : article_excerpt_from_body($article['body_html']);
      ?>
      <a class="article-card <?= $hasCover ? '' : 'article-card--text' ?>" href="<?= e(article_url($article['slug'])) ?>">
        <?php if ($hasCover): ?>
          <img class="article-cover" src="<?= e(url('uploads/articles/' . $article['cover_file'])) ?>"
               alt="" loading="lazy">
        <?php elseif ($hasMeta): ?>
          <span class="article-card-kicker">
            <?php if ($showDate): ?>
              <span class="article-date"><?= calendar_icon_svg() ?><?= e(format_date($article['article_date'])) ?></span>
            <?php endif; ?>
            <?php if ($inMedia): ?>
              <span class="badge badge-media">Verschenen in de media</span>
            <?php endif; ?>
          </span>
        <?php endif; ?>
        <span class="article-card-body">
          <?php if ($hasCover && $hasMeta): ?>
            <span class="article-card-meta">
              <?php if ($showDate): ?>
                <span class="article-date"><?= calendar_icon_svg() ?><?= e(format_date($article['article_date'])) ?></span>
              <?php endif; ?>
              <?php if ($inMedia): ?>
                <span class="badge badge-media">Verschenen in de media</span>
              <?php endif; ?>
            </span>
          <?php endif; ?>
          <strong class="article-title"><?= e($article['title']) ?></strong>
          <span class="article-title-rule" aria-hidden="true"></span>
          <?php if ($excerpt !== ''): ?>
            <span class="article-excerpt"><?= e($excerpt) ?></span>
          <?php endif; ?>
          <span class="article-more">Lees verder <span aria-hidden="true">→</span></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
