<?php
require __DIR__ . '/../../app/bootstrap.php';
require APP_ROOT . '/app/auth.php';
require_admin();

$articles = articles_all();

$pageTitle = 'Artikelen';
include APP_ROOT . '/app/templates/admin_header.php';
?>
<?php if (isset($_GET['deleted'])): ?>
  <p class="alert alert-success">Het artikel is verwijderd.</p>
<?php endif; ?>

<div class="admin-toolbar">
  <h1>Artikelen</h1>
  <a class="btn btn-primary" href="<?= e(url('admin/artikel-bewerken.php')) ?>">+ Nieuw artikel</a>
</div>

<?php if (!$articles): ?>
  <div class="empty-state">
    <p>Er zijn nog geen artikelen. Klik op <strong>+ Nieuw artikel</strong> om het eerste te schrijven.</p>
  </div>
<?php else: ?>
  <div class="table-scroll">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Titel</th>
        <th>Datum</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($articles as $article): ?>
      <tr>
        <td><strong><?= e($article['title']) ?></strong></td>
        <td class="nowrap"><?= e(format_date($article['article_date'])) ?></td>
        <td>
          <?php if ((int) $article['published']): ?>
            <span class="badge badge-success">Online</span>
          <?php else: ?>
            <span class="badge">Concept</span>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a class="btn btn-small btn-secondary" href="<?= e(url('admin/artikel-bewerken.php?id=' . $article['id'])) ?>">Bewerken</a>
          <?php if ((int) $article['published']): ?>
            <a class="btn btn-small btn-secondary" href="<?= e(url('artikel.php?a=' . $article['slug'])) ?>">Bekijken</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
<?php include APP_ROOT . '/app/templates/admin_footer.php'; ?>
