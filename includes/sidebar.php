<?php
/**
 * Shared Sidebar Component
 * HR Management System
 */

$currentUser = getCurrentUser();
$role = $currentUser['role'] ?? 'employee';
$activePage = $activePage ?? '';
?>
<aside class="app-sidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand">
        <div class="brand-text ms-0">
            <span class="brand-title fs-4 fw-bold" style="letter-spacing: 0.5px;">HRMS</span>
            <span class="brand-subtitle">Enterprise Cloud</span>
        </div>
    </div>

    <!-- Navigation List -->
    <nav class="sidebar-nav">
        <!-- Overview Group -->
        <div class="nav-section-title">Overview</div>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>dashboard.php" class="nav-link <?= ($activePage === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <?php if ($role === 'admin' || $role === 'hr'): ?>
        <!-- People Group -->
        <div class="nav-section-title">People</div>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>employees/index.php" class="nav-link <?= ($activePage === 'employees') ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Employees</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>departments/index.php" class="nav-link <?= ($activePage === 'departments') ? 'active' : '' ?>">
                <i class="bi bi-diagram-3"></i>
                <span>Departments</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>departments/designations.php" class="nav-link <?= ($activePage === 'designations') ? 'active' : '' ?>">
                <i class="bi bi-award"></i>
                <span>Designations</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Workforce Group -->
        <div class="nav-section-title">Workforce</div>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>attendance/index.php" class="nav-link <?= ($activePage === 'attendance') ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Attendance</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>leaves/index.php" class="nav-link <?= ($activePage === 'leaves') ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i>
                <span>Leave Management</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>settings/holidays.php" class="nav-link <?= ($activePage === 'holidays') ? 'active' : '' ?>">
                <i class="bi bi-calendar-heart"></i>
                <span>Holidays</span>
            </a>
        </li>

        <!-- Finance Group -->
        <div class="nav-section-title">Finance</div>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>payroll/index.php" class="nav-link <?= ($activePage === 'payroll') ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i>
                <span><?= ($role === 'employee') ? 'My Payslips' : 'Payroll Management' ?></span>
            </a>
        </li>

        <?php if ($role === 'admin' || $role === 'hr'): ?>
        <!-- Talent Group -->
        <div class="nav-section-title">Talent</div>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>recruitment/index.php" class="nav-link <?= ($activePage === 'job_openings') ? 'active' : '' ?>">
                <i class="bi bi-briefcase"></i>
                <span>Job Openings</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>recruitment/candidates.php" class="nav-link <?= ($activePage === 'candidates') ? 'active' : '' ?>">
                <i class="bi bi-person-lines-fill"></i>
                <span>Candidates</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>recruitment/interviews.php" class="nav-link <?= ($activePage === 'interviews') ? 'active' : '' ?>">
                <i class="bi bi-camera-video"></i>
                <span>Interviews</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>performance/index.php" class="nav-link <?= ($activePage === 'performance') ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Performance Reviews</span>
            </a>
        </li>
        <?php else: ?>
        <!-- Employee Performance View -->
        <li class="nav-item">
            <a href="<?= BASE_URL ?>performance/index.php" class="nav-link <?= ($activePage === 'performance') ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i>
                <span>My Performance</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Administration Group -->
        <div class="nav-section-title">Administration</div>
        <?php if ($role === 'admin' || $role === 'hr'): ?>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>reports/index.php" class="nav-link <?= ($activePage === 'reports') ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reports & Analytics</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
        <li class="nav-item">
            <a href="<?= BASE_URL ?>settings/logs.php" class="nav-link <?= ($activePage === 'audit_logs') ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i>
                <span>Audit Logs</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>settings/index.php" class="nav-link <?= ($activePage === 'settings') ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </li>
    </nav>

    <!-- Sidebar Footer with User Pill -->
    <div class="sidebar-footer">
        <div class="user-quick-pill">
            <div class="user-avatar-sm">
                <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-meta ms-2 overflow-hidden">
                <span class="user-meta-name text-truncate" title="<?= htmlspecialchars($currentUser['name'] ?? '') ?>">
                    <?= htmlspecialchars($currentUser['name'] ?? 'User') ?>
                </span>
                <span class="user-meta-role badge bg-light text-primary border" style="font-size: 0.68rem; padding: 1px 6px; width: fit-content;">
                    <?= htmlspecialchars(strtoupper($role)) ?>
                </span>
            </div>
            <a href="<?= BASE_URL ?>auth/logout.php" class="ms-auto text-secondary text-hover-danger p-1" title="Sign Out">
                <i class="bi bi-box-arrow-right fs-6"></i>
            </a>
        </div>
    </div>
</aside>

