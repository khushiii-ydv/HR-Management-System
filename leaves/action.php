<?php
/**
 * Approve or Reject Leave Request Action
 * HR Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$currentUser = getCurrentUser();
$pdo = getDBConnection();

$id      = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$status  = strtolower(trim($_GET['status'] ?? $_POST['status'] ?? ''));
$remarks = trim($_POST['remarks'] ?? $_GET['remarks'] ?? ($status === 'approved' ? 'Approved by HR' : 'Declined per departmental requirement'));

if (!$id || !in_array($status, ['approved', 'rejected', 'cancelled'])) {
    setFlashMessage('danger', 'Invalid leave action.');
    header("Location: " . BASE_URL . "leaves/index.php");
    exit;
}

try {
    // Fetch request
    $stmt = $pdo->prepare("SELECT lr.*, e.user_id, e.employee_code, e.first_name, e.last_name, lt.leave_name 
                           FROM leave_requests lr 
                           JOIN employees e ON e.id = lr.employee_id 
                           JOIN leave_types lt ON lt.id = lr.leave_type_id 
                           WHERE lr.id = ?");
    $stmt->execute([$id]);
    $lr = $stmt->fetch();

    if (!$lr) {
        setFlashMessage('danger', 'Leave request not found.');
    } else {
        $upd = $pdo->prepare("UPDATE leave_requests SET status = ?, reviewed_by = ?, review_remarks = ?, reviewed_at = NOW() WHERE id = ?");
        $upd->execute([$status, $currentUser['id'], $remarks, $id]);

        // If approved, mark attendance records for those dates as 'on_leave'
        if ($status === 'approved') {
            $start = new DateTime($lr['start_date']);
            $end = new DateTime($lr['end_date']);
            $end->modify('+1 day');
            $period = new DatePeriod($start, new DateInterval('P1D'), $end);

            $attStmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, status, remarks) 
                                      VALUES (?, ?, 'on_leave', ?) 
                                      ON DUPLICATE KEY UPDATE status = 'on_leave', remarks = VALUES(remarks)");

            foreach ($period as $dt) {
                if ((int)$dt->format('N') < 6) { // Mon-Fri
                    $dateStr = $dt->format('Y-m-d');
                    $attStmt->execute([$lr['employee_id'], $dateStr, "Approved " . $lr['leave_name']]);
                }
            }
        }

        // Notify employee
        createNotification(
            $pdo,
            $lr['user_id'],
            "Leave Request " . ucfirst($status),
            "Your request for {$lr['total_days']} day(s) {$lr['leave_name']} from " . formatDate($lr['start_date']) . " was $status.",
            'leave'
        );

        logAudit($pdo, $currentUser['id'], 'Review Leave', 'Leaves', "Marked leave request #$id as $status for {$lr['first_name']} {$lr['last_name']}");
        setFlashMessage('success', "Leave request #$id marked as " . ucfirst($status) . ".");
    }
} catch (Exception $e) {
    setFlashMessage('danger', 'Error updating leave request: ' . $e->getMessage());
}

header("Location: " . BASE_URL . "leaves/index.php");
exit;

