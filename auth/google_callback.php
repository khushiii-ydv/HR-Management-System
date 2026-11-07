<?php
/**
 * Official Google OAuth 2.0 Callback Handler
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/google_oauth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDBConnection();
$error = '';
$verifiedEmail = null;
$googleId = null;
$action = 'login';

try {
    // 1. Handle Local Test Simulator (Strictly disabled in production)
    if (defined('APP_DEBUG') && APP_DEBUG && defined('APP_ENV') && APP_ENV !== 'production' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulation_token'])) {
        $expectedToken = hash_hmac('sha256', 'google_sim', 'ky_hrms_salt_2026');
        if (!hash_equals($expectedToken, $_POST['simulation_token'])) {
            throw new Exception("Security validation failed for simulated OAuth callback.");
        }
        $verifiedEmail = strtolower(trim($_POST['verified_google_email'] ?? ''));
        $googleId = 'google_sim_' . substr(md5($verifiedEmail), 0, 16);
    } 
    // 2. Handle Official Google OAuth Callback
    elseif (isset($_GET['code'])) {
        // CSRF state check
        $stateParam = $_GET['state'] ?? '';
        $stateParts = explode(':', $stateParam, 2);
        $returnedCsrf = $stateParts[0] ?? '';
        $action = $stateParts[1] ?? 'login';

        $storedCsrf = $_SESSION['oauth_state'] ?? '';
        if (empty($returnedCsrf) || empty($storedCsrf) || !hash_equals($storedCsrf, $returnedCsrf)) {
            throw new Exception("Invalid OAuth state token. Potential CSRF detected.");
        }
        unset($_SESSION['oauth_state']);

        // Exchange code for token
        $tokenData = exchangeGoogleCodeForToken($_GET['code']);
        $profile = getGoogleUserProfile($tokenData['access_token']);

        if (empty($profile['email_verified']) && $profile['email_verified'] !== true) {
            throw new Exception("The Google account provided does not have a verified email address.");
        }

        $verifiedEmail = $profile['email'];
        $googleId = $profile['google_id'];
    } else {
        $errorParam = $_GET['error'] ?? 'No authorization code received from Google.';
        throw new Exception("Google Authentication Error: " . htmlspecialchars($errorParam));
    }

    if (empty($verifiedEmail) || !filter_var($verifiedEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid or missing email in Google identity response.");
    }

    // 3. Handle Account Linking Flow (from User Settings)
    if ($action === 'link') {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            throw new Exception("Authentication required to link an account.");
        }

        $userId = (int)$_SESSION['user_id'];
        $uStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $currentEmail = strtolower(trim($uStmt->fetchColumn() ?: ''));

        // Strict Email Matching Rule for Linking
        if ($verifiedEmail !== $currentEmail) {
            setFlashMessage('danger', "Google account linking failed: The authenticated Google account (" . maskEmail($verifiedEmail) . ") does not match your registered HRMS email (" . maskEmail($currentEmail) . ").");
            header("Location: " . BASE_URL . "settings/index.php");
            exit;
        }

        $upd = $pdo->prepare("UPDATE users SET google_id = ?, google_email = ? WHERE id = ?");
        $upd->execute([$googleId, $verifiedEmail, $userId]);

        logAudit($pdo, $userId, 'Link Google Account', 'Security', "Linked Google account ($verifiedEmail)");
        setFlashMessage('success', "Google Account successfully linked to your profile!");
        header("Location: " . BASE_URL . "settings/index.php");
        exit;
    }

    // 4. Handle Standard Login Flow: Strict Email Matching Rule
    $sql = "SELECT u.*, e.id AS emp_id, e.employee_code, e.department_id, e.designation_id, 
                   d.department_name, ds.designation_name
            FROM users u
            LEFT JOIN employees e ON e.user_id = u.id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations ds ON ds.id = e.designation_id
            WHERE u.email = ? OR u.google_email = ?
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$verifiedEmail, $verifiedEmail]);
    $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRecord) {
        // STRICT ACCESS DENIAL: Unregistered Email
        logAudit($pdo, null, 'Google OAuth Denied', 'Authentication', "Denied unregistered Google email: $verifiedEmail");
        setFlashMessage('danger', "Access Denied: The Google account <strong>" . htmlspecialchars(maskEmail($verifiedEmail)) . "</strong> is not registered in the HRMS database. Please create an account first.");
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }

    // Status verification
    if ($userRecord['status'] === 'inactive' || $userRecord['status'] === 'suspended') {
        logAudit($pdo, $userRecord['id'], 'Google Login Blocked', 'Authentication', "Suspended account tried logging in via Google: $verifiedEmail");
        setFlashMessage('danger', "Access Denied: Your account is currently inactive or suspended. Please contact HR Administration.");
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }

    // If account was pending verification, activate it now since Google verified email ownership!
    if ($userRecord['status'] === 'pending_verification' || (int)$userRecord['email_verified'] === 0) {
        $pdo->prepare("UPDATE users SET status = 'active', email_verified = 1, email_verified_at = NOW() WHERE id = ?")->execute([$userRecord['id']]);
        $userRecord['status'] = 'active';
    }

    // Update google_id & last login
    $updStmt = $pdo->prepare("UPDATE users SET google_id = ?, google_email = ?, last_login = NOW(), failed_attempts = 0 WHERE id = ?");
    $updStmt->execute([$googleId, $verifiedEmail, $userRecord['id']]);

    // Establish secure session with regeneration to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']          = $userRecord['id'];
    $_SESSION['user_name']        = $userRecord['name'];
    $_SESSION['user_email']       = $userRecord['email'];
    $_SESSION['user_role']        = $userRecord['role'];
    $_SESSION['employee_id']      = $userRecord['emp_id'];
    $_SESSION['employee_code']    = $userRecord['employee_code'];
    $_SESSION['department_name']  = $userRecord['department_name'];
    $_SESSION['designation_name'] = $userRecord['designation_name'];
    $_SESSION['email_verified']   = 1;
    $_SESSION['last_activity']    = time();

    logAudit($pdo, $userRecord['id'], 'Google OAuth Login', 'Authentication', "Signed in via verified Google OAuth ({$userRecord['email']})");
    setFlashMessage('success', "Signed in successfully via Google as " . htmlspecialchars($userRecord['name']) . " (" . strtoupper($userRecord['role']) . ")!");

    header("Location: " . BASE_URL . "dashboard.php");
    exit;

} catch (Exception $e) {
    setFlashMessage('danger', "Google Authentication Error: " . $e->getMessage());
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

