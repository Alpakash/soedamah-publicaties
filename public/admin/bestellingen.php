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
    } elseif ($order !== null && $action === 'delete') {
        $stmt = db()->prepare('DELETE FROM orders WHERE id = ?');
        $stmt->execute([$order['id']]);
        $msg = 'verwijderd';
    } elseif ($order !== null && $action === 'notify') {
        // Verstuur de verkopersmelding voor deze bestelling (nogmaals).
        $msg = order_notify_admin($order) ? 'meldingok' : 'meldingfout';
    } elseif ($order !== null && $action === 'remind' && $order['email'] !== '') {
        // Stuur handmatig de "lukt het betalen?"-herinnering naar de koper. Verzamel
        // alle nog niet-betaalde bestellingen van hetzelfde e-mailadres, zodat er
        // één nette mail uitgaat i.p.v. één per (mislukte) poging.
        $stmt = db()->prepare(
            "SELECT * FROM orders WHERE email = ? AND status IN ('pending', 'expired', 'failed') ORDER BY id"
        );
        $stmt->execute([$order['email']]);
        $group = $stmt->fetchAll();
        if ($group === []) {
            $group = [$order];
        }
        $msg = order_send_payment_reminder($group) ? 'herinneringok' : 'herinneringfout';
    } elseif ($action === 'testmail') {
        // Testbericht om te controleren of e-mail versturen werkt.
        $to = admin_notify_email();
        $body = "Dit is een testbericht van de webshop.\n\n"
            . "Ontvang je deze e-mail, dan werkt het versturen van mail vanaf de webshop\n"
            . "en komen ook de bestelmeldingen op dit adres binnen.\n\n"
            . base_url() . "\n";
        $msg = send_mail($to, 'Testmail van de webshop', $body) ? 'testok' : 'testfout';
    }
    redirect(url('admin/bestellingen.php' . ($msg !== '' ? '?msg=' . $msg : '')));
}

// Ruim automatisch op: bestellingen die al langer dan 3 dagen op betaling wachten,
// worden als 'verlopen' gemarkeerd. De Stripe-betaalsessie is dan allang vervallen.
orders_expire_stale(3);

$statusLabels = [
    'paid'    => ['Betaald', 'badge-success'],
    'free'    => ['Gratis', 'badge-success'],
    'pending' => ['Wacht op betaling', ''],
    'expired' => ['Verlopen', 'badge-muted'],
    'failed'  => ['Mislukt', 'badge-error'],
];

$allOrders = db()->query('SELECT * FROM orders ORDER BY id DESC LIMIT 500')->fetchAll();

// Tellingen per status voor de filterknoppen.
$counts = ['all' => count($allOrders)];
foreach ($allOrders as $o) {
    $s = (string) $o['status'];
    $counts[$s] = ($counts[$s] ?? 0) + 1;
}

// Actief filter: 'all' of een geldige status.
$filter = (string) ($_GET['status'] ?? 'all');
if ($filter !== 'all' && !isset($statusLabels[$filter])) {
    $filter = 'all';
}
$orders = $filter === 'all'
    ? $allOrders
    : array_values(array_filter($allOrders, static fn (array $o): bool => (string) $o['status'] === $filter));

// Filterknoppen: alleen tonen wat daadwerkelijk voorkomt (naast "Alle").
$filterTabs = [['all', 'Alle']];
foreach ($statusLabels as $key => [$label]) {
    if (($counts[$key] ?? 0) > 0) {
        $filterTabs[] = [$key, $label];
    }
}

$adminNotifyEmail = admin_notify_email();

$pageTitle = 'Bestellingen';
include APP_ROOT . '/app/templates/admin_header.php';
?>
<div class="page-head">
  <h1>Bestellingen</h1>
  <form method="post" action="<?= e(url('admin/bestellingen.php')) ?>" class="inline-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="testmail">
    <button type="submit" class="btn btn-small btn-secondary"
            title="Stuurt een testbericht naar <?= e($adminNotifyEmail) ?> om te controleren of e-mail werkt">Testmail versturen</button>
  </form>
</div>
<p class="muted">Meldingen van nieuwe bestellingen gaan naar <strong><?= e($adminNotifyEmail) ?></strong>.</p>

<?php if (($_GET['msg'] ?? '') === 'reset'): ?>
  <p class="alert alert-success">Downloads gereset en de geldigheid van de link is verlengd.</p>
<?php elseif (($_GET['msg'] ?? '') === 'mailok'): ?>
  <p class="alert alert-success">De e-mail met downloadlinks is opnieuw verstuurd.</p>
<?php elseif (($_GET['msg'] ?? '') === 'mailfout'): ?>
  <p class="alert alert-error">De e-mail kon niet worden verstuurd. Controleer het e-mailadres van de bestelling.</p>
