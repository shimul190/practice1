<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDB();

$userCounts = $pdo->query(
    "SELECT role, COUNT(*) cnt FROM users GROUP BY role"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$pendingRestaurants = (int)$pdo->query(
    "SELECT COUNT(*) FROM restaurants WHERE is_approved = 0"
)->fetchColumn();

$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

$todayRevenue = (float)$pdo->query(
    "SELECT COALESCE(SUM(total_price),0) FROM orders
     WHERE order_date = CURDATE() AND order_status NOT IN ('rejected','cancelled')"
)->fetchColumn();

$pendingPaymentsTotal = (float)$pdo->query(
    "SELECT COALESCE(SUM(total_amount),0) FROM payments WHERE status IN ('pending','overdue')"
)->fetchColumn();

$recentOrders = $pdo->query(
    "SELECT o.order_id, o.total_price, o.order_status, o.created_at, u.full_name AS customer, r.restaurant_name
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     JOIN restaurants r ON r.restaurant_id = o.restaurant_id
     ORDER BY o.created_at DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  <h2 class="mt-0">Admin Dashboard</h2>
</div>

<div class="grid grid-4">
  <div class="stat-card">
    <div class="stat-label">Students</div>
    <div class="stat-value"><?= (int)($userCounts['student'] ?? 0) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Employees</div>
    <div class="stat-value"><?= (int)($userCounts['employee'] ?? 0) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">General Public</div>
    <div class="stat-value"><?= (int)($userCounts['public'] ?? 0) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Restaurants</div>
    <div class="stat-value"><?= (int)($userCounts['restaurant'] ?? 0) ?></div>
  </div>
</div>

<div class="grid grid-4 mt-2">
  <div class="stat-card">
    <div class="stat-label">Pending Restaurant Approvals</div>
    <div class="stat-value"><?= $pendingRestaurants ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Orders</div>
    <div class="stat-value"><?= $totalOrders ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Today's Revenue</div>
    <div class="stat-value"><?= formatMoney($todayRevenue) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Outstanding Payments</div>
    <div class="stat-value"><?= formatMoney($pendingPaymentsTotal) ?></div>
  </div>
</div>

<div class="grid grid-2 mt-2">
  <div class="card">
    <h3 class="mt-0">Users</h3>
    <p class="text-muted">View, suspend, or reactivate user accounts.</p>
    <a href="<?= BASE_URL ?>/admin/manage_users.php" class="btn">Manage Users</a>
  </div>
  <div class="card">
    <h3 class="mt-0">Restaurants</h3>
    <p class="text-muted">Approve new restaurant partners and monitor existing ones.</p>
    <a href="<?= BASE_URL ?>/admin/manage_restaurants.php" class="btn">Manage Restaurants</a>
  </div>
</div>

<div class="card mt-2">
  <div class="flex-between">
    <h3 class="mt-0">Recent Orders</h3>
    <a href="<?= BASE_URL ?>/admin/reports.php">Full Reports &rarr;</a>
  </div>
  <table>
    <thead><tr><th>Order #</th><th>Customer</th><th>Restaurant</th><th>Total</th><th>Status</th><th>Placed</th></tr></thead>
    <tbody>
      <?php foreach ($recentOrders as $o): ?>
      <tr>
        <td>#<?= $o['order_id'] ?></td>
        <td><?= e($o['customer']) ?></td>
        <td><?= e($o['restaurant_name']) ?></td>
        <td><?= formatMoney((float)$o['total_price']) ?></td>
        <td><span class="badge badge-<?= e($o['order_status']) ?>"><?= e(str_replace('_', ' ', $o['order_status'])) ?></span></td>
        <td><?= e($o['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
