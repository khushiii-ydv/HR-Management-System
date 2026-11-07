<?php
/**
 * Reports CSV Exporter
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$type = $_GET['type'] ?? 'employee';
$pdo = getDBConnection();
$currentUser = getCurrentUser();

logAudit($pdo, $currentUser['id'], 'Export Report', 'Reports', "Exported $type report to CSV");

// Set CSV download headers
$filename = "hrms_" . $type . "_report_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

if ($type === 'employee') {
    fputcsv($output, ['Employee Code', 'First Name', 'Last Name', 'Email', 'Department', 'Designation', 'Joining Date', 'Type', 'Basic Salary', 'Status']);

    $sql = "SELECT e.employee_code, e.first_name, e.last_name, u.email, d.department_name, ds.designation_name, e.joining_date, e.employment_type, e.basic_salary, e.status
            FROM employees e
            JOIN users u ON u.id = e.user_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations ds ON ds.id = e.designation_id
            ORDER BY e.employee_code ASC";
    $rows = $pdo->query($sql)->fetchAll();

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['employee_code'], $r['first_name'], $r['last_name'], $r['email'],
            $r['department_name'] ?? 'N/A', $r['designation_name'] ?? 'N/A',
            $r['joining_date'], $r['employment_type'], $r['basic_salary'], $r['status']
        ]);
    }

} elseif ($type === 'attendance') {
    fputcsv($output, ['Date', 'Employee Code', 'Employee Name', 'Department', 'Check In', 'Check Out', 'Working Hours', 'Status', 'Remarks']);

    $sql = "SELECT a.attendance_date, e.employee_code, e.first_name, e.last_name, d.department_name, a.check_in, a.check_out, a.working_hours, a.status, a.remarks
            FROM attendance a
            JOIN employees e ON e.id = a.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            ORDER BY a.attendance_date DESC, e.employee_code ASC";
    $rows = $pdo->query($sql)->fetchAll();

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['attendance_date'], $r['employee_code'], $r['first_name'] . ' ' . $r['last_name'],
            $r['department_name'] ?? 'N/A', $r['check_in'], $r['check_out'],
            $r['working_hours'], $r['status'], $r['remarks']
        ]);
    }

} elseif ($type === 'leave') {
    fputcsv($output, ['Application ID', 'Employee Code', 'Employee Name', 'Department', 'Leave Category', 'Start Date', 'End Date', 'Days', 'Status', 'Reason']);

    $sql = "SELECT lr.id, e.employee_code, e.first_name, e.last_name, d.department_name, lt.leave_name, lr.start_date, lr.end_date, lr.total_days, lr.status, lr.reason
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            JOIN leave_types lt ON lt.id = lr.leave_type_id
            LEFT JOIN departments d ON d.id = e.department_id
            ORDER BY lr.applied_at DESC";
    $rows = $pdo->query($sql)->fetchAll();

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['id'], $r['employee_code'], $r['first_name'] . ' ' . $r['last_name'],
            $r['department_name'] ?? 'N/A', $r['leave_name'], $r['start_date'],
            $r['end_date'], $r['total_days'], $r['status'], $r['reason']
        ]);
    }

} elseif ($type === 'payroll') {
    fputcsv($output, ['Payslip ID', 'Month', 'Year', 'Employee Code', 'Employee Name', 'Department', 'Basic Salary', 'Allowances', 'Bonus', 'Deductions', 'Gross Salary', 'Net Salary', 'Status']);

    $sql = "SELECT p.id, p.month, p.year, e.employee_code, e.first_name, e.last_name, d.department_name, p.basic_salary, p.allowances, p.bonus, p.deductions, p.gross_salary, p.net_salary, p.payment_status
            FROM payroll p
            JOIN employees e ON e.id = p.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            ORDER BY p.year DESC, p.month DESC, e.employee_code ASC";
    $rows = $pdo->query($sql)->fetchAll();

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['id'], $r['month'], $r['year'], $r['employee_code'], $r['first_name'] . ' ' . $r['last_name'],
            $r['department_name'] ?? 'N/A', $r['basic_salary'], $r['allowances'], $r['bonus'],
            $r['deductions'], $r['gross_salary'], $r['net_salary'], $r['payment_status']
        ]);
    }
}

fclose($output);
exit;

