<?php
/**
 * Run / Generate Monthly Payroll
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$currentUser = getCurrentUser();
$pdo = getDBConnection();

$month = (int)($_POST['month'] ?? date('n'));
$year  = (int)($_POST['year'] ?? date('Y'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bonusDefault = (float)($_POST['bonus_default'] ?? 0.00);

    try {
        // Fetch all active employees
        $employees = $pdo->query("SELECT * FROM employees WHERE status = 'active'")->fetchAll();

        $processedCount = 0;
        $pdo->beginTransaction();

        $stmtPayroll = $pdo->prepare("INSERT INTO payroll 
            (employee_id, month, year, basic_salary, allowances, bonus, deductions, gross_salary, net_salary, payment_date, payment_status, generated_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'paid', ?)
            ON DUPLICATE KEY UPDATE 
            basic_salary = VALUES(basic_salary),
            allowances = VALUES(allowances),
            bonus = VALUES(bonus),
            deductions = VALUES(deductions),
            gross_salary = VALUES(gross_salary),
            net_salary = VALUES(net_salary),
            payment_status = 'paid'");

        $stmtDelDetails = $pdo->prepare("DELETE FROM payroll_details WHERE payroll_id = ?");
        $stmtPayDetails = $pdo->prepare("INSERT INTO payroll_details (payroll_id, component_name, component_type, amount, description) VALUES (?, ?, ?, ?, ?)");

        foreach ($employees as $emp) {
            $basic = (float)$emp['basic_salary'];
            $hra = round($basic * 0.40, 2);
            $conveyance = 3000.00;
            $medical = 2500.00;
            $allowances = $hra + $conveyance + $medical;
            $bonus = $bonusDefault;
            $pf = round($basic * 0.12, 2);
            $tax = round($basic * 0.08, 2);
            $insurance = 1500.00;
            $deductions = $pf + $tax + $insurance;

            $gross = $basic + $allowances + $bonus;
            $net = $gross - $deductions;

            $stmtPayroll->execute([
                $emp['id'], $month, $year, $basic, $allowances, $bonus, $deductions, $gross, $net, $currentUser['id']
            ]);

            // Get generated payroll ID
            $pIdStmt = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND month = ? AND year = ?");
            $pIdStmt->execute([$emp['id'], $month, $year]);
            $payrollId = $pIdStmt->fetchColumn();

            if ($payrollId) {
                $stmtDelDetails->execute([$payrollId]);
                $stmtPayDetails->execute([$payrollId, 'Basic Salary', 'earning', $basic, 'Base salary']);
                $stmtPayDetails->execute([$payrollId, 'House Rent Allowance (HRA)', 'earning', $hra, '40% of basic']);
                $stmtPayDetails->execute([$payrollId, 'Conveyance Allowance', 'earning', $conveyance, 'Fixed monthly conveyance']);
                $stmtPayDetails->execute([$payrollId, 'Medical Allowance', 'earning', $medical, 'Health allowance']);
                if ($bonus > 0) {
                    $stmtPayDetails->execute([$payrollId, 'Performance Bonus', 'earning', $bonus, 'Monthly incentive']);
                }
                $stmtPayDetails->execute([$payrollId, 'Provident Fund (PF)', 'deduction', $pf, 'Retirement contribution 12%']);
                $stmtPayDetails->execute([$payrollId, 'Professional Tax', 'deduction', $tax, 'Income tax deduction']);
                $stmtPayDetails->execute([$payrollId, 'Group Health Insurance', 'deduction', $insurance, 'Medical insurance']);
            }

            // Notify employee
            createNotification(
                $pdo,
                $emp['user_id'],
                "Monthly Payslip Ready",
                "Your payslip for " . date('F Y', mktime(0, 0, 0, $month, 10, $year)) . " has been generated and paid.",
                'payroll'
            );

            $processedCount++;
        }

        $pdo->commit();
        logAudit($pdo, $currentUser['id'], 'Process Payroll', 'Payroll', "Batch executed payroll for $processedCount employees for $month/$year");
        setFlashMessage('success', "Payroll generated successfully for $processedCount active employees for " . date('F Y', mktime(0, 0, 0, $month, 10, $year)) . "!");

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlashMessage('danger', 'Error processing payroll: ' . $e->getMessage());
    }
}

header("Location: " . BASE_URL . "payroll/index.php?month=$month&year=$year");
exit;

