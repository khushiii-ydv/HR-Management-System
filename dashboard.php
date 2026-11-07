<?php
/**
 * Master Role-Based Dashboard
 * Admin / HR / Employee
 */

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

requireLogin();

$currentUser = getCurrentUser();
$role = $currentUser['role'];
$pdo = getDBConnection();
$today = date('Y-m-d');

// Data containers
$stats = [];
$recentActivities = [];
$pendingLeaves = [];
$upcomingHolidays = [];
$todayAttendanceBreakdown = ['present' => 0, 'late' => 0, 'on_leave' => 0, 'absent' => 0];
$deptDistribution = [];
$employeeProfile = null;
$myAttendanceToday = null;
$myRecentAttendance = [];
$myLeaveBalances = [];
$myLatestPayroll = null;

try {
    // 1. Upcoming Holidays (Common to all)
    $holStmt = $pdo->prepare("SELECT * FROM holidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date ASC LIMIT 4");
    $holStmt->execute();
    $upcomingHolidays = $holStmt->fetchAll();

    if ($role === 'admin' || $role === 'hr') {
        // --- ADMIN / HR DATA ---
        
        // Total & Active Employees
        $empStats = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
            FROM employees")->fetch();
        $stats['total_employees'] = $empStats['total'] ?? 0;
        $stats['active_employees'] = $empStats['active'] ?? 0;

        // Pending Leave Requests
        $leaveStats = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
        $stats['pending_leaves'] = (int)$leaveStats;

        // Today's Attendance breakdown
        $attStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance WHERE attendance_date = ? GROUP BY status");
        $attStmt->execute([$today]);
        $attRows = $attStmt->fetchAll();
        foreach ($attRows as $row) {
            $todayAttendanceBreakdown[$row['status']] = (int)$row['count'];
        }
        $stats['today_present'] = ($todayAttendanceBreakdown['present'] ?? 0) + ($todayAttendanceBreakdown['late'] ?? 0);
        $stats['today_absent'] = max(0, $stats['active_employees'] - $stats['today_present'] - ($todayAttendanceBreakdown['on_leave'] ?? 0));

        // Latest Monthly Payroll
        $payStmt = $pdo->query("SELECT SUM(net_salary) as total_payout, month, year FROM payroll GROUP BY year, month ORDER BY year DESC, month DESC LIMIT 1");
        $latestPay = $payStmt->fetch();
        $stats['monthly_payroll'] = $latestPay['total_payout'] ?? 0;
        $stats['payroll_period'] = $latestPay ? date('F Y', mktime(0, 0, 0, $latestPay['month'], 10, $latestPay['year'])) : date('F Y');

        // Active Job Openings & Candidates
        $stats['open_jobs'] = (int)$pdo->query("SELECT COUNT(*) FROM job_openings WHERE status = 'open'")->fetchColumn();
        $stats['scheduled_interviews'] = (int)$pdo->query("SELECT COUNT(*) FROM interviews WHERE interview_status = 'scheduled'")->fetchColumn();

        // Department Distribution
        $deptStmt = $pdo->query("SELECT d.department_name, COUNT(e.id) as emp_count 
                                 FROM departments d 
                                 LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
                                 GROUP BY d.id, d.department_name ORDER BY emp_count DESC");
        $deptDistribution = $deptStmt->fetchAll();

        // Pending Approvals List
        $pendingStmt = $pdo->query("SELECT lr.*, e.first_name, e.last_name, e.employee_code, lt.leave_name, d.department_name
                                    FROM leave_requests lr
                                    JOIN employees e ON e.id = lr.employee_id
                                    JOIN leave_types lt ON lt.id = lr.leave_type_id
                                    LEFT JOIN departments d ON d.id = e.department_id
                                    WHERE lr.status = 'pending'
                                    ORDER BY lr.applied_at DESC LIMIT 5");
        $pendingLeaves = $pendingStmt->fetchAll();

        // Recent Audit Activities
        $auditStmt = $pdo->query("SELECT a.*, u.name as user_name 
                                  FROM audit_logs a 
                                  LEFT JOIN users u ON u.id = a.user_id 
                                  ORDER BY a.created_at DESC LIMIT 6");
        $recentActivities = $auditStmt->fetchAll();

        // Scheduled Interviews for HR
        $interviewStmt = $pdo->query("SELECT i.*, c.first_name, c.last_name, j.job_title 
                                     FROM interviews i 
                                     JOIN candidates c ON c.id = i.candidate_id 
                                     JOIN job_openings j ON j.id = c.job_id 
                                     WHERE i.interview_status = 'scheduled' 
                                     ORDER BY i.interview_date ASC, i.interview_time ASC LIMIT 4");
        $upcomingInterviews = $interviewStmt->fetchAll();

    } else {
        // --- EMPLOYEE DATA ---
        $empId = $currentUser['employee_id'];

        if ($empId) {
            // Profile Info
            $pStmt = $pdo->prepare("SELECT e.*, d.department_name, ds.designation_name 
                                    FROM employees e 
                                    LEFT JOIN departments d ON d.id = e.department_id 
                                    LEFT JOIN designations ds ON ds.id = e.designation_id 
                                    WHERE e.id = ?");
            $pStmt->execute([$empId]);
            $employeeProfile = $pStmt->fetch();

            // Today's Attendance
            $attTodayStmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $attTodayStmt->execute([$empId, $today]);
            $myAttendanceToday = $attTodayStmt->fetch();

            // Recent 7 Days Attendance History
            $myAttHistStmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 7");
            $myAttHistStmt->execute([$empId]);
            $myRecentAttendance = $myAttHistStmt->fetchAll();

            // Leave Balances
            $ltStmt = $pdo->query("SELECT * FROM leave_types WHERE status = 'active'");
            $allLeaveTypes = $ltStmt->fetchAll();
            foreach ($allLeaveTypes as $lt) {
                $usedStmt = $pdo->prepare("SELECT SUM(total_days) FROM leave_requests WHERE employee_id = ? AND leave_type_id = ? AND status = 'approved' AND YEAR(start_date) = YEAR(CURDATE())");
                $usedStmt->execute([$empId, $lt['id']]);
                $usedDays = (float)$usedStmt->fetchColumn() ?: 0.0;
                $myLeaveBalances[] = [
                    'id'          => $lt['id'],
                    'name'        => $lt['leave_name'],
                    'limit'       => $lt['annual_limit'],
                    'used'        => $usedDays,
                    'remaining'   => max(0, $lt['annual_limit'] - $usedDays)
                ];
            }

            // Latest Payslip
            $paySlipStmt = $pdo->prepare("SELECT * FROM payroll WHERE employee_id = ? ORDER BY year DESC, month DESC LIMIT 1");
            $paySlipStmt->execute([$empId]);
            $myLatestPayroll = $paySlipStmt->fetch();
        }
    }

} catch (Exception $e) {
    $error = "Dashboard loading error: " . $e->getMessage();
}

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Top Welcome Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                Good <?= (date('H') < 12) ? 'morning' : ((date('H') < 17) ? 'afternoon' : 'evening') ?>, 
                <?= htmlspecialchars($currentUser['name']) ?> 👋
            </h2>
            <p class="text-secondary small mb-0">
                <i class="bi bi-calendar3 me-1"></i> <?= date('l, F j, Y') ?> &bull; 
                <span>Enterprise Portal</span>
            </p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <?php if ($role === 'admin' || $role === 'hr'): ?>
                <a href="<?= BASE_URL ?>employees/add.php" class="btn btn-primary">
                    <i class="bi bi-person-plus-fill me-1"></i> Add Employee
                </a>
                <a href="<?= BASE_URL ?>payroll/generate.php" class="btn btn-outline-primary">
                    <i class="bi bi-cash-coin me-1"></i> Run Payroll
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                    <i class="bi bi-calendar-plus me-1"></i> Request Time Off
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($role === 'admin' || $role === 'hr'): ?>
        <!-- ==========================================
             ADMIN / HR DASHBOARD
             ========================================== -->
        <!-- KPI Cards Row -->
        <div class="row g-3 mb-4">
            <!-- Total Employees -->
            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Total Employees</span>
                        <div class="kpi-icon-box" style="background: var(--light-blue); color: var(--primary-blue);">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                    <div class="kpi-value"><?= number_format($stats['total_employees']) ?></div>
                    <div class="kpi-trend text-success">
                        <i class="bi bi-check-circle-fill"></i> <?= $stats['active_employees'] ?> Active in organization
                    </div>
                </div>
            </div>

            <!-- Today's Attendance -->
            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Today's Attendance</span>
                        <div class="kpi-icon-box" style="background: var(--attendance-orange-bg); color: var(--attendance-orange);">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                    </div>
                    <div class="kpi-value"><?= number_format($stats['today_present']) ?></div>
                    <div class="kpi-trend text-secondary">
                        <span><?= $stats['today_absent'] ?> absent</span> &bull; <span><?= $todayAttendanceBreakdown['on_leave'] ?? 0 ?> on leave</span>
                    </div>
                </div>
            </div>

            <!-- Pending Leave Requests -->
            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Pending Leaves</span>
                        <div class="kpi-icon-box" style="background: var(--warning-amber-bg); color: var(--warning-amber);">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="kpi-value"><?= number_format($stats['pending_leaves']) ?></div>
                    <div class="kpi-trend <?= $stats['pending_leaves'] > 0 ? 'text-warning' : 'text-success' ?>">
                        <i class="bi bi-info-circle"></i> Requires management review
                    </div>
                </div>
            </div>

            <!-- Monthly Payroll Total -->
            <div class="col-xl-3 col-sm-6">
                <div class="kpi-card">
                    <div class="kpi-card-header">
                        <span class="kpi-title">Monthly Payroll</span>
                        <div class="kpi-icon-box" style="background: var(--success-green-bg); color: var(--success-green);">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                    <div class="kpi-value"><?= formatCurrency($stats['monthly_payroll']) ?></div>
                    <div class="kpi-trend text-muted">
                        <span>Cycle: <?= htmlspecialchars($stats['payroll_period']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts & Analytics Section -->
        <div class="row g-3 mb-4">
            <!-- Attendance Overview Chart -->
            <div class="col-lg-5">
                <div class="card h-100 mb-0">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-pie-chart text-primary"></i> Today's Attendance Overview</h5>
                        <span class="badge bg-light text-secondary border"><?= date('M d, Y') ?></span>
                    </div>
                    <?php if ($stats['active_employees'] > 0): ?>
                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                            <div style="width: 230px; height: 230px; position: relative;">
                                <canvas id="attendanceDoughnutChart"></canvas>
                            </div>
                            <div class="d-flex justify-content-center gap-3 mt-3 w-100 pt-2 border-top small">
                                <div><span class="badge rounded-circle p-1 me-1" style="background: #16A36A;">&nbsp;</span> Present: <strong><?= $todayAttendanceBreakdown['present'] ?></strong></div>
                                <div><span class="badge rounded-circle p-1 me-1" style="background: #EA8A19;">&nbsp;</span> Late: <strong><?= $todayAttendanceBreakdown['late'] ?></strong></div>
                                <div><span class="badge rounded-circle p-1 me-1" style="background: #2478B8;">&nbsp;</span> Leave: <strong><?= $todayAttendanceBreakdown['on_leave'] ?></strong></div>
                                <div><span class="badge rounded-circle p-1 me-1" style="background: #DC4545;">&nbsp;</span> Absent: <strong><?= $stats['today_absent'] ?></strong></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center py-5">
                            <div class="mb-3 d-flex align-items-center justify-content-center bg-light rounded-circle" style="width: 60px; height: 60px;">
                                <i class="bi bi-calendar2-x fs-2 text-secondary opacity-50"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">No Attendance Records Today</h6>
                            <p class="text-secondary small mb-3" style="max-width: 280px;">No employees have checked in today yet. Once attendance is logged, the breakdown will appear here.</p>
                            <a href="<?= BASE_URL ?>employees/add.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-person-plus me-1"></i> Add Employee
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Department Distribution Bar Chart -->
            <div class="col-lg-7">
                <div class="card h-100 mb-0">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-bar-chart-fill text-primary"></i> Department Headcount Distribution</h5>
                        <a href="<?= BASE_URL ?>departments/index.php" class="small text-primary">Manage Depts &rarr;</a>
                    </div>
                    <?php if ($stats['active_employees'] > 0): ?>
                        <div class="card-body">
                            <div style="height: 250px;">
                                <canvas id="departmentBarChart"></canvas>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center py-5">
                            <div class="mb-3 d-flex align-items-center justify-content-center bg-light rounded-circle" style="width: 60px; height: 60px;">
                                <i class="bi bi-diagram-3 fs-2 text-secondary opacity-50"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">No Department Headcount Data</h6>
                            <p class="text-secondary small mb-3" style="max-width: 320px;">Departments are configured, but no staff members have been assigned yet. Add employees to visualize headcount distribution.</p>
                            <a href="<?= BASE_URL ?>departments/index.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-building me-1"></i> View Departments
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pending Approvals & Activity Feed -->
        <div class="row g-3">
            <!-- Pending Leave Approvals Table -->
            <div class="col-lg-8">
                <div class="card h-100 mb-0">
                    <div class="card-header">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title"><i class="bi bi-hourglass-split text-warning"></i> Pending Leave Approvals</h5>
                            <?php if (count($pendingLeaves) > 0): ?>
                                <span class="badge bg-warning text-dark"><?= count($pendingLeaves) ?> Action Required</span>
                            <?php endif; ?>
                        </div>
                        <a href="<?= BASE_URL ?>leaves/index.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Duration</th>
                                    <th>Reason</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendingLeaves)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-1"></i>
                                            All leave applications have been reviewed!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pendingLeaves as $pl): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($pl['first_name'] . ' ' . $pl['last_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($pl['employee_code']) ?> &bull; <?= htmlspecialchars($pl['department_name'] ?? 'General') ?></small>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($pl['leave_name']) ?></span></td>
                                            <td>
                                                <div class="small fw-medium"><?= formatDate($pl['start_date']) ?> - <?= formatDate($pl['end_date']) ?></div>
                                                <small class="text-muted"><?= $pl['total_days'] ?> day(s)</small>
                                            </td>
                                            <td><div class="small text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($pl['reason']) ?>"><?= htmlspecialchars($pl['reason']) ?></div></td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?= BASE_URL ?>leaves/action.php?id=<?= $pl['id'] ?>&status=approved" class="btn btn-success" title="Approve">
                                                        <i class="bi bi-check-lg"></i>
                                                    </a>
                                                    <a href="<?= BASE_URL ?>leaves/action.php?id=<?= $pl['id'] ?>&status=rejected" class="btn btn-outline-danger" title="Reject">
                                                        <i class="bi bi-x-lg"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quick Upcoming & Activity -->
            <div class="col-lg-4">
                <!-- Upcoming Holidays Card -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-calendar-event text-danger"></i> Upcoming Holidays</h5>
                        <a href="<?= BASE_URL ?>settings/holidays.php" class="small text-primary">Calendar</a>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($upcomingHolidays as $h): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                    <div>
                                        <div class="fw-semibold text-dark small"><?= htmlspecialchars($h['holiday_name']) ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($h['description'] ?? '') ?></div>
                                    </div>
                                    <span class="badge bg-light text-primary border"><?= formatDate($h['holiday_date'], 'M d') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Recent Activities Stream -->
                <div class="card mb-0">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-clock-history text-secondary"></i> Recent System Activity</h5>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($recentActivities)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-activity fs-3 text-secondary opacity-50 mb-1 d-block"></i>
                                <span class="small">No system activity recorded yet.</span>
                            </div>
                        <?php else: ?>
                            <div class="activity-timeline" style="font-size: 0.82rem;">
                                <?php foreach ($recentActivities as $act): ?>
                                    <div class="d-flex mb-3">
                                        <div class="me-2 text-primary"><i class="bi bi-dot fs-3 line-height-1"></i></div>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="text-dark fw-semibold text-truncate"><?= htmlspecialchars($act['action']) ?> <span class="text-muted fw-normal">in <?= htmlspecialchars($act['module']) ?></span></div>
                                            <div class="text-secondary small text-truncate"><?= htmlspecialchars($act['description'] ?? '') ?></div>
                                            <small class="text-muted" style="font-size: 0.7rem;"><?= timeAgo($act['created_at']) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ==========================================
             EMPLOYEE SELF-SERVICE DASHBOARD
             ========================================== -->
        <div class="row g-3 mb-4">
            <!-- Profile Summary & Live Check-in Widget -->
            <div class="col-lg-6">
                <div class="attendance-widget-card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge bg-white text-dark mb-2 px-2 py-1" style="font-size: 0.75rem;">
                                    <i class="bi bi-badge-ad me-1"></i> <?= htmlspecialchars($employeeProfile['employee_code'] ?? 'EMP') ?>
                                </span>
                                <h3 class="fw-bold mb-0 text-white"><?= htmlspecialchars($currentUser['name']) ?></h3>
                                <div class="text-white-50 small"><?= htmlspecialchars($employeeProfile['designation_name'] ?? 'Staff') ?> &bull; <?= htmlspecialchars($employeeProfile['department_name'] ?? 'Operations') ?></div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success px-2 py-1">Active Employee</span>
                            </div>
                        </div>

                        <div class="my-3 py-2 border-top border-bottom border-white-50">
                            <div class="text-white-50 small mb-1">Live Clock (Server Time)</div>
                            <div class="clock-timer text-white live-clock">--:--:-- --</div>
                        </div>
                    </div>

                    <!-- Attendance Action Controls -->
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3 text-white-50 small">
                            <div>
                                Today's Status: 
                                <strong class="text-white">
                                    <?= $myAttendanceToday ? ucfirst($myAttendanceToday['status']) : 'Not Checked In' ?>
                                </strong>
                            </div>
                            <?php if ($myAttendanceToday && $myAttendanceToday['check_in']): ?>
                                <div>Checked In: <strong class="text-white"><?= formatTime($myAttendanceToday['check_in']) ?></strong></div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex gap-2">
                            <?php if (!$myAttendanceToday || empty($myAttendanceToday['check_in'])): ?>
                                <form action="<?= BASE_URL ?>attendance/check_in_out.php" method="POST" class="flex-grow-1">
                                    <input type="hidden" name="action" value="check_in">
                                    <button type="submit" class="btn btn-light w-100 py-2 fw-bold text-primary">
                                        <i class="bi bi-box-arrow-in-right me-1"></i> Check In Now
                                    </button>
                                </form>
                            <?php elseif (empty($myAttendanceToday['check_out'])): ?>
                                <form action="<?= BASE_URL ?>attendance/check_in_out.php" method="POST" class="flex-grow-1">
                                    <input type="hidden" name="action" value="check_out">
                                    <button type="submit" class="btn btn-warning w-100 py-2 fw-bold text-dark">
                                        <i class="bi bi-box-arrow-right me-1"></i> Check Out for Today
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="w-100 p-2 text-center rounded bg-white text-dark small fw-semibold">
                                    <i class="bi bi-check2-all text-success me-1"></i> Completed day: <?= $myAttendanceToday['working_hours'] ?> hrs recorded
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Balances Cards -->
            <div class="col-lg-6">
                <div class="card h-100 mb-0">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-calendar2-heart text-primary"></i> Leave Entitlements</h5>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                            <i class="bi bi-plus-lg"></i> Apply Leave
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($myLeaveBalances as $lb): ?>
                                <div class="col-sm-6">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-semibold text-dark small"><?= htmlspecialchars($lb['name']) ?></span>
                                            <span class="badge bg-white text-primary border"><?= $lb['limit'] ?> Total</span>
                                        </div>
                                        <div class="d-flex align-items-baseline gap-1 my-1">
                                            <span class="fs-4 fw-bold text-primary"><?= $lb['remaining'] ?></span>
                                            <span class="text-muted small">days remaining</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <?php 
                                            $pct = ($lb['limit'] > 0) ? min(100, round(($lb['used'] / $lb['limit']) * 100)) : 0;
                                            ?>
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <div class="text-muted small mt-1" style="font-size: 0.72rem;"><?= $lb['used'] ?> days taken this year</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Quick Sections: Attendance History & Payslip Shortcut -->
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-0">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-clock-history text-primary"></i> Recent Attendance History</h5>
                        <a href="<?= BASE_URL ?>attendance/index.php" class="small text-primary">Full History &rarr;</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Working Hours</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($myRecentAttendance)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">No attendance logs available yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($myRecentAttendance as $ra): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= formatDate($ra['attendance_date']) ?></td>
                                            <td><?= formatTime($ra['check_in']) ?></td>
                                            <td><?= formatTime($ra['check_out']) ?></td>
                                            <td><?= $ra['working_hours'] > 0 ? $ra['working_hours'] . ' hrs' : '-' ?></td>
                                            <td><?= renderStatusBadge($ra['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column: Recent Payslip & Holidays -->
            <div class="col-lg-4">
                <!-- Latest Payslip Card -->
                <?php if ($myLatestPayroll): ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-wallet2 text-success"></i> Latest Payslip</h5>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Paid</span>
                        </div>
                        <div class="card-body">
                            <div class="text-muted small mb-1">Cycle: <?= date('F Y', mktime(0, 0, 0, $myLatestPayroll['month'], 10, $myLatestPayroll['year'])) ?></div>
                            <div class="fs-4 fw-bold text-dark mb-2"><?= formatCurrency($myLatestPayroll['net_salary']) ?></div>
                            <div class="small text-secondary mb-3">Net Take Home Pay deposited to your account.</div>
                            <a href="<?= BASE_URL ?>payroll/payslip.php?id=<?= $myLatestPayroll['id'] ?>" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-file-earmark-pdf me-1"></i> View Itemized Payslip
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Upcoming Holidays Card -->
                <div class="card mb-0">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-calendar-heart text-danger"></i> Upcoming Holidays</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($upcomingHolidays as $h): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                    <span class="small fw-semibold text-dark"><?= htmlspecialchars($h['holiday_name']) ?></span>
                                    <span class="badge bg-light text-primary border"><?= formatDate($h['holiday_date'], 'M d') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Apply for Leave (Accessible by Employees and HR) -->
<div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-labelledby="applyLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="applyLeaveModalLabel">
                    <i class="bi bi-calendar-plus text-primary me-2"></i> Apply for Time Off
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>leaves/apply.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="leave_type_id">Leave Category <span class="required">*</span></label>
                        <select class="form-select" id="leave_type_id" name="leave_type_id" required>
                            <option value="">-- Choose Leave Category --</option>
                            <?php
                            $ltAll = $pdo->query("SELECT * FROM leave_types WHERE status = 'active'")->fetchAll();
                            foreach ($ltAll as $lcat): ?>
                                <option value="<?= $lcat['id'] ?>"><?= htmlspecialchars($lcat['leave_name']) ?> (Limit: <?= $lcat['annual_limit'] ?> days)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="start_date">Start Date <span class="required">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="end_date">End Date <span class="required">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="reason">Reason for Absence <span class="required">*</span></label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Provide a concise description for your manager..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($role === 'admin' || $role === 'hr'): ?>
<!-- Chart.js Scripts for Admin/HR Dashboards -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Attendance Doughnut Chart
    const attCtx = document.getElementById('attendanceDoughnutChart');
    if (attCtx) {
        new Chart(attCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Late', 'On Leave', 'Absent'],
                datasets: [{
                    data: [
                        <?= $todayAttendanceBreakdown['present'] ?>,
                        <?= $todayAttendanceBreakdown['late'] ?>,
                        <?= $todayAttendanceBreakdown['on_leave'] ?>,
                        <?= $stats['today_absent'] ?>
                    ],
                    backgroundColor: ['#16A36A', '#EA8A19', '#2478B8', '#DC4545'],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                cutout: '72%'
            }
        });
    }

    // 2. Department Bar Chart
    const deptCtx = document.getElementById('departmentBarChart');
    if (deptCtx) {
        const deptLabels = <?= json_encode(array_column($deptDistribution, 'department_name')) ?>;
        const deptCounts = <?= json_encode(array_map('intval', array_column($deptDistribution, 'emp_count'))) ?>;

        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: deptLabels,
                datasets: [{
                    label: 'Employees',
                    data: deptCounts,
                    backgroundColor: '#0454D9',
                    borderRadius: 6,
                    barThickness: 24
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 },
                        grid: { color: '#E5EAF1' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

