<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['restaurant']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    redirect('/restaurant/dashboard.php');
}

$pdo = getDB();
$userId = currentUserId();

$stmt = $pdo->prepare("UPDATE restaurants SET is_open = NOT is_open WHERE user_id = :uid");
$stmt->execute([':uid' => $userId]);

setFlash('success', 'Restaurant availability updated.');
redirect('/restaurant/dashboard.php');
