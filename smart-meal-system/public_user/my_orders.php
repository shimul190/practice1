<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['public']);

$pdo = getDB();
$userId = currentUserId();

$stmt = $pdo->prepare(
    "SELECT o.*, m.meal_name, m.meal_type, r.restaurant_name
     FROM orders o
     JOIN meals m ON m.meal_id = o.meal_id
     JOIN restaurants r ON r.restaurant_id = o.restaurant_id
     WHERE o.user_id = :uid
     ORDER BY o.created_at DESC"
);
$stmt->execute([':uid' => $userId]);
$orders = $stmt->fetchAll();

$pageTitle = 'My Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between section-title">
  <h2 class="mt-0">My Orders</h2>
  <a href="<?= BASE_URL ?>/public_user/browse_meals.php" class="btn">Order a Meal</a>
</div>

<?php if (empty($orders)): ?>
  <div class="empty-state">You haven't placed any orders yet.</div>
<?php else: ?>
  <table>
    <thead>
      <tr><th>Date</th><th>Meal</th><th>Restaurant</th><th>Type</th><th>Qty</th><th>Total</th><th>Status</th><th>Track</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><?= e($o['order_date']) ?></td>
        <td><?= e($o['meal_name']) ?></td>
        <td><?= e($o['restaurant_name']) ?></td>
        <td><?= e(ucfirst($o['meal_type'])) ?></td>
        <td><?= (int)$o['quantity'] ?></td>
        <td><?= formatMoney((float)$o['total_price']) ?></td>
        <td><span class="badge badge-<?= e($o['order_status']) ?>"><?= e(str_replace('_', ' ', $o['order_status'])) ?></span></td>
        <td>
          <?php if (in_array($o['order_status'], ['accepted', 'preparing', 'out_for_delivery'], true)): ?>
            <a href="<?= BASE_URL ?>/public_user/track_delivery.php?order_id=<?= $o['order_id'] ?>" class="btn btn-sm btn-secondary">Track</a>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($o['order_status'] === 'pending'): ?>
            <form method="POST" action="<?= BASE_URL ?>/public_user/cancel_order.php" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" data-confirm="Cancel this order?">Cancel</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
