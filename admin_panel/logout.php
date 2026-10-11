<?php
/**
 * logout.php - Securely terminate admin session
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional: Log the logout event (useful for security auditing)
if (isset($_SESSION['admin_id'])) {
    $log_message = sprintf(
        "[LOGOUT] User ID: %d | Username: %s | IP: %s | Time: %s",
        $_SESSION['admin_id'],
        $_SESSION['admin_username'] ?? 'unknown',
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        date('Y-m-d H:i:s')
    );
    error_log($log_message);
}

// Clear all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params["path"],
            'domain' => $params["domain"],
            'secure' => $params["secure"],
            'httponly' => $params["httponly"],
            'samesite' => 'Strict' // Modern browsers support SameSite
        ]
    );
}

// Destroy the session
session_destroy();

// Prevent caching of this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login page with optional logout message
$redirect_url = "login.php?logout=1";
header("Location: " . $redirect_url);
exit;