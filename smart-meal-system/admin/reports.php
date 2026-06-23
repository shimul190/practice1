<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDB();

// Daily revenue (last 7 days)
$dailyStmt = $pdo->query(
    "SELECT order_date, COUNT(*) AS order_count, COALESCE(SUM(total_price),0) AS revenue
     FROM orders
     WHERE order_status NOT IN ('rejected','cancelled') AND order_date >= CURDATE() - INTERVAL 6 DAY
     GROUP BY order_date
     ORDER BY order_date DESC"
);
$dailyRevenue = $dailyStmt->fetchAll();

// Weekly revenue (current week)
$dayOfWeek = (int)(new DateTime('today'))->format('N');
$weekStart = (new DateTime('today'))->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
$weekStmt = $pdo->prepare(
    "SELECT COUNT(*) AS order_count, COALESCE(SUM(total_price),0) AS revenue
     FROM orders WHERE order_status NOT IN ('rejected','cancelled') AND order_date >= :start"
);
$weekStmt->execute([':start' => $weekStart]);
$weeklyRevenue = $weekStmt->fetch();

// Monthly revenue (last 6 months)
$monthlyStmt = $pdo->query(
    "SELECT DATE_FORMAT(order_date, '%Y-%m') AS ym, COUNT(*) AS order_count, COALESCE(SUM(total_price),0) AS revenue
     FROM orders
     WHERE order_status NOT IN ('rejected','cancelled') AND order_date >= CURDATE() - INTERVAL 6 MONTH
     GROUP BY ym
     ORDER BY ym DESC"
);
$monthlyRevenue = $monthlyStmt->fetchAll();

// Revenue by restaurant
$byRestaurantStmt = $pdo->query(
    "SELECT r.restaurant_name, COUNT(o.order_id) AS order_count, COALESCE(SUM(o.total_price),0) AS revenue
     FROM restaurants r
     LEFT JOIN orders o ON o.restaurant_id = r.restaurant_id AND o.order_status NOT IN ('rejected','cancelled')
     GROUP BY r.restaurant_id
     ORDER BY revenue DESC"
);
$byRestaurant = $byRestaurantStmt->fetchAll();

// Payment status breakdown
$paymentStatusStmt = $pdo->query(
    "SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amount FROM payments GROUP BY status"
);
$paymentStatus = $paymentStatusStmt->fetchAll();

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  <h2 class="mt-0">Revenue Reports</h2>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3 class="mt-0">This Week</h3>
    <div class="stat-value"><?= formatMoney((float)$weeklyRevenue['revenue']) ?></div>
    <p class="text-muted"><?= (int)$weeklyRevenue['order_count'] ?> orders since <?= e($weekStart) ?></p>
  </div>
  <div class="card">
    <h3 class="mt-0">Payment Status Breakdown</h3>
    <table>
      <thead><tr><th>Status</th><th>Count</th><th>Amount</th></tr></thead>
      <tbody>
        <?php foreach ($paymentStatus as $p): ?>
        <tr>
          <td><span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
          <td><?= (int)$p['cnt'] ?></td>
          <td><?= formatMoney((float)$p['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-2">
  <h3 class="mt-0">Daily Revenue (Last 7 Days)</h3>
  <?php if (empty($dailyRevenue)): ?>
    <p class="empty-state">No orders in the last 7 days.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead>
      <tbody>
        <?php foreach ($dailyRevenue as $d): ?>
        <tr>
          <td><?= e($d['order_date']) ?></td>
          <td><?= (int)$d['order_count'] ?></td>
          <td><?= formatMoney((float)$d['revenue']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card mt-2">
  <h3 class="mt-0">Monthly Revenue (Last 6 Months)</h3>
  <?php if (empty($monthlyRevenue)): ?>
    <p class="empty-state">No orders in the last 6 months.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Month</th><th>Orders</th><th>Revenue</th></tr></thead>
      <tbody>
        <?php foreach ($monthlyRevenue as $m): ?>
        <tr>
          <td><?= e($m['ym']) ?></td>
          <td><?= (int)$m['order_count'] ?></td>
          <td><?= formatMoney((float)$m['revenue']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card mt-2">
  <h3 class="mt-0">Revenue by Restaurant</h3>
  <table>
    <thead><tr><th>Restaurant</th><th>Orders</th><th>Revenue</th></tr></thead>
    <tbody>
      <?php foreach ($byRestaurant as $r): ?>
      <tr>
        <td><?= e($r['restaurant_name']) ?></td>
        <td><?= (int)$r['order_count'] ?></td>
        <td><?= formatMoney((float)$r['revenue']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
