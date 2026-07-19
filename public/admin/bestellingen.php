<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $order = order_find((int) ($_POST['order_id'] ?? 0));
    $action = (string) ($_POST['action'] ?? '');
    $msg = '';
    if ($order !== null && $action === 'reset') {
        $stmt = db()->prepare(
            'UPDATE orders SET downloads_pdf = 0, downloads_epub = 0, expires_at = ? WHERE id = ?'
        );
        $stmt->execute([order_expiry_from_now(), $order['id']]);
        $msg = 'reset';
    } elseif ($order !== null && $action === 'resend') {
        $stmt = db()->prepare('UPDATE orders SET email_sent_at = NULL WHERE id = ?');
        $stmt->execute([$order['id']]);
        $refreshed = order_find((int) $order['id']);
        $msg = ($refreshed !== null && order_send_links($refreshed)) ? 'mailok' : 'mailfout';
    }
    redirect(url('admin/bestellingen.php' . ($msg !== '' ? '?msg=' . $msg : '')));
}

$orders = db()->query('SELECT * FROM orders ORDER BY id DESC LIMIT 200')->fetchAll();

$statusLabels = [
    'paid'    => ['Betaald', 'badge-success'],
    'free'    => ['Gratis', 'badge-success'],
    'pending' => ['Wacht op betaling', ''],
    'failed'  => ['Mislukt', 'badge-error'],
];

$pageTitle = 'Bestellingen';
include APP_ROOT . '/app/templates/admin_header.php';
?>
<h1>Bestellingen</h1>

<?php if (($_GET['msg'] ?? '') === 'reset'): ?>
  <p class="alert alert-success">Downloads gereset en de geldigheid van de link is verlengd.</p>
<?php elseif (($_GET['msg'] ?? '') === 'mailok'): ?>
  <p class="alert alert-success">De e-mail met downloadlinks is opnieuw verstuurd.</p>
<?php elseif (($_GET['msg'] ?? '') === 'mailfout'): ?>
  <p class="alert alert-error">De e-mail kon niet worden verstuurd. Controleer het e-mailadres van de bestelling.</p>
<?php endif; ?>

<?php if (!$orders): ?>
  <div class="empty-state"><p>Er zijn nog geen bestellingen.</p></div>
<?php else: ?>
  <div class="table-scroll">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Datum</th>
        <th>Publicatie</th>
        <th>Koper</th>
        <th>Bedrag</th>
        <th>Status</th>
        <th>Downloads</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $order): ?>
        <?php [$label, $badgeClass] = $statusLabels[$order['status']] ?? [$order['status'], '']; ?>
        <?php $orderBook = $order['book_id'] ? book_find((int) $order['book_id']) : null; ?>
        <?php $isPhysicalOrder = $orderBook !== null && book_is_physical($orderBook); ?>
      <tr>
        <td class="nowrap"><?= e(format_datetime($order['created_at'])) ?></td>
        <td><?= e($order['book_title']) ?></td>
        <td>
          <?php if ($order['name'] !== ''): ?><?= e($order['name']) ?><br><?php endif; ?>
          <?= $order['email'] !== '' ? '<span class="muted">' . e($order['email']) . '</span>' : '<span class="muted">-</span>' ?>
          <?php if ($order['shipping_address'] !== ''): ?>
            <br><span class="muted"><?= nl2br(e($order['shipping_address'])) ?></span>
          <?php endif; ?>
        </td>
        <td class="nowrap"><?= e(format_price((int) $order['amount_cents'])) ?></td>
        <td><span class="badge <?= e($badgeClass) ?>"><?= e($label) ?></span></td>
        <td class="nowrap">
          <?php if ($isPhysicalOrder): ?>
            Verzending per post
          <?php else: ?>
            PDF <?= (int) $order['downloads_pdf'] ?>× · EPUB <?= (int) $order['downloads_epub'] ?>×<br>
            <span class="muted">geldig t/m <?= e(format_date($order['expires_at'])) ?></span>
          <?php endif; ?>
        </td>
        <td class="actions actions-stack">
          <?php if (in_array($order['status'], ['paid', 'free'], true)): ?>
            <?php if (!$isPhysicalOrder): ?>
            <form method="post" action="<?= e(url('admin/bestellingen.php')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
              <input type="hidden" name="action" value="reset">
              <button type="submit" class="btn btn-small btn-secondary" title="Zet de downloadteller op nul en verleng de geldigheid">Reset&nbsp;&amp;&nbsp;verleng</button>
            </form>
            <?php endif; ?>
            <?php if ($order['email'] !== ''): ?>
            <form method="post" action="<?= e(url('admin/bestellingen.php')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
              <input type="hidden" name="action" value="resend">
              <button type="submit" class="btn btn-small btn-secondary">Mail&nbsp;opnieuw</button>
            </form>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p class="muted">Gebruik “Reset &amp; verleng” als een koper zijn downloadlimiet heeft bereikt of de link is verlopen.</p>
<?php endif; ?>
<?php include APP_ROOT . '/app/templates/admin_footer.php'; ?>
