<?php
/**
 * Shared header/navbar. Included at the top of every page.
 * Expects $pageTitle to optionally be set before include.
 */
$pageTitle = $pageTitle ?? 'Smart Meal Management System';
$role = currentUserRole();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | Smart Meal System</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="navbar">
  <div class="navbar-inner">
    <a href="<?= BASE_URL ?>/index.php" class="brand">🍽️ Smart Meal System</a>
    <nav class="nav-links">
      <?php if (isLoggedIn()): ?>
        <?php if ($role === 'student'): ?>
          <a href="<?= BASE_URL ?>/student/dashboard.php">Dashboard</a>
          <a href="<?= BASE_URL ?>/student/browse_meals.php">Browse Meals</a>
          <a href="<?= BASE_URL ?>/student/my_orders.php">My Orders</a>
          <a href="<?= BASE_URL ?>/student/payments.php">Payments</a>
        <?php elseif ($role === 'employee'): ?>
          <a href="<?= BASE_URL ?>/employee/dashboard.php">Dashboard</a>
          <a href="<?= BASE_URL ?>/employee/browse_meals.php">Browse Meals</a>
          <a href="<?= BASE_URL ?>/employee/my_orders.php">My Orders</a>
          <a href="<?= BASE_URL ?>/employee/payments.php">Payments</a>
        <?php elseif ($role === 'public'): ?>
          <a href="<?= BASE_URL ?>/public_user/dashboard.php">Dashboard</a>
          <a href="<?= BASE_URL ?>/public_user/browse_meals.php">Browse Meals</a>
          <a href="<?= BASE_URL ?>/public_user/my_orders.php">My Orders</a>
          <a href="<?= BASE_URL ?>/public_user/payments.php">Payments</a>
        <?php elseif ($role === 'restaurant'): ?>
          <a href="<?= BASE_URL ?>/restaurant/dashboard.php">Dashboard</a>
          <a href="<?= BASE_URL ?>/restaurant/manage_meals.php">Manage Meals</a>
          <a href="<?= BASE_URL ?>/restaurant/manage_orders.php">Orders</a>
        <?php elseif ($role === 'admin'): ?>
          <a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
          <a href="<?= BASE_URL ?>/admin/manage_users.php">Users</a>
          <a href="<?= BASE_URL ?>/admin/manage_restaurants.php">Restaurants</a>
          <a href="<?= BASE_URL ?>/admin/reports.php">Reports</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/includes/notifications.php">🔔</a>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="btn-logout">Logout (<?= e($_SESSION['full_name'] ?? '') ?>)</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/auth/login.php">Login</a>
        <a href="<?= BASE_URL ?>/auth/register.php" class="btn-cta">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">
<?php
$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
if ($flashSuccess): ?>
  <div class="alert alert-success"><?= e($flashSuccess) ?></div>
<?php endif;
if ($flashError): ?>
  <div class="alert alert-error"><?= e($flashError) ?></div>
<?php endif; ?>
