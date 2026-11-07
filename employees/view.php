<?php
/**
 * Detailed 7-Tab Employee Profile
 * HR Management System
 */

$pageTitle = 'Employee Profile';
$breadcrumbParent = 'Employees';
$activePage = 'employees';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$currentUser = getCurrentUser();
$empId = (int)($_GET['id'] ?? 0);

// Default to logged in user's employee profile if id not passed or employee role
if (!$empId && $currentUser['employee_id']) {
    $empId = (int)$currentUser['employee_id'];
}

// Role authorization check: Employee can only view themselves
if (!isCurrentEmployee($empId)) {
    http_response_code(403);
    include __DIR__ . '/../includes/403.php';
    exit;
}

$pdo = getDBConnection();

// Fetch Employee
$sql = "SELECT e.*, u.email, u.role as user_role, u.status as user_status, 
               d.department_name, ds.designation_name 
        FROM employees e
        JOIN users u ON u.id = e.user_id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN designations ds ON ds.id = e.designation_id
        WHERE e.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$empId]);
$emp = $stmt->fetch();

if (!$emp) {
    setFlashMessage('danger', 'Employee profile not found.');
    header("Location: " . BASE_URL . "employees/index.php");
    exit;
}

// Handle Document Upload in Documents Tab
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_doc') {
    if (hasRole(['admin', 'hr']) || (int)$currentUser['employee_id'] === $empId) {
        $docName = trim($_POST['document_name'] ?? '');
        $docType = trim($_POST['document_type'] ?? 'General');

        if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $fTmp = $_FILES['doc_file']['tmp_name'];
            $fName = basename($_FILES['doc_file']['name']);
            $fExt = pathinfo($fName, PATHINFO_EXTENSION);
            $cleanName = time() . '_' . rand(100, 999) . '.' . $fExt;
            $destination = __DIR__ . '/../uploads/documents/' . $cleanName;

            if (move_uploaded_file($fTmp, $destination)) {
                $docIns = $pdo->prepare("INSERT INTO employee_documents (employee_id, document_name, document_type, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                $docIns->execute([$empId, $docName ?: $fName, $docType, 'uploads/documents/' . $cleanName, $currentUser['id']]);
                logAudit($pdo, $currentUser['id'], 'Upload Document', 'Employees', "Uploaded document '$docName' for {$emp['employee_code']}");
                setFlashMessage('success', 'Document uploaded successfully.');
                header("Location: " . BASE_URL . "employees/view.php?id=" . $empId . "#documents");
                exit;
            }
        }
    }
}

// 1. Attendance Records
$attStmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 30");
$attStmt->execute([$empId]);
$attendanceLogs = $attStmt->fetchAll();

