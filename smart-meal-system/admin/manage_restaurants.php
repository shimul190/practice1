<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid form submission.');
        redirect('/admin/manage_restaurants.php');
    }

    $restaurantId = (int)($_POST['restaurant_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $pdo->prepare("UPDATE restaurants SET is_approved = 1, is_open = 1 WHERE restaurant_id = :rid")
            ->execute([':rid' => $restaurantId]);

        $own = $pdo->prepare("SELECT user_id FROM restaurants WHERE restaurant_id = :rid");
        $own->execute([':rid' => $restaurantId]);
        $owner = $own->fetch();
        if ($owner) {
            notify($pdo, (int)$owner['user_id'], 'Restaurant Approved',
                'Congratulations! Your restaurant has been approved. You can now log in and start adding meals.');
        }
        setFlash('success', 'Restaurant approved.');
    } elseif ($action === 'revoke') {
        $pdo->prepare("UPDATE restaurants SET is_approved = 0, is_open = 0 WHERE restaurant_id = :rid")
            ->execute([':rid' => $restaurantId]);
        setFlash('success', 'Restaurant approval revoked.');
    }
    redirect('/admin/manage_restaurants.php');
}

$stmt = $pdo->query(
    "SELECT r.*, u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone,
            (SELECT COUNT(*) FROM meals m WHERE m.restaurant_id = r.restaurant_id) AS meal_count,
            (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id = r.restaurant_id) AS order_count
     FROM restaurants r
     JOIN users u ON u.user_id = r.user_id
     ORDER BY r.is_approved ASC, r.created_at DESC"
);
$restaurants = $stmt->fetchAll();

$pageTitle = 'Manage Restaurants';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  <h2 class="mt-0">Manage Restaurants</h2>
</div>

<?php if (empty($restaurants)): ?>
  <div class="empty-state">No restaurants registered yet.</div>
<?php else: ?>
  <table>
    <thead><tr><th>Restaurant</th><th>Owner</th><th>Contact</th><th>Meals</th><th>Orders</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($restaurants as $r): ?>
      <tr>
        <td><?= e($r['restaurant_name']) ?><br><span class="text-muted" style="font-size:0.78rem"><?= e($r['address']) ?></span></td>
        <td><?= e($r['owner_name']) ?></td>
        <td><?= e($r['owner_email']) ?><br><span class="text-muted"><?= e($r['owner_phone']) ?></span></td>
        <td><?= (int)$r['meal_count'] ?></td>
        <td><?= (int)$r['order_count'] ?></td>
        <td>
          <span class="badge <?= $r['is_approved'] ? 'badge-accepted' : 'badge-pending' ?>">
            <?= $r['is_approved'] ? 'Approved' : 'Pending Approval' ?>
          </span>
        </td>
        <td>
          <form method="POST" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="restaurant_id" value="<?= $r['restaurant_id'] ?>">
            <?php if (!$r['is_approved']): ?>
              <input type="hidden" name="action" value="approve">
              <button type="submit" class="btn btn-sm btn-success">Approve</button>
            <?php else: ?>
              <input type="hidden" name="action" value="revoke">
              <button type="submit" class="btn btn-sm btn-danger" data-confirm="Revoke approval for this restaurant?">Revoke</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
