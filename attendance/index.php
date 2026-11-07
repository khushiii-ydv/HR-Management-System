<?php
/**
 * Attendance Management Module
 * HR Management System
 */

$pageTitle = 'Attendance Management';
$breadcrumbParent = 'Workforce';
$activePage = 'attendance';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$currentUser = getCurrentUser();
$role = $currentUser['role'];
$pdo = getDBConnection();

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$deptFilter   = $_GET['department_id'] ?? '';
$searchQuery  = trim($_GET['q'] ?? '');

// Handle Manual Attendance Mark / Correction (Admin & HR only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasRole(['admin', 'hr'])) {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_manual') {
        $empId    = (int)($_POST['employee_id'] ?? 0);
        $attDate  = $_POST['attendance_date'] ?? date('Y-m-d');
        $checkIn  = !empty($_POST['check_in']) ? $_POST['check_in'] : null;
        $checkOut = !empty($_POST['check_out']) ? $_POST['check_out'] : null;
        $status   = $_POST['status'] ?? 'present';
        $remarks  = trim($_POST['remarks'] ?? 'Manually logged by HR');

        $workingHours = 0.00;
        if ($checkIn && $checkOut) {
            $diff = strtotime($attDate . ' ' . $checkOut) - strtotime($attDate . ' ' . $checkIn);
            if ($diff > 0) {
                $workingHours = round($diff / 3600, 2);
            }
        }

        if ($empId && !empty($attDate)) {
            try {
                $sql = "INSERT INTO attendance (employee_id, attendance_date, check_in, check_out, status, working_hours, remarks) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        check_in = VALUES(check_in), 
                        check_out = VALUES(check_out), 
                        status = VALUES(status), 
                        working_hours = VALUES(working_hours), 
                        remarks = VALUES(remarks)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$empId, $attDate, $checkIn, $checkOut, $status, $workingHours, $remarks]);

                logAudit($pdo, $currentUser['id'], 'Manual Attendance', 'Attendance', "Marked attendance for Employee ID $empId on $attDate as $status");
                setFlashMessage('success', "Attendance record saved successfully.");
            } catch (Exception $e) {
                setFlashMessage('danger', 'Error updating attendance: ' . $e->getMessage());
            }
        }
    }
    header("Location: " . BASE_URL . "attendance/index.php?date=" . urlencode($selectedDate));
    exit;
}

// Fetch departments for filter
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();

// Fetch employees list for manual modal
$allEmployees = $pdo->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name ASC")->fetchAll();

