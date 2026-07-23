<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

// Periode: 30 dagen standaard, 7 of 90 als alternatief.
$days = (int) ($_GET['periode'] ?? 30);
if (!in_array($days, [7, 30, 90], true)) {
    $days = 30;
}
$from = gmdate('Y-m-d', time() - ($days - 1) * 86400);

// Per dag: weergaven en unieke bezoekers.
$stmt = db()->prepare(
    'SELECT day, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors
     FROM page_views WHERE day >= ? GROUP BY day'
);
$stmt->execute([$from]);
$byDay = [];
foreach ($stmt->fetchAll() as $row) {
    $byDay[$row['day']] = $row;
}

// Doorlopende reeks dagen (ook dagen zonder bezoek), oudste eerst.
$series = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = gmdate('Y-m-d', time() - $i * 86400);
    $series[] = [
        'day'      => $d,
        'views'    => (int) ($byDay[$d]['views'] ?? 0),
        'visitors' => (int) ($byDay[$d]['visitors'] ?? 0),
    ];
}
$totalViews = array_sum(array_column($series, 'views'));
$maxViews = max(1, max(array_column($series, 'views')));

$stmt = db()->prepare('SELECT COUNT(DISTINCT visitor_hash || day) FROM page_views WHERE day >= ?');
$stmt->execute([$from]);
$totalVisitors = (int) $stmt->fetchColumn();

// Meest bekeken pagina's.
$stmt = db()->prepare(
    'SELECT page, COUNT(*) AS views, COUNT(DISTINCT visitor_hash || day) AS visitors
     FROM page_views WHERE day >= ? GROUP BY page ORDER BY views DESC LIMIT 15'
);
$stmt->execute([$from]);
$topPages = $stmt->fetchAll();

// Externe verwijzers (waar komen bezoekers vandaan).
$stmt = db()->prepare(
    "SELECT referrer_host, COUNT(*) AS views FROM page_views
     WHERE day >= ? AND referrer_host <> '' GROUP BY referrer_host ORDER BY views DESC LIMIT 10"
);
$stmt->execute([$from]);
$referrers = $stmt->fetchAll();

// Trechter: hoe ver komen bezoekers in het koopproces?
$funnelStep = static function (string $like) use ($from): int {
    $stmt = db()->prepare(
        'SELECT COUNT(DISTINCT visitor_hash || day) FROM page_views WHERE day >= ? AND page LIKE ?'
    );
    $stmt->execute([$from, $like]);
    return (int) $stmt->fetchColumn();
};
$funnel = [
    ['Bezoekers (alle pagina\'s)', $totalVisitors],
    ['Boekpagina bekeken', $funnelStep('boek:%')],
    ['Winkelmandje bekeken', $funnelStep('mandje')],
    ['Afrekenpagina bereikt', $funnelStep('afrekenen')],
    ['Betaling afgerond (bedanktpagina)', $funnelStep('bedankt')],
];
$maxFunnel = max(1, max(array_column($funnel, 1)));

// Betaalde bestellingen in dezelfde periode (uit de bestellingen zelf, de echte waarheid).
$stmt = db()->prepare(
    "SELECT COUNT(*) FROM orders WHERE status IN ('paid', 'free') AND created_at >= ?"
);
$stmt->execute([$from . ' 00:00:00']);
$paidOrders = (int) $stmt->fetchColumn();

$pageTitle = 'Statistieken';
include APP_ROOT . '/app/templates/admin_header.php';
?>
<h1>Statistieken</h1>

<nav class="filter-tabs" aria-label="Kies een periode">
  <?php foreach ([7 => 'Laatste 7 dagen', 30 => 'Laatste 30 dagen', 90 => 'Laatste 90 dagen'] as $n => $label): ?>
    <a href="<?= e(url('admin/statistieken.php' . ($n === 30 ? '' : '?periode=' . $n))) ?>"
       class="filter-tab<?= $days === $n ? ' is-active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<div class="stat-tiles">
  <div class="stat-tile"><span class="stat-number"><?= $totalVisitors ?></span><span class="stat-label">Unieke bezoekers</span></div>
  <div class="stat-tile"><span class="stat-number"><?= $totalViews ?></span><span class="stat-label">Paginaweergaven</span></div>
  <div class="stat-tile"><span class="stat-number"><?= $paidOrders ?></span><span class="stat-label">Betaalde/gratis bestellingen</span></div>
</div>

<?php if ($totalViews === 0): ?>
  <div class="empty-state">
    <p>Er zijn in deze periode nog geen bezoeken gemeten.</p>
    <p class="muted">De meting is net ingeschakeld: vanaf nu telt elk bezoek aan de site mee.
       Kom morgen terug voor de eerste cijfers.</p>
  </div>
<?php else: ?>

  <h2 class="stats-heading">Bezoek per dag</h2>
  <div class="chart-bars" role="img" aria-label="Staafdiagram van paginaweergaven per dag">
    <?php foreach ($series as $point): ?>
      <div class="chart-col" title="<?= e(format_date($point['day'])) ?>: <?= $point['visitors'] ?> bezoekers, <?= $point['views'] ?> weergaven">
        <div class="chart-bar" style="height: <?= max(2, (int) round($point['views'] / $maxViews * 100)) ?>%"></div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted">Hoogste dag: <?= $maxViews ?> weergaven. Beweeg over een staaf voor de datum en aantallen.</p>

  <h2 class="stats-heading">Hoe ver komen bezoekers in het koopproces?</h2>
  <div class="funnel">
    <?php foreach ($funnel as [$label, $count]): ?>
      <div class="funnel-row">
        <span class="funnel-label"><?= e($label) ?></span>
        <span class="funnel-track"><span class="funnel-fill" style="width: <?= max(1, (int) round($count / $maxFunnel * 100)) ?>%"></span></span>
        <span class="funnel-count"><?= $count ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted">Zo zie je waar mensen afhaken: veel boekweergaven maar weinig mandjes betekent
     bijvoorbeeld dat de boekpagina niet overtuigt of de prijs afschrikt.</p>

  <div class="stats-columns">
    <div>
      <h2 class="stats-heading">Meest bekeken pagina's</h2>
      <table class="admin-table">
        <thead><tr><th>Pagina</th><th class="nowrap">Weergaven</th><th class="nowrap">Bezoekers</th></tr></thead>
        <tbody>
          <?php foreach ($topPages as $row): ?>
            <tr>
              <td><?= e(analytics_page_display((string) $row['page'])) ?></td>
              <td class="nowrap"><?= (int) $row['views'] ?></td>
              <td class="nowrap"><?= (int) $row['visitors'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div>
      <h2 class="stats-heading">Bezoekers komen via</h2>
      <?php if (!$referrers): ?>
        <p class="muted">Nog geen externe verwijzers gemeten (bezoekers typten het adres zelf in,
           gebruikten een bladwijzer of een link uit een e-mail).</p>
      <?php else: ?>
        <table class="admin-table">
          <thead><tr><th>Website</th><th class="nowrap">Bezoeken</th></tr></thead>
          <tbody>
            <?php foreach ($referrers as $row): ?>
              <tr><td><?= e((string) $row['referrer_host']) ?></td><td class="nowrap"><?= (int) $row['views'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>

<p class="muted">De meting is privacyvriendelijk: geen cookies, geen externe dienst en geen
   herleidbare gegevens — bezoekers worden alleen per dag anoniem geteld. Zoekmachine-bots
   tellen niet mee. Eigen bezoeken tellen wél mee zolang je op deze site rondklikt.</p>
<?php include APP_ROOT . '/app/templates/admin_footer.php'; ?>
