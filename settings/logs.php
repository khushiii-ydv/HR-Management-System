<?php
/**
 * System Audit & Security Logs
 * HR Management System
 */

$pageTitle = 'Audit Logs';
$breadcrumbParent = 'Administration';
$activePage = 'audit_logs';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$pdo = getDBConnection();

$moduleFilter = $_GET['module'] ?? '';
$where = ["1=1"];
$params = [];

if (!empty($moduleFilter)) {
    $where[] = "a.module = ?";
    $params[] = $moduleFilter;
}

$sql = "SELECT a.*, u.name as user_name, u.email as user_email 
        FROM audit_logs a 
        LEFT JOIN users u ON u.id = a.user_id 
        WHERE " . implode(" AND ", $where) . " 
        ORDER BY a.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$modules = $pdo->query("SELECT DISTINCT module FROM audit_logs ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">System Audit Logs</h2>
            <p class="text-secondary small mb-0">Immutable compliance journal tracking authentications, record modifications, and financial runs.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form action="<?= BASE_URL ?>settings/logs.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <select name="module" class="form-select" onchange="this.form.submit()">
                        <option value="">All Functional Modules</option>
                        <?php foreach ($modules as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= ($moduleFilter === $m) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control" placeholder="Search logs..." data-table-search="#logsTable">
                </div>
                <div class="col-md-2">
                    <?php if (!empty($moduleFilter)): ?>
                        <a href="<?= BASE_URL ?>settings/logs.php" class="btn btn-light border w-100">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title"><i class="bi bi-journal-text text-primary"></i> Audit Trail (Latest 100 Events)</h5>
            <span class="badge bg-light text-secondary border"><?= count($logs) ?> Events</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle" id="logsTable">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No audit events found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="small text-muted font-monospace"><?= $log['created_at'] ?></td>
                                <td>
                                    <strong class="text-dark"><?= htmlspecialchars($log['user_name'] ?? 'System/Guest') ?></strong>
                                    <small class="text-muted d-block"><?= htmlspecialchars($log['user_email'] ?? '') ?></small>
                                </td>
                                <td><span class="badge bg-light text-primary border"><?= htmlspecialchars($log['module']) ?></span></td>
                                <td><span class="fw-semibold text-dark"><?= htmlspecialchars($log['action']) ?></span></td>
                                <td class="small text-secondary" style="max-width: 320px;"><?= htmlspecialchars($log['description'] ?? '-') ?></td>
                                <td><span class="badge bg-light text-secondary font-monospace"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

