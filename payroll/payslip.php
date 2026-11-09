<?php
/**
 * Itemized Printable Payslip View
 * HR Management System
 */

$pageTitle = 'Employee Payslip';
$breadcrumbParent = 'Payroll';
$activePage = 'payroll';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$currentUser = getCurrentUser();
$payrollId = (int)($_GET['id'] ?? 0);

if (!$payrollId) {
    header("Location: " . BASE_URL . "payroll/index.php");
    exit;
}

$pdo = getDBConnection();

// Fetch payroll record
$sql = "SELECT p.*, e.first_name, e.last_name, e.employee_code, e.phone, e.joining_date, 
               u.email as employee_email, d.department_name, ds.designation_name 
        FROM payroll p
        JOIN employees e ON e.id = p.employee_id
        JOIN users u ON u.id = e.user_id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN designations ds ON ds.id = e.designation_id
        WHERE p.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$payrollId]);
$pay = $stmt->fetch();

if (!$pay) {
    setFlashMessage('danger', 'Payslip record not found.');
    header("Location: " . BASE_URL . "payroll/index.php");
    exit;
}

// Role check: Employee can only see their own payslip
if (!isCurrentEmployee($pay['employee_id'])) {
    http_response_code(403);
    include __DIR__ . '/../includes/403.php';
    exit;
}

// Fetch itemized details
$detailsStmt = $pdo->prepare("SELECT * FROM payroll_details WHERE payroll_id = ? ORDER BY component_type ASC");
$detailsStmt->execute([$payrollId]);
$details = $detailsStmt->fetchAll();

$earnings = array_filter($details, fn($d) => $d['component_type'] === 'earning');
$deductions = array_filter($details, fn($d) => $d['component_type'] === 'deduction');

$companyStmt = $pdo->query("SELECT * FROM company_settings LIMIT 1");
$company = $companyStmt->fetch() ?: [
    'company_name' => 'HRMS Solutions Inc.',
    'company_email' => 'hr@hrms.com',
    'company_phone' => '+1 (555) 345-6789',
    'company_address' => '500 Tech Parkway, Suite 700, San Francisco, CA'
];

