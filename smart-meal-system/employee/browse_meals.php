<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['employee']);

$pdo = getDB();
$userId = currentUserId();

// Optional filters
$mealTypeFilter = $_GET['meal_type'] ?? '';
$restaurantFilter = $_GET['restaurant_id'] ?? '';

$sql = "SELECT m.*, r.restaurant_name, r.restaurant_id
        FROM meals m
        JOIN restaurants r ON r.restaurant_id = m.restaurant_id
        WHERE m.is_available = 1 AND r.is_open = 1 AND r.is_approved = 1";
$params = [];

if (in_array($mealTypeFilter, ['breakfast', 'lunch', 'dinner'], true)) {
    $sql .= " AND m.meal_type = :type";
    $params[':type'] = $mealTypeFilter;
}
if ($restaurantFilter !== '' && ctype_digit($restaurantFilter)) {
    $sql .= " AND r.restaurant_id = :rid";
    $params[':rid'] = (int)$restaurantFilter;
}
$sql .= " ORDER BY FIELD(m.meal_type,'breakfast','lunch','dinner'), m.meal_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$meals = $stmt->fetchAll();

// Restaurant list for filter dropdown
$restaurants = $pdo->query(
    "SELECT restaurant_id, restaurant_name FROM restaurants WHERE is_approved = 1 ORDER BY restaurant_name"
)->fetchAll();

$pageTitle = 'Browse Meals';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between section-title">
  <h2 class="mt-0">Browse Meals</h2>
</div>

<div class="card">
  <form method="GET" action="" class="flex-between" style="gap:14px; flex-wrap:wrap;">
    <div style="flex:1; min-width:160px;">
      <label for="meal_type">Meal Type</label>
      <select name="meal_type" id="meal_type" onchange="this.form.submit()">
        <option value="">All Types</option>
        <option value="breakfast" <?= $mealTypeFilter === 'breakfast' ? 'selected' : '' ?>>Breakfast</option>
        <option value="lunch"     <?= $mealTypeFilter === 'lunch' ? 'selected' : '' ?>>Lunch</option>
        <option value="dinner"    <?= $mealTypeFilter === 'dinner' ? 'selected' : '' ?>>Dinner</option>
      </select>
    </div>
    <div style="flex:1; min-width:200px;">
      <label for="restaurant_id">Restaurant</label>
      <select name="restaurant_id" id="restaurant_id" onchange="this.form.submit()">
        <option value="">All Restaurants</option>
        <?php foreach ($restaurants as $r): ?>
          <option value="<?= $r['restaurant_id'] ?>" <?= $restaurantFilter == $r['restaurant_id'] ? 'selected' : '' ?>>
            <?= e($r['restaurant_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<?php if (empty($meals)): ?>
  <div class="empty-state">No meals available right now. Please check back later.</div>
<?php else: ?>
  <div class="grid grid-3 mt-2">
    <?php foreach ($meals as $meal): ?>
      <div class="meal-card">
        <div class="meal-body">
          <span class="meal-type-tag <?= e($meal['meal_type']) ?>"><?= e(ucfirst($meal['meal_type'])) ?></span>
          <h3 class="mt-0"><?= e($meal['meal_name']) ?></h3>
          <p class="text-muted" style="font-size:0.88rem;"><?= e($meal['restaurant_name']) ?></p>
          <?php if ($meal['description']): ?>
            <p style="font-size:0.88rem;"><?= e($meal['description']) ?></p>
          <?php endif; ?>
          <div class="meal-price"><?= formatMoney((float)$meal['price']) ?></div>

          <form method="POST" action="<?= BASE_URL ?>/employee/place_order.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="meal_id" value="<?= $meal['meal_id'] ?>">
            <label for="qty_<?= $meal['meal_id'] ?>">Quantity</label>
            <input type="number" id="qty_<?= $meal['meal_id'] ?>" name="quantity" value="1" min="1" max="20" required>
            <label for="addr_<?= $meal['meal_id'] ?>">Delivery Address</label>
            <input type="text" id="addr_<?= $meal['meal_id'] ?>" name="delivery_address"
                   value="<?= e($_SESSION['last_address'] ?? '') ?>" placeholder="Where should we deliver?" required>
            <button type="submit" class="btn btn-block btn-sm">Place Order</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
