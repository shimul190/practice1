<?php
/**
 * Database Configuration
 * Smart Meal Management & Delivery System
 *
 * Update DB_HOST, DB_NAME, DB_USER, DB_PASS to match your local MySQL setup.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_meal_system');
define('DB_USER', 'root');
define('DB_PASS', '');        // set your MySQL root password here if you have one
define('DB_CHARSET', 'utf8mb4');

// Base URL of the project (used for redirects, links, asset paths)
// Example: if you access the app at http://localhost/smart-meal-system/
define('BASE_URL', '/smart-meal-system');

/**
 * Returns a shared PDO connection (singleton pattern).
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
