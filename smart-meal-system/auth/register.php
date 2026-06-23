<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect('/index.php');
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => 'student',
        'student_id_no' => '', 'employee_id_no' => '', 'institution' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $old['full_name']      = trim($_POST['full_name'] ?? '');
    $old['email']          = trim($_POST['email'] ?? '');
    $old['phone']          = trim($_POST['phone'] ?? '');
    $old['role']           = $_POST['role'] ?? 'student';
    $old['student_id_no']  = trim($_POST['student_id_no'] ?? '');
    $old['employee_id_no'] = trim($_POST['employee_id_no'] ?? '');
    $old['institution']    = trim($_POST['institution'] ?? '');
    $old['address']        = trim($_POST['address'] ?? '');
    $password               = $_POST['password'] ?? '';
    $confirmPassword        = $_POST['confirm_password'] ?? '';
    $restaurantName          = trim($_POST['restaurant_name'] ?? '');
    $restaurantAddress       = trim($_POST['restaurant_address'] ?? '');

    $allowedRoles = ['student', 'employee', 'public', 'restaurant'];

    if ($old['full_name'] === '') $errors[] = 'Full name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($old['phone'] === '') $errors[] = 'Phone number is required.';
    if (!in_array($old['role'], $allowedRoles, true)) $errors[] = 'Invalid role selected.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';
    if ($old['role'] === 'restaurant' && $restaurantName === '') $errors[] = 'Restaurant name is required.';
    if ($old['role'] === 'restaurant' && $restaurantAddress === '') $errors[] = 'Restaurant address is required.';

    if (empty($errors)) {
        $pdo = getDB();

        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
        $check->execute([':email' => $old['email']]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, email, phone, password_hash, role, student_id_no, employee_id_no, institution, address)
                 VALUES (:full_name, :email, :phone, :hash, :role, :student_id, :employee_id, :institution, :address)"
            );
            $stmt->execute([
                ':full_name'   => $old['full_name'],
                ':email'       => $old['email'],
                ':phone'       => $old['phone'],
                ':hash'        => $hash,
                ':role'        => $old['role'],
                ':student_id'  => $old['role'] === 'student' ? $old['student_id_no'] : null,
                ':employee_id' => $old['role'] === 'employee' ? $old['employee_id_no'] : null,
                ':institution' => $old['institution'] ?: null,
                ':address'     => $old['address'] ?: null,
            ]);
            $newUserId = (int)$pdo->lastInsertId();

            if ($old['role'] === 'restaurant') {
                $rstmt = $pdo->prepare(
                    "INSERT INTO restaurants (user_id, restaurant_name, address, is_approved, is_open)
                     VALUES (:uid, :name, :address, 0, 0)"
                );
                $rstmt->execute([
                    ':uid'     => $newUserId,
                    ':name'    => $restaurantName,
                    ':address' => $restaurantAddress,
                ]);
            }

            $pdo->commit();

            setFlash('success', $old['role'] === 'restaurant'
                ? 'Registration successful! Your restaurant is pending admin approval before you can log in and add meals.'
                : 'Registration successful! You can now log in.');
            redirect('/auth/login.php');

        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors[] = 'Registration failed: ' . $ex->getMessage();
        }
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="form-card wide">
  <h2>Create an Account</h2>
  <p class="text-muted">Register as a Student, Employee, General Public user, or a Restaurant partner.</p>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

    <label for="role">I am registering as</label>
    <select name="role" id="role" onchange="toggleRoleFields()">
      <option value="student"   <?= $old['role'] === 'student' ? 'selected' : '' ?>>Student</option>
      <option value="employee"  <?= $old['role'] === 'employee' ? 'selected' : '' ?>>Employee</option>
      <option value="public"    <?= $old['role'] === 'public' ? 'selected' : '' ?>>General Public</option>
      <option value="restaurant"<?= $old['role'] === 'restaurant' ? 'selected' : '' ?>>Restaurant Partner</option>
    </select>

    <label for="full_name">Full Name</label>
    <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>

    <label for="phone">Phone</label>
    <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" required>

    <div id="studentFields" style="display:none">
      <label for="student_id_no">Student ID</label>
      <input type="text" id="student_id_no" name="student_id_no" value="<?= e($old['student_id_no']) ?>">
    </div>

    <div id="employeeFields" style="display:none">
      <label for="employee_id_no">Employee ID</label>
      <input type="text" id="employee_id_no" name="employee_id_no" value="<?= e($old['employee_id_no']) ?>">
    </div>

    <div id="institutionField" style="display:none">
      <label for="institution">Institution / Company Name</label>
      <input type="text" id="institution" name="institution" value="<?= e($old['institution']) ?>">
    </div>

    <div id="addressField">
      <label for="address">Address</label>
      <input type="text" id="address" name="address" value="<?= e($old['address']) ?>">
    </div>

    <div id="restaurantFields" style="display:none">
      <label for="restaurant_name">Restaurant Name</label>
      <input type="text" id="restaurant_name" name="restaurant_name" value="<?= e($_POST['restaurant_name'] ?? '') ?>">
      <label for="restaurant_address">Restaurant Address</label>
      <input type="text" id="restaurant_address" name="restaurant_address" value="<?= e($_POST['restaurant_address'] ?? '') ?>">
    </div>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required minlength="6">

    <label for="confirm_password">Confirm Password</label>
    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">

    <button type="submit" class="btn btn-block">Create Account</button>
  </form>

  <p class="mt-2 text-muted">Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Log in</a></p>
</div>

<script>
function toggleRoleFields() {
  const role = document.getElementById('role').value;
  document.getElementById('studentFields').style.display = role === 'student' ? 'block' : 'none';
  document.getElementById('employeeFields').style.display = role === 'employee' ? 'block' : 'none';
  document.getElementById('institutionField').style.display = (role === 'student' || role === 'employee') ? 'block' : 'none';
  document.getElementById('restaurantFields').style.display = role === 'restaurant' ? 'block' : 'none';
  document.getElementById('addressField').style.display = role === 'restaurant' ? 'none' : 'block';
}
toggleRoleFields();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