$attSummaryStmt = $pdo->prepare("SELECT 
    COUNT(*) as total_days,
    SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
    SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as leave_days,
    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
    FROM attendance WHERE employee_id = ?");
$attSummaryStmt->execute([$empId]);
$attSummary = $attSummaryStmt->fetch();

// 2. Leave Records & Balances
$leaveStmt = $pdo->prepare("SELECT lr.*, lt.leave_name, u.name as reviewer_name 
                           FROM leave_requests lr 
                           JOIN leave_types lt ON lt.id = lr.leave_type_id 
                           LEFT JOIN users u ON u.id = lr.reviewed_by 
                           WHERE lr.employee_id = ? ORDER BY lr.applied_at DESC");
$leaveStmt->execute([$empId]);
$leaveRequests = $leaveStmt->fetchAll();

// 3. Payroll Records
$payStmt = $pdo->prepare("SELECT * FROM payroll WHERE employee_id = ? ORDER BY year DESC, month DESC");
$payStmt->execute([$empId]);
$payrollLogs = $payStmt->fetchAll();

// 4. Documents
$docStmt = $pdo->prepare("SELECT ed.*, u.name as uploader_name 
                         FROM employee_documents ed 
                         LEFT JOIN users u ON u.id = ed.uploaded_by 
                         WHERE ed.employee_id = ? ORDER BY ed.uploaded_at DESC");
$docStmt->execute([$empId]);
$documents = $docStmt->fetchAll();

// 5. Performance Reviews
$perfStmt = $pdo->prepare("SELECT pr.*, u.name as reviewer_name 
                          FROM performance_reviews pr 
                          LEFT JOIN users u ON u.id = pr.reviewer_id 
                          WHERE pr.employee_id = ? ORDER BY pr.review_date DESC");
$perfStmt->execute([$empId]);
$performanceReviews = $perfStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Profile Top Hero Banner -->
    <div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #102A43 0%, #0454D9 100%); border-radius: 14px; color: #FFFFFF;">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="user-avatar" style="width: 72px; height: 72px; font-size: 1.8rem; background: #FFFFFF; color: #0454D9; box-shadow: 0 4px 14px rgba(0,0,0,0.2);">
                        <?= strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h3 class="fw-bold mb-0 text-white"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h3>
                            <span class="badge bg-white text-dark font-monospace"><?= htmlspecialchars($emp['employee_code']) ?></span>
                            <?= renderStatusBadge($emp['status']) ?>
                        </div>
                        <div class="text-white-50 small">
                            <span><i class="bi bi-briefcase me-1"></i><?= htmlspecialchars($emp['designation_name'] ?? 'Staff') ?></span> &bull; 
                            <span><i class="bi bi-diagram-3 me-1"></i><?= htmlspecialchars($emp['department_name'] ?? 'General') ?></span> &bull; 
                            <span><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($emp['email']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <?php if (hasRole(['admin', 'hr'])): ?>
                        <a href="<?= BASE_URL ?>employees/edit.php?id=<?= $empId ?>" class="btn btn-light text-primary fw-semibold">
                            <i class="bi bi-pencil me-1"></i> Edit Profile
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-light" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 7-Tab Navigation Header -->
    <ul class="nav nav-pills mb-4 p-2 bg-white rounded-3 border shadow-sm" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active px-3 py-2 fw-semibold" id="tab-overview" data-bs-toggle="pill" data-bs-target="#pane-overview" type="button" role="tab">
                <i class="bi bi-grid-1x2 me-1"></i> Overview
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 fw-semibold" id="tab-personal" data-bs-toggle="pill" data-bs-target="#pane-personal" type="button" role="tab">
                <i class="bi bi-person me-1"></i> Personal Info
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 fw-semibold" id="tab-attendance" data-bs-toggle="pill" data-bs-target="#pane-attendance" type="button" role="tab">
                <i class="bi bi-calendar-check me-1"></i> Attendance
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 fw-semibold" id="tab-leave" data-bs-toggle="pill" data-bs-target="#pane-leave" type="button" role="tab">
                <i class="bi bi-clock-history me-1"></i> Leave
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 fw-semibold" id="tab-payroll" data-bs-toggle="pill" data-bs-target="#pane-payroll" type="button" role="tab">
                <i class="bi bi-cash-stack me-1"></i> Payroll
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 fw-semibold" id="tab-documents" data-bs-toggle="pill" data-bs-target="#pane-documents" type="button" role="tab">
                <i class="bi bi-folder2-open me-1"></i> Documents
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 fw-semibold" id="tab-performance" data-bs-toggle="pill" data-bs-target="#pane-performance" type="button" role="tab">
                <i class="bi bi-star me-1"></i> Performance
            </button>
        </li>
    </ul>

    <!-- Tabs Content Container -->
    <div class="tab-content" id="profileTabsContent">
        <!-- TAB 1: OVERVIEW -->
        <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-4">
                    <!-- Quick Stats Card -->
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="card-title"><i class="bi bi-info-circle text-primary"></i> Employment Summary</h6></div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                                    <span class="text-secondary small">Joining Date</span>
                                    <span class="fw-semibold text-dark small"><?= formatDate($emp['joining_date']) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                                    <span class="text-secondary small">Employment Type</span>
                                    <span class="badge bg-light text-secondary border text-capitalize"><?= str_replace('_', ' ', $emp['employment_type']) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                                    <span class="text-secondary small">Basic Monthly Pay</span>
                                    <span class="fw-semibold text-success small"><?= formatCurrency($emp['basic_salary']) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                                    <span class="text-secondary small">Portal Role</span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= ucfirst($emp['user_role']) ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Contact & Emergency Card -->
                    <div class="card">
                        <div class="card-header"><h6 class="card-title"><i class="bi bi-telephone text-primary"></i> Contact & Emergency</h6></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted d-block">Phone Number</small>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($emp['phone'] ?: 'Not recorded') ?></span>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block">Emergency Phone</small>
                                <span class="fw-semibold text-danger"><?= htmlspecialchars($emp['emergency_contact'] ?: 'Not recorded') ?></span>
                            </div>
                            <div>
                                <small class="text-muted d-block">Residential Address</small>
                                <span class="text-dark small"><?= nl2br(htmlspecialchars($emp['address'] ?: 'Not provided')) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <!-- Quick KPI overview tiles -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-4">
                            <div class="p-3 bg-white border rounded-3 text-center">
                                <span class="text-muted small text-uppercase">Recorded Days</span>
                                <h3 class="fw-bold text-dark my-1"><?= $attSummary['total_days'] ?? 0 ?></h3>
                                <small class="text-success"><i class="bi bi-check-circle"></i> In system records</small>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 bg-white border rounded-3 text-center">
                                <span class="text-muted small text-uppercase">Leave Requests</span>
                                <h3 class="fw-bold text-dark my-1"><?= count($leaveRequests) ?></h3>
                                <small class="text-primary"><i class="bi bi-calendar"></i> Total requests</small>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 bg-white border rounded-3 text-center">
                                <span class="text-muted small text-uppercase">Processed Slips</span>
                                <h3 class="fw-bold text-dark my-1"><?= count($payrollLogs) ?></h3>
                                <small class="text-success"><i class="bi bi-receipt"></i> Monthly payouts</small>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity in Profile -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="card-title"><i class="bi bi-clock-history text-primary"></i> Recent Attendance Snippet</h6>
                            <button class="btn btn-sm btn-outline-primary" onclick="bootstrap.Tab.getInstance(document.getElementById('tab-attendance')).show()">View Full Logs</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($attendanceLogs, 0, 5) as $al): ?>
                                        <tr>
                                            <td><?= formatDate($al['attendance_date']) ?></td>
                                            <td><?= formatTime($al['check_in']) ?></td>
                                            <td><?= formatTime($al['check_out']) ?></td>
                                            <td><?= $al['working_hours'] > 0 ? $al['working_hours'] . ' hrs' : '-' ?></td>
                                            <td><?= renderStatusBadge($al['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: PERSONAL INFORMATION -->
        <div class="tab-pane fade" id="pane-personal" role="tabpanel">
            <div class="card">
                <div class="card-header"><h5 class="card-title"><i class="bi bi-person-lines-fill text-primary"></i> Complete Personal & System Details</h5></div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-secondary text-uppercase small border-bottom pb-2">Identity & Background</h6>
                            <table class="table table-sm table-borderless">
                                <tr><td class="text-muted" style="width: 40%;">Full Legal Name:</td><td class="fw-semibold"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></td></tr>
                                <tr><td class="text-muted">Employee Code:</td><td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($emp['employee_code']) ?></td></tr>
                                <tr><td class="text-muted">Gender:</td><td class="text-capitalize"><?= htmlspecialchars($emp['gender'] ?? 'Not specified') ?></td></tr>
                                <tr><td class="text-muted">Date of Birth:</td><td><?= formatDate($emp['date_of_birth']) ?></td></tr>
                                <tr><td class="text-muted">Phone Number:</td><td><?= htmlspecialchars($emp['phone'] ?? '-') ?></td></tr>
                                <tr><td class="text-muted">Emergency Phone:</td><td class="text-danger fw-semibold"><?= htmlspecialchars($emp['emergency_contact'] ?? '-') ?></td></tr>
                                <tr><td class="text-muted">Address:</td><td><?= nl2br(htmlspecialchars($emp['address'] ?? '-')) ?></td></tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h6 class="fw-bold text-secondary text-uppercase small border-bottom pb-2">Organizational & System</h6>
                            <table class="table table-sm table-borderless">
                                <tr><td class="text-muted" style="width: 40%;">Assigned Department:</td><td class="fw-semibold"><?= htmlspecialchars($emp['department_name'] ?? 'Unassigned') ?></td></tr>
                                <tr><td class="text-muted">Official Designation:</td><td class="fw-semibold"><?= htmlspecialchars($emp['designation_name'] ?? 'Unassigned') ?></td></tr>
                                <tr><td class="text-muted">Joining Date:</td><td><?= formatDate($emp['joining_date']) ?></td></tr>
                                <tr><td class="text-muted">Employment Contract:</td><td class="text-capitalize"><?= str_replace('_', ' ', $emp['employment_type']) ?></td></tr>
                                <tr><td class="text-muted">Portal User Account:</td><td><?= htmlspecialchars($emp['email']) ?></td></tr>
                                <tr><td class="text-muted">System Security Role:</td><td><span class="badge bg-light text-dark border"><?= ucfirst($emp['user_role']) ?></span></td></tr>
                                <tr><td class="text-muted">Account Status:</td><td><?= renderStatusBadge($emp['status']) ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: ATTENDANCE -->
        <div class="tab-pane fade" id="pane-attendance" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><i class="bi bi-calendar-check text-primary"></i> Attendance History (Last 30 Records)</h5>
                    <div class="d-flex gap-2">
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Present: <?= $attSummary['present_days'] ?? 0 ?></span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Late: <?= $attSummary['late_days'] ?? 0 ?></span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Absent: <?= $attSummary['absent_days'] ?? 0 ?></span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Working Hours</th>
                                <th>Remarks</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceLogs)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No attendance entries recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($attendanceLogs as $log): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= formatDate($log['attendance_date']) ?></td>
                                        <td><?= formatTime($log['check_in']) ?></td>
                                        <td><?= formatTime($log['check_out']) ?></td>
                                        <td><?= $log['working_hours'] > 0 ? $log['working_hours'] . ' hrs' : '-' ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($log['remarks'] ?? '-') ?></td>
                                        <td><?= renderStatusBadge($log['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 4: LEAVE -->
        <div class="tab-pane fade" id="pane-leave" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><i class="bi bi-clock-history text-primary"></i> Time Off Applications</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                        <i class="bi bi-plus-lg me-1"></i> Apply Leave
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Total Days</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Review Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaveRequests)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No leave requests found for this employee.</td></tr>
                            <?php else: ?>
                                <?php foreach ($leaveRequests as $lr): ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($lr['leave_name']) ?></span></td>
                                        <td><?= formatDate($lr['start_date']) ?></td>
                                        <td><?= formatDate($lr['end_date']) ?></td>
                                        <td class="fw-semibold"><?= $lr['total_days'] ?> day(s)</td>
                                        <td class="small" style="max-width: 200px;"><?= htmlspecialchars($lr['reason']) ?></td>
                                        <td><?= renderStatusBadge($lr['status']) ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($lr['review_remarks'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 5: PAYROLL -->
        <div class="tab-pane fade" id="pane-payroll" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><i class="bi bi-cash-stack text-success"></i> Monthly Salary History</h5>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Pay Period</th>
                                <th>Basic Pay</th>
                                <th>Allowances</th>
                                <th>Bonus</th>
                                <th>Deductions</th>
                                <th>Gross Salary</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th class="text-end">Payslip</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payrollLogs)): ?>
                                <tr><td colspan="9" class="text-center py-4 text-muted">No payroll statements generated yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($payrollLogs as $pl): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= date('F Y', mktime(0, 0, 0, $pl['month'], 10, $pl['year'])) ?></td>
                                        <td><?= formatCurrency($pl['basic_salary']) ?></td>
                                        <td><?= formatCurrency($pl['allowances']) ?></td>
                                        <td><?= formatCurrency($pl['bonus']) ?></td>
                                        <td class="text-danger">-<?= formatCurrency($pl['deductions']) ?></td>
                                        <td><?= formatCurrency($pl['gross_salary']) ?></td>
                                        <td class="fw-bold text-success"><?= formatCurrency($pl['net_salary']) ?></td>
                                        <td><?= renderStatusBadge($pl['payment_status']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>payroll/payslip.php?id=<?= $pl['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-receipt me-1"></i> Payslip
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 6: DOCUMENTS -->
        <div class="tab-pane fade" id="pane-documents" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><i class="bi bi-folder2-open text-primary"></i> Employee Document Vault</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                        <i class="bi bi-upload me-1"></i> Upload Document
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Document Name</th>
                                <th>Category</th>
                                <th>Uploaded By</th>
                                <th>Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No documents uploaded for this profile.</td></tr>
                            <?php else: ?>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-file-earmark-pdf fs-4 text-danger me-2"></i>
                                                <span class="fw-semibold text-dark"><?= htmlspecialchars($doc['document_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($doc['document_type']) ?></span></td>
                                        <td><span class="small text-muted"><?= htmlspecialchars($doc['uploader_name'] ?? 'System') ?></span></td>
                                        <td><?= formatDate($doc['uploaded_at']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL . htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-download me-1"></i> View / Download
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 7: PERFORMANCE -->
        <div class="tab-pane fade" id="pane-performance" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><i class="bi bi-star-fill text-warning"></i> Performance Appraisals</h5>
                    <?php if (hasRole(['admin', 'hr'])): ?>
                        <a href="<?= BASE_URL ?>performance/add.php?employee_id=<?= $empId ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Conduct Review
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($performanceReviews)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-clipboard2-check fs-1 text-secondary d-block mb-2"></i>
                            No performance evaluations recorded for this employee yet.
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($performanceReviews as $pr): ?>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-3 bg-light h-100">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <div class="fw-bold text-dark">Review Cycle</div>
                                                <small class="text-muted"><?= formatDate($pr['review_period_start']) ?> to <?= formatDate($pr['review_period_end']) ?></small>
                                            </div>
                                            <div class="d-flex align-items-center px-2 py-1 bg-white border rounded">
                                                <i class="bi bi-star-fill text-warning me-1"></i>
                                                <span class="fw-bold text-dark"><?= number_format($pr['rating'], 1) ?></span>
                                                <span class="text-muted small">/ 5.0</span>
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <strong class="text-success small d-block">Strengths:</strong>
                                            <p class="small text-secondary mb-1"><?= nl2br(htmlspecialchars($pr['strengths'] ?? '')) ?></p>
                                        </div>

                                        <div class="mb-2">
                                            <strong class="text-warning small d-block">Areas for Improvement:</strong>
                                            <p class="small text-secondary mb-1"><?= nl2br(htmlspecialchars($pr['improvements'] ?? '')) ?></p>
                                        </div>

                                        <div class="pt-2 border-top text-muted small d-flex justify-content-between">
                                            <span>Reviewed by: <strong><?= htmlspecialchars($pr['reviewer_name'] ?? 'HR Admin') ?></strong></span>
                                            <span><?= formatDate($pr['review_date']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Upload Document -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-labelledby="uploadDocModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="uploadDocModalLabel"><i class="bi bi-file-earmark-arrow-up text-primary me-2"></i> Upload Employee Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>employees/view.php?id=<?= $empId ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_doc">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="document_name">Document Title <span class="required">*</span></label>
                        <input type="text" class="form-control" id="document_name" name="document_name" placeholder="e.g. Identity Proof / Master Agreement" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="document_type">Document Category</label>
                        <select class="form-select" id="document_type" name="document_type">
                            <option value="Contract">Contract / Offer Letter</option>
                            <option value="Identity Proof">Identity / Passport</option>
                            <option value="Tax">Tax Exemption / Declaration</option>
                            <option value="Education">Degree & Certificates</option>
                            <option value="General">General / Miscellaneous</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="doc_file">Choose File <span class="required">*</span></label>
                        <input type="file" class="form-control" id="doc_file" name="doc_file" accept=".pdf,.doc,.docx,.png,.jpg" required>
                        <small class="text-muted" style="font-size: 0.72rem;">PDF, DOC, DOCX, PNG, JPG accepted (Max 10MB).</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload File</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

