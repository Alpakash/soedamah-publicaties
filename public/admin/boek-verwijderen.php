<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$book = book_find((int) ($_GET['id'] ?? ($_POST['id'] ?? 0)));
if ($book === null) {
    redirect(url('admin/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($book['cover_file'] !== '') {
        @unlink(DATA_DIR . '/uploads/covers/' . $book['cover_file']);
    }
    foreach (['pdf_file', 'epub_file'] as $column) {
        if ($book[$column] !== '') {
            @unlink(DATA_DIR . '/uploads/books/' . $book[$column]);
        }
    }
    $stmt = db()->prepare('DELETE FROM books WHERE id = ?');
    $stmt->execute([$book['id']]);
    log_msg('Publicatie verwijderd: ' . $book['title'] . ' (id ' . $book['id'] . ')');
    redirect(url('admin/?deleted=1'));
}

$pageTitle = 'Verwijderen: ' . $book['title'];
include APP_ROOT . '/app/templates/admin_header.php';
?>
<div class="form-page">
  <h1>Publicatie verwijderen</h1>
  <p>Weet je zeker dat je <strong><?= e($book['title']) ?></strong> definitief wilt verwijderen?</p>
  <p class="alert alert-error">Dit verwijdert ook de geüploade bestanden. Bestaande kopers kunnen dit boek
     daarna <strong>niet</strong> meer downloaden. Wil je het alleen uit de shop halen?
     Zet dan het vinkje “Zichtbaar in de shop” uit bij Bewerken.</p>
  <form method="post" action="<?= e(url('admin/boek-verwijderen.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $book['id'] ?>">
    <div class="form-actions">
      <button type="submit" class="btn btn-danger">Ja, definitief verwijderen</button>
      <a class="btn btn-secondary" href="<?= e(url('admin/boek-bewerken.php?id=' . $book['id'])) ?>">Annuleren</a>
    </div>
  </form>
</div>
<?php include APP_ROOT . '/app/templates/admin_footer.php'; ?>
