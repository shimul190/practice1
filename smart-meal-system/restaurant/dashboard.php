<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['restaurant']);

$pdo = getDB();
$userId = currentUserId();

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE user_id = :uid");
$stmt->execute([':uid' => $userId]);
$restaurant = $stmt->fetch();

if (!$restaurant) {
    die('Restaurant profile not found. Please contact admin.');
}
$restaurantId = (int)$restaurant['restaurant_id'];

// Order stats
$stmt = $pdo->prepare(
    "SELECT
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN order_status = 'preparing' THEN 1 ELSE 0 END) AS preparing_count,
        SUM(CASE WHEN order_status = 'out_for_delivery' THEN 1 ELSE 0 END) AS delivering_count,
        SUM(CASE WHEN order_status = 'delivered' AND order_date = CURDATE() THEN 1 ELSE 0 END) AS delivered_today,
        SUM(CASE WHEN order_status NOT IN ('rejected','cancelled') AND order_date = CURDATE() THEN total_price ELSE 0 END) AS revenue_today
     FROM orders WHERE restaurant_id = :rid"
);
$stmt->execute([':rid' => $restaurantId]);
$stats = $stmt->fetch();

// Meal count
$mealCountStmt = $pdo->prepare("SELECT COUNT(*) FROM meals WHERE restaurant_id = :rid");
$mealCountStmt->execute([':rid' => $restaurantId]);
$mealCount = (int)$mealCountStmt->fetchColumn();

// Recent orders
$stmt = $pdo->prepare(
    "SELECT o.*, m.meal_name, u.full_name AS customer_name
     FROM orders o
     JOIN meals m ON m.meal_id = o.meal_id
     JOIN users u ON u.user_id = o.user_id
     WHERE o.restaurant_id = :rid
     ORDER BY o.created_at DESC LIMIT 8"
);
$stmt->execute([':rid' => $restaurantId]);
$recentOrders = $stmt->fetchAll();

$pageTitle = 'Restaurant Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between section-title">
  <h2 class="mt-0"><?= e($restaurant['restaurant_name']) ?></h2>
  <div>
    <span class="badge <?= $restaurant['is_open'] ? 'badge-accepted' : 'badge-rejected' ?>">
      <?= $restaurant['is_open'] ? 'Open for Orders' : 'Closed' ?>
    </span>
    <form method="POST" action="<?= BASE_URL ?>/restaurant/toggle_availability.php" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <button type="submit" class="btn btn-sm btn-secondary"><?= $restaurant['is_open'] ? 'Close Restaurant' : 'Open Restaurant' ?></button>
    </form>
  </div>
</div>

<div class="grid grid-4">
  <div class="stat-card">
    <div class="stat-label">Pending Orders</div>
    <div class="stat-value"><?= (int)$stats['pending_count'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Preparing</div>
    <div class="stat-value"><?= (int)$stats['preparing_count'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Out for Delivery</div>
    <div class="stat-value"><?= (int)$stats['delivering_count'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Today's Revenue</div>
    <div class="stat-value"><?= formatMoney((float)$stats['revenue_today']) ?></div>
  </div>
</div>

<div class="grid grid-2 mt-2">
  <div class="card">
    <h3 class="mt-0">Menu</h3>
    <p class="text-muted">You have <?= $mealCount ?> meal(s) listed.</p>
    <a href="<?= BASE_URL ?>/restaurant/manage_meals.php" class="btn">Manage Meals</a>
  </div>
  <div class="card">
    <h3 class="mt-0">Orders</h3>
    <p class="text-muted">Accept, reject, and update the status of incoming orders.</p>
    <a href="<?= BASE_URL ?>/restaurant/manage_orders.php" class="btn">Manage Orders</a>
  </div>
</div>

<div class="card mt-2">
  <h3 class="mt-0">Recent Orders</h3>
  <?php if (empty($recentOrders)): ?>
    <p class="empty-state">No orders yet.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Customer</th><th>Meal</th><th>Qty</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><?= e($o['customer_name']) ?></td>
          <td><?= e($o['meal_name']) ?></td>
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
