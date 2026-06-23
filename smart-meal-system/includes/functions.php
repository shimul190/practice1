<?php
/**
 * Core Business Logic Functions
 * - Billing cycle rules (students/employees = monthly, public = weekly)
 * - Meal summary aggregation
 * - Notification helper
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Determine the billing cycle type for a given role.
 */
function billingCycleForRole(string $role): string
{
    return $role === 'public' ? 'weekly' : 'monthly';
}

/**
 * Get the start/end date range for the CURRENT billing period of a user,
 * based on their role.
 *
 * @return array{start: string, end: string, due: string}
 */
function currentBillingPeriod(string $role): array
{
    $today = new DateTime('today');

    if ($role === 'public') {
        // Weekly cycle: Monday -> Sunday
        $dayOfWeek = (int)$today->format('N'); // 1 (Mon) - 7 (Sun)
        $start = (clone $today)->modify('-' . ($dayOfWeek - 1) . ' days');
        $end   = (clone $start)->modify('+6 days');
        return [
            'start' => $start->format('Y-m-d'),
            'end'   => $end->format('Y-m-d'),
            'due'   => $end->format('Y-m-d'),
        ];
    }

    // Monthly cycle for students & employees
    $start = new DateTime($today->format('Y-m-01'));
    $end   = (clone $start)->modify('last day of this month');
    return [
        'start' => $start->format('Y-m-d'),
        'end'   => $end->format('Y-m-d'),
        'due'   => $end->format('Y-m-d'),
    ];
}

/**
 * Recalculate (or create) the pending payment record for a user's CURRENT
 * billing period, based on their delivered/accepted orders in that period.
 * Call this after placing or updating an order.
 */
function syncCurrentPayment(PDO $pdo, int $userId, string $role): void
{
    $cycle  = billingCycleForRole($role);
    $period = currentBillingPeriod($role);

    // Sum up orders within the current period that are not cancelled/rejected
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS meal_count, COALESCE(SUM(total_price), 0) AS total_amount
         FROM orders
         WHERE user_id = :uid
           AND order_date BETWEEN :start AND :end
           AND order_status NOT IN ('rejected','cancelled')"
    );
    $stmt->execute([
        ':uid'   => $userId,
        ':start' => $period['start'],
        ':end'   => $period['end'],
    ]);
    $summary = $stmt->fetch();

    // Check if a payment row already exists for this exact period
    $check = $pdo->prepare(
        "SELECT payment_id, status FROM payments
         WHERE user_id = :uid AND period_start = :start AND period_end = :end"
    );
    $check->execute([':uid' => $userId, ':start' => $period['start'], ':end' => $period['end']]);
    $existing = $check->fetch();

    if ($existing) {
        // Don't overwrite an already-paid record's amount silently; only update if still pending
        if ($existing['status'] === 'pending') {
            $upd = $pdo->prepare(
                "UPDATE payments
                 SET total_meals = :meals, total_amount = :amount
                 WHERE payment_id = :pid"
            );
            $upd->execute([
                ':meals'  => $summary['meal_count'],
                ':amount' => $summary['total_amount'],
                ':pid'    => $existing['payment_id'],
            ]);
        }
    } else {
        $ins = $pdo->prepare(
            "INSERT INTO payments
                (user_id, billing_cycle, period_start, period_end, total_meals, total_amount, due_date, payment_method, status)
             VALUES (:uid, :cycle, :start, :end, :meals, :amount, :due, 'unpaid', 'pending')"
        );
        $ins->execute([
            ':uid'    => $userId,
            ':cycle'  => $cycle,
            ':start'  => $period['start'],
            ':end'    => $period['end'],
            ':meals'  => $summary['meal_count'],
            ':amount' => $summary['total_amount'],
            ':due'    => $period['due'],
        ]);
    }
}

/**
 * Get meal summary counts (daily/weekly/monthly) + total payable for a user.
 */
function getMealSummary(PDO $pdo, int $userId): array
{
    $today = (new DateTime('today'))->format('Y-m-d');

    // Daily
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) cnt, COALESCE(SUM(total_price),0) amt FROM orders
         WHERE user_id = :uid AND order_date = :today AND order_status NOT IN ('rejected','cancelled')"
    );
    $stmt->execute([':uid' => $userId, ':today' => $today]);
    $daily = $stmt->fetch();

    // Weekly (Mon-Sun current week)
    $dayOfWeek = (int)(new DateTime('today'))->format('N');
    $weekStart = (new DateTime('today'))->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
    $weekEnd   = (new DateTime('today'))->modify('+' . (7 - $dayOfWeek) . ' days')->format('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) cnt, COALESCE(SUM(total_price),0) amt FROM orders
         WHERE user_id = :uid AND order_date BETWEEN :s AND :e AND order_status NOT IN ('rejected','cancelled')"
    );
    $stmt->execute([':uid' => $userId, ':s' => $weekStart, ':e' => $weekEnd]);
    $weekly = $stmt->fetch();

    // Monthly (current calendar month)
    $monthStart = (new DateTime('today'))->format('Y-m-01');
    $monthEnd   = (new DateTime($monthStart))->modify('last day of this month')->format('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) cnt, COALESCE(SUM(total_price),0) amt FROM orders
         WHERE user_id = :uid AND order_date BETWEEN :s AND :e AND order_status NOT IN ('rejected','cancelled')"
    );
    $stmt->execute([':uid' => $userId, ':s' => $monthStart, ':e' => $monthEnd]);
    $monthly = $stmt->fetch();

    // Total payable (pending/overdue payments)
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(total_amount),0) amt FROM payments
         WHERE user_id = :uid AND status IN ('pending','overdue')"
    );
    $stmt->execute([':uid' => $userId]);
    $payable = $stmt->fetch();

    return [
        'daily_count'    => (int)$daily['cnt'],
        'daily_amount'   => (float)$daily['amt'],
        'weekly_count'   => (int)$weekly['cnt'],
        'weekly_amount'  => (float)$weekly['amt'],
        'monthly_count'  => (int)$monthly['cnt'],
        'monthly_amount' => (float)$monthly['amt'],
        'total_payable'  => (float)$payable['amt'],
    ];
}

/**
 * Create a notification for a user.
 */
function notify(PDO $pdo, int $userId, string $title, string $message): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, title, message) VALUES (:uid, :title, :msg)"
    );
    $stmt->execute([':uid' => $userId, ':title' => $title, ':msg' => $message]);
}

/**
 * Fetch unread notification count for the navbar badge.
 */
function unreadNotificationCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $stmt->execute([':uid' => $userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Format currency for display (BDT).
 */
function formatMoney(float $amount): string
{
    return '৳' . number_format($amount, 2);
}
