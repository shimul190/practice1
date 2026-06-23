<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['employee']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    redirect('/employee/my_orders.php');
}

$pdo = getDB();
$userId = currentUserId();
$orderId = (int)($_POST['order_id'] ?? 0);

// Ownership check + only allow cancelling pending orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :oid AND user_id = :uid");
$stmt->execute([':oid' => $orderId, ':uid' => $userId]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect('/employee/my_orders.php');
}

if ($order['order_status'] !== 'pending') {
    setFlash('error', 'Only pending orders can be cancelled.');
    redirect('/employee/my_orders.php');
}

$upd = $pdo->prepare("UPDATE orders SET order_status = 'cancelled' WHERE order_id = :oid");
$upd->execute([':oid' => $orderId]);

syncCurrentPayment($pdo, $userId, 'employee');

setFlash('success', 'Order cancelled.');
redirect('/employee/my_orders.php');
