<?php
/**
 * User Self-Registration & Admin Invitation Flow
 * Supports Employee, HR, and Admin registration with Hashed Role Verification / Invitations
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

$pdo = getDBConnection();
$error = '';
$success = '';

// Check for Invitation Token
$inviteToken = trim($_GET['invite'] ?? $_POST['invite_token'] ?? '');
$inviteData = null;

if (!empty($inviteToken)) {
    $tokenHash = hash('sha256', $inviteToken);
    $invStmt = $pdo->prepare("SELECT * FROM role_invitations WHERE token_hash = ? AND status = 'pending' AND expires_at > NOW() LIMIT 1");
    $invStmt->execute([$tokenHash]);
    $inviteData = $invStmt->fetch(PDO::FETCH_ASSOC);

    if (!$inviteData) {
        $error = "This invitation link is invalid or has expired. Please contact your Administrator.";
    }
}

// Fetch active departments for optional employee assignment
$departments = $pdo->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security verification failed (CSRF mismatch). Please refresh and submit again.';
    } else {
        $name         = trim($_POST['name'] ?? '');
        $email        = strtolower(trim($_POST['email'] ?? ''));
        $password     = $_POST['password'] ?? '';
        $confirmPass  = $_POST['confirm_password'] ?? '';
        $role         = $inviteData ? $inviteData['role'] : ($_POST['role'] ?? 'employee');
        $roleKeyInput = trim($_POST['role_access_key'] ?? '');
        $deptId       = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

        if ($inviteData && !empty($inviteData['email'])) {
            $email = strtolower(trim($inviteData['email']));
        }

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Please fill out all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid work email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters in length.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'Password must contain both letters and numbers for enhanced security.';
        } elseif ($password !== $confirmPass) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // Check if email already registered
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetch()) {
                    throw new Exception("The email address '$email' is already registered. Please sign in instead.");
                }

                // Role Verification ID check for Admin & HR (Unless registering via verified invitation link)
                if (($role === 'admin' || $role === 'hr') && !$inviteData) {
                    $keyStmt = $pdo->prepare("SELECT key_hash FROM role_access_keys WHERE role = ?");
                    $keyStmt->execute([$role]);
                    $storedHash = $keyStmt->fetchColumn();

                    if (empty($roleKeyInput) || empty($storedHash) || !password_verify($roleKeyInput, $storedHash)) {
                        throw new Exception("Invalid Security Verification ID for role '" . strtoupper($role) . "'. Elevated registration was denied.");
                    }
                }

                $pdo->beginTransaction();

                // Insert into users with status 'pending_verification'
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $userStmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, email_verified, created_at) 
                                           VALUES (?, ?, ?, ?, 'pending_verification', 0, NOW())");
                $userStmt->execute([$name, $email, $hashedPassword, $role]);
                $newUserId = $pdo->lastInsertId();

                // Generate employee code (e.g. EMP-1001)
                $lastCodeStmt = $pdo->query("SELECT employee_code FROM employees ORDER BY id DESC LIMIT 1");
                $lastCode = $lastCodeStmt->fetchColumn();
                $nextNum = 1001;
                if ($lastCode && preg_match('/EMP-(\d+)/', $lastCode, $m)) {
                    $nextNum = (int)$m[1] + 1;
                }
                $empCode = 'EMP-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

                // Split name into first and last
                $parts = explode(' ', $name, 2);
                $firstName = $parts[0];
                $lastName = $parts[1] ?? 'User';

                // Create linked employee profile
                $empStmt = $pdo->prepare("INSERT INTO employees 
                    (user_id, employee_code, first_name, last_name, gender, department_id, joining_date, employment_type, basic_salary, status) 
                    VALUES (?, ?, ?, ?, 'other', ?, CURDATE(), 'full_time', 0.00, 'active')");
                $empStmt->execute([$newUserId, $empCode, $firstName, $lastName, $deptId]);
                $newEmpId = $pdo->lastInsertId();

                // If registered via invitation, mark invitation as accepted
                if ($inviteData) {
                    $updInv = $pdo->prepare("UPDATE role_invitations SET status = 'accepted' WHERE id = ?");
                    $updInv->execute([$inviteData['id']]);
                }

                // Log registration audit
                logAudit($pdo, $newUserId, 'User Registration', 'Authentication', "New user registered with role: $role ($email)");

                // Create welcome notification
                createNotification($pdo, $newUserId, "Welcome to HRMS", "Your account has been registered. Please verify your email to unlock all features.", 'info');

                // Dispatch Email Verification OTP to user's email
                $mailResult = sendOtpEmail($pdo, [
                    'id'    => $newUserId,
                    'name'  => $name,
                    'email' => $email
                ], 'email_verification');

                if (!$mailResult['success']) {
                    throw new Exception("Registration could not be completed because the verification email could not be dispatched: " . $mailResult['error'] . ". Please check your SMTP configuration.");
                }

                $pdo->commit();

                // Set pending verification session
                $_SESSION['pending_user_id']  = $newUserId;
                $_SESSION['pending_otp_type'] = 'email_verification';

                setFlashMessage('info', "Registration submitted! We have dispatched a 6-digit verification code to your email address.");
                header("Location: " . BASE_URL . "auth/verify_otp.php");
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
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
    <title>Create an Account | <?= defined('APP_NAME') ? APP_NAME : 'HRMS' ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
    <!-- Left Branded Presentation Side -->
    <div class="auth-brand-side">
        <div class="d-flex align-items-center">
            <div>
                <h3 class="mb-0 fw-bold text-white" style="letter-spacing: 0.5px;">HRMS</h3>
                <small class="text-white-50">Enterprise Human Resource Cloud</small>
            </div>
        </div>

        <div class="my-auto py-5">
            <span class="badge mb-3 px-3 py-2" style="background: rgba(255,255,255,0.15); font-size: 0.8rem; border-radius: 20px;">
                <i class="bi bi-shield-check me-1 text-success"></i> Production Identity & Role Governance
            </span>
            <h1 class="display-6 fw-bold text-white mb-3" style="line-height: 1.25;">
                Start Managing Your Workforce With Precision.
            </h1>
            <p class="text-white-50 fs-6 mb-4" style="max-width: 460px;">
                Secure role verification, email validation, automated payroll, and intelligent employee lifecycle management.
            </p>
        </div>

        <div class="d-flex justify-content-between text-white-50 small">
            <span>&copy; <?= date('Y') ?> HRMS Technologies</span>
            <span>Zero Dummy Data &bull; Enterprise Compliance</span>
        </div>
    </div>

    <!-- Right Registration Form Side -->
    <div class="auth-form-side">
        <div class="auth-box" style="max-width: 440px;">
            <div class="text-center mb-4">
                <h3 class="fw-bold mb-1 text-dark">Create Your Account</h3>
                <p class="text-secondary small">Register to access your organization portal</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-3" role="alert" style="border-radius: 8px;">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($inviteData): ?>
                <div class="alert alert-success py-2 px-3 small d-flex align-items-center mb-3" style="border-radius: 8px;">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div>
                        <strong>Authorized Invitation Verified:</strong> You are registering as <strong><?= strtoupper($inviteData['role']) ?></strong> for email <code><?= htmlspecialchars($inviteData['email']) ?></code>.
                    </div>
                </div>
            <?php else: ?>
                <!-- Google SSO Registration Shortcut -->
                <a href="<?= BASE_URL ?>auth/google_auth.php" class="btn-google mb-3 text-decoration-none">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" class="me-2">
                        <path d="M17.64 9.20455C17.64 8.56636 17.5827 7.95273 17.4764 7.36364H9V10.845H13.8436C13.635 11.97 13.0009 12.9232 12.0477 13.5614V15.8195H14.9564C16.6582 14.2527 17.64 11.9455 17.64 9.20455Z" fill="#4285F4"/>
                        <path d="M9 18C11.43 18 13.4673 17.1941 14.9564 15.8195L12.0477 13.5614C11.2418 14.1014 10.2109 14.4205 9 14.4205C6.65591 14.4205 4.67182 12.8373 3.96409 10.71H0.957275V13.0418C2.43818 15.9832 5.48182 18 9 18Z" fill="#34A853"/>
                        <path d="M3.96409 10.71C3.78409 10.17 3.68182 9.59318 3.68182 9C3.68182 8.40682 3.78409 7.83 3.96409 7.29V4.95818H0.957275C0.347727 6.17318 0 7.54773 0 9C0 10.4523 0.347727 11.8268 0.957275 13.0418L3.96409 10.71Z" fill="#FBBC05"/>
                        <path d="M9 3.57955C10.3214 3.57955 11.5077 4.03364 12.4405 4.92545L15.0218 2.34409C13.4632 0.891818 11.4259 0 9 0C5.48182 0 2.43818 2.01682 0.957275 4.95818L3.96409 7.29C4.67182 5.16273 6.65591 3.57955 9 3.57955Z" fill="#EA4335"/>
                    </svg>
                    <span>Continue with Google</span>
                </a>

                <div class="d-flex align-items-center mb-3">
                    <hr class="flex-grow-1 my-0" style="border-color: var(--border-color);">
                    <span class="px-2 text-muted" style="font-size: 0.72rem;">OR REGISTER WITH EMAIL</span>
                    <hr class="flex-grow-1 my-0" style="border-color: var(--border-color);">
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>auth/register.php" method="POST" id="regForm">
                <?= getCsrfInput() ?>
                <?php if ($inviteData): ?>
                    <input type="hidden" name="invite_token" value="<?= htmlspecialchars($inviteToken) ?>">
                    <input type="hidden" name="role" value="<?= htmlspecialchars($inviteData['role']) ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($inviteData['email']) ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label" for="name">Full Name <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-secondary"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control border-start-0" id="name" name="name" placeholder="e.g. Khushi Yadav" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="email">Work Email Address <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-secondary"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control border-start-0" id="email" name="email" 
                               placeholder="name@company.com" 
                               value="<?= htmlspecialchars($inviteData['email'] ?? $_POST['email'] ?? '') ?>" 
                               <?= $inviteData ? 'readonly' : 'required' ?>>
                    </div>
                    <?php if ($inviteData): ?>
                        <small class="text-success" style="font-size: 0.72rem;"><i class="bi bi-lock-fill"></i> Locked to invited email address.</small>
                    <?php endif; ?>
                </div>

                <!-- Role Selection (Disabled/Hidden if Invitation) -->
                <?php if (!$inviteData): ?>
                    <div class="mb-3">
                        <label class="form-label" for="roleSelect">Account Role <span class="required">*</span></label>
                        <select class="form-select" id="roleSelect" name="role" required onchange="toggleRoleKey(this.value)">
                            <option value="employee" <?= (isset($_POST['role']) && $_POST['role'] === 'employee') ? 'selected' : '' ?>>Employee (Standard Self-Service)</option>
                            <option value="hr" <?= (isset($_POST['role']) && $_POST['role'] === 'hr') ? 'selected' : '' ?>>HR Specialist (Requires Role Security Key)</option>
                            <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Administrator (Requires Master Security Key)</option>
                        </select>
                    </div>

                    <!-- Role Verification ID input (Hashed Backend Verification) -->
                    <div class="mb-3 p-3 rounded-3 border" id="roleKeyGroup" style="background: #FFF8E5; border-color: #D99A16 !important; display: none;">
                        <div class="d-flex align-items-center mb-1">
                            <i class="bi bi-shield-lock-fill text-warning me-2"></i>
                            <label class="form-label mb-0 fw-bold text-dark" id="roleKeyLabel" for="role_access_key">Role Verification ID <span class="required">*</span></label>
                        </div>
                        <small class="text-secondary d-block mb-2" id="roleKeyHint" style="font-size: 0.75rem;">
                            This elevated role requires the authorized corporate security ID to confirm your permission.
                        </small>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-warning"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control border-start-0 font-monospace" id="role_access_key" name="role_access_key" placeholder="Enter Role Security ID">
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mb-3">
                        <label class="form-label">Authorized Role</label>
                        <div class="form-control bg-light font-monospace fw-bold text-primary">
                            <i class="bi bi-shield-check me-1"></i> <?= strtoupper($inviteData['role']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="reg_password">Password <span class="required">*</span></label>
                        <input type="password" class="form-control" id="reg_password" name="password" minlength="6" placeholder="Min 6 chars" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="reg_confirm">Confirm <span class="required">*</span></label>
                        <input type="password" class="form-control" id="reg_confirm" name="confirm_password" minlength="6" placeholder="Confirm" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
                    <i class="bi bi-check2-circle me-1"></i> Complete Registration & Verify
                </button>

                <div class="text-center">
                    <span class="text-secondary small">Already have an account?</span>
                    <a href="<?= BASE_URL ?>auth/login.php" class="small fw-semibold text-primary text-decoration-none ms-1">
                        Sign In here &rarr;
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleRoleKey(role) {
    const keyGroup = document.getElementById('roleKeyGroup');
    const keyInput = document.getElementById('role_access_key');
    const keyLabel = document.getElementById('roleKeyLabel');
    const keyHint  = document.getElementById('roleKeyHint');

    if (!keyGroup || !keyInput) return;

    if (role === 'admin') {
        keyGroup.style.display = 'block';
        keyInput.required = true;
        keyLabel.innerHTML = 'Admin Role Secret Verification ID <span class="text-danger">*</span>';
        keyHint.textContent = 'Enter the master Admin Security ID to authorize Administrator account creation.';
    } else if (role === 'hr') {
        keyGroup.style.display = 'block';
        keyInput.required = true;
        keyLabel.innerHTML = 'HR Role Secret Verification ID <span class="text-danger">*</span>';
        keyHint.textContent = 'Enter the authorized HR Department Security ID to authorize HR account creation.';
    } else {
        keyGroup.style.display = 'none';
        keyInput.required = false;
        keyInput.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('roleSelect');
    if (roleSelect) {
        toggleRoleKey(roleSelect.value);
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
