<?php
/**
 * User Login Screen
 * Supports Email or Employee ID, Role selection, and Google Sign-in
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

$error = '';
$selectedRole = $_GET['role'] ?? 'admin';
$loginIdentifier = '';

// Handle POST Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security verification failed (CSRF mismatch). Please refresh and submit again.';
    } else {
        $loginIdentifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $remember = isset($_POST['remember']);

        if (empty($loginIdentifier) || empty($password)) {
            $error = 'Please enter both your email/employee ID and password.';
        } else {
            try {
                $pdo = getDBConnection();

                // Look up by email in users OR by employee_code in employees
                $sql = "SELECT u.*, e.id AS emp_id, e.employee_code, e.department_id, e.designation_id, 
                               d.department_name, ds.designation_name
                        FROM users u
                        LEFT JOIN employees e ON e.user_id = u.id
                        LEFT JOIN departments d ON d.id = e.department_id
                        LEFT JOIN designations ds ON ds.id = e.designation_id
                        WHERE (u.email = ? OR e.employee_code = ?)
                        LIMIT 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$loginIdentifier, $loginIdentifier]);
                $user = $stmt->fetch();

                if ($user) {
                    // Check temporary brute-force lockout
                    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
                        $minsRemaining = max(1, (int)ceil((strtotime($user['locked_until']) - time()) / 60));
                        throw new Exception("Account is temporarily locked due to multiple failed login attempts. Please try again in $minsRemaining minute(s).");
                    }

                    // Verify password hash
                    if (password_verify($password, $user['password'])) {
                        // Check account status
                        if ($user['status'] === 'inactive' || $user['status'] === 'suspended') {
                            throw new Exception("Access Denied: Your account is inactive or suspended. Please contact HR administration.");
                        }

                        // Check email verification status
                        if ($user['status'] === 'pending_verification' || (int)$user['email_verified'] === 0) {
                            $mailRes = sendOtpEmail($pdo, $user, 'email_verification');
                            if (!$mailRes['success']) {
                                throw new Exception("Unable to dispatch verification email: " . $mailRes['error'] . ". Please check SMTP configuration.");
                            }
                            $_SESSION['pending_user_id'] = $user['id'];
                            $_SESSION['pending_otp_type'] = 'email_verification';
                            setFlashMessage('info', "Email verification required. A 6-digit verification code has been dispatched to your email address.");
                            header("Location: " . BASE_URL . "auth/verify_otp.php");
                            exit;
                        }

                        // Check 2FA Requirement (Active for elevated roles or when two_factor_enabled = 1)
                        $csReq2fa = $pdo->query("SELECT require_2fa FROM company_settings LIMIT 1")->fetchColumn();
                        $is2faRequired = ($user['two_factor_enabled'] == 1) || in_array($user['role'], ['admin', 'hr']) || ($csReq2fa == 1);

                        if ($is2faRequired) {
                            $mailRes = sendOtpEmail($pdo, $user, 'login_2fa');
                            if (!$mailRes['success']) {
                                throw new Exception("Unable to dispatch two-factor authentication code: " . $mailRes['error'] . ". Please check SMTP configuration.");
                            }
                            $_SESSION['pending_user_id'] = $user['id'];
                            $_SESSION['pending_otp_type'] = 'login_2fa';
                            setFlashMessage('info', "Two-factor verification required. A one-time code has been sent to your registered email address.");
                            header("Location: " . BASE_URL . "auth/verify_otp.php");
                            exit;
                        }

                        // Successful login without 2FA
                        session_regenerate_id(true);

                        // Reset failed attempts & update last login
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW(), failed_attempts = 0, locked_until = NULL WHERE id = ?");
                        $updateStmt->execute([$user['id']]);

                        // Set Session
                        $_SESSION['user_id']          = $user['id'];
                        $_SESSION['user_name']        = $user['name'];
                        $_SESSION['user_email']       = $user['email'];
                        $_SESSION['user_role']        = $user['role'];
                        $_SESSION['employee_id']      = $user['emp_id'];
                        $_SESSION['employee_code']    = $user['employee_code'];
                        $_SESSION['department_name']  = $user['department_name'];
                        $_SESSION['designation_name'] = $user['designation_name'];
                        $_SESSION['last_activity']    = time();

                        logAudit($pdo, $user['id'], 'Login', 'Authentication', 'User successfully authenticated via credentials.');

                        $redirect = $_GET['redirect'] ?? (BASE_URL . 'dashboard.php');
                        header("Location: " . $redirect);
                        exit;
                    } else {
                        // Password verification failed: Increment rate-limiting attempts
                        $newAttempts = (int)($user['failed_attempts'] ?? 0) + 1;
                        if ($newAttempts >= 5) {
                            $lockStmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?");
                            $lockStmt->execute([$newAttempts, $user['id']]);
                            logAudit($pdo, $user['id'], 'Account Locked', 'Security', "Account locked for 15 minutes due to 5 consecutive failed login attempts.");
                            throw new Exception("Account is temporarily locked due to multiple failed login attempts. Please try again in 15 minutes.");
                        } else {
                            $updAtt = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
                            $updAtt->execute([$newAttempts, $user['id']]);
                            $attemptsLeft = 5 - $newAttempts;
                            throw new Exception("Invalid credentials or unauthorized access. ($attemptsLeft attempt(s) remaining before temporary lockout)");
                        }
                    }
                } else {
                    // User not found: Return generic secure error
                    throw new Exception("Invalid credentials or unauthorized access.");
                }
            } catch (Exception $e) {
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
    <title>Sign In | <?= defined('APP_NAME') ? APP_NAME : 'HRMS' ?></title>
    
    <!-- Google Fonts & Bootstrap -->
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
            <div class="brand-logo-icon bg-white text-primary" style="box-shadow: none;">KY</div>
            <div class="ms-3">
                <h4 class="mb-0 fw-bold text-white">HRMS</h4>
                <small class="text-white-50">Enterprise Human Resource Cloud</small>
            </div>
        </div>

        <div class="my-auto py-5">
            <span class="badge mb-3 px-3 py-2" style="background: rgba(255,255,255,0.15); font-size: 0.8rem; border-radius: 20px;">
                <i class="bi bi-stars me-1 text-warning"></i> Next-Gen Workforce Intelligence
            </span>
            <h1 class="display-6 fw-bold text-white mb-3" style="line-height: 1.25;">
                Intelligent People Management for High-Performing Teams.
            </h1>
            <p class="text-white-50 fs-6 mb-4" style="max-width: 460px;">
                Streamline employee lifecycle, automate payroll, track precise attendance, and orchestrate talent recruitment seamlessly.
            </p>
            
            <div class="row g-3 pt-3" style="max-width: 480px;">
                <div class="col-6">
                    <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12);">
                        <i class="bi bi-shield-check text-success fs-4 d-block mb-1"></i>
                        <h6 class="text-white mb-0">Role-Based Security</h6>
                        <small class="text-white-50" style="font-size: 0.72rem;">Granular Admin, HR & Employee controls</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12);">
                        <i class="bi bi-lightning-charge text-warning fs-4 d-block mb-1"></i>
                        <h6 class="text-white mb-0">Automated Payroll</h6>
                        <small class="text-white-50" style="font-size: 0.72rem;">1-Click calculation with itemized payslips</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between text-white-50 small">
            <span>&copy; <?= date('Y') ?> HRMS Technologies</span>
            <span>Security Compliant &bull; 256-bit Encryption</span>
        </div>
    </div>

    <!-- Right Login Form Side -->
    <div class="auth-form-side">
        <div class="auth-box">
            <div class="text-center mb-4">
                <div class="d-inline-flex p-3 rounded-circle mb-3" style="background: var(--light-blue); color: var(--primary-blue);">
                    <i class="bi bi-fingerprint fs-2"></i>
                </div>
                <h3 class="fw-bold mb-1 text-dark">Welcome back</h3>
                <p class="text-secondary small">Sign in to your workplace account to continue</p>
            </div>

            <!-- Portal Role Selector Tabs -->
            <div class="role-tabs mb-3">
                <button type="button" class="role-tab <?= $selectedRole === 'admin' ? 'active' : '' ?>" onclick="switchRole('admin', 'e.g. admin@company.com')">
                    <i class="bi bi-shield-lock me-1"></i> Admin Portal
                </button>
                <button type="button" class="role-tab <?= $selectedRole === 'hr' ? 'active' : '' ?>" onclick="switchRole('hr', 'e.g. hr@company.com')">
                    <i class="bi bi-people me-1"></i> HR Portal
                </button>
                <button type="button" class="role-tab <?= $selectedRole === 'employee' ? 'active' : '' ?>" onclick="switchRole('employee', 'e.g. EMP-1001 or email')">
                    <i class="bi bi-person me-1"></i> Employee Portal
                </button>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-3" role="alert" style="border-radius: 8px;">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Google Authentication Button -->
            <a href="<?= BASE_URL ?>auth/google_auth.php" class="btn-google mb-3 text-decoration-none">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.64 9.20455C17.64 8.56636 17.5827 7.95273 17.4764 7.36364H9V10.845H13.8436C13.635 11.97 13.0009 12.9232 12.0477 13.5614V15.8195H14.9564C16.6582 14.2527 17.64 11.9455 17.64 9.20455Z" fill="#4285F4"/>
                    <path d="M9 18C11.43 18 13.4673 17.1941 14.9564 15.8195L12.0477 13.5614C11.2418 14.1014 10.2109 14.4205 9 14.4205C6.65591 14.4205 4.67182 12.8373 3.96409 10.71H0.957275V13.0418C2.43818 15.9832 5.48182 18 9 18Z" fill="#34A853"/>
                    <path d="M3.96409 10.71C3.78409 10.17 3.68182 9.59318 3.68182 9C3.68182 8.40682 3.78409 7.83 3.96409 7.29V4.95818H0.957275C0.347727 6.17318 0 7.54773 0 9C0 10.4523 0.347727 11.8268 0.957275 13.0418L3.96409 10.71Z" fill="#FBBC05"/>
                    <path d="M9 3.57955C10.3214 3.57955 11.5077 4.03364 12.4405 4.92545L15.0218 2.34409C13.4632 0.891818 11.4259 0 9 0C5.48182 0 2.43818 2.01682 0.957275 4.95818L3.96409 7.29C4.67182 5.16273 6.65591 3.57955 9 3.57955Z" fill="#EA4335"/>
                </svg>
                <span>Continue with Google</span>
            </a>

            <div class="d-flex align-items-center mb-3">
                <hr class="flex-grow-1 my-0" style="border-color: var(--border-color);">
                <span class="px-2 text-muted" style="font-size: 0.75rem;">OR USE EMAIL / ID</span>
                <hr class="flex-grow-1 my-0" style="border-color: var(--border-color);">
            </div>

            <!-- Main Form -->
            <form action="<?= BASE_URL ?>auth/login.php" method="POST" id="loginForm">
                <?= getCsrfInput() ?>
                <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($selectedRole) ?>">

                <div class="mb-3">
                    <label class="form-label" for="identifier">Email Address or Employee ID</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-secondary">
                            <i class="bi bi-person"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="identifier" name="identifier" 
                               placeholder="e.g. your.email@company.com or EMP-1001" 
                               value="<?= htmlspecialchars($loginIdentifier) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label mb-0" for="password">Password</label>
                        <a href="<?= BASE_URL ?>auth/forgot_password.php" class="small text-decoration-none">Forgot password?</a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-secondary">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <button class="input-group-text bg-white border-start-0 text-secondary toggle-password" type="button" data-target="#password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="rememberMe" checked>
                        <label class="form-check-label small text-secondary" for="rememberMe">Remember me</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Sign In to Portal
                </button>
            </form>

            <!-- Register New Account Link -->
            <div class="p-3 bg-light rounded-3 border text-center">
                <span class="text-secondary small d-block mb-2">New to HRMS?</span>
                <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-outline-primary btn-sm px-4">
                    <i class="bi bi-person-plus me-1"></i> Create an Account / Register
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script>
function switchRole(role, placeholder) {
    document.getElementById('roleInput').value = role;
    const idInput = document.getElementById('identifier');
    if (placeholder) {
        idInput.placeholder = placeholder;
    }
    document.querySelectorAll('.role-tab').forEach(tab => tab.classList.remove('active'));
    event.currentTarget.classList.add('active');
}
</script>
</body>
</html>
