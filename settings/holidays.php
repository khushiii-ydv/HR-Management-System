<?php
/**
 * Corporate Holiday Calendar
 * HR Management System
 */

$pageTitle = 'Holiday Calendar';
$breadcrumbParent = 'Workforce';
$activePage = 'holidays';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$currentUser = getCurrentUser();
$role = $currentUser['role'];
$pdo = getDBConnection();

// Handle Add / Delete Holiday
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasRole(['admin', 'hr'])) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['holiday_name'] ?? '');
        $date = $_POST['holiday_date'] ?? '';
        $desc = trim($_POST['description'] ?? '');

        if (!empty($name) && !empty($date)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO holidays (holiday_name, holiday_date, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $date, $desc]);
                logAudit($pdo, $currentUser['id'], 'Add Holiday', 'Holidays', "Added holiday: $name on $date");
                setFlashMessage('success', "Holiday '$name' added to corporate calendar!");
            } catch (Exception $e) {
                setFlashMessage('danger', 'Error adding holiday: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['holiday_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM holidays WHERE id = ?")->execute([$id]);
            logAudit($pdo, $currentUser['id'], 'Delete Holiday', 'Holidays', "Deleted holiday ID $id");
            setFlashMessage('success', 'Holiday removed.');
        }
    }
    header("Location: " . BASE_URL . "settings/holidays.php");
    exit;
}

$holidays = $pdo->query("SELECT * FROM holidays ORDER BY holiday_date ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Company Holiday Calendar</h2>
            <p class="text-secondary small mb-0">Official gazetted and corporate paid holidays observed across the fiscal year.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <?php if (hasRole(['admin', 'hr'])): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Holiday
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Holidays Grid -->
    <div class="row g-3">
        <?php foreach ($holidays as $h): 
            $isPast = strtotime($h['holiday_date']) < strtotime(date('Y-m-d'));
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border <?= $isPast ? 'opacity-75' : '' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge" style="background: <?= $isPast ? '#F1F5F9' : 'var(--error-red-bg)' ?>; color: <?= $isPast ? '#64748B' : 'var(--error-red)' ?>; font-size: 0.85rem; padding: 4px 10px;">
                                <i class="bi bi-calendar-event me-1"></i> <?= formatDate($h['holiday_date'], 'D, M d, Y') ?>
                            </span>
                            <?php if (hasRole(['admin', 'hr'])): ?>
                                <form action="<?= BASE_URL ?>settings/holidays.php" method="POST" onsubmit="return confirm('Remove this holiday?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="holiday_id" value="<?= $h['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Remove Holiday">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <h5 class="fw-bold text-dark mt-2 mb-1"><?= htmlspecialchars($h['holiday_name']) ?></h5>
                        <p class="text-secondary small mb-0"><?= htmlspecialchars($h['description'] ?: 'Official paid corporate holiday.') ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal: Add Holiday -->
<div class="modal fade" id="addHolidayModal" tabindex="-1" aria-labelledby="addHolidayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addHolidayModalLabel"><i class="bi bi-calendar-plus text-primary me-2"></i> Add Company Holiday</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>settings/holidays.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="hol_name">Holiday Name <span class="required">*</span></label>
                        <input type="text" class="form-control" id="hol_name" name="holiday_name" placeholder="e.g. Thanksgiving Day" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="hol_date">Holiday Date <span class="required">*</span></label>
                        <input type="date" class="form-control" id="hol_date" name="holiday_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="hol_desc">Description</label>
                        <textarea class="form-control" id="hol_desc" name="description" rows="2" placeholder="Brief context or holiday notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

