<?php
/**
 * Settings & Account Preferences
 * HR Management System
 */

$pageTitle = 'Settings';
$breadcrumbParent = 'Administration';
$activePage = 'settings';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/mailer.php';

requireLogin();

$currentUser = getCurrentUser();
$role = $currentUser['role'];
$pdo = getDBConnection();
$error = '';
$success = '';

// Check one-time rotated key notice from session
$rotatedNotice = $_SESSION['rotated_key_notice'] ?? null;
unset($_SESSION['rotated_key_notice']);

// 1. Handle Company Settings & Google OAuth Credentials Update (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_company' && hasRole('admin')) {
    $companyName  = trim($_POST['company_name'] ?? '');
    $companyEmail = trim($_POST['company_email'] ?? '');
    $companyPhone = trim($_POST['company_phone'] ?? '');
    $companyAddr  = trim($_POST['company_address'] ?? '');
    $timezone     = trim($_POST['timezone'] ?? 'Asia/Kolkata');
    $currency     = trim($_POST['currency'] ?? 'INR');
    $googleCid    = trim($_POST['google_client_id'] ?? '');
    $googleSec    = trim($_POST['google_client_secret'] ?? '');
    $require2fa   = isset($_POST['require_2fa']) ? 1 : 0;

    if (!empty($companyName)) {
        try {
            $stmt = $pdo->prepare("UPDATE company_settings SET 
                company_name = ?, company_email = ?, company_phone = ?, company_address = ?, timezone = ?, currency = ?,
                google_client_id = ?, google_client_secret = ?, require_2fa = ?
                WHERE id = 1");
            $stmt->execute([$companyName, $companyEmail, $companyPhone, $companyAddr, $timezone, $currency, $googleCid, $googleSec, $require2fa]);

            $_SESSION['company_name'] = $companyName;
            $_SESSION['company_currency'] = $currency;

            logAudit($pdo, $currentUser['id'], 'Update Settings', 'Settings', 'Updated company parameters & OAuth configuration');
            setFlashMessage('success', 'Company profile and parameters saved successfully.');
            header("Location: " . BASE_URL . "settings/index.php");
            exit;
        } catch (Exception $e) {
            $error = 'Error saving settings: ' . $e->getMessage();
        }
    }
}

// 2. Handle Admin Role Invitation Dispatch (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_invitation' && hasRole('admin')) {
    $inviteEmail = strtolower(trim($_POST['invite_email'] ?? ''));
    $inviteRole  = trim($_POST['invite_role'] ?? 'hr');

    if (empty($inviteEmail) || !filter_var($inviteEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid recipient email address.';
    } elseif (!in_array($inviteRole, ['admin', 'hr'])) {
        $error = 'Invalid invitation role selected.';
    } else {
        try {
            // Check if user already exists
            $ch = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $ch->execute([$inviteEmail]);
            if ($ch->fetch()) {
                throw new Exception("A user with email '$inviteEmail' already exists in the system.");
            }

            // Generate cryptographically secure 64-char hex token
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);

            // Invalidate any previous pending invitations for this email
            $pdo->prepare("UPDATE role_invitations SET status = 'revoked' WHERE email = ? AND status = 'pending'")->execute([$inviteEmail]);

            // Insert new invitation valid for 48 hours
            $ins = $pdo->prepare("INSERT INTO role_invitations (email, role, token_hash, invited_by, expires_at, status, created_at) 
                                  VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR), 'pending', NOW())");
            $ins->execute([$inviteEmail, $inviteRole, $tokenHash, $currentUser['id']]);

            $inviteLink = BASE_URL . 'auth/register.php?invite=' . $rawToken;

            // Send invitation email
            $emailSubject = "Official Invitation to Join HRMS as " . strtoupper($inviteRole);
            $emailHtml = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 20px; border: 1px solid #E5EAF1; border-radius: 8px;">
    <h3 style="color: #0454D9; margin-top: 0;">HRMS Cloud Enterprise</h3>
    <p>Hello,</p>
    <p>You have been officially invited by <strong>{$currentUser['name']}</strong> to join the HR Management System as an authorized <strong>{$inviteRole}</strong>.</p>
    <p style="margin: 24px 0;">
        <a href="{$inviteLink}" style="background: #0454D9; color: #FFFFFF; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">Complete Registration</a>
    </p>
    <p style="color: #64748B; font-size: 13px;">This invitation link is valid for 48 hours. If you were not expecting this invitation, you can ignore this email.</p>
</div>
HTML;
            sendEmail($inviteEmail, 'Invited Colleague', $emailSubject, $emailHtml);

            logAudit($pdo, $currentUser['id'], 'Create Invitation', 'Administration', "Dispatched {$inviteRole} registration invitation to {$inviteEmail}");
            setFlashMessage('success', "Invitation sent to {$inviteEmail}! Direct registration link: <a href='{$inviteLink}' class='fw-bold text-decoration-underline' target='_blank'>{$inviteLink}</a>");
            header("Location: " . BASE_URL . "settings/index.php");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// 3. Handle Revoke Invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_invitation' && hasRole('admin')) {
    $invId = (int)($_POST['invitation_id'] ?? 0);
    $pdo->prepare("UPDATE role_invitations SET status = 'revoked' WHERE id = ?")->execute([$invId]);
    logAudit($pdo, $currentUser['id'], 'Revoke Invitation', 'Administration', "Revoked invitation #{$invId}");
    setFlashMessage('info', 'Invitation successfully revoked.');
    header("Location: " . BASE_URL . "settings/index.php");
    exit;
}

