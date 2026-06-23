<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['restaurant']);

$pdo = getDB();
$userId = currentUserId();

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE user_id = :uid");
$stmt->execute([':uid' => $userId]);
$restaurant = $stmt->fetch();
$restaurantId = (int)$restaurant['restaurant_id'];

// Handle status update actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid form submission.');
        redirect('/restaurant/manage_orders.php');
    }

    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $validStatuses = ['accepted', 'rejected', 'preparing', 'out_for_delivery', 'delivered'];

    if (!in_array($newStatus, $validStatuses, true)) {
        setFlash('error', 'Invalid status.');
        redirect('/restaurant/manage_orders.php');
    }

    // Verify ownership
    $own = $pdo->prepare("SELECT o.*, u.user_id AS customer_id, u.role AS customer_role FROM orders o JOIN users u ON u.user_id = o.user_id WHERE o.order_id = :oid AND o.restaurant_id = :rid");
    $own->execute([':oid' => $orderId, ':rid' => $restaurantId]);
    $order = $own->fetch();

    if (!$order) {
        setFlash('error', 'Order not found.');
        redirect('/restaurant/manage_orders.php');
    }

    try {
        $pdo->beginTransaction();

        $upd = $pdo->prepare("UPDATE orders SET order_status = :status WHERE order_id = :oid");
        $upd->execute([':status' => $newStatus, ':oid' => $orderId]);

        // When accepted -> create a delivery tracking record
        if ($newStatus === 'accepted') {
            $check = $pdo->prepare("SELECT tracking_id FROM delivery_tracking WHERE order_id = :oid");
            $check->execute([':oid' => $orderId]);
            if (!$check->fetch()) {
                $ins = $pdo->prepare(
                    "INSERT INTO delivery_tracking
                        (order_id, delivery_person_name, delivery_person_phone, current_lat, current_lng,
                         destination_lat, destination_lng, estimated_minutes, delivery_status)
                     VALUES (:oid, :name, :phone, :clat, :clng, :dlat, :dlng, :eta, 'assigned')"
                );
                // Demo: auto-assign a delivery person and randomize a nearby starting point
                $names = ['Sumon Mia', 'Jasim Uddin', 'Rakib Hasan', 'Alamin Sheikh'];
                $ins->execute([
                    ':oid'   => $orderId,
                    ':name'  => $names[array_rand($names)],
                    ':phone' => '017' . random_int(10000000, 99999999),
                    ':clat'  => $restaurant['latitude'] ?? 23.7808,
                    ':clng'  => $restaurant['longitude'] ?? 90.4000,
                    ':dlat'  => $restaurant['latitude'] ?? 23.7900,
                    ':dlng'  => $restaurant['longitude'] ?? 90.4100,
                    ':eta'   => random_int(15, 35),
                ]);
            }
        }

        // When out_for_delivery -> update tracking status
        if ($newStatus === 'out_for_delivery') {
            $pdo->prepare("UPDATE delivery_tracking SET delivery_status = 'on_the_way' WHERE order_id = :oid")->execute([':oid' => $orderId]);
        }
        if ($newStatus === 'delivered') {
            $pdo->prepare("UPDATE delivery_tracking SET delivery_status = 'delivered' WHERE order_id = :oid")->execute([':oid' => $orderId]);
        }

        // Re-sync the customer's billing if status moved to/from a billable state
        syncCurrentPayment($pdo, (int)$order['customer_id'], $order['customer_role']);

        // Notify customer
        $statusLabels = [
            'accepted' => 'accepted! Your delivery person will be assigned shortly.',
            'rejected' => 'rejected. Sorry for the inconvenience — please try another restaurant.',
            'preparing' => 'now being prepared.',
            'out_for_delivery' => 'out for delivery!',
            'delivered' => 'delivered. Enjoy your meal!',
        ];
        notify($pdo, (int)$order['customer_id'], 'Order Update',
            "Your order #{$orderId} has been " . $statusLabels[$newStatus]);

        $pdo->commit();
        setFlash('success', 'Order updated.');
    } catch (Exception $ex) {
        $pdo->rollBack();
        setFlash('error', 'Failed to update order: ' . $ex->getMessage());
    }

    redirect('/restaurant/manage_orders.php');
}

// Filter
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT o.*, m.meal_name, u.full_name AS customer_name, u.phone AS customer_phone
        FROM orders o
        JOIN meals m ON m.meal_id = o.meal_id
        JOIN users u ON u.user_id = o.user_id
        WHERE o.restaurant_id = :rid";
$params = [':rid' => $restaurantId];
if ($statusFilter !== '') {
    $sql .= " AND o.order_status = :status";
    $params[':status'] = $statusFilter;
}
$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Manage Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between section-title">
  <h2 class="mt-0">Manage Orders</h2>
  <form method="GET" action="">
    <select name="status" onchange="this.form.submit()">
      <option value="">All Statuses</option>
      <?php foreach (['pending', 'accepted', 'preparing', 'out_for_delivery', 'delivered', 'rejected'] as $s): ?>
        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (empty($orders)): ?>
  <div class="empty-state">No orders found.</div>
<?php else: ?>
  <table>
    <thead>
      <tr><th>Customer</th><th>Phone</th><th>Meal</th><th>Qty</th><th>Total</th><th>Address</th><th>Status</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><?= e($o['customer_name']) ?></td>
        <td><?= e($o['customer_phone']) ?></td>
        <td><?= e($o['meal_name']) ?></td>
        <td><?= (int)$o['quantity'] ?></td>
        <td><?= formatMoney((float)$o['total_price']) ?></td>
        <td style="max-width:180px"><?= e($o['delivery_address']) ?></td>
        <td><span class="badge badge-<?= e($o['order_status']) ?>"><?= e(str_replace('_', ' ', $o['order_status'])) ?></span></td>
        <td>
          <?php if ($o['order_status'] === 'pending'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
              <input type="hidden" name="new_status" value="accepted">
              <button type="submit" class="btn btn-sm btn-success">Accept</button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
              <input type="hidden" name="new_status" value="rejected">
              <button type="submit" class="btn btn-sm btn-danger" data-confirm="Reject this order?">Reject</button>
            </form>
          <?php elseif ($o['order_status'] === 'accepted'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
              <input type="hidden" name="new_status" value="preparing">
              <button type="submit" class="btn btn-sm btn-secondary">Start Preparing</button>
            </form>
          <?php elseif ($o['order_status'] === 'preparing'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
              <input type="hidden" name="new_status" value="out_for_delivery">
              <button type="submit" class="btn btn-sm btn-secondary">Send for Delivery</button>
            </form>
          <?php elseif ($o['order_status'] === 'out_for_delivery'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
              <input type="hidden" name="new_status" value="delivered">
              <button type="submit" class="btn btn-sm btn-success">Mark Delivered</button>
            </form>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
