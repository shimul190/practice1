<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect('/index.php');
}

$errors = [];
$emailOld = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $emailOld = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($emailOld === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    }

    if (empty($errors)) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $emailOld]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Invalid email or password.';
        } elseif ($user['status'] === 'suspended') {
            $errors[] = 'Your account has been suspended. Contact admin support.';
        } elseif ($user['role'] === 'restaurant') {
            $rstmt = $pdo->prepare("SELECT is_approved FROM restaurants WHERE user_id = :uid");
            $rstmt->execute([':uid' => $user['user_id']]);
            $restaurant = $rstmt->fetch();
            if (!$restaurant || !$restaurant['is_approved']) {
                $errors[] = 'Your restaurant account is pending admin approval.';
            } else {
                loginUser($user);
                redirect('/restaurant/dashboard.php');
            }
        } else {
            loginUser($user);
            $redirectMap = [
                'student'  => '/student/dashboard.php',
                'employee' => '/employee/dashboard.php',
                'public'   => '/public_user/dashboard.php',
                'admin'    => '/admin/dashboard.php',
            ];
            redirect($redirectMap[$user['role']] ?? '/index.php');
        }
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
  <h2>Welcome Back</h2>
  <p class="text-muted">Log in to manage your meals, orders, and payments.</p>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($emailOld) ?>" required autofocus>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit" class="btn btn-block">Log In</button>
  </form>

  <p class="mt-2 text-muted">Don't have an account? <a href="<?= BASE_URL ?>/auth/register.php">Register here</a></p>

  <p class="mt-2 text-muted" style="font-size:0.8rem">
    Demo accounts (password shown after seeding): admin@smartmeal.com · student@example.com · employee@example.com · public@example.com
  </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
