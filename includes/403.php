<?php
// 403 Forbidden Access Page
$pageTitle = 'Access Denied - 403';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - <?= defined('APP_NAME') ? APP_NAME : 'HRMS' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-sm border-0 text-center p-5" style="max-width: 480px; border-radius: 16px;">
        <div class="mb-4">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 80px; height: 80px; background: #FDECEC; color: #DC4545;">
                <i class="bi bi-shield-lock" style="font-size: 40px;"></i>
            </span>
        </div>
        <h3 class="fw-bold mb-2 text-dark">Access Denied</h3>
        <p class="text-secondary mb-4">You do not have permission to access this module. Your current role is <strong><?= htmlspecialchars(ucfirst($_SESSION['user_role'] ?? 'Guest')) ?></strong>.</p>
        <div>
            <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-primary px-4 py-2" style="background-color: #0454D9; border-color: #0454D9; border-radius: 8px;">
                <i class="bi bi-arrow-left me-2"></i>Return to Dashboard
            </a>
        </div>
    </div>
</body>
</html>

