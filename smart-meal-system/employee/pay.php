<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

requireRole(['employee']);

$pdo = getDB();
$userId = currentUserId();
$paymentId = (int)($_GET['payment_id'] ?? $_POST['payment_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = :pid AND user_id = :uid");
$stmt->execute([':pid' => $paymentId, ':uid' => $userId]);
$payment = $stmt->fetch();

if (!$payment) {
    setFlash('error', 'Payment record not found.');
    redirect('/employee/payments.php');
}

if (!in_array($payment['status'], ['pending', 'overdue'], true)) {
    setFlash('error', 'This payment has already been processed.');
    redirect('/employee/payments.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $method = $_POST['payment_method'] ?? '';
    if (!in_array($method, ['bkash', 'nagad', 'card'], true)) {
        $errors[] = 'Please select a valid payment method.';
    }

    if (empty($errors)) {
        $result = processPayment($method, (float)$payment['total_amount'], $_POST);

        if ($result['success']) {
            $upd = $pdo->prepare(
                "UPDATE payments
                 SET payment_method = :method, transaction_ref = :ref, status = 'paid', paid_at = NOW()
                 WHERE payment_id = :pid"
            );
            $upd->execute([
                ':method' => $method,
                ':ref'    => $result['transaction_ref'],
                ':pid'    => $paymentId,
            ]);

            notify($pdo, $userId, 'Payment Successful',
                'Your payment of ' . formatMoney((float)$payment['total_amount']) . " via " . strtoupper($method) . " was successful. Ref: {$result['transaction_ref']}");

            setFlash('success', 'Payment successful! Transaction ref: ' . $result['transaction_ref']);
            redirect('/employee/payments.php');
        } else {
            $errors[] = $result['message'];
        }
    }
}

$pageTitle = 'Make Payment';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="form-card">
  <h2>Pay Your Bill</h2>
  <p class="text-muted">
    Period: <?= e($payment['period_start']) ?> to <?= e($payment['period_end']) ?><br>
    Total Meals: <?= (int)$payment['total_meals'] ?><br>
    Amount Due: <strong><?= formatMoney((float)$payment['total_amount']) ?></strong>
  </p>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="payment_id" value="<?= $paymentId ?>">

    <label for="payment_method">Payment Method</label>
    <select name="payment_method" id="payment_method" onchange="togglePaymentFields()">
      <option value="bkash">bKash</option>
      <option value="nagad">Nagad</option>
      <option value="card">Debit / Credit Card</option>
    </select>

    <div id="mobileWalletFields">
      <label for="wallet_number">Mobile Number</label>
      <input type="tel" id="wallet_number" name="wallet_number" placeholder="01XXXXXXXXX" pattern="01[0-9]{9}">
      <p class="text-muted" style="font-size:0.78rem;">Demo mode: any valid-looking number will simulate a successful payment.</p>
    </div>

    <div id="cardFields" style="display:none">
      <label for="card_number">Card Number</label>
      <input type="text" id="card_number" name="card_number" placeholder="4242 4242 4242 4242" maxlength="19">
      <div style="display:flex; gap:10px;">
        <div style="flex:1">
          <label for="card_expiry">Expiry</label>
          <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY" maxlength="5">
        </div>
        <div style="flex:1">
          <label for="card_cvv">CVV</label>
          <input type="text" id="card_cvv" name="card_cvv" placeholder="123" maxlength="4">
        </div>
      </div>
      <p class="text-muted" style="font-size:0.78rem;">Demo mode: this is a stub gateway. No real card is charged.</p>
    </div>

    <button type="submit" class="btn btn-block">Pay <?= formatMoney((float)$payment['total_amount']) ?></button>
  </form>
</div>

<script>
function togglePaymentFields() {
  const method = document.getElementById('payment_method').value;
  document.getElementById('mobileWalletFields').style.display = method === 'card' ? 'none' : 'block';
  document.getElementById('cardFields').style.display = method === 'card' ? 'block' : 'none';
}
togglePaymentFields();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
