<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$article = article_find((int) ($_GET['id'] ?? ($_POST['id'] ?? 0)));
if ($article === null) {
    redirect(url('admin/artikelen.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($article['cover_file'] !== '') {
        @unlink(APP_ROOT . '/public/uploads/articles/' . $article['cover_file']);
    }
    $stmt = db()->prepare('DELETE FROM articles WHERE id = ?');
    $stmt->execute([$article['id']]);
    log_msg('Artikel verwijderd: ' . $article['title'] . ' (id ' . $article['id'] . ')');
    redirect(url('admin/artikelen.php?deleted=1'));
}

$pageTitle = 'Verwijderen: ' . $article['title'];
include APP_ROOT . '/app/templates/admin_header.php';
?>
<div class="form-page">
  <h1>Artikel verwijderen</h1>
  <p>Weet je zeker dat je <strong><?= e($article['title']) ?></strong> definitief wilt verwijderen?</p>
  <form method="post" action="<?= e(url('admin/artikel-verwijderen.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $article['id'] ?>">
    <div class="form-actions">
      <button type="submit" class="btn btn-danger">Ja, definitief verwijderen</button>
      <a class="btn btn-secondary" href="<?= e(url('admin/artikel-bewerken.php?id=' . $article['id'])) ?>">Annuleren</a>
    </div>
  </form>
</div>
<?php include APP_ROOT . '/app/templates/admin_footer.php'; ?>