// 4. Handle Role Key Rotation (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rotate_role_key' && hasRole('admin')) {
    $targetRole = $_POST['target_role'] ?? '';
    $newRawKey  = trim($_POST['new_raw_key'] ?? '');

    if (!in_array($targetRole, ['admin', 'hr'])) {
        $error = 'Invalid role selected for key rotation.';
    } else {
        if (empty($newRawKey)) {
            // Auto-generate strong key
            $prefix = ($targetRole === 'admin') ? 'ADM-KEY-' : 'HR-KEY-';
            $newRawKey = $prefix . strtoupper(bin2hex(random_bytes(6))) . '-HRMS';
        }

        try {
            $hashedKey = password_hash($newRawKey, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE role_access_keys SET key_hash = ?, updated_at = NOW() WHERE role = ?");
            $upd->execute([$hashedKey, $targetRole]);

            // Save one-time notification in session
            $_SESSION['rotated_key_notice'] = [
                'role'    => $targetRole,
                'raw_key' => $newRawKey
            ];

            logAudit($pdo, $currentUser['id'], 'Rotate Security Key', 'Security', "Rotated cryptographic key for role: {$targetRole}");
            setFlashMessage('success', "Security key for " . strtoupper($targetRole) . " rotated successfully.");
            header("Location: " . BASE_URL . "settings/index.php");
            exit;
        } catch (Exception $e) {
            $error = 'Key rotation error: ' . $e->getMessage();
        }
    }
}

// 5. Handle Unlink Google Account (All Users)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlink_google') {
    try {
        $upd = $pdo->prepare("UPDATE users SET google_id = NULL, google_email = NULL WHERE id = ?");
        $upd->execute([$currentUser['id']]);
        logAudit($pdo, $currentUser['id'], 'Unlink Google Account', 'Security', 'User unlinked their Google account');
        setFlashMessage('success', 'Google account has been unlinked successfully.');
        header("Location: " . BASE_URL . "settings/index.php");
        exit;
    } catch (Exception $e) {
        $error = 'Error unlinking account: ' . $e->getMessage();
    }
}

// 6. Handle Password Change (All Users)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    try {
        $uStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $uStmt->execute([$currentUser['id']]);
        $currentHashed = $uStmt->fetchColumn();

        if (!password_verify($currentPass, $currentHashed)) {
            $error = 'Incorrect current password.';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters in length.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New passwords do not match.';
        } else {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$hashed, $currentUser['id']]);

            logAudit($pdo, $currentUser['id'], 'Change Password', 'Security', 'User updated account credentials');
            setFlashMessage('success', 'Your password has been changed successfully.');
            header("Location: " . BASE_URL . "settings/index.php");
            exit;
        }
    } catch (Exception $e) {
        $error = 'Error changing password: ' . $e->getMessage();
    }
}

// Fetch current user details including Google info
$userFullStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userFullStmt->execute([$currentUser['id']]);
$userFull = $userFullStmt->fetch(PDO::FETCH_ASSOC);

// Fetch current company settings
$companyStmt = $pdo->query("SELECT * FROM company_settings LIMIT 1");
$company = $companyStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'company_name' => 'HRMS Solutions Inc.',
    'company_email' => 'hr@hrms.com',
    'company_phone' => '+1 (555) 345-6789',
    'company_address' => '500 Tech Parkway, Suite 700, San Francisco, CA',
    'timezone' => 'Asia/Kolkata',
    'currency' => 'INR',
    'google_client_id' => '',
    'google_client_secret' => '',
    'require_2fa' => 1
];

