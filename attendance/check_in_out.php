<?php
/**
 * Attendance Check-In / Check-Out Handler
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$currentUser = getCurrentUser();
$empId = $currentUser['employee_id'];

if (!$empId) {
    setFlashMessage('danger', 'Your account is not linked to an employee profile.');
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? (BASE_URL . 'dashboard.php');
$today = date('Y-m-d');
$now = date('H:i:s');

try {
    $pdo = getDBConnection();

    // Fetch today's record if any
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $stmt->execute([$empId, $today]);
    $att = $stmt->fetch();

    if ($action === 'check_in') {
        if ($att && $att['check_in'] !== null) {
            setFlashMessage('warning', 'You have already checked in for today at ' . formatTime($att['check_in']) . '.');
        } else {
            // Determine if late (e.g., after 09:30 AM)
            $status = (strtotime($now) > strtotime('09:30:00')) ? 'late' : 'present';
            $remarks = ($status === 'late') ? 'Late Check-in' : 'Normal Check-in';

            if ($att) {
                $upd = $pdo->prepare("UPDATE attendance SET check_in = ?, status = ?, remarks = ? WHERE id = ?");
                $upd->execute([$now, $status, $remarks, $att['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, check_in, status, remarks) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$empId, $today, $now, $status, $remarks]);
            }

            logAudit($pdo, $currentUser['id'], 'Attendance Check-in', 'Attendance', "Checked in at $now ($status)");
            setFlashMessage('success', "Check-in recorded successfully at " . formatTime($now) . "!");
        }
    } elseif ($action === 'check_out') {
        if (!$att || empty($att['check_in'])) {
            setFlashMessage('danger', 'You must check in first before checking out.');
        } elseif (!empty($att['check_out'])) {
            setFlashMessage('warning', 'You have already checked out today at ' . formatTime($att['check_out']) . '.');
        } else {
            $checkInTimestamp = strtotime($today . ' ' . $att['check_in']);
            $checkOutTimestamp = strtotime($today . ' ' . $now);
            $hours = round(($checkOutTimestamp - $checkInTimestamp) / 3600, 2);
            if ($hours < 0) $hours = 0;

            // Half-day if under 5 hours
            $status = $att['status'];
            if ($hours < 5.0 && $status === 'present') {
                $status = 'half_day';
            }

            $upd = $pdo->prepare("UPDATE attendance SET check_out = ?, working_hours = ?, status = ? WHERE id = ?");
            $upd->execute([$now, $hours, $status, $att['id']]);

            logAudit($pdo, $currentUser['id'], 'Attendance Check-out', 'Attendance', "Checked out at $now. Working hours: $hours hrs");
            setFlashMessage('success', "Check-out recorded at " . formatTime($now) . "! Total working hours: " . $hours . " hrs.");
        }
    }
} catch (Exception $e) {
    setFlashMessage('danger', 'Attendance error: ' . $e->getMessage());
}

header("Location: " . $redirect);
exit;

