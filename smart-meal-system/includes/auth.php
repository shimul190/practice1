<?php
/**
 * Authentication & Session Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Log a user in by storing their info in the session.
 */
function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['user_id'];
    $_SESSION['full_name']  = $user['full_name'];
    $_SESSION['email']      = $user['email'];
    $_SESSION['role']       = $user['role'];
}

/**
 * Log the current user out.
 */
function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function currentUserRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Redirect helper.
 */
function redirect(string $path): never
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Require the user to be logged in, otherwise redirect to login.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('/auth/login.php');
    }
}

/**
 * Require the user to be logged in AND have one of the allowed roles.
 * @param string[] $roles
 */
function requireRole(array $roles): void
{
    requireLogin();
    if (!in_array(currentUserRole(), $roles, true)) {
        http_response_code(403);
        die('<h2>403 Forbidden</h2><p>You do not have permission to access this page.</p>');
    }
}

/**
 * Escape output safely for HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Simple CSRF token generation & validation.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && $token !== null
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash message helpers (one-time messages shown after redirect).
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function getFlash(string $type): ?string
{
    if (!empty($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}
