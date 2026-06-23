<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['restaurant']);

$pdo = getDB();
$userId = currentUserId();

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE user_id = :uid");
$stmt->execute([':uid' => $userId]);
$restaurant = $stmt->fetch();
$restaurantId = (int)$restaurant['restaurant_id'];

$errors = [];
$editMeal = null;

// Handle Add / Edit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission.';
    }

    $mealId      = (int)($_POST['meal_id'] ?? 0);
    $mealName    = trim($_POST['meal_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $mealType    = $_POST['meal_type'] ?? '';
    $price       = $_POST['price'] ?? '';

    if ($mealName === '') $errors[] = 'Meal name is required.';
    if (!in_array($mealType, ['breakfast', 'lunch', 'dinner'], true)) $errors[] = 'Please select a valid meal type.';
    if (!is_numeric($price) || (float)$price <= 0) $errors[] = 'Please enter a valid price greater than 0.';

    if (empty($errors)) {
        if ($mealId > 0) {
            // Edit — verify ownership
            $own = $pdo->prepare("SELECT meal_id FROM meals WHERE meal_id = :mid AND restaurant_id = :rid");
            $own->execute([':mid' => $mealId, ':rid' => $restaurantId]);
            if (!$own->fetch()) {
                $errors[] = 'Meal not found.';
            } else {
                $upd = $pdo->prepare(
                    "UPDATE meals SET meal_name=:name, description=:desc, meal_type=:type, price=:price WHERE meal_id=:mid"
                );
                $upd->execute([':name' => $mealName, ':desc' => $description, ':type' => $mealType, ':price' => $price, ':mid' => $mealId]);
                setFlash('success', 'Meal updated successfully.');
            }
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO meals (restaurant_id, meal_name, description, meal_type, price, is_available)
                 VALUES (:rid, :name, :desc, :type, :price, 1)"
            );
            $ins->execute([':rid' => $restaurantId, ':name' => $mealName, ':desc' => $description, ':type' => $mealType, ':price' => $price]);
            setFlash('success', 'Meal added successfully.');
        }

        if (empty($errors)) {
            redirect('/restaurant/manage_meals.php');
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $own = $pdo->prepare("SELECT meal_id FROM meals WHERE meal_id = :mid AND restaurant_id = :rid");
    $own->execute([':mid' => $delId, ':rid' => $restaurantId]);
    if ($own->fetch()) {
        $pdo->prepare("DELETE FROM meals WHERE meal_id = :mid")->execute([':mid' => $delId]);
        setFlash('success', 'Meal deleted.');
    }
    redirect('/restaurant/manage_meals.php');
}

// Handle toggle availability
if (isset($_GET['toggle'])) {
    $togId = (int)$_GET['toggle'];
    $own = $pdo->prepare("SELECT meal_id FROM meals WHERE meal_id = :mid AND restaurant_id = :rid");
    $own->execute([':mid' => $togId, ':rid' => $restaurantId]);
    if ($own->fetch()) {
        $pdo->prepare("UPDATE meals SET is_available = NOT is_available WHERE meal_id = :mid")->execute([':mid' => $togId]);
    }
    redirect('/restaurant/manage_meals.php');
}

// Load meal for editing
if (isset($_GET['edit'])) {
    $edId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM meals WHERE meal_id = :mid AND restaurant_id = :rid");
    $stmt->execute([':mid' => $edId, ':rid' => $restaurantId]);
    $editMeal = $stmt->fetch();
}

// List all meals
$stmt = $pdo->prepare("SELECT * FROM meals WHERE restaurant_id = :rid ORDER BY FIELD(meal_type,'breakfast','lunch','dinner'), meal_name");
$stmt->execute([':rid' => $restaurantId]);
$meals = $stmt->fetchAll();

$pageTitle = 'Manage Meals';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-title">
  <h2 class="mt-0">Manage Meals</h2>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <h3 class="mt-0"><?= $editMeal ? 'Edit Meal' : 'Add a New Meal' ?></h3>
  <form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="meal_id" value="<?= $editMeal['meal_id'] ?? 0 ?>">

    <label for="meal_name">Meal Name</label>
    <input type="text" id="meal_name" name="meal_name" value="<?= e($editMeal['meal_name'] ?? '') ?>" required>

    <label for="description">Description</label>
    <textarea id="description" name="description"><?= e($editMeal['description'] ?? '') ?></textarea>

    <label for="meal_type">Meal Type</label>
    <select id="meal_type" name="meal_type">
      <?php foreach (['breakfast', 'lunch', 'dinner'] as $t): ?>
        <option value="<?= $t ?>" <?= ($editMeal['meal_type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="price">Price (৳)</label>
    <input type="number" id="price" name="price" step="0.01" min="1" value="<?= e($editMeal['price'] ?? '') ?>" required>

    <button type="submit" class="btn btn-block"><?= $editMeal ? 'Update Meal' : 'Add Meal' ?></button>
    <?php if ($editMeal): ?>
      <a href="<?= BASE_URL ?>/restaurant/manage_meals.php" class="btn btn-secondary btn-block" style="margin-top:8px; text-align:center;">Cancel Edit</a>
    <?php endif; ?>
  </form>
</div>

<div class="card mt-2">
  <h3 class="mt-0">Your Meals</h3>
  <?php if (empty($meals)): ?>
    <p class="empty-state">No meals added yet.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Name</th><th>Type</th><th>Price</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($meals as $m): ?>
        <tr>
          <td><?= e($m['meal_name']) ?></td>
          <td><?= e(ucfirst($m['meal_type'])) ?></td>
          <td><?= formatMoney((float)$m['price']) ?></td>
          <td>
            <span class="badge <?= $m['is_available'] ? 'badge-accepted' : 'badge-rejected' ?>">
              <?= $m['is_available'] ? 'Available' : 'Unavailable' ?>
            </span>
          </td>
          <td>
            <a href="?edit=<?= $m['meal_id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
            <a href="?toggle=<?= $m['meal_id'] ?>" class="btn btn-sm btn-secondary"><?= $m['is_available'] ? 'Disable' : 'Enable' ?></a>
            <a href="?delete=<?= $m['meal_id'] ?>" class="btn btn-sm btn-danger" data-confirm="Delete this meal permanently?">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
