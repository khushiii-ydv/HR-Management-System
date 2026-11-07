<?php
/**
 * Shared Header Component
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_check.php';

requireLogin();

$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? 'Dashboard';
$breadcrumbParent = $breadcrumbParent ?? '';

// Fetch unread notifications for current user
$unreadNotifications = [];
$unreadCount = 0;
try {
    $pdo = getDBConnection();
    $notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $notifStmt->execute([$currentUser['id']]);
    $unreadNotifications = $notifStmt->fetchAll();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $countStmt->execute([$currentUser['id']]);
    $unreadCount = (int)$countStmt->fetchColumn();
} catch (Exception $e) {
    // Fail gracefully
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= defined('APP_NAME') ? APP_NAME : 'HRMS' ?></title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Chart.js for Dashboards & Reports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    
    <!-- Custom Theme Stylesheet -->
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
    <!-- Left Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="app-main">
        <!-- Sticky Top Header -->
        <header class="app-header">
            <div class="header-left">
                <!-- Mobile Sidebar Hamburger Toggle -->
                <button class="sidebar-toggle-btn" type="button" aria-label="Toggle Navigation">
                    <i class="bi bi-list"></i>
                </button>

                <!-- Page Title & Breadcrumb -->
                <div class="header-breadcrumb">
                    <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
                    <div class="breadcrumb-sub">
                        <?php if (!empty($breadcrumbParent)): ?>
                            <span><?= htmlspecialchars($breadcrumbParent) ?></span>
                            <i class="bi bi-chevron-right mx-1" style="font-size: 0.65rem;"></i>
                        <?php endif; ?>
                        <span class="text-primary fw-medium"><?= htmlspecialchars($pageTitle) ?></span>
                    </div>
                </div>
            </div>

            <!-- Global Search -->
            <div class="header-search d-none d-md-block">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Search employees, tasks..." data-table-search=".searchable-table">
            </div>

            <div class="header-right">
                <!-- Role Badge Indicator -->
                <div class="d-none d-lg-flex align-items-center me-2">
                    <span class="badge" style="background-color: var(--light-blue); color: var(--primary-blue); font-weight: 600; padding: 6px 12px; border-radius: 20px; border: 1px solid rgba(4,84,217,0.15);">
                        <i class="bi bi-shield-check me-1"></i><?= ucfirst(htmlspecialchars($currentUser['role'])) ?> Portal
                    </span>
                </div>

                <!-- Notification Bell with Dropdown -->
                <div class="dropdown">
                    <button class="header-icon-btn" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                        <i class="bi bi-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="unread-dot"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm border p-0 mt-2" aria-labelledby="notifDropdown" style="width: 320px; border-radius: 12px;">
                        <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                            <span class="fw-bold" style="font-size: 0.9rem;">Notifications</span>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-primary rounded-pill"><?= $unreadCount ?> new</span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list" style="max-height: 280px; overflow-y: auto;">
                            <?php if (empty($unreadNotifications)): ?>
                                <div class="p-4 text-center text-muted" style="font-size: 0.85rem;">
                                    <i class="bi bi-bell-slash fs-3 d-block mb-2 text-secondary"></i>
                                    No new notifications
                                </div>
                            <?php else: ?>
                                <?php foreach ($unreadNotifications as $n): ?>
                                    <div class="p-3 border-bottom text-wrap <?= !$n['is_read'] ? 'bg-light' : '' ?>" style="font-size: 0.82rem;">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <strong class="text-dark"><?= htmlspecialchars($n['title']) ?></strong>
                                            <small class="text-muted ms-2"><?= timeAgo($n['created_at']) ?></small>
                                        </div>
                                        <div class="text-secondary"><?= htmlspecialchars($n['message']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-2 text-center border-top bg-light" style="border-radius: 0 0 12px 12px;">
                            <a href="<?= BASE_URL ?>settings/notifications.php" class="text-primary fw-medium" style="font-size: 0.8rem;">View All Notifications</a>
                        </div>
                    </div>
                </div>

                <!-- User Profile Menu -->
                <div class="dropdown">
                    <button class="user-dropdown-btn dropdown-toggle" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar">
                            <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="user-meta d-none d-sm-flex">
                            <span class="user-meta-name"><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></span>
                            <span class="user-meta-role"><?= htmlspecialchars($currentUser['designation'] ?? ucfirst($currentUser['role'])) ?></span>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2" aria-labelledby="userMenuDropdown" style="width: 220px; border-radius: 12px;">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($currentUser['name']) ?></div>
                            <small class="text-muted text-truncate d-block"><?= htmlspecialchars($currentUser['email']) ?></small>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>settings/index.php">
                                <i class="bi bi-person me-2 text-secondary"></i>Account Settings
                            </a>
                        </li>
                        <?php if ($currentUser['employee_id']): ?>
                        <li>
                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>employees/view.php?id=<?= $currentUser['employee_id'] ?>">
                                <i class="bi bi-person-badge me-2 text-secondary"></i>My Profile
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item py-2" href="<?= BASE_URL ?>leaves/index.php">
                                <i class="bi bi-calendar2-check me-2 text-secondary"></i>My Leaves
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Flash Notification Banner if available -->
        <?php $flash = getFlashMessage(); ?>
        <?php if ($flash): ?>
            <div class="mx-4 mt-3 alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show d-flex align-items-center" role="alert" style="border-radius: 10px;">
                <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($flash['message']) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Main Content Body -->
        <main class="app-content">