<?php elseif (($_GET['msg'] ?? '') === 'verwijderd'): ?>
  <p class="alert alert-success">De bestelling is verwijderd.</p>
<?php elseif (($_GET['msg'] ?? '') === 'meldingok'): ?>
  <p class="alert alert-success">De verkopersmelding is opnieuw verstuurd naar <?= e($adminNotifyEmail) ?>. Controleer ook de map Spam/Ongewenst.</p>
<?php elseif (($_GET['msg'] ?? '') === 'meldingfout'): ?>
  <p class="alert alert-error">De verkopersmelding kon niet worden verstuurd. Kijk in <code>data/app.log</code> en controleer de mailinstellingen.</p>
<?php elseif (($_GET['msg'] ?? '') === 'testok'): ?>
  <p class="alert alert-success">Testmail verstuurd naar <?= e($adminNotifyEmail) ?>. Komt hij niet aan? Kijk in de map Spam/Ongewenst en in <code>data/app.log</code>.</p>
<?php elseif (($_GET['msg'] ?? '') === 'testfout'): ?>
  <p class="alert alert-error">De testmail kon niet worden verstuurd. E-mail werkt nog niet: controleer de mailinstellingen in Plesk (bestaat het afzenderadres?) en <code>data/app.log</code>.</p>
<?php elseif (($_GET['msg'] ?? '') === 'herinneringok'): ?>
  <p class="alert alert-success">De herinnering ("lukt het betalen?") is naar de koper verstuurd.</p>
<?php elseif (($_GET['msg'] ?? '') === 'herinneringfout'): ?>
  <p class="alert alert-error">De herinnering kon niet worden verstuurd. Kijk in <code>data/app.log</code> en controleer de mailinstellingen.</p>
<?php endif; ?>

<?php if ($allOrders): ?>
  <nav class="filter-tabs" aria-label="Filter op status">
    <?php foreach ($filterTabs as [$key, $label]): ?>
      <a href="<?= e(url('admin/bestellingen.php' . ($key === 'all' ? '' : '?status=' . $key))) ?>"
         class="filter-tab<?= $filter === $key ? ' is-active' : '' ?>">
        <?= e($label) ?> <span class="filter-count"><?= (int) ($counts[$key] ?? 0) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?php if (!$allOrders): ?>
  <div class="empty-state"><p>Er zijn nog geen bestellingen.</p></div>
<?php elseif (!$orders): ?>
  <div class="empty-state"><p>Geen bestellingen met deze status.</p></div>
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
          <?php if (!empty($order['consent_at'])): ?>
            <br><span class="muted" title="Akkoord algemene voorwaarden<?= !empty($order['withdrawal_waived']) ? ' + afstand herroepingsrecht (onmiddellijke levering e-book)' : '' ?>">
              ✓ Akkoord voorwaarden<?= !empty($order['withdrawal_waived']) ? ' + herroeping' : '' ?>
              (<?= e(format_datetime($order['consent_at'])) ?>)</span>
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
            <form method="post" action="<?= e(url('admin/bestellingen.php')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
              <input type="hidden" name="action" value="notify">
              <button type="submit" class="btn btn-small btn-secondary" title="Stuur de melding van deze bestelling (nogmaals) naar de verkoper">Melding&nbsp;verkoper</button>
            </form>
          <?php elseif (in_array($order['status'], ['pending', 'expired', 'failed'], true) && $order['email'] !== ''): ?>
            <form method="post" action="<?= e(url('admin/bestellingen.php')) ?>" class="inline-form"
                  onsubmit="return confirm('Een vriendelijke herinnering (&quot;lukt het betalen?&quot;) sturen naar:\n<?= e(addslashes($order['email'])) ?>\n\nAlle nog niet-betaalde bestellingen van deze koper worden in één mail meegenomen.');">
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
              <input type="hidden" name="action" value="remind">
              <button type="submit" class="btn btn-small btn-secondary" title="Stuur de koper de vriendelijke 'lukt het betalen?'-herinnering">Herinnering&nbsp;sturen</button>
            </form>
          <?php endif; ?>
          <form method="post" action="<?= e(url('admin/bestellingen.php')) ?>" class="inline-form"
                onsubmit="return confirm('Deze bestelling definitief verwijderen?\n\n<?= e(addslashes($order['book_title'])) ?> — <?= e(addslashes($order['name'] !== '' ? $order['name'] : ($order['email'] !== '' ? $order['email'] : 'onbekend'))) ?>\n\nDit kan niet ongedaan worden gemaakt.');">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-small btn-danger" title="Verwijder deze bestelling (bijv. een testbetaling)">Verwijderen</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p class="muted">Gebruik “Reset &amp; verleng” als een koper zijn downloadlimiet heeft bereikt of de link is verlopen.</p>
<?php endif; ?>
<?php include APP_ROOT . '/app/templates/admin_footer.php'; ?>
