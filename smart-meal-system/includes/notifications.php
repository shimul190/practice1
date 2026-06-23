<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

requireLogin();

$pdo = getDB();
$userId = currentUserId();

// Mark all as read when visiting this page
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid")->execute([':uid' => $userId]);

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 50");
$stmt->execute([':uid' => $userId]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications';
require_once __DIR__ . '/header.php';
?>

<div class="section-title">
  <h2 class="mt-0">Notifications</h2>
</div>

<?php if (empty($notifications)): ?>
  <div class="empty-state">No notifications yet.</div>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($notifications as $n): ?>
      <div class="card">
        <strong><?= e($n['title']) ?></strong>
        <p class="text-muted" style="margin:8px 0;"><?= e($n['message']) ?></p>
        <span class="text-muted" style="font-size:0.78rem;"><?= e($n['created_at']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
