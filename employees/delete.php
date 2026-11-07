<?php
/**
 * Delete / Deactivate Employee Handler
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole('admin');

$empId = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!$empId || !verifyCsrfToken($token)) {
    setFlashMessage('danger', 'Invalid request or expired security token.');
    header("Location: " . BASE_URL . "employees/index.php");
    exit;
}

$pdo = getDBConnection();
$currentUser = getCurrentUser();

try {
    // Fetch employee
    $stmt = $pdo->prepare("SELECT e.*, u.id as user_id FROM employees e JOIN users u ON u.id = e.user_id WHERE e.id = ?");
    $stmt->execute([$empId]);
    $emp = $stmt->fetch();

    if ($emp) {
        // In enterprise HRMS, we deactivate or mark terminated to preserve audit and payroll history
        $upd = $pdo->prepare("UPDATE employees SET status = 'terminated' WHERE id = ?");
        $upd->execute([$empId]);

        $updUser = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $updUser->execute([$emp['user_id']]);

        logAudit($pdo, $currentUser['id'], 'Terminate Employee', 'Employees', "Deactivated employee {$emp['first_name']} {$emp['last_name']} ({$emp['employee_code']})");
        setFlashMessage('success', "Employee {$emp['first_name']} {$emp['last_name']} has been deactivated/terminated.");
    }
} catch (Exception $e) {
    setFlashMessage('danger', 'Could not deactivate employee: ' . $e->getMessage());
}

header("Location: " . BASE_URL . "employees/index.php");
exit;

