<?php
/**
 * Submit Leave Application Handler
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
    header("Location: " . BASE_URL . "leaves/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlashMessage('danger', 'Invalid security token.');
        header("Location: " . BASE_URL . "leaves/index.php");
        exit;
    }

    $leaveTypeId = (int)($_POST['leave_type_id'] ?? 0);
    $startDate   = $_POST['start_date'] ?? '';
    $endDate     = $_POST['end_date'] ?? '';
    $reason      = trim($_POST['reason'] ?? '');

    if (!$leaveTypeId || empty($startDate) || empty($endDate) || empty($reason)) {
        setFlashMessage('danger', 'Please provide leave type, start/end dates, and a valid reason.');
    } elseif (strtotime($endDate) < strtotime($startDate)) {
        setFlashMessage('danger', 'End date cannot be prior to start date.');
    } else {
        try {
            $pdo = getDBConnection();

            // Calculate total days (excluding weekends)
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $end->modify('+1 day');
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $end);

            $totalDays = 0;
            foreach ($period as $dt) {
                $dayOfWeek = (int)$dt->format('N');
                if ($dayOfWeek < 6) { // Monday to Friday
                    $totalDays++;
                }
            }
            if ($totalDays <= 0) $totalDays = 1;

            $stmt = $pdo->prepare("INSERT INTO leave_requests 
                (employee_id, leave_type_id, start_date, end_date, total_days, reason, status, applied_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$empId, $leaveTypeId, $startDate, $endDate, $totalDays, $reason]);

            logAudit($pdo, $currentUser['id'], 'Apply Leave', 'Leaves', "Applied for $totalDays days leave from $startDate to $endDate");

            // Notify Admin & HR
            $admins = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'hr')")->fetchAll();
            foreach ($admins as $adm) {
                createNotification($pdo, $adm['id'], 'New Leave Application', "{$currentUser['name']} requested $totalDays day(s) off.", 'leave');
            }

            setFlashMessage('success', "Your leave application for $totalDays day(s) has been submitted for approval.");
        } catch (Exception $e) {
            setFlashMessage('danger', 'Error submitting leave: ' . $e->getMessage());
        }
    }
}

header("Location: " . BASE_URL . "leaves/index.php");
exit;