// Fetch recent invitations
$invitations = [];
if (hasRole('admin')) {
    $invStmt = $pdo->query("SELECT ri.*, u.name as inviter_name 
                            FROM role_invitations ri 
                            LEFT JOIN users u ON u.id = ri.invited_by 
                            ORDER BY ri.created_at DESC LIMIT 10");
    $invitations = $invStmt->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">System & Account Settings</h2>
            <p class="text-secondary small mb-0">Manage enterprise parameters, role invitations, Google account linking, and security credentials.</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <!-- One-Time Key Rotation Alert (Shown only once upon generation) -->
    <?php if ($rotatedNotice): ?>
        <div class="alert alert-warning border-warning p-3 mb-4" style="background: #FFF8E5;">
            <div class="d-flex align-items-start">
                <i class="bi bi-shield-exclamation text-warning fs-3 me-3"></i>
                <div class="flex-grow-1">
                    <h6 class="fw-bold text-dark mb-1">
                        New Security Verification Key Generated for <?= strtoupper($rotatedNotice['role']) ?>!
                    </h6>
                    <p class="text-secondary small mb-2">
                        Please copy and store this key now. To protect system security, it is stored as a <strong>one-way BCRYPT cryptographic hash</strong> in the database and <strong>will never be displayed again</strong>.
                    </p>
                    <div class="input-group" style="max-width: 480px;">
                        <input type="text" class="form-control font-monospace fw-bold bg-white text-danger" id="oneTimeKey" value="<?= htmlspecialchars($rotatedNotice['raw_key']) ?>" readonly>
                        <button class="btn btn-outline-danger" type="button" onclick="navigator.clipboard.writeText(document.getElementById('oneTimeKey').value); alert('Key copied to clipboard! Store it safely.');">
                            <i class="bi bi-clipboard me-1"></i> Copy Key
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (hasRole('admin')): ?>
    <div class="row g-4">
        <!-- Left Column (Admin Management) -->
        <div class="col-lg-7">
            <!-- Organization Profile (Admin Only) -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-building text-primary"></i> Organization Profile</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?= BASE_URL ?>settings/index.php" method="POST">
                            <input type="hidden" name="action" value="update_company">

                            <div class="mb-3">
                                <label class="form-label" for="comp_name">Company Legal Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="comp_name" name="company_name" value="<?= htmlspecialchars($company['company_name']) ?>" required>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="comp_email">Official Corporate Email</label>
                                    <input type="email" class="form-control" id="comp_email" name="company_email" value="<?= htmlspecialchars($company['company_email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="comp_phone">Headquarters Phone</label>
                                    <input type="text" class="form-control" id="comp_phone" name="company_phone" value="<?= htmlspecialchars($company['company_phone'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="comp_address">Headquarters Address</label>
                                <textarea class="form-control" id="comp_address" name="company_address" rows="2"><?= htmlspecialchars($company['company_address'] ?? '') ?></textarea>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="comp_currency">System Currency</label>
                                    <select class="form-select" id="comp_currency" name="currency">
                                        <option value="INR" <?= ($company['currency'] === 'INR') ? 'selected' : '' ?>>INR (₹ - Indian Rupee)</option>
                                        <option value="USD" <?= ($company['currency'] === 'USD') ? 'selected' : '' ?>>USD ($ - US Dollar)</option>
                                        <option value="EUR" <?= ($company['currency'] === 'EUR') ? 'selected' : '' ?>>EUR (€ - Euro)</option>
                                        <option value="GBP" <?= ($company['currency'] === 'GBP') ? 'selected' : '' ?>>GBP (£ - British Pound)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="comp_tz">System Timezone</label>
                                    <select class="form-select" id="comp_tz" name="timezone">
                                        <option value="Asia/Kolkata" <?= ($company['timezone'] === 'Asia/Kolkata') ? 'selected' : '' ?>>Asia/Kolkata (IST)</option>
                                        <option value="America/New_York" <?= ($company['timezone'] === 'America/New_York') ? 'selected' : '' ?>>America/New_York (EST)</option>
                                        <option value="America/Los_Angeles" <?= ($company['timezone'] === 'America/Los_Angeles') ? 'selected' : '' ?>>America/Los_Angeles (PST)</option>
                                        <option value="Europe/London" <?= ($company['timezone'] === 'Europe/London') ? 'selected' : '' ?>>Europe/London (GMT)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Google OAuth API Credentials -->
                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <h6 class="fw-bold text-dark mb-2">
                                    <i class="bi bi-google me-1 text-primary"></i> Official Google OAuth 2.0 API Credentials
                                </h6>
                                <p class="text-secondary small mb-2">
                                    Configure your OAuth Client ID and Secret from the Google Cloud Console.
                                </p>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-dark mb-1" for="google_client_id">Google Client ID</label>
                                    <input type="text" class="form-control form-control-sm font-monospace" id="google_client_id" name="google_client_id" value="<?= htmlspecialchars($company['google_client_id'] ?? '') ?>" placeholder="e.g. 123456789-xxxx.apps.googleusercontent.com">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-dark mb-1" for="google_client_secret">Google Client Secret</label>
                                    <input type="password" class="form-control form-control-sm font-monospace" id="google_client_secret" name="google_client_secret" value="<?= htmlspecialchars($company['google_client_secret'] ?? '') ?>" placeholder="Client Secret">
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="require_2fa" id="require_2fa" value="1" <?= (!empty($company['require_2fa'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label small text-secondary" for="require_2fa">
                                        Enforce Two-Factor Email OTP verification for all logins
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2 me-1"></i> Save Company Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Admin Invitations System Card (Preferred Security Approach) -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-envelope-plus text-primary me-2"></i> Role Invitations System
                        </h5>
                        <span class="badge bg-light text-primary border">Admin & HR Onboarding</span>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small mb-3">
                            The preferred production security approach: Authorized Administrators generate single-use invitation tokens for specific email addresses, eliminating the need to distribute shared keys.
                        </p>

                        <form action="<?= BASE_URL ?>settings/index.php" method="POST" class="row g-2 mb-4">
                            <input type="hidden" name="action" value="create_invitation">
                            <div class="col-md-6">
                                <input type="email" name="invite_email" class="form-control form-control-sm" placeholder="colleague.work@company.com" required>
                            </div>
                            <div class="col-md-3">
                                <select name="invite_role" class="form-select form-select-sm">
                                    <option value="hr">HR Specialist</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="bi bi-send me-1"></i> Send Invite
                                </button>
                            </div>
                        </form>

                        <!-- Invitations Table -->
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" style="font-size: 0.8rem;">
                                <thead>
                                    <tr>
                                        <th>Invited Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Expires</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($invitations)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">No invitations issued yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($invitations as $inv): ?>
                                            <tr>
                                                <td class="fw-semibold text-dark"><?= htmlspecialchars($inv['email']) ?></td>
                                                <td><span class="badge bg-light text-dark border text-uppercase"><?= $inv['role'] ?></span></td>
                                                <td><?= renderStatusBadge($inv['status']) ?></td>
                                                <td class="text-muted"><?= formatDate($inv['expires_at'], 'M d, H:i') ?></td>
                                                <td class="text-end">
                                                    <?php if ($inv['status'] === 'pending'): ?>
                                                        <form action="<?= BASE_URL ?>settings/index.php" method="POST" class="d-inline" onsubmit="return confirm('Revoke this invitation?');">
                                                            <input type="hidden" name="action" value="revoke_invitation">
                                                            <input type="hidden" name="invitation_id" value="<?= $inv['id'] ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Revoke">
                                                                <i class="bi bi-slash-circle"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Role Key Rotation & Hashed Storage Management -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-key-fill text-warning me-2"></i> Role Key Rotation (Hashed Storage)
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small mb-3">
                            Verification keys are stored strictly as <strong>one-way BCRYPT hashes</strong> in the database. Rotating a key generates a new cryptographic hash and invalidates previous keys.
                        </p>

                        <div class="row g-3">
                            <!-- Admin Key Rotation -->
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 border bg-light">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-shield-shaded text-danger me-1"></i> Admin Verification Key</h6>
                                    <div class="text-muted small mb-2">Status: <span class="badge bg-success-subtle text-success">Active & Hashed in DB</span></div>
                                    <form action="<?= BASE_URL ?>settings/index.php" method="POST">
                                        <input type="hidden" name="action" value="rotate_role_key">
                                        <input type="hidden" name="target_role" value="admin">
                                        <div class="input-group input-group-sm mb-2">
                                            <input type="text" name="new_raw_key" class="form-control font-monospace" placeholder="Leave empty to auto-generate">
                                            <button class="btn btn-outline-danger" type="submit">Rotate</button>
                                        </div>
                                        <small class="text-muted" style="font-size: 0.72rem;">Generates a new key and updates BCRYPT hash.</small>
                                    </form>
                                </div>
                            </div>

                            <!-- HR Key Rotation -->
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 border bg-light">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-primary me-1"></i> HR Verification Key</h6>
                                    <div class="text-muted small mb-2">Status: <span class="badge bg-success-subtle text-success">Active & Hashed in DB</span></div>
                                    <form action="<?= BASE_URL ?>settings/index.php" method="POST">
                                        <input type="hidden" name="action" value="rotate_role_key">
                                        <input type="hidden" name="target_role" value="hr">
                                        <div class="input-group input-group-sm mb-2">
                                            <input type="text" name="new_raw_key" class="form-control font-monospace" placeholder="Leave empty to auto-generate">
                                            <button class="btn btn-outline-primary" type="submit">Rotate</button>
                                        </div>
                                        <small class="text-muted" style="font-size: 0.72rem;">Generates a new key and updates BCRYPT hash.</small>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-5">
            <!-- Official Google Account Linking Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="bi bi-google text-primary me-2"></i> Google Account Single Sign-On</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($userFull['google_id'])): ?>
                        <div class="p-3 rounded-3 border mb-3" style="background: #E8F7EF; border-color: #16A36A !important;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-success text-white"><i class="bi bi-check-circle-fill me-1"></i> Linked</span>
                                <span class="small text-muted font-monospace" style="font-size: 0.7rem;">ID: <?= substr($userFull['google_id'], 0, 12) ?>...</span>
                            </div>
                            <div class="fw-bold text-dark small mb-1">Linked Google Email:</div>
                            <div class="font-monospace text-primary small mb-3"><?= htmlspecialchars($userFull['google_email'] ?? $userFull['email']) ?></div>
                            <form action="<?= BASE_URL ?>settings/index.php" method="POST" onsubmit="return confirm('Unlink your Google account? You will need to use your password to sign in.');">
                                <input type="hidden" name="action" value="unlink_google">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                    <i class="bi bi-x-circle me-1"></i> Unlink Google Account
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="p-3 bg-light rounded-3 border mb-3 text-center">
                            <i class="bi bi-google text-secondary fs-2 mb-2 d-block"></i>
                            <h6 class="fw-bold text-dark mb-1">No Google Account Linked</h6>
                            <p class="text-secondary small mb-3">Link your corporate Google account to enable 1-click Single Sign-On.</p>
                            <a href="<?= BASE_URL ?>auth/google_auth.php?action=link" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-link-45deg me-1"></i> Link Google Account
                            </a>
                        </div>
                    <?php endif; ?>
                    <small class="text-muted d-block" style="font-size: 0.73rem;">
                        <i class="bi bi-info-circle me-1"></i> The authenticated Google email must match your registered corporate email (<code><?= htmlspecialchars(maskEmail($userFull['email'])) ?></code>).
                    </small>
                </div>
            </div>

            <!-- Security & Password Update -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="bi bi-shield-lock text-primary me-2"></i> Security & Password</h5>
                </div>
                <div class="card-body">
                    <form action="<?= BASE_URL ?>settings/index.php" method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label class="form-label" for="cur_pass">Current Password <span class="required">*</span></label>
                            <input type="password" class="form-control" id="cur_pass" name="current_password" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="new_pass">New Password <span class="required">*</span></label>
                            <input type="password" class="form-control" id="new_pass" name="new_password" minlength="6" required>
                            <small class="text-muted" style="font-size: 0.72rem;">Minimum 6 characters.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="confirm_pass">Confirm New Password <span class="required">*</span></label>
                            <input type="password" class="form-control" id="confirm_pass" name="confirm_password" minlength="6" required>
                        </div>

                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-key me-1"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Role Access Card -->
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title"><i class="bi bi-person-badge text-secondary me-2"></i> Current Session Details</h6>
                </div>
                <div class="card-body small">
                    <div class="mb-2"><strong>Signed in as:</strong> <?= htmlspecialchars($currentUser['name']) ?></div>
                    <div class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($currentUser['email']) ?></div>
                    <div class="mb-2"><strong>Assigned Role:</strong> <span class="badge bg-primary text-uppercase"><?= htmlspecialchars($currentUser['role']) ?></span></div>
                    <div class="mb-2"><strong>Email Verified:</strong> <span class="text-success"><i class="bi bi-check-circle-fill"></i> Verified</span></div>
                    <div><strong>Session Status:</strong> <span class="text-success"><i class="bi bi-circle-fill" style="font-size: 0.6rem;"></i> Active & Encrypted</span></div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Centered Layout for HR & Employee -->
    <div class="row justify-content-center g-4">
        <div class="col-lg-6 col-md-8 col-sm-11">
            <!-- Security & Password Update (Centered) -->
            <div class="card mb-4 shadow-sm border">
                <div class="card-header bg-white border-bottom py-3 text-center">
                    <h5 class="card-title mb-0 fw-semibold text-dark"><i class="bi bi-shield-lock text-primary me-2"></i> Security & Password</h5>
                    <small class="text-secondary">Update your login password</small>
                </div>
                <div class="card-body p-4">
                    <form action="<?= BASE_URL ?>settings/index.php" method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label class="form-label fw-medium" for="cur_pass_center">Current Password <span class="required text-danger">*</span></label>
                            <input type="password" class="form-control" id="cur_pass_center" name="current_password" required placeholder="Enter current password">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-medium" for="new_pass_center">New Password <span class="required text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_pass_center" name="new_password" minlength="6" required placeholder="Enter new password (min. 6 characters)">
                            <small class="text-muted" style="font-size: 0.72rem;">Minimum 6 characters.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-medium" for="confirm_pass_center">Confirm New Password <span class="required text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_pass_center" name="confirm_password" minlength="6" required placeholder="Confirm new password">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-key me-1"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Official Google Account Linking Card -->
            <div class="card mb-4 shadow-sm border">
                <div class="card-header bg-white border-bottom py-3 text-center">
                    <h5 class="card-title mb-0 fw-semibold text-dark"><i class="bi bi-google text-primary me-2"></i> Google Account Single Sign-On</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($userFull['google_id'])): ?>
                        <div class="p-3 rounded-3 border mb-3" style="background: #E8F7EF; border-color: #16A36A !important;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-success text-white"><i class="bi bi-check-circle-fill me-1"></i> Linked</span>
                                <span class="small text-muted font-monospace" style="font-size: 0.7rem;">ID: <?= substr($userFull['google_id'], 0, 12) ?>...</span>
                            </div>
                            <div class="fw-bold text-dark small mb-1">Linked Google Email:</div>
                            <div class="font-monospace text-primary small mb-3"><?= htmlspecialchars($userFull['google_email'] ?? $userFull['email']) ?></div>
                            <form action="<?= BASE_URL ?>settings/index.php" method="POST" onsubmit="return confirm('Unlink your Google account? You will need to use your password to sign in.');">
                                <input type="hidden" name="action" value="unlink_google">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                    <i class="bi bi-x-circle me-1"></i> Unlink Google Account
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="p-3 bg-light rounded-3 border mb-3 text-center">
                            <i class="bi bi-google text-secondary fs-2 mb-2 d-block"></i>
                            <h6 class="fw-bold text-dark mb-1">No Google Account Linked</h6>
                            <p class="text-secondary small mb-3">Link your corporate Google account to enable 1-click Single Sign-On.</p>
                            <a href="<?= BASE_URL ?>auth/google_auth.php?action=link" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-link-45deg me-1"></i> Link Google Account
                            </a>
                        </div>
                    <?php endif; ?>
                    <small class="text-muted d-block text-center" style="font-size: 0.73rem;">
                        <i class="bi bi-info-circle me-1"></i> The authenticated Google email must match your registered corporate email (<code><?= htmlspecialchars(maskEmail($userFull['email'])) ?></code>).
                    </small>
                </div>
            </div>

            <!-- Current Session Details Card -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="card-title mb-0 fw-semibold text-dark"><i class="bi bi-person-badge text-secondary me-2"></i> Current Session Details</h6>
                </div>
                <div class="card-body p-4 small">
                    <div class="mb-2"><strong>Signed in as:</strong> <?= htmlspecialchars($currentUser['name']) ?></div>
                    <div class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($currentUser['email']) ?></div>
                    <div class="mb-2"><strong>Assigned Role:</strong> <span class="badge bg-primary text-uppercase"><?= htmlspecialchars($currentUser['role']) ?></span></div>
                    <div class="mb-2"><strong>Email Verified:</strong> <span class="text-success"><i class="bi bi-check-circle-fill"></i> Verified</span></div>
                    <div><strong>Session Status:</strong> <span class="text-success"><i class="bi bi-circle-fill" style="font-size: 0.6rem;"></i> Active & Encrypted</span></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
