<?php
/**
 * Database Seeder — sets correct bcrypt password hashes for demo accounts.
 * Run this ONCE after importing database/schema.sql:
 *
 *   php database/seed.php
 *
 * This fixes the placeholder password hashes in schema.sql with real,
 * freshly-generated bcrypt hashes so the demo accounts actually work.
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

$demoAccounts = [
    'admin@smartmeal.com'    => 'Admin@123',
    'student@example.com'    => 'Student@123',
    'employee@example.com'   => 'Employee@123',
    'public@example.com'     => 'Public@123',
    'spicegarden@example.com'=> 'Restaurant@123',
    'dailymess@example.com'  => 'Restaurant@123',
];

$updated = 0;
foreach ($demoAccounts as $email => $plainPassword) {
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email");
    $stmt->execute([':hash' => $hash, ':email' => $email]);
    if ($stmt->rowCount() > 0) {
        $updated++;
        echo "Updated password for $email -> $plainPassword\n";
    } else {
        echo "WARNING: no user found with email $email (did you run schema.sql first?)\n";
    }
}

echo "\nDone. $updated account(s) updated with working passwords.\n";
echo "You can now log in with the emails/passwords listed above.\n";
