<?php
/**
 * Secure OTP Verification (Email Verification & Login 2FA)
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDBConnection();
$error = '';
$success = '';

// Check pending user session
$pendingUserId = $_SESSION['pending_user_id'] ?? null;
$otpType       = $_SESSION['pending_otp_type'] ?? 'login_2fa'; // 'login_2fa' or 'email_verification'

if (!$pendingUserId) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

// Fetch pending user
$uStmt = $pdo->prepare("SELECT u.*, e.id AS emp_id, e.employee_code, e.department_id, e.designation_id, 
                               d.department_name, ds.designation_name
                        FROM users u
                        LEFT JOIN employees e ON e.user_id = u.id
                        LEFT JOIN departments d ON d.id = e.department_id
                        LEFT JOIN designations ds ON ds.id = e.designation_id
                        WHERE u.id = ?");
$uStmt->execute([$pendingUserId]);
$userRecord = $uStmt->fetch(PDO::FETCH_ASSOC);

if (!$userRecord) {
    unset($_SESSION['pending_user_id'], $_SESSION['pending_otp_type']);
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

// Handle Resend Request
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    $sendResult = sendOtpEmail($pdo, $userRecord, $otpType);
    if ($sendResult['success']) {
        $success = "A new verification code has been dispatched to your email address.";
    } else {
        $error = $sendResult['error'] ?: "Failed to dispatch verification code. Please check your SMTP settings or try again.";
    }
}

// Handle OTP Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = "Security token mismatch. Please refresh and try again.";
    } else {
        $submittedCode = trim($_POST['otp_code'] ?? '');

        if (empty($submittedCode) || strlen($submittedCode) !== 6 || !ctype_digit($submittedCode)) {
            $error = "Please enter a valid 6-digit verification code.";
        } else {
            try {
                $pdo->beginTransaction();

                $result = verifyOtp($pdo, $pendingUserId, $submittedCode, $otpType);

                if ($result['valid']) {
                    // VERIFICATION SUCCESSFUL

                    // If email verification, activate user account
                    if ($otpType === 'email_verification') {
                        $upd = $pdo->prepare("UPDATE users SET status = 'active', email_verified = 1, email_verified_at = NOW() WHERE id = ?");
                        $upd->execute([$pendingUserId]);
                        $userRecord['status'] = 'active';
                        $userRecord['email_verified'] = 1;
                    }

                    // Reset failed login attempts & update last login
                    $pdo->prepare("UPDATE users SET last_login = NOW(), failed_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$userRecord['id']]);

                    $pdo->commit();

                    // Clear pending session markers
                    unset($_SESSION['pending_user_id'], $_SESSION['pending_otp_type'], $_SESSION['last_otp_resend']);

                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    // Establish authenticated session
                    $_SESSION['user_id']          = $userRecord['id'];
                    $_SESSION['user_name']        = $userRecord['name'];
                    $_SESSION['user_email']       = $userRecord['email'];
                    $_SESSION['user_role']        = $userRecord['role'];
                    $_SESSION['employee_id']      = $userRecord['emp_id'];
                    $_SESSION['employee_code']    = $userRecord['employee_code'];
                    $_SESSION['department_name']  = $userRecord['department_name'];
                    $_SESSION['designation_name'] = $userRecord['designation_name'];
                    $_SESSION['last_activity']    = time();

                    logAudit($pdo, $userRecord['id'], '2FA / Email Verified', 'Authentication', "User successfully completed {$otpType} verification");
                    setFlashMessage('success', "Authentication verified! Welcome to your " . ucfirst($userRecord['role']) . " portal.");

                    header("Location: " . BASE_URL . "dashboard.php");
                    exit;
                } else {
                    $pdo->commit();
                    $error = $result['error'];
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                secureLog('error', "Verification transaction error: " . $e->getMessage());
                $error = "An unexpected error occurred during verification. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Verification | <?= defined('APP_NAME') ? APP_NAME : 'HRMS' ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="card shadow-sm border-0 p-4 p-md-5" style="max-width: 460px; width: 92%; border-radius: 16px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex p-3 rounded-circle mb-3" style="background: var(--light-blue); color: var(--primary-blue);">
                <i class="bi bi-shield-lock-fill fs-2"></i>
            </div>
            <h3 class="fw-bold text-dark mb-1">
                <?= ($otpType === 'email_verification') ? 'Verify Your Email' : 'Two-Factor Verification' ?>
            </h3>
            <p class="text-secondary small mb-2">
                A verification code has been sent to your registered email address:
            </p>
            <div class="badge bg-light text-primary border px-3 py-2 font-monospace fs-6">
                <?= htmlspecialchars(maskEmail($userRecord['email'])) ?>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-3" role="alert" style="border-radius: 8px;">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 px-3 small d-flex align-items-center mb-3" role="alert" style="border-radius: 8px;">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>auth/verify_otp.php" method="POST">
            <?= getCsrfInput() ?>
            <div class="mb-4 text-center">
                <label class="form-label fw-semibold text-dark mb-2" for="otp_code">Enter 6-Digit Verification Code</label>
                <input type="text" class="form-control text-center font-monospace fs-3 fw-bold letter-spacing-2" 
                       id="otp_code" name="otp_code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" 
                       placeholder="••••••" required autofocus autocomplete="one-time-code">
                <small class="text-muted d-block mt-2" style="font-size: 0.75rem;">
                    <i class="bi bi-clock-history me-1"></i> Code expires in 10 minutes &bull; Single-use
                </small>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
                <i class="bi bi-check2-circle me-1"></i> Verify & Proceed
            </button>
        </form>

        <div class="d-flex justify-content-between align-items-center pt-2 border-top small">
            <a href="<?= BASE_URL ?>auth/verify_otp.php?resend=1" class="text-primary text-decoration-none fw-medium">
                <i class="bi bi-arrow-clockwise me-1"></i> Resend Code
            </a>
            <a href="<?= BASE_URL ?>auth/logout.php" class="text-secondary text-decoration-none">
                <i class="bi bi-x-circle me-1"></i> Cancel Sign In
            </a>
        </div>
    </div>
</body>
</html>
