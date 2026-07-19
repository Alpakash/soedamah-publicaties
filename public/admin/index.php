<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$books = books_all();
$stats = db()->query(
    "SELECT COUNT(*) AS aantal, COALESCE(SUM(amount_cents), 0) AS omzet
     FROM orders WHERE status = 'paid'"
)->fetch() ?: ['aantal' => 0, 'omzet' => 0];

$pageTitle = 'Boeken';
include APP_ROOT . '/app/templates/admin_header.php';
?>
<?php if (isset($_GET['deleted'])): ?>
  <p class="alert alert-success">De publicatie is verwijderd.</p>
<?php endif; ?>

<div class="admin-toolbar">
  <h1>Boeken &amp; publicaties</h1>
  <a class="btn btn-primary" href="<?= e(url('admin/boek-bewerken.php')) ?>">+ Nieuwe publicatie</a>
</div>

<p class="stats-line">
  Verkocht: <strong><?= (int) $stats['aantal'] ?>×</strong> ·
  Omzet: <strong><?= e(format_price((int) $stats['omzet'])) ?></strong> ·
  <a href="<?= e(url('admin/bestellingen.php')) ?>">alle bestellingen bekijken</a>
</p>

<?php if (!$books): ?>
  <div class="empty-state">
    <p>Er zijn nog geen publicaties. Klik op <strong>+ Nieuwe publicatie</strong> om de eerste toe te voegen.</p>
  </div>
<?php else: ?>
  <div class="table-scroll">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Titel</th>
        <th>Prijs</th>
        <th>Bestanden</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($books as $book): ?>
      <tr>
        <td>
          <strong><?= e($book['title']) ?></strong>
          <?php if ($book['subtitle'] !== ''): ?><br><span class="muted"><?= e($book['subtitle']) ?></span><?php endif; ?>
        </td>
        <td><?= e(format_price((int) $book['price_cents'])) ?></td>
        <td><?= e(book_formats_label($book)) ?><?= $book['cover_file'] !== '' ? ' · omslag' : '' ?></td>
        <td>
          <?php if ((int) $book['published']): ?>
            <span class="badge badge-success">Online</span>
          <?php else: ?>
            <span class="badge">Concept</span>
          <?php endif; ?>
          <?php if ((int) $book['published'] && !(int) $book['in_stock']): ?>
            <span class="badge">Niet op voorraad</span>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a class="btn btn-small btn-secondary" href="<?= e(url('admin/boek-bewerken.php?id=' . $book['id'])) ?>">Bewerken</a>
          <?php if ((int) $book['published']): ?>
            <a class="btn btn-small btn-secondary" href="<?= e(url('boek.php?b=' . $book['slug'])) ?>">Bekijken</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
<?php include APP_ROOT . '/app/templates/admin_footer.php'; ?>
