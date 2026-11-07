<?php
/**
 * Leave Management Dashboard
 * HR Management System
 */

$pageTitle = 'Leave Management';
$breadcrumbParent = 'Workforce';
$activePage = 'leaves';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$currentUser = getCurrentUser();
$role = $currentUser['role'];
$pdo = getDBConnection();

$statusFilter = $_GET['status'] ?? '';
$deptFilter   = $_GET['department_id'] ?? '';

// Fetch all leave categories
$leaveTypes = $pdo->query("SELECT * FROM leave_types WHERE status = 'active' ORDER BY leave_name ASC")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();

if ($role === 'admin' || $role === 'hr') {
    // --- ADMIN / HR VIEW ---
    $where = ["1=1"];
    $params = [];

    if (!empty($statusFilter)) {
        $where[] = "lr.status = ?";
        $params[] = $statusFilter;
    }

    if (!empty($deptFilter)) {
        $where[] = "e.department_id = ?";
        $params[] = $deptFilter;
    }

    $whereClause = implode(" AND ", $where);

    $sql = "SELECT lr.*, e.first_name, e.last_name, e.employee_code, lt.leave_name, d.department_name, u.name as reviewer_name
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            JOIN leave_types lt ON lt.id = lr.leave_type_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN users u ON u.id = lr.reviewed_by
            WHERE $whereClause
            ORDER BY FIELD(lr.status, 'pending', 'approved', 'rejected', 'cancelled'), lr.applied_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leaveList = $stmt->fetchAll();

    // Summary counts
    $countsStmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM leave_requests");
    $counts = $countsStmt->fetch();

} else {
    // --- EMPLOYEE VIEW ---
    $empId = $currentUser['employee_id'];

    $sql = "SELECT lr.*, lt.leave_name, u.name as reviewer_name
            FROM leave_requests lr
            JOIN leave_types lt ON lt.id = lr.leave_type_id
            LEFT JOIN users u ON u.id = lr.reviewed_by
            WHERE lr.employee_id = ?
            ORDER BY lr.applied_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empId]);
    $leaveList = $stmt->fetchAll();

    // Calculate personal balances
    $myLeaveBalances = [];
    foreach ($leaveTypes as $lt) {
        $usedStmt = $pdo->prepare("SELECT SUM(total_days) FROM leave_requests WHERE employee_id = ? AND leave_type_id = ? AND status = 'approved' AND YEAR(start_date) = YEAR(CURDATE())");
        $usedStmt->execute([$empId, $lt['id']]);
        $usedDays = (float)$usedStmt->fetchColumn() ?: 0.0;
        $myLeaveBalances[] = [
            'id'        => $lt['id'],
            'name'      => $lt['leave_name'],
            'limit'     => $lt['annual_limit'],
            'used'      => $usedDays,
            'remaining' => max(0, $lt['annual_limit'] - $usedDays)
        ];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Leave Management</h2>
            <p class="text-secondary small mb-0">Apply for time off, review requests, and maintain annual leave compliance.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                <i class="bi bi-calendar-plus me-1"></i> Apply for Leave
            </button>
        </div>
    </div>

    <?php if ($role === 'employee'): ?>
        <!-- Employee Balances Cards -->
        <div class="row g-3 mb-4">
            <?php foreach ($myLeaveBalances as $lb): ?>
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 bg-white border rounded-3 h-100 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold text-dark small"><?= htmlspecialchars($lb['name']) ?></span>
                            <span class="badge bg-light text-secondary border"><?= $lb['limit'] ?> Total</span>
                        </div>
                        <div class="d-flex align-items-baseline gap-1 my-1">
                            <span class="fs-3 fw-bold text-primary"><?= $lb['remaining'] ?></span>
                            <span class="text-muted small">days available</span>
                        </div>
                        <div class="progress my-2" style="height: 6px;">
                            <?php $p = ($lb['limit'] > 0) ? min(100, round(($lb['used'] / $lb['limit']) * 100)) : 0; ?>
                            <div class="progress-bar bg-primary" style="width: <?= $p ?>%;"></div>
                        </div>
                        <div class="text-muted small" style="font-size: 0.72rem;"><?= $lb['used'] ?> days approved this year</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Admin / HR KPI Summary -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Total Applications</span>
                        <div class="kpi-icon-box" style="background: var(--light-blue); color: var(--primary-blue);"><i class="bi bi-calendar2-range"></i></div>
                    </div>
                    <div class="kpi-value"><?= $counts['total'] ?? 0 ?></div>
                    <div class="kpi-trend text-muted small">All recorded requests</div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Pending Approvals</span>
                        <div class="kpi-icon-box" style="background: var(--warning-amber-bg); color: var(--warning-amber);"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                    <div class="kpi-value text-warning"><?= $counts['pending'] ?? 0 ?></div>
                    <div class="kpi-trend <?= ($counts['pending'] ?? 0) > 0 ? 'text-warning' : 'text-success' ?> small">Awaiting review</div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Approved Leaves</span>
                        <div class="kpi-icon-box" style="background: var(--success-green-bg); color: var(--success-green);"><i class="bi bi-check2-circle"></i></div>
                    </div>
                    <div class="kpi-value text-success"><?= $counts['approved'] ?? 0 ?></div>
                    <div class="kpi-trend text-muted small">Sanctioned time off</div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Rejected Requests</span>
                        <div class="kpi-icon-box" style="background: var(--error-red-bg); color: var(--error-red);"><i class="bi bi-x-circle"></i></div>
                    </div>
                    <div class="kpi-value text-danger"><?= $counts['rejected'] ?? 0 ?></div>
                    <div class="kpi-trend text-muted small">Declined applications</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card mb-4">
            <div class="card-body py-3">
                <form action="<?= BASE_URL ?>leaves/index.php" method="GET" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Application Statuses</option>
                            <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : '' ?>>Pending Review</option>
                            <option value="approved" <?= ($statusFilter === 'approved') ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= ($statusFilter === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="department_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($deptFilter == $d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" placeholder="Search applicant name..." data-table-search="#leavesTable">
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Leave Requests Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title"><i class="bi bi-calendar2-check text-primary"></i> Leave Applications</h5>
            <span class="badge bg-light text-secondary border"><?= count($leaveList) ?> Entries</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle" id="leavesTable">
                <thead>
                    <tr>
                        <?php if ($role === 'admin' || $role === 'hr'): ?>
                            <th>Applicant</th>
                            <th>Department</th>
                        <?php endif; ?>
                        <th>Leave Type</th>
                        <th>Period</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Applied On</th>
                        <th>Status</th>
                        <?php if ($role === 'admin' || $role === 'hr'): ?>
                            <th class="text-end">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaveList)): ?>
                        <tr>
                            <td colspan="<?= ($role === 'admin' || $role === 'hr') ? 9 : 7 ?>" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-check fs-1 d-block mb-2 text-secondary"></i>
                                No leave applications found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leaveList as $item): ?>
                            <tr>
                                <?php if ($role === 'admin' || $role === 'hr'): ?>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($item['employee_code']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($item['department_name'] ?? 'General') ?></td>
                                <?php endif; ?>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['leave_name']) ?></span></td>
                                <td>
                                    <div class="small fw-semibold"><?= formatDate($item['start_date']) ?> - <?= formatDate($item['end_date']) ?></div>
                                </td>
                                <td><span class="fw-bold"><?= $item['total_days'] ?></span> day(s)</td>
                                <td>
                                    <div class="small text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($item['reason']) ?>">
                                        <?= htmlspecialchars($item['reason']) ?>
                                    </div>
                                    <?php if (!empty($item['review_remarks'])): ?>
                                        <div class="text-muted small" style="font-size: 0.72rem;">
                                            <em>Remark: <?= htmlspecialchars($item['review_remarks']) ?></em>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($item['applied_at']) ?></td>
                                <td><?= renderStatusBadge($item['status']) ?></td>
                                <?php if ($role === 'admin' || $role === 'hr'): ?>
                                    <td class="text-end">
                                        <?php if ($item['status'] === 'pending'): ?>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= BASE_URL ?>leaves/action.php?id=<?= $item['id'] ?>&status=approved" class="btn btn-success" title="Approve Request">
                                                    <i class="bi bi-check-lg me-1"></i> Approve
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" onclick="openRejectModal(<?= $item['id'] ?>)" title="Reject Request">
                                                    <i class="bi bi-x-lg me-1"></i> Reject
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="small text-muted">Reviewed by <?= htmlspecialchars($item['reviewer_name'] ?? 'HR') ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Apply for Leave -->
<div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-labelledby="applyLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="applyLeaveModalLabel"><i class="bi bi-calendar-plus text-primary me-2"></i> Submit Leave Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>leaves/apply.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="leave_type_select">Leave Type <span class="required">*</span></label>
                        <select class="form-select" id="leave_type_select" name="leave_type_id" required>
                            <option value="">-- Choose Leave Category --</option>
                            <?php foreach ($leaveTypes as $lt): ?>
                                <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['leave_name']) ?> (Limit: <?= $lt['annual_limit'] ?> days)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="leave_start">Start Date <span class="required">*</span></label>
                            <input type="date" class="form-control" id="leave_start" name="start_date" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="leave_end">End Date <span class="required">*</span></label>
                            <input type="date" class="form-control" id="leave_end" name="end_date" min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="leave_reason">Reason for Absence <span class="required">*</span></label>
                        <textarea class="form-control" id="leave_reason" name="reason" rows="3" placeholder="Provide reason for absence..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Reject Remarks (Admin/HR) -->
<div class="modal fade" id="rejectLeaveModal" tabindex="-1" aria-labelledby="rejectLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-danger" id="rejectLeaveModalLabel"><i class="bi bi-x-circle me-1"></i> Decline Leave Request</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>leaves/action.php" method="POST">
                <input type="hidden" name="id" id="reject_leave_id">
                <input type="hidden" name="status" value="rejected">
                <div class="modal-body">
                    <label class="form-label small" for="reject_remarks">Reason for Rejection</label>
                    <textarea class="form-control" id="reject_remarks" name="remarks" rows="3" placeholder="Explain rejection reason..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRejectModal(id) {
    document.getElementById('reject_leave_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectLeaveModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

