<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    $redirectMap = [
        'student'    => '/student/dashboard.php',
        'employee'   => '/employee/dashboard.php',
        'public'     => '/public_user/dashboard.php',
        'restaurant' => '/restaurant/dashboard.php',
        'admin'      => '/admin/dashboard.php',
    ];
    redirect($redirectMap[currentUserRole()] ?? '/auth/login.php');
}

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<div class="hero">
  <h1>Order meals. Track delivery. Pay on your schedule.</h1>
  <p>One platform for students, employees, and the general public to request meals from local
     restaurants — with flexible billing cycles and real-time delivery tracking.</p>
  <a href="<?= BASE_URL ?>/auth/register.php" class="btn">Get Started</a>
  <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-secondary">Log In</a>

  <div class="role-grid">
    <div class="role-card">
      <div class="role-icon">🎓</div>
      <h3>Students</h3>
      <p class="text-muted">Order breakfast, lunch & dinner from campus-area restaurants. Billed monthly.</p>
    </div>
    <div class="role-card">
      <div class="role-icon">💼</div>
      <h3>Employees</h3>
      <p class="text-muted">Workday meals delivered on time. Billed monthly, deducted with payroll ease.</p>
    </div>
    <div class="role-card">
      <div class="role-icon">🧑‍🤝‍🧑</div>
      <h3>General Public</h3>
      <p class="text-muted">Order from any partner restaurant. Billed weekly — pay as you go.</p>
    </div>
    <div class="role-card">
      <div class="role-icon">🍳</div>
      <h3>Restaurants</h3>
      <p class="text-muted">List your meals, manage availability, and accept incoming orders.</p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
