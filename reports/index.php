<?php
/**
 * Reports & Business Intelligence
 * HR Management System
 */

$pageTitle = 'Reports & Analytics';
$breadcrumbParent = 'Administration';
$activePage = 'reports';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$pdo = getDBConnection();
$activeTab = $_GET['tab'] ?? 'employee';

// 1. Employee Report Data
$empDept = $_GET['emp_dept'] ?? '';
$empWhere = ["1=1"];
$empParams = [];
if (!empty($empDept)) {
    $empWhere[] = "e.department_id = ?";
    $empParams[] = $empDept;
}
$empSql = "SELECT e.*, u.email, d.department_name, ds.designation_name 
           FROM employees e 
           JOIN users u ON u.id = e.user_id 
           LEFT JOIN departments d ON d.id = e.department_id 
           LEFT JOIN designations ds ON ds.id = e.designation_id 
           WHERE " . implode(" AND ", $empWhere) . " ORDER BY e.first_name ASC";
$empStmt = $pdo->prepare($empSql);
$empStmt->execute($empParams);
$employeeReport = $empStmt->fetchAll();

// 2. Attendance Summary Report Data
$attStartDate = $_GET['att_start'] ?? date('Y-m-01');
$attEndDate   = $_GET['att_end'] ?? date('Y-m-d');
$attSql = "SELECT e.id, e.employee_code, e.first_name, e.last_name, d.department_name,
           COUNT(a.id) as total_logged,
           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
           SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
           SUM(CASE WHEN a.status = 'on_leave' THEN 1 ELSE 0 END) as leave_days,
           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
           SUM(a.working_hours) as total_hours
           FROM employees e
           LEFT JOIN departments d ON d.id = e.department_id
           LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date BETWEEN ? AND ?
           GROUP BY e.id, e.employee_code, e.first_name, e.last_name, d.department_name
           ORDER BY e.first_name ASC";
$attStmt = $pdo->prepare($attSql);
$attStmt->execute([$attStartDate, $attEndDate]);
$attendanceReport = $attStmt->fetchAll();

// 3. Leave Utilization Report
$leaveSql = "SELECT e.employee_code, e.first_name, e.last_name, d.department_name, lt.leave_name,
             COUNT(lr.id) as applications_count,
             SUM(lr.total_days) as total_days_taken
             FROM leave_requests lr
             JOIN employees e ON e.id = lr.employee_id
             JOIN leave_types lt ON lt.id = lr.leave_type_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE lr.status = 'approved'
             GROUP BY e.id, lt.id
             ORDER BY e.first_name ASC";
$leaveReport = $pdo->query($leaveSql)->fetchAll();

// 4. Payroll Expenditure Report
$payrollReportSql = "SELECT p.year, p.month, 
                     COUNT(p.id) as employees_paid,
                     SUM(p.basic_salary) as total_basic,
                     SUM(p.allowances) as total_allowances,
                     SUM(p.bonus) as total_bonus,
                     SUM(p.deductions) as total_deductions,
                     SUM(p.gross_salary) as total_gross,
                     SUM(p.net_salary) as total_net
                     FROM payroll p
                     GROUP BY p.year, p.month
                     ORDER BY p.year DESC, p.month DESC";
$payrollReport = $pdo->query($payrollReportSql)->fetchAll();

$departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Reports & Workforce Intelligence</h2>
            <p class="text-secondary small mb-0">Export audited compliance records, attendance metrics, and payroll journals.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="<?= BASE_URL ?>reports/export.php?type=<?= htmlspecialchars($activeTab) ?>" class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Active Report to CSV
            </a>
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-pills mb-4 p-2 bg-white rounded-3 border shadow-sm" id="reportTabs" role="tablist">
        <li class="nav-item">
            <a href="<?= BASE_URL ?>reports/index.php?tab=employee" class="nav-link <?= ($activeTab === 'employee') ? 'active' : '' ?> fw-semibold">
                <i class="bi bi-people me-1"></i> Employee Headcount
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>reports/index.php?tab=attendance" class="nav-link <?= ($activeTab === 'attendance') ? 'active' : '' ?> fw-semibold">
                <i class="bi bi-calendar-check me-1"></i> Attendance & Hours
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>reports/index.php?tab=leave" class="nav-link <?= ($activeTab === 'leave') ? 'active' : '' ?> fw-semibold">
                <i class="bi bi-clock-history me-1"></i> Leave Utilization
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>reports/index.php?tab=payroll" class="nav-link <?= ($activeTab === 'payroll') ? 'active' : '' ?> fw-semibold">
                <i class="bi bi-cash-stack me-1"></i> Payroll Financials
            </a>
        </li>
    </ul>

    <!-- Report Views -->
    <?php if ($activeTab === 'employee'): ?>
        <!-- EMPLOYEE REPORT -->
        <div class="card mb-4">
            <div class="card-body py-3">
                <form action="<?= BASE_URL ?>reports/index.php" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="employee">
                    <div class="col-md-4">
                        <select name="emp_dept" class="form-select" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($empDept == $d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" class="form-control" placeholder="Live search records..." data-table-search="#repEmpTable">
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title"><i class="bi bi-people text-primary"></i> Master Employee Roster</h5>
                <span class="badge bg-light text-secondary border"><?= count($employeeReport) ?> Personnel</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle" id="repEmpTable">
                    <thead>
                        <tr>
                            <th>Employee Code</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Joining Date</th>
                            <th>Contract</th>
                            <th>Basic Salary</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employeeReport as $er): ?>
                            <tr>
                                <td><span class="font-monospace fw-bold text-primary"><?= htmlspecialchars($er['employee_code']) ?></span></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($er['first_name'] . ' ' . $er['last_name']) ?></td>
                                <td><?= htmlspecialchars($er['email']) ?></td>
                                <td><?= htmlspecialchars($er['department_name'] ?? 'General') ?></td>
                                <td><?= htmlspecialchars($er['designation_name'] ?? 'Staff') ?></td>
                                <td><?= formatDate($er['joining_date']) ?></td>
                                <td class="text-capitalize"><?= str_replace('_', ' ', $er['employment_type']) ?></td>
                                <td><?= formatCurrency($er['basic_salary']) ?></td>
                                <td><?= renderStatusBadge($er['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($activeTab === 'attendance'): ?>
        <!-- ATTENDANCE REPORT -->
        <div class="card mb-4">
            <div class="card-body py-3">
                <form action="<?= BASE_URL ?>reports/index.php" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="attendance">
                    <div class="col-md-3">
                        <label class="form-label small mb-1 fw-bold">From Date</label>
                        <input type="date" name="att_start" class="form-control" value="<?= htmlspecialchars($attStartDate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1 fw-bold">To Date</label>
                        <input type="date" name="att_end" class="form-control" value="<?= htmlspecialchars($attEndDate) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title"><i class="bi bi-clock-history text-primary"></i> Attendance Summary (<?= formatDate($attStartDate) ?> to <?= formatDate($attEndDate) ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Days Logged</th>
                            <th>Present</th>
                            <th>Late</th>
                            <th>On Leave</th>
                            <th>Total Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceReport as $ar): ?>
                            <tr>
                                <td><span class="font-monospace fw-bold text-primary"><?= htmlspecialchars($ar['employee_code']) ?></span></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($ar['first_name'] . ' ' . $ar['last_name']) ?></td>
                                <td><?= htmlspecialchars($ar['department_name'] ?? 'General') ?></td>
                                <td><?= $ar['total_logged'] ?></td>
                                <td><span class="badge bg-success-subtle text-success border border-success-subtle"><?= $ar['present_days'] ?? 0 ?></span></td>
                                <td><span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= $ar['late_days'] ?? 0 ?></span></td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= $ar['leave_days'] ?? 0 ?></span></td>
                                <td class="fw-bold"><?= number_format($ar['total_hours'] ?? 0, 1) ?> hrs</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($activeTab === 'leave'): ?>
        <!-- LEAVE UTILIZATION REPORT -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title"><i class="bi bi-calendar2-range text-primary"></i> Approved Leave Consumption by Staff</h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th>Leave Category</th>
                            <th>Approved Applications</th>
                            <th>Total Days Consumed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaveReport)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No approved leaves on record.</td></tr>
                        <?php else: ?>
                            <?php foreach ($leaveReport as $lr): ?>
                                <tr>
                                    <td><span class="font-monospace text-primary fw-bold"><?= htmlspecialchars($lr['employee_code']) ?></span></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($lr['first_name'] . ' ' . $lr['last_name']) ?></td>
                                    <td><?= htmlspecialchars($lr['department_name'] ?? 'General') ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($lr['leave_name']) ?></span></td>
                                    <td><?= $lr['applications_count'] ?> request(s)</td>
                                    <td><span class="badge bg-primary fs-6 px-3"><?= $lr['total_days_taken'] ?> days</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($activeTab === 'payroll'): ?>
        <!-- PAYROLL FINANCIALS REPORT -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title"><i class="bi bi-cash-stack text-success"></i> Monthly Financial Disbursement Ledger</h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Fiscal Cycle</th>
                            <th>Employees Paid</th>
                            <th>Total Basic Pay</th>
                            <th>Allowances</th>
                            <th>Bonuses</th>
                            <th>Deductions</th>
                            <th>Gross Outflow</th>
                            <th>Net Bank Outflow</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payrollReport as $pr): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= date('F Y', mktime(0, 0, 0, $pr['month'], 10, $pr['year'])) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= $pr['employees_paid'] ?> Staff</span></td>
                                <td><?= formatCurrency($pr['total_basic']) ?></td>
                                <td><?= formatCurrency($pr['total_allowances']) ?></td>
                                <td><?= formatCurrency($pr['total_bonus']) ?></td>
                                <td class="text-danger">-<?= formatCurrency($pr['total_deductions']) ?></td>
                                <td class="fw-semibold"><?= formatCurrency($pr['total_gross']) ?></td>
                                <td class="fw-bold text-success fs-6"><?= formatCurrency($pr['total_net']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

