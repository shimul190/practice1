<?php
/**
 * Cron Job: Mark Overdue Payments
 * Run daily at 1 AM:
 *   0 1 * * * /usr/bin/php /var/www/html/smart-meal-system/database/cron_overdue.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDB();

$stmt = $pdo->prepare(
    "UPDATE payments SET status = 'overdue'
     WHERE status = 'pending' AND due_date < CURDATE()"
);
$stmt->execute();
$overdueFlagged = $stmt->rowCount();

// Also notify each user whose payment just became overdue
$users = $pdo->query(
    "SELECT DISTINCT user_id FROM payments WHERE status = 'overdue'
     AND due_date = CURDATE() - INTERVAL 1 DAY"
)->fetchAll();

foreach ($users as $u) {
    notify($pdo, (int)$u['user_id'],
        'Payment Overdue',
        'Your meal payment is now overdue. Please log in and pay as soon as possible to avoid service suspension.'
    );
}

echo date('Y-m-d H:i:s') . " | Marked {$overdueFlagged} payment(s) as overdue. Notified " . count($users) . " user(s).\n";