$monthName = date('F', mktime(0, 0, 0, $pay['month'], 10));

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Top Action Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom no-print">
        <div>
            <h2 class="fw-bold text-dark mb-1">Salary Payslip</h2>
            <p class="text-secondary small mb-0">Official monthly remuneration statement and tax deduction statement.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print / Save PDF
            </button>
            <a href="<?= BASE_URL ?>payroll/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <!-- Printable Payslip Document -->
    <div class="card shadow-sm border mx-auto" style="max-width: 860px; border-radius: 14px; background: #FFFFFF;">
        <div class="card-body p-4 p-md-5">
            <!-- Header with Company Info -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
                <div class="d-flex align-items-center">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">HRMS</h4>
                        <small class="text-secondary d-block"><?= htmlspecialchars($company['company_address']) ?></small>
                        <small class="text-secondary"><?= htmlspecialchars($company['company_email']) ?> &bull; <?= htmlspecialchars($company['company_phone']) ?></small>
                    </div>
                </div>
                <div class="text-end">
                    <span class="badge" style="background: var(--success-green-bg); color: var(--success-green); font-size: 0.85rem; padding: 6px 14px; border: 1px solid rgba(22, 163, 106, 0.2);">
                        <i class="bi bi-check-circle-fill me-1"></i> Remuneration Paid
                    </span>
                    <h5 class="fw-bold text-dark mt-2 mb-0">PAYSLIP #<?= str_pad($pay['id'], 6, '0', STR_PAD_LEFT) ?></h5>
                    <small class="text-muted">Pay Period: <?= $monthName . ' ' . $pay['year'] ?></small>
                </div>
            </div>

            <!-- Employee Info Grid -->
            <div class="p-3 bg-light rounded-3 border mb-4">
                <div class="row g-3 small">
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted d-block">Employee Name:</span>
                        <strong class="text-dark fs-6"><?= htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']) ?></strong>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted d-block">Employee ID:</span>
                        <strong class="font-monospace text-primary fs-6"><?= htmlspecialchars($pay['employee_code']) ?></strong>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted d-block">Designation:</span>
                        <strong class="text-dark"><?= htmlspecialchars($pay['designation_name'] ?? 'Staff') ?></strong>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted d-block">Department:</span>
                        <strong class="text-dark"><?= htmlspecialchars($pay['department_name'] ?? 'General') ?></strong>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted d-block">Joining Date:</span>
                        <span class="text-dark"><?= formatDate($pay['joining_date']) ?></span>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted d-block">Payment Date:</span>
                        <span class="text-dark"><?= formatDate($pay['payment_date'] ?: $pay['created_at']) ?></span>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted d-block">Disbursement Mode:</span>
                        <span class="text-dark">Direct Bank Deposit</span>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <span class="text-muted d-block">Currency:</span>
                        <span class="text-dark font-monospace"><?= htmlspecialchars($_SESSION['company_currency'] ?? 'INR') ?></span>
                    </div>
                </div>
            </div>

            <!-- Earnings vs Deductions Dual Breakdown Table -->
            <div class="row g-4 mb-4">
                <!-- Earnings Column -->
                <div class="col-md-6">
                    <div class="border rounded-3 h-100 overflow-hidden">
                        <div class="bg-light p-2 px-3 fw-bold text-dark border-bottom d-flex justify-content-between">
                            <span>Earnings Component</span>
                            <span>Amount</span>
                        </div>
                        <table class="table table-sm mb-0">
                            <tbody>
                                <?php if (empty($earnings)): ?>
                                    <tr>
                                        <td>Basic Salary</td>
                                        <td class="text-end fw-semibold"><?= formatCurrency($pay['basic_salary']) ?></td>
                                    </tr>
                                    <?php if ($pay['allowances'] > 0): ?>
                                        <tr>
                                            <td>Allowances (HRA + Conveyance + Medical)</td>
                                            <td class="text-end fw-semibold"><?= formatCurrency($pay['allowances']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($pay['bonus'] > 0): ?>
                                        <tr>
                                            <td>Performance Bonus</td>
                                            <td class="text-end fw-semibold"><?= formatCurrency($pay['bonus']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php foreach ($earnings as $earn): ?>
                                        <tr>
                                            <td class="py-2"><?= htmlspecialchars($earn['component_name']) ?></td>
                                            <td class="text-end py-2 fw-semibold"><?= formatCurrency($earn['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="border-top">
                                <tr class="bg-light fw-bold text-dark">
                                    <td class="py-2">Gross Earnings</td>
                                    <td class="text-end py-2 text-primary"><?= formatCurrency($pay['gross_salary']) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Deductions Column -->
                <div class="col-md-6">
                    <div class="border rounded-3 h-100 overflow-hidden">
                        <div class="bg-light p-2 px-3 fw-bold text-dark border-bottom d-flex justify-content-between">
                            <span>Deductions Component</span>
                            <span>Amount</span>
                        </div>
                        <table class="table table-sm mb-0">
                            <tbody>
                                <?php if (empty($deductions)): ?>
                                    <tr>
                                        <td>Statutory Deductions (PF, Tax, Ins.)</td>
                                        <td class="text-end fw-semibold text-danger"><?= formatCurrency($pay['deductions']) ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($deductions as $ded): ?>
                                        <tr>
                                            <td class="py-2"><?= htmlspecialchars($ded['component_name']) ?></td>
                                            <td class="text-end py-2 fw-semibold text-danger"><?= formatCurrency($ded['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="border-top">
                                <tr class="bg-light fw-bold text-dark">
                                    <td class="py-2">Total Deductions</td>
                                    <td class="text-end py-2 text-danger"><?= formatCurrency($pay['deductions']) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Net Take-Home Salary Highlight Banner -->
            <div class="p-4 rounded-3 mb-4" style="background: var(--success-green-bg); border: 1px solid rgba(22, 163, 106, 0.25);">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
                    <div>
                        <span class="text-success small fw-bold text-uppercase letter-spacing-1">Net Take-Home Remuneration</span>
                        <div class="text-dark small mt-1">
                            <em>Amount in Words:</em> 
                            <strong><?= convertNumberToWords($pay['net_salary']) ?> <?= htmlspecialchars($_SESSION['company_currency'] ?? 'INR') ?> Only</strong>
                        </div>
                    </div>
                    <div class="mt-3 mt-sm-0 text-sm-end">
                        <h2 class="fw-bold mb-0" style="color: var(--success-green);"><?= formatCurrency($pay['net_salary']) ?></h2>
                    </div>
                </div>
            </div>

            <!-- Signatures & Disclaimer Footer -->
            <div class="row pt-4 mt-4 border-top text-center text-muted small">
                <div class="col-6 text-start">
                    <div style="height: 50px;"></div>
                    <div class="border-top pt-2" style="max-width: 200px;">
                        <strong>Employee Signature</strong>
                    </div>
                </div>
                <div class="col-6 text-end">
                    <div style="height: 50px;"></div>
                    <div class="border-top pt-2 ms-auto" style="max-width: 200px;">
                        <strong>Authorized Signatory</strong>
                    </div>
                </div>
                <div class="col-12 mt-4 text-center">
                    <p class="mb-0 text-muted" style="font-size: 0.72rem;">
                        This document is a computer-generated payroll summary issued by <?= htmlspecialchars($company['company_name']) ?>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

