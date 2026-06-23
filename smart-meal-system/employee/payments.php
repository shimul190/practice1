<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['employee']);

$pdo = getDB();
$userId = currentUserId();

syncCurrentPayment($pdo, $userId, 'employee');

$stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = :uid ORDER BY period_start DESC");
$stmt->execute([':uid' => $userId]);
$payments = $stmt->fetchAll();

$pageTitle = 'Payments';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  <h2 class="mt-0">Payments</h2>
  <p class="text-muted">As an Employee, your meals are billed <strong>monthly</strong>. The bill for the current month
     updates automatically as you order; pay any time before the due date.</p>
</div>

<?php if (empty($payments)): ?>
  <div class="empty-state">No payment records yet.</div>
<?php else: ?>
  <table>
    <thead>
      <tr><th>Period</th><th>Meals</th><th>Amount</th><th>Due Date</th><th>Method</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($payments as $p): ?>
      <tr>
        <td><?= e($p['period_start']) ?> &ndash; <?= e($p['period_end']) ?></td>
        <td><?= (int)$p['total_meals'] ?></td>
        <td><?= formatMoney((float)$p['total_amount']) ?></td>
        <td><?= e($p['due_date']) ?></td>
        <td><?= e(strtoupper($p['payment_method'])) ?></td>
        <td><span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
        <td>
          <?php if ($p['status'] === 'pending' || $p['status'] === 'overdue'): ?>
            <a href="<?= BASE_URL ?>/employee/pay.php?payment_id=<?= $p['payment_id'] ?>" class="btn btn-sm">Pay Now</a>
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
