<?php
/**
 * Forgot Password Screen
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $message = 'Security verification failed (CSRF mismatch). Please refresh and try again.';
        $messageType = 'danger';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please provide a valid registered work email address.';
            $messageType = 'danger';
        } else {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $sendRes = sendPasswordResetEmail($pdo, $user);
                    if ($sendRes['success']) {
                        logAudit($pdo, $user['id'], 'Password Reset Request', 'Authentication', 'Requested password reset email.');
                    } else {
                        secureLog('error', "Failed to dispatch password reset email: " . $sendRes['error']);
                    }
                }

                // Security best practice: Always show generic message to prevent account enumeration
                $message = "If an active account exists for that work email, password reset instructions have been dispatched to your inbox.";
                $messageType = 'info';

            } catch (Exception $e) {
                secureLog('error', "Forgot password error: " . $e->getMessage());
                $message = 'An unexpected error occurred. Please try again later.';
                $messageType = 'danger';
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
    <title>Forgot Password | <?= defined('APP_NAME') ? APP_NAME : 'HRMS' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="card shadow-sm border-0 p-4 p-md-5" style="max-width: 480px; width: 90%; border-radius: 16px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex p-3 rounded-circle mb-3" style="background: var(--light-blue); color: var(--primary-blue);">
                <i class="bi bi-key fs-2"></i>
            </div>
            <h3 class="fw-bold text-dark mb-1">Reset your password</h3>
            <p class="text-secondary small">Enter your work email and we'll help you get back in</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> py-2 px-3 small mb-3" role="alert" style="border-radius: 8px;">
                <i class="bi bi-info-circle me-1"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>auth/forgot_password.php" method="POST">
            <?= getCsrfInput() ?>
            <div class="mb-4">
                <label class="form-label" for="email">Work Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-secondary">
                        <i class="bi bi-envelope"></i>
                    </span>
                    <input type="email" class="form-control border-start-0" id="email" name="email" 
                           placeholder="name@company.com" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
                Send Reset Instructions
            </button>

            <div class="text-center">
                <a href="<?= BASE_URL ?>auth/login.php" class="small text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i> Back to sign in
                </a>
            </div>
        </form>
    </div>
</body>
</html>
