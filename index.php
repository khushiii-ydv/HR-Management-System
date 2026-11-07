<?php
/**
 * Root Index Dispatcher
 * HR Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "dashboard.php");
} else {
    header("Location: " . BASE_URL . "auth/login.php");
}
exit;

