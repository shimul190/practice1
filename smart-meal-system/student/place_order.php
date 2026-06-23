<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/student/browse_meals.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Invalid form submission. Please try again.');
    redirect('/student/browse_meals.php');
}

$pdo = getDB();
$userId = currentUserId();

$mealId = (int)($_POST['meal_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$deliveryAddress = trim($_POST['delivery_address'] ?? '');

if ($mealId <= 0 || $quantity < 1 || $quantity > 20 || $deliveryAddress === '') {
    setFlash('error', 'Invalid order details. Please try again.');
    redirect('/student/browse_meals.php');
}

// Fetch meal & verify it's still available
$stmt = $pdo->prepare(
    "SELECT m.*, r.is_open, r.is_approved
     FROM meals m JOIN restaurants r ON r.restaurant_id = m.restaurant_id
     WHERE m.meal_id = :mid"
);
$stmt->execute([':mid' => $mealId]);
$meal = $stmt->fetch();

if (!$meal || !$meal['is_available'] || !$meal['is_open'] || !$meal['is_approved']) {
    setFlash('error', 'This meal is no longer available.');
    redirect('/student/browse_meals.php');
}

$unitPrice = (float)$meal['price'];
$totalPrice = $unitPrice * $quantity;

try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare(
        "INSERT INTO orders (user_id, restaurant_id, meal_id, quantity, unit_price, total_price, delivery_address, order_status, order_date)
         VALUES (:uid, :rid, :mid, :qty, :unit, :total, :addr, 'pending', CURDATE())"
    );
    $ins->execute([
        ':uid'   => $userId,
        ':rid'   => $meal['restaurant_id'],
        ':mid'   => $mealId,
        ':qty'   => $quantity,
        ':unit'  => $unitPrice,
        ':total' => $totalPrice,
        ':addr'  => $deliveryAddress,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    // Recalculate this user's current billing period total
    syncCurrentPayment($pdo, $userId, 'student');

    // Notify the restaurant owner
    $rstmt = $pdo->prepare("SELECT user_id FROM restaurants WHERE restaurant_id = :rid");
    $rstmt->execute([':rid' => $meal['restaurant_id']]);
    $restaurantOwner = $rstmt->fetch();
    if ($restaurantOwner) {
        notify($pdo, (int)$restaurantOwner['user_id'], 'New Order Received',
            "New order #{$orderId} for {$meal['meal_name']} (x{$quantity}) - please accept or reject.");
    }

    $pdo->commit();

    $_SESSION['last_address'] = $deliveryAddress;
    setFlash('success', "Order placed successfully! Total: " . formatMoney($totalPrice));
    redirect('/student/my_orders.php');

} catch (Exception $ex) {
    $pdo->rollBack();
    setFlash('error', 'Failed to place order: ' . $ex->getMessage());
    redirect('/student/browse_meals.php');
}
