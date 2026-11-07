<?php
/**
 * User Notifications Center
 * HR Management System
 */

$pageTitle = 'Notifications Center';
$breadcrumbParent = 'Administration';
$activePage = 'notifications';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$currentUser = getCurrentUser();
$pdo = getDBConnection();

// Handle Mark As Read
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    setFlashMessage('success', 'All notifications marked as read.');
    header("Location: " . BASE_URL . "settings/notifications.php");
    exit;
}

if (isset($_GET['read_id'])) {
    $id = (int)$_GET['read_id'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $currentUser['id']]);
    header("Location: " . BASE_URL . "settings/notifications.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$currentUser['id']]);
$notifications = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Notifications Center</h2>
            <p class="text-secondary small mb-0">System updates, leave review alerts, interview schedules, and payroll receipts.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="<?= BASE_URL ?>settings/notifications.php?mark_all_read=1" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-check2-all me-1"></i> Mark All as Read
            </a>
        </div>
    </div>

    <div class="card shadow-sm border mx-auto" style="max-width: 800px;">
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-bell-slash fs-1 text-secondary d-block mb-2"></i>
                        No notifications found.
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <div class="list-group-item p-3 d-flex justify-content-between align-items-center <?= !$n['is_read'] ? 'bg-light' : '' ?>">
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center p-2 mt-1 <?= !$n['is_read'] ? 'bg-primary text-white' : 'bg-light text-secondary border' ?>" style="width: 36px; height: 36px;">
                                    <i class="bi bi-bell-fill fs-6"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark mb-1">
                                        <?= htmlspecialchars($n['title']) ?>
                                        <?php if (!$n['is_read']): ?>
                                            <span class="badge bg-danger ms-1" style="font-size: 0.65rem;">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-secondary small mb-1"><?= htmlspecialchars($n['message']) ?></p>
                                    <small class="text-muted"><i class="bi bi-clock me-1"></i><?= timeAgo($n['created_at']) ?> (<?= formatDate($n['created_at']) ?>)</small>
                                </div>
                            </div>
                            <?php if (!$n['is_read']): ?>
                                <a href="<?= BASE_URL ?>settings/notifications.php?read_id=<?= $n['id'] ?>" class="btn btn-sm btn-light border text-primary" title="Mark Read">
                                    <i class="bi bi-check"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

