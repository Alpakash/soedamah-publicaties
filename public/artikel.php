<?php
require __DIR__ . '/../app/bootstrap.php';

$article = article_find_by_slug((string) ($_GET['a'] ?? ''));
if ($article === null || !(int) $article['published']) {
    http_response_code(404);
    $pageTitle = 'Niet gevonden';
    include APP_ROOT . '/app/templates/header.php';
    echo '<div class="empty-state"><p>Dit artikel bestaat niet (meer).</p>'
        . '<p><a class="btn btn-secondary" href="' . e(articles_url()) . '">Terug naar de artikelen</a></p></div>';
    include APP_ROOT . '/app/templates/footer.php';
    exit;
}

$pageTitle = $article['title'];
$metaDescription = $article['excerpt'] !== '' ? $article['excerpt'] : article_excerpt_from_body($article['body_html']);
$canonicalUrl = article_url($article['slug']);
if ($article['cover_file'] !== '') {
    $ogImage = url('uploads/articles/' . $article['cover_file']);
}
include APP_ROOT . '/app/templates/header.php';
?>
<nav class="breadcrumb"><a href="<?= e(articles_url()) ?>">Artikelen</a> <span>/</span> <?= e($article['title']) ?></nav>
<?php if (isset($_COOKIE['sp_admin']) && admin_logged_in()): ?>
  <p class="admin-edit-bar">
    <a class="btn btn-small btn-secondary" href="<?= e(url('admin/artikel-bewerken.php?id=' . $article['id'])) ?>">Bewerken</a>
  </p>
<?php endif; ?>
<article class="article-detail">
  <p class="article-date"><?= e(format_date($article['article_date'])) ?></p>
  <h1><?= e($article['title']) ?></h1>
  <?php if ($article['cover_file'] !== ''): ?>
    <img class="article-hero" src="<?= e(url('uploads/articles/' . $article['cover_file'])) ?>"
         alt="">
  <?php endif; ?>
  <div class="article-body"><?= $article['body_html'] ?></div>
</article>

<aside class="author-mini">
  <img src="<?= e(url('assets/LSoedamah.jpeg')) ?>" alt="Portret van Lachman Soedamah">
  <div>
    <p class="author-mini-name">Geschreven door</p>
    <p><strong>Mr. dr. Lachman Soedamah</strong> is advocaat in Amsterdam en schrijft over
       Suriname, recht, mens en maatschappij.
       <a href="<?= e(url('#over-de-auteur')) ?>">Lees meer →</a></p>
  </div>
</aside>
<p class="back-link"><a href="<?= e(articles_url()) ?>">← Alle artikelen</a></p>
<?php include APP_ROOT . '/app/templates/footer.php'; ?>
