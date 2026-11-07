<?php
/**
 * Password Reset Execution
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;
$invalidToken = false;

if (empty($token)) {
    $invalidToken = true;
    $error = "Missing password reset token.";
} else {
    $pdo = getDBConnection();
    $tokenHash = hash('sha256', $token);

    // Look up hashed token (with fallback to legacy raw token)
    $stmt = $pdo->prepare("SELECT pr.*, u.email, u.id AS uid 
                           FROM password_resets pr 
                           JOIN users u ON u.id = pr.user_id 
                           WHERE (pr.token_hash = ? OR pr.token = ?) 
                           AND pr.is_used = 0 
                           AND pr.expires_at > NOW() 
                           LIMIT 1");
    $stmt->execute([$tokenHash, $token]);
    $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRecord) {
        $invalidToken = true;
        $error = "This password reset link is invalid or has expired. Please request a new one.";
    }
}

if (!$invalidToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security verification failed (CSRF mismatch). Please refresh and try again.';
    } else {
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters in length.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must contain both letters and numbers.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $pdo->beginTransaction();

                $hashed = password_hash($password, PASSWORD_BCRYPT);
                
                // Update user password and unlock if locked
                $updateStmt = $pdo->prepare("UPDATE users SET password = ?, locked_until = NULL, failed_attempts = 0, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$hashed, $resetRecord['user_id']]);

                // Invalidate all reset tokens for this user
                $delStmt = $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE user_id = ?");
                $delStmt->execute([$resetRecord['user_id']]);

                logAudit($pdo, $resetRecord['user_id'], 'Password Reset Completed', 'Authentication', 'User successfully reset password via verified token.');

                $pdo->commit();
                $success = true;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                secureLog('error', "Reset password error: " . $e->getMessage());
                $error = 'Error updating password. Please try again.';
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
    <title>Set New Password | <?= defined('APP_NAME') ? APP_NAME : 'HRMS' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="card shadow-sm border-0 p-4 p-md-5" style="max-width: 480px; width: 90%; border-radius: 16px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex p-3 rounded-circle mb-3" style="background: var(--light-blue); color: var(--primary-blue);">
                <i class="bi bi-shield-lock fs-2"></i>
            </div>
            <h3 class="fw-bold text-dark mb-1">Create new password</h3>
            <?php if (!$invalidToken && !empty($resetRecord)): ?>
                <p class="text-secondary small">Set a new secure password for <strong><?= htmlspecialchars($resetRecord['email']) ?></strong></p>
            <?php else: ?>
                <p class="text-secondary small">Reset link status</p>
            <?php endif; ?>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success text-center py-3 mb-4" role="alert" style="border-radius: 8px;">
                <i class="bi bi-check-circle-fill fs-4 d-block mb-2 text-success"></i>
                <div class="fw-semibold">Password updated successfully!</div>
                <p class="small text-muted mb-3">You can now sign in using your newly chosen credentials.</p>
                <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-primary btn-sm px-4">
                    Proceed to Login
                </a>
            </div>
        <?php elseif ($invalidToken): ?>
            <div class="alert alert-danger py-3 px-3 small text-center mb-4" role="alert" style="border-radius: 8px;">
                <i class="bi bi-exclamation-triangle-fill fs-4 d-block mb-2 text-danger"></i>
                <div class="fw-semibold mb-1"><?= htmlspecialchars($error) ?></div>
                <p class="small text-muted mb-3">Password reset links expire after 1 hour or after first use.</p>
                <a href="<?= BASE_URL ?>auth/forgot_password.php" class="btn btn-outline-primary btn-sm px-3">
                    Request New Reset Link
                </a>
            </div>
        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small mb-3" role="alert" style="border-radius: 8px;">
                    <i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>auth/reset_password.php?token=<?= urlencode($token) ?>" method="POST">
                <?= getCsrfInput() ?>
                <div class="mb-3">
                    <label class="form-label" for="password">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-secondary"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control border-start-0" id="password" name="password" minlength="8" placeholder="At least 8 chars with letters & numbers" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-secondary"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" minlength="8" placeholder="Repeat new password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                    Reset Password
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
