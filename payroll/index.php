<?php
/**
 * Payroll Management Dashboard
 * HR Management System
 */

$pageTitle = 'Payroll Management';
$breadcrumbParent = 'Finance';
$activePage = 'payroll';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$currentUser = getCurrentUser();
$role = $currentUser['role'];
$pdo = getDBConnection();

$selectedMonth = (int)($_GET['month'] ?? date('n'));
$selectedYear  = (int)($_GET['year'] ?? date('Y'));

if ($role === 'admin' || $role === 'hr') {
    // --- ADMIN / HR VIEW ---
    $sql = "SELECT p.*, e.first_name, e.last_name, e.employee_code, d.department_name, ds.designation_name 
            FROM payroll p
            JOIN employees e ON e.id = p.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations ds ON ds.id = e.designation_id
            WHERE p.month = ? AND p.year = ?
            ORDER BY e.first_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selectedMonth, $selectedYear]);
    $payrollRecords = $stmt->fetchAll();

    // Summary calculations
    $totalGross = 0;
    $totalNet = 0;
    $totalDeductions = 0;
    $paidCount = 0;

    foreach ($payrollRecords as $pr) {
        $totalGross += (float)$pr['gross_salary'];
        $totalNet += (float)$pr['net_salary'];
        $totalDeductions += (float)$pr['deductions'];
        if ($pr['payment_status'] === 'paid') {
            $paidCount++;
        }
    }

} else {
    // --- EMPLOYEE VIEW ---
    $empId = $currentUser['employee_id'];
    $stmt = $pdo->prepare("SELECT * FROM payroll WHERE employee_id = ? ORDER BY year DESC, month DESC");
    $stmt->execute([$empId]);
    $payrollRecords = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Action Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <?= ($role === 'employee') ? 'My Salary Payslips' : 'Payroll Management' ?>
            </h2>
            <p class="text-secondary small mb-0">Compute monthly compensation, statutory deductions, bonuses, and generate itemized payslips.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <?php if (hasRole(['admin', 'hr'])): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generatePayrollModal">
                    <i class="bi bi-play-circle me-1"></i> Process Monthly Payroll
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (hasRole(['admin', 'hr'])): ?>
        <!-- Month Filter Toolbar -->
        <div class="card mb-4">
            <div class="card-body py-3">
                <form action="<?= BASE_URL ?>payroll/index.php" method="GET" class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label small mb-1 fw-bold">Select Month</label>
                        <select name="month" class="form-select" onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= ($selectedMonth === $m) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1 fw-bold">Select Year</label>
                        <select name="year" class="form-select" onchange="this.form.submit()">
                            <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= ($selectedYear === $y) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1 fw-bold">Filter Staff</label>
                        <input type="text" class="form-control" placeholder="Search by name or code..." data-table-search="#payrollTable">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-arrow-repeat"></i> Load Cycle
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- KPI Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Gross Remuneration</span>
                        <div class="kpi-icon-box" style="background: var(--light-blue); color: var(--primary-blue);"><i class="bi bi-wallet2"></i></div>
                    </div>
                    <div class="kpi-value"><?= formatCurrency($totalGross) ?></div>
                    <div class="kpi-trend text-muted small">Total earnings before deductions</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Net Take-Home Payout</span>
                        <div class="kpi-icon-box" style="background: var(--success-green-bg); color: var(--success-green);"><i class="bi bi-cash-stack"></i></div>
                    </div>
                    <div class="kpi-value text-success"><?= formatCurrency($totalNet) ?></div>
                    <div class="kpi-trend text-success small"><i class="bi bi-check-circle"></i> Ready for bank deposit</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Statutory Deductions</span>
                        <div class="kpi-icon-box" style="background: var(--error-red-bg); color: var(--error-red);"><i class="bi bi-shield-minus"></i></div>
                    </div>
                    <div class="kpi-value text-danger"><?= formatCurrency($totalDeductions) ?></div>
                    <div class="kpi-trend text-muted small">PF, Tax & Insurance</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Disbursed Records</span>
                        <div class="kpi-icon-box" style="background: var(--attendance-orange-bg); color: var(--attendance-orange);"><i class="bi bi-people-fill"></i></div>
                    </div>
                    <div class="kpi-value"><?= $paidCount ?> <span class="text-muted fs-6">/ <?= count($payrollRecords) ?></span></div>
                    <div class="kpi-trend text-muted small">Employees processed</div>
                </div>
            </div>
        </div>

        <!-- Payroll Records Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title">
                    <i class="bi bi-receipt text-success"></i> Payroll Register: <?= date('F Y', mktime(0, 0, 0, $selectedMonth, 10, $selectedYear)) ?>
                </h5>
                <span class="badge bg-light text-secondary border"><?= count($payrollRecords) ?> Statements</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle" id="payrollTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Basic Salary</th>
                            <th>Allowances</th>
                            <th>Bonus</th>
                            <th>Deductions</th>
                            <th>Gross Pay</th>
                            <th>Net Payout</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payrollRecords)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="bi bi-receipt-cutoff fs-1 d-block mb-2 text-secondary"></i>
                                    No payroll records generated for this cycle. Click "Process Monthly Payroll" above to run calculations.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payrollRecords as $p): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($p['employee_code']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($p['department_name'] ?? 'General') ?></td>
                                    <td><?= formatCurrency($p['basic_salary']) ?></td>
                                    <td><?= formatCurrency($p['allowances']) ?></td>
                                    <td><?= $p['bonus'] > 0 ? formatCurrency($p['bonus']) : '-' ?></td>
                                    <td class="text-danger">-<?= formatCurrency($p['deductions']) ?></td>
                                    <td class="fw-semibold"><?= formatCurrency($p['gross_salary']) ?></td>
                                    <td class="fw-bold text-success fs-6"><?= formatCurrency($p['net_salary']) ?></td>
                                    <td><?= renderStatusBadge($p['payment_status']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>payroll/payslip.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-file-earmark-text me-1"></i> Payslip
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- EMPLOYEE VIEW: My Personal Payslips -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title"><i class="bi bi-wallet2 text-success"></i> Remuneration Statements</h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Pay Cycle</th>
                            <th>Basic Salary</th>
                            <th>Allowances</th>
                            <th>Bonus</th>
                            <th>Deductions</th>
                            <th>Gross Pay</th>
                            <th>Net Take-Home</th>
                            <th>Status</th>
                            <th class="text-end">View Statement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payrollRecords)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No payslip records available.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payrollRecords as $pr): ?>
                                <tr>
                                    <td class="fw-semibold text-dark">
                                        <?= date('F Y', mktime(0, 0, 0, $pr['month'], 10, $pr['year'])) ?>
                                    </td>
                                    <td><?= formatCurrency($pr['basic_salary']) ?></td>
                                    <td><?= formatCurrency($pr['allowances']) ?></td>
                                    <td><?= formatCurrency($pr['bonus']) ?></td>
                                    <td class="text-danger">-<?= formatCurrency($pr['deductions']) ?></td>
                                    <td><?= formatCurrency($pr['gross_salary']) ?></td>
                                    <td class="fw-bold text-success fs-6"><?= formatCurrency($pr['net_salary']) ?></td>
                                    <td><?= renderStatusBadge($pr['payment_status']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>payroll/payslip.php?id=<?= $pr['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-receipt me-1"></i> View / Download
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Process Monthly Payroll -->
<div class="modal fade" id="generatePayrollModal" tabindex="-1" aria-labelledby="generatePayrollModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="generatePayrollModalLabel">
                    <i class="bi bi-calculator text-success me-2"></i> Run Monthly Payroll Batch
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>payroll/generate.php" method="POST">
                <div class="modal-body">
                    <p class="text-secondary small mb-3">
                        This operation computes basic earnings, HRA (40%), statutory deductions (PF, Tax, Insurance), and generates itemized payslips for all active personnel.
                    </p>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="run_month">Month <span class="required">*</span></label>
                            <select class="form-select" id="run_month" name="month" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= ($selectedMonth === $m) ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="run_year">Year <span class="required">*</span></label>
                            <select class="form-select" id="run_year" name="year" required>
                                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                    <option value="<?= $y ?>" <?= ($selectedYear === $y) ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="bonus_default">Standard Incentive / Bonus per Employee (<?= htmlspecialchars($_SESSION['company_currency'] ?? 'INR') ?>)</label>
                        <input type="number" step="0.01" class="form-control" id="bonus_default" name="bonus_default" value="0.00">
                        <small class="text-muted" style="font-size: 0.72rem;">Optional. Added to monthly gross pay.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i> Confirm & Execute
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

