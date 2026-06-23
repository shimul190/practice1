<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDB();

// Handle suspend/activate toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid form submission.');
        redirect('/admin/manage_users.php');
    }
    $targetUserId = (int)($_POST['user_id'] ?? 0);

    if ($targetUserId === currentUserId()) {
        setFlash('error', 'You cannot suspend your own admin account.');
        redirect('/admin/manage_users.php');
    }

    $stmt = $pdo->prepare("SELECT status, role FROM users WHERE user_id = :uid");
    $stmt->execute([':uid' => $targetUserId]);
    $target = $stmt->fetch();

    if ($target) {
        $newStatus = $target['status'] === 'active' ? 'suspended' : 'active';
        $pdo->prepare("UPDATE users SET status = :s WHERE user_id = :uid")->execute([':s' => $newStatus, ':uid' => $targetUserId]);
        setFlash('success', "User status updated to {$newStatus}.");
    }
    redirect('/admin/manage_users.php');
}

$roleFilter = $_GET['role'] ?? '';
$sql = "SELECT * FROM users WHERE role != 'admin'";
$params = [];
if (in_array($roleFilter, ['student', 'employee', 'public', 'restaurant'], true)) {
    $sql .= " AND role = :role";
    $params[':role'] = $roleFilter;
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between section-title">
  <h2 class="mt-0">Manage Users</h2>
  <form method="GET" action="">
    <select name="role" onchange="this.form.submit()">
      <option value="">All Roles</option>
      <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
      <option value="employee" <?= $roleFilter === 'employee' ? 'selected' : '' ?>>Employee</option>
      <option value="public" <?= $roleFilter === 'public' ? 'selected' : '' ?>>General Public</option>
      <option value="restaurant" <?= $roleFilter === 'restaurant' ? 'selected' : '' ?>>Restaurant</option>
    </select>
  </form>
</div>

<?php if (empty($users)): ?>
  <div class="empty-state">No users found.</div>
<?php else: ?>
  <table>
    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Joined</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['full_name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['phone']) ?></td>
        <td><?= e(ucfirst($u['role'])) ?></td>
        <td><?= e(substr($u['created_at'], 0, 10)) ?></td>
        <td>
          <span class="badge <?= $u['status'] === 'active' ? 'badge-accepted' : 'badge-rejected' ?>">
            <?= e(ucfirst($u['status'])) ?>
          </span>
        </td>
        <td>
          <form method="POST" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
            <button type="submit" class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>"
                    data-confirm="<?= $u['status'] === 'active' ? 'Suspend' : 'Reactivate' ?> this user?">
              <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