if ($role === 'admin' || $role === 'hr') {
    // --- ADMIN / HR VIEW ---
    $where = ["1=1"];
    $params = [];

    if (!empty($selectedDate)) {
        $where[] = "a.attendance_date = ?";
        $params[] = $selectedDate;
    }

    if (!empty($deptFilter)) {
        $where[] = "e.department_id = ?";
        $params[] = $deptFilter;
    }

    if (!empty($searchQuery)) {
        $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ?)";
        $term = "%$searchQuery%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereClause = implode(" AND ", $where);

    $sql = "SELECT a.*, e.first_name, e.last_name, e.employee_code, d.department_name, ds.designation_name 
            FROM attendance a
            JOIN employees e ON e.id = a.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations ds ON ds.id = e.designation_id
            WHERE $whereClause
            ORDER BY a.check_in ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendanceRecords = $stmt->fetchAll();

    // Summary statistics for selected date
    $sumSql = "SELECT 
        SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_day_count,
        SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as leave_count,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
        FROM attendance WHERE attendance_date = ?";
    $sumStmt = $pdo->prepare($sumSql);
    $sumStmt->execute([$selectedDate]);
    $dateSummary = $sumStmt->fetch();

} else {
    // --- EMPLOYEE VIEW ---
    $empId = $currentUser['employee_id'];
    $monthFilter = $_GET['month'] ?? date('Y-m');

    $sql = "SELECT * FROM attendance 
            WHERE employee_id = ? AND attendance_date LIKE ? 
            ORDER BY attendance_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empId, "$monthFilter%"]);
    $attendanceRecords = $stmt->fetchAll();

    // Today's record for employee check-in status
    $todayAttStmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $todayAttStmt->execute([$empId, date('Y-m-d')]);
    $todayAtt = $todayAttStmt->fetch();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Top Action Bar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Attendance Management</h2>
            <p class="text-secondary small mb-0">Monitor real-time clock-in records, working hours, and daily workforce presence.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <?php if (hasRole(['admin', 'hr'])): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">
                    <i class="bi bi-clock-fill me-1"></i> Log Manual Attendance
                </button>
            <?php else: ?>
                <?php if (!$todayAtt || empty($todayAtt['check_in'])): ?>
                    <form action="<?= BASE_URL ?>attendance/check_in_out.php" method="POST">
                        <input type="hidden" name="action" value="check_in">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Clock In Now
                        </button>
                    </form>
                <?php elseif (empty($todayAtt['check_out'])): ?>
                    <form action="<?= BASE_URL ?>attendance/check_in_out.php" method="POST">
                        <input type="hidden" name="action" value="check_out">
                        <button type="submit" class="btn btn-warning text-dark fw-bold">
                            <i class="bi bi-box-arrow-right me-1"></i> Clock Out Now
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (hasRole(['admin', 'hr'])): ?>
        <!-- KPI Summary Cards for Attendance Date -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Present Today</span>
                        <div class="kpi-icon-box" style="background: var(--success-green-bg); color: var(--success-green);">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-success"><?= $dateSummary['present_count'] ?? 0 ?></div>
                    <div class="kpi-trend text-muted small">Checked in on <?= formatDate($selectedDate) ?></div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Late Arrivals</span>
                        <div class="kpi-icon-box" style="background: var(--attendance-orange-bg); color: var(--attendance-orange);">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="kpi-value" style="color: var(--attendance-orange);"><?= $dateSummary['late_count'] ?? 0 ?></div>
                    <div class="kpi-trend text-muted small">Clocked in after 09:30 AM</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">On Approved Leave</span>
                        <div class="kpi-icon-box" style="background: var(--light-blue); color: var(--primary-blue);">
                            <i class="bi bi-calendar2-check"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-primary"><?= $dateSummary['leave_count'] ?? 0 ?></div>
                    <div class="kpi-trend text-muted small">Scheduled absence</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Half Day / Unmarked</span>
                        <div class="kpi-icon-box" style="background: var(--warning-amber-bg); color: var(--warning-amber);">
                            <i class="bi bi-pie-chart"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-warning"><?= $dateSummary['half_day_count'] ?? 0 ?></div>
                    <div class="kpi-trend text-muted small">Under 5 working hours</div>
                </div>
            </div>
        </div>

        <!-- Filter Toolbar -->
        <div class="card mb-4">
            <div class="card-body py-3">
                <form action="<?= BASE_URL ?>attendance/index.php" method="GET" class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label small mb-1 fw-bold">Attendance Date</label>
                        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1 fw-bold">Department</label>
                        <select name="department_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($deptFilter == $d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1 fw-bold">Search Staff</label>
                        <input type="text" name="q" class="form-control" placeholder="Search by name or code..." value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-funnel"></i> Apply
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Logs Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title">
                    <i class="bi bi-clock text-warning"></i> Attendance Records for <?= formatDate($selectedDate) ?>
                </h5>
                <span class="badge bg-light text-secondary border"><?= count($attendanceRecords) ?> Logs</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Working Hours</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendanceRecords)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
                                    No attendance entries found for <?= formatDate($selectedDate) ?>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendanceRecords as $row): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($row['employee_code']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($row['department_name'] ?? 'General') ?></td>
                                    <td>
                                        <span class="fw-semibold <?= $row['status'] === 'late' ? 'text-warning' : 'text-dark' ?>">
                                            <?= formatTime($row['check_in']) ?>
                                        </span>
                                    </td>
                                    <td><?= formatTime($row['check_out']) ?></td>
                                    <td>
                                        <?php if ($row['working_hours'] > 0): ?>
                                            <span class="badge bg-light text-dark border"><?= $row['working_hours'] ?> hrs</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= renderStatusBadge($row['status']) ?></td>
                                    <td><small class="text-secondary"><?= htmlspecialchars($row['remarks'] ?? '-') ?></small></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-secondary" onclick='editManualAtt(<?= json_encode($row) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- EMPLOYEE VIEW: Personal Monthly Attendance -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title"><i class="bi bi-calendar-month text-primary"></i> My Attendance Record</h5>
                <form action="<?= BASE_URL ?>attendance/index.php" method="GET" class="d-flex gap-2">
                    <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($monthFilter) ?>" onchange="this.form.submit()">
                </form>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Hours Recorded</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendanceRecords)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No attendance logs found for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($attendanceRecords as $ar): ?>
                                <tr>
                                    <td class="fw-semibold"><?= formatDate($ar['attendance_date']) ?></td>
                                    <td><span class="text-secondary small"><?= date('l', strtotime($ar['attendance_date'])) ?></span></td>
                                    <td><?= formatTime($ar['check_in']) ?></td>
                                    <td><?= formatTime($ar['check_out']) ?></td>
                                    <td><?= $ar['working_hours'] > 0 ? $ar['working_hours'] . ' hrs' : '-' ?></td>
                                    <td><?= renderStatusBadge($ar['status']) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($ar['remarks'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Log / Edit Manual Attendance (HR & Admin) -->
<div class="modal fade" id="markAttendanceModal" tabindex="-1" aria-labelledby="markAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="markAttendanceModalLabel"><i class="bi bi-clock-history text-primary me-2"></i> Log Attendance Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>attendance/index.php?date=<?= urlencode($selectedDate) ?>" method="POST">
                <input type="hidden" name="action" value="mark_manual">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="manual_emp_id">Employee <span class="required">*</span></label>
                        <select class="form-select" id="manual_emp_id" name="employee_id" required>
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($allEmployees as $ae): ?>
                                <option value="<?= $ae['id'] ?>">
                                    <?= htmlspecialchars($ae['first_name'] . ' ' . $ae['last_name']) ?> (<?= htmlspecialchars($ae['employee_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="manual_att_date">Date <span class="required">*</span></label>
                        <input type="date" class="form-control" id="manual_att_date" name="attendance_date" value="<?= htmlspecialchars($selectedDate) ?>" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="manual_check_in">Check-In Time</label>
                            <input type="time" class="form-control" id="manual_check_in" name="check_in" value="09:00">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="manual_check_out">Check-Out Time</label>
                            <input type="time" class="form-control" id="manual_check_out" name="check_out" value="18:00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="manual_status">Status <span class="required">*</span></label>
                        <select class="form-select" id="manual_status" name="status" required>
                            <option value="present">Present (Normal)</option>
                            <option value="late">Late Arrival</option>
                            <option value="half_day">Half Day</option>
                            <option value="on_leave">On Leave</option>
                            <option value="absent">Absent</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="manual_remarks">Notes / Remarks</label>
                        <textarea class="form-control" id="manual_remarks" name="remarks" rows="2" placeholder="e.g. Approved remote work or transit delay"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editManualAtt(row) {
    document.getElementById('manual_emp_id').value = row.employee_id;
    document.getElementById('manual_att_date').value = row.attendance_date;
    document.getElementById('manual_check_in').value = row.check_in ? row.check_in.substring(0, 5) : '';
    document.getElementById('manual_check_out').value = row.check_out ? row.check_out.substring(0, 5) : '';
    document.getElementById('manual_status').value = row.status;
    document.getElementById('manual_remarks').value = row.remarks || '';
    new bootstrap.Modal(document.getElementById('markAttendanceModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

