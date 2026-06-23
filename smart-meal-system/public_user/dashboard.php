<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['public']);

$pdo = getDB();
$userId = currentUserId();

syncCurrentPayment($pdo, $userId, 'public');
$summary = getMealSummary($pdo, $userId);

// Recent orders
$stmt = $pdo->prepare(
    "SELECT o.*, m.meal_name, m.meal_type, r.restaurant_name
     FROM orders o
     JOIN meals m ON m.meal_id = o.meal_id
     JOIN restaurants r ON r.restaurant_id = o.restaurant_id
     WHERE o.user_id = :uid
     ORDER BY o.created_at DESC
     LIMIT 5"
);
$stmt->execute([':uid' => $userId]);
$recentOrders = $stmt->fetchAll();

$pageTitle = 'General Public Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between section-title">
  <h2 class="mt-0">Welcome, <?= e($_SESSION['full_name']) ?> 👋</h2>
  <a href="<?= BASE_URL ?>/public_user/browse_meals.php" class="btn">Order a Meal</a>
</div>

<div class="grid grid-4">
  <div class="stat-card">
    <div class="stat-label">Today's Meals</div>
    <div class="stat-value"><?= $summary['daily_count'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">This Week</div>
    <div class="stat-value"><?= $summary['weekly_count'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">This Month</div>
    <div class="stat-value"><?= $summary['monthly_count'] ?> meals</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Payable</div>
    <div class="stat-value"><?= formatMoney($summary['total_payable']) ?></div>
  </div>
</div>

<div class="card mt-2">
  <h3 class="mt-0">Billing Cycle</h3>
  <p class="text-muted">As a <strong>General Public</strong> user, you are billed <strong>weekly</strong>.
     Your current week's total is <?= formatMoney($summary['weekly_amount']) ?>.
     <a href="<?= BASE_URL ?>/public_user/payments.php">View payment details &rarr;</a></p>
</div>

<div class="card mt-2">
  <div class="flex-between">
    <h3 class="mt-0">Recent Orders</h3>
    <a href="<?= BASE_URL ?>/public_user/my_orders.php">View all &rarr;</a>
  </div>
  <?php if (empty($recentOrders)): ?>
    <p class="empty-state">No orders yet. <a href="<?= BASE_URL ?>/public_user/browse_meals.php">Browse meals</a> to place your first order.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Meal</th><th>Restaurant</th><th>Type</th><th>Qty</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><?= e($o['meal_name']) ?></td>
          <td><?= e($o['restaurant_name']) ?></td>
          <td><?= e(ucfirst($o['meal_type'])) ?></td>
          <td><?= (int)$o['quantity'] ?></td>
          <td><?= formatMoney((float)$o['total_price']) ?></td>
          <td><span class="badge badge-<?= e($o['order_status']) ?>"><?= e(str_replace('_', ' ', $o['order_status'])) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
