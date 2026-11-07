<?php
/**
 * Logout Handler
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    try {
        $pdo = getDBConnection();
        logAudit($pdo, $_SESSION['user_id'], 'Logout', 'Authentication', 'User logged out.');
    } catch (Exception $e) {
        // Continue logout
    }
}

// Clear all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Start a fresh session for the flash message
session_start();
setFlashMessage('success', 'You have been successfully logged out.');

header("Location: " . BASE_URL . "auth/login.php");
exit;

