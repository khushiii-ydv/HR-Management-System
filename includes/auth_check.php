<?php
/**
 * Authentication & Authorization Check
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session inactivity timeout (30 minutes = 1800 seconds)
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $now = time();
    $lastAct = $_SESSION['last_activity'] ?? $now;
    if ($now - $lastAct > 1800) {
        // Session timed out
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        session_start();
        setFlashMessage('warning', 'Your session has expired due to 30 minutes of inactivity. Please sign in again.');
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
    $_SESSION['last_activity'] = $now;
}

// Localhost Automated QA / Preview Helper (Secured strictly to local non-production environments with APP_DEBUG=true)
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = ($clientIp === '127.0.0.1' || $clientIp === '::1');
if ($isLocal && defined('APP_DEBUG') && APP_DEBUG && defined('APP_ENV') && APP_ENV !== 'production' && isset($_GET['preview_as'])) {
    $previewRole = $_GET['preview_as'];
    try {
        $pdo = getDBConnection();
        $sql = "SELECT u.*, e.id AS emp_id, e.employee_code, e.department_id, e.designation_id, 
                       d.department_name, ds.designation_name
                FROM users u
                LEFT JOIN employees e ON e.user_id = u.id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN designations ds ON ds.id = e.designation_id
                WHERE u.role = ? AND u.status = 'active' 
                ORDER BY u.id ASC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$previewRole]);
        $u = $stmt->fetch();
        if ($u) {
            $_SESSION['user_id']          = $u['id'];
            $_SESSION['user_name']        = $u['name'];
            $_SESSION['user_email']       = $u['email'];
            $_SESSION['user_role']        = $u['role'];
            $_SESSION['employee_id']      = $u['emp_id'];
            $_SESSION['employee_code']    = $u['employee_code'];
            $_SESSION['department_name']  = $u['department_name'];
            $_SESSION['designation_name'] = $u['designation_name'];
        }
    } catch (Exception $e) {}
}

/**
 * Check if a user is currently logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user details
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'            => $_SESSION['user_id'],
        'name'          => $_SESSION['user_name'] ?? 'User',
        'email'         => $_SESSION['user_email'] ?? '',
        'role'          => $_SESSION['user_role'] ?? 'employee',
        'employee_id'   => $_SESSION['employee_id'] ?? null,
        'employee_code' => $_SESSION['employee_code'] ?? null,
        'profile_image' => $_SESSION['profile_image'] ?? null,
        'department'    => $_SESSION['department_name'] ?? null,
        'designation'   => $_SESSION['designation_name'] ?? null
    ];
}

/**
 * Enforce authentication. Redirects to login if not logged in.
 * Prevents unverified users from bypassing email verification via direct URLs.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        header("Location: " . BASE_URL . "auth/login.php?redirect=" . urlencode($currentUrl));
        exit;
    }

    // Direct dashboard bypass prevention: check if email verification is still pending
    if (isset($_SESSION['email_verified']) && (int)$_SESSION['email_verified'] === 0) {
        $_SESSION['pending_user_id'] = $_SESSION['user_id'];
        $_SESSION['pending_otp_type'] = 'email_verification';
        header("Location: " . BASE_URL . "auth/verify_otp.php");
        exit;
    }
}

/**
 * Check if the user has a specific role or one of multiple roles
 * 
 * @param string|array $roles Single role or array of allowed roles
 * @return bool
 */
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    $currentRole = $_SESSION['user_role'] ?? '';
    if (is_array($roles)) {
        return in_array($currentRole, $roles, true);
    }
    return $currentRole === $roles;
}

/**
 * Enforce role-based access. Halts execution or redirects if role is not allowed.
 * 
 * @param string|array $roles Allowed role(s)
 */
function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        http_response_code(403);
        include __DIR__ . '/403.php';
        exit;
    }
}

/**
 * Check if the logged-in user is an employee matching given employee_id
 */
function isCurrentEmployee($employeeId) {
    if (!isLoggedIn()) return false;
    if (hasRole(['admin', 'hr'])) return true; // Admins and HR have access to all employees
    return isset($_SESSION['employee_id']) && (int)$_SESSION['employee_id'] === (int)$employeeId;
}

/**
 * Get Employee ID for current user
 */
function getLoggedEmployeeId() {
    return $_SESSION['employee_id'] ?? null;
}

// Load company settings into session if not already loaded
if (!isset($_SESSION['company_settings_loaded'])) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT * FROM company_settings LIMIT 1");
        $settings = $stmt->fetch();
        if ($settings) {
            $_SESSION['company_name']     = $settings['company_name'] ?? 'HRMS';
            $_SESSION['company_email']    = $settings['company_email'] ?? 'hr@company.com';
            $_SESSION['company_currency'] = $settings['currency'] ?? 'INR';
            $_SESSION['company_timezone'] = $settings['timezone'] ?? 'Asia/Kolkata';
            $_SESSION['company_logo']     = $settings['company_logo'] ?? null;
        }
        $_SESSION['company_settings_loaded'] = true;
    } catch (Exception $e) {
        // Fallback default
        $_SESSION['company_name'] = 'HRMS';
        $_SESSION['company_currency'] = 'INR';
    }
}
