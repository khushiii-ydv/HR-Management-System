<?php
/**
 * Official Google OAuth 2.0 Authorization Dispatcher
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/google_oauth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? 'login'; // 'login' or 'link'

// If already logged in and not linking account, redirect to dashboard
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && $action !== 'link') {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

// If Google OAuth credentials are fully configured, redirect directly to Google!
if (isGoogleOAuthConfigured()) {
    $authUrl = getGoogleAuthUrl($action);
    header("Location: " . $authUrl);
    exit;
}

// If credentials not yet configured in local environment, render informative setup page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google OAuth Setup | <?= defined('APP_NAME') ? APP_NAME : 'HRMS' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="card shadow-sm border-0 p-4 p-md-5" style="max-width: 580px; width: 92%; border-radius: 16px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex p-3 rounded-circle mb-3 bg-white shadow-sm">
                <svg width="36" height="36" viewBox="0 0 18 18" fill="none">
                    <path d="M17.64 9.20455C17.64 8.56636 17.5827 7.95273 17.4764 7.36364H9V10.845H13.8436C13.635 11.97 13.0009 12.9232 12.0477 13.5614V15.8195H14.9564C16.6582 14.2527 17.64 11.9455 17.64 9.20455Z" fill="#4285F4"/>
                    <path d="M9 18C11.43 18 13.4673 17.1941 14.9564 15.8195L12.0477 13.5614C11.2418 14.1014 10.2109 14.4205 9 14.4205C6.65591 14.4205 4.67182 12.8373 3.96409 10.71H0.957275V13.0418C2.43818 15.9832 5.48182 18 9 18Z" fill="#34A853"/>
                    <path d="M3.96409 10.71C3.78409 10.17 3.68182 9.59318 3.68182 9C3.68182 8.40682 3.78409 7.83 3.96409 7.29V4.95818H0.957275C0.347727 6.17318 0 7.54773 0 9C0 10.4523 0.347727 11.8268 0.957275 13.0418L3.96409 10.71Z" fill="#FBBC05"/>
                    <path d="M9 3.57955C10.3214 3.57955 11.5077 4.03364 12.4405 4.92545L15.0218 2.34409C13.4632 0.891818 11.4259 0 9 0C5.48182 0 2.43818 2.01682 0.957275 4.95818L3.96409 7.29C4.67182 5.16273 6.65591 3.57955 9 3.57955Z" fill="#EA4335"/>
                </svg>
            </div>
            <h4 class="fw-bold text-dark mb-1">Official Google OAuth 2.0</h4>
            <p class="text-secondary small">Production Single Sign-On Identity Gateway</p>
        </div>

        <div class="alert alert-info py-2 px-3 small d-flex align-items-center mb-3">
            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
            <div>
                Google OAuth Client ID & Secret are not yet configured in <code>company_settings</code>.
            </div>
        </div>

        <div class="p-3 bg-light rounded-3 border mb-3 small">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-gear-fill me-1 text-primary"></i> Production Setup Steps:</h6>
            <ol class="ps-3 mb-2 text-secondary">
                <li class="mb-1">Create an OAuth 2.0 Client in <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="fw-semibold">Google Cloud Console</a>.</li>
                <li class="mb-1">Add this Authorized Redirect URI:</li>
                <div class="p-2 bg-white rounded border font-monospace text-dark my-1 user-select-all" style="font-size: 0.76rem;">
                    <?= htmlspecialchars(GOOGLE_REDIRECT_URI) ?>
                </div>
                <li>Paste your Client ID & Secret into <strong>Settings &rarr; Organization Profile</strong> or set environment variables <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code>.</li>
            </ol>
        </div>

        <?php if (defined('APP_DEBUG') && APP_DEBUG && defined('APP_ENV') && APP_ENV !== 'production'): ?>
        <!-- Local Test Callback Verifier (Allows testing the exact OAuth callback flow on localhost) -->
        <div class="card border p-3 mb-3" style="background: #F8FAFC;">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-shield-check text-success me-2 fs-5"></i>
                <h6 class="fw-bold text-dark mb-0">Local OAuth Callback Tester</h6>
            </div>
            <p class="text-secondary small mb-2">Test Google OAuth identity verification and email matching rule directly:</p>
            
            <form action="<?= BASE_URL ?>auth/google_callback.php" method="POST">
                <input type="hidden" name="simulation_token" value="<?= hash_hmac('sha256', 'google_sim', 'ky_hrms_salt_2026') ?>">
                <div class="mb-2">
                    <label class="form-label small fw-semibold text-dark mb-1">Simulated Google Account Email:</label>
                    <input type="email" name="verified_google_email" class="form-control form-control-sm" placeholder="e.g. khushi.ydv@gmail.com" required>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-arrow-right-circle me-1"></i> Simulate Verified Google Callback
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="text-center">
            <a href="<?= BASE_URL ?>auth/login.php" class="small text-secondary text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Back to sign in
            </a>
        </div>
    </div>
</body>
</html>
