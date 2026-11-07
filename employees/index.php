<?php
/**
 * Employees Directory
 * HR Management System
 */

$pageTitle = 'Employees Directory';
$breadcrumbParent = 'People';
$activePage = 'employees';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$pdo = getDBConnection();

// Filters
$deptFilter = $_GET['department_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');

// Build query
$where = ["1=1"];
$params = [];

if (!empty($deptFilter)) {
    $where[] = "e.department_id = ?";
    $params[] = $deptFilter;
}

if (!empty($statusFilter)) {
    $where[] = "e.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ? OR u.email LIKE ?)";
    $term = "%$searchQuery%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$whereClause = implode(" AND ", $where);

$sql = "SELECT e.*, u.email, u.role as user_role, d.department_name, ds.designation_name 
        FROM employees e
        JOIN users u ON u.id = e.user_id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN designations ds ON ds.id = e.designation_id
        WHERE $whereClause
        ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Fetch departments for filter dropdown
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Top Action Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Employee Directory</h2>
            <p class="text-secondary small mb-0">Manage workforce profiles, employment credentials, and organizational assignments.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="<?= BASE_URL ?>employees/add.php" class="btn btn-primary">
                <i class="bi bi-person-plus-fill me-1"></i> Add New Employee
            </a>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form action="<?= BASE_URL ?>employees/index.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white text-secondary border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Search by name, code, email..." value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="department_id" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= ($deptFilter == $dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Employment Status</option>
                        <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        <option value="terminated" <?= ($statusFilter === 'terminated') ? 'selected' : '' ?>>Terminated</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <?php if (!empty($deptFilter) || !empty($statusFilter) || !empty($searchQuery)): ?>
                        <a href="<?= BASE_URL ?>employees/index.php" class="btn btn-light text-secondary" title="Reset Filters">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Employee Table Card -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="card-title">
                <i class="bi bi-people text-primary"></i> 
                <span>All Employees</span>
                <span class="badge bg-light text-secondary border ms-2"><?= count($employees) ?> Total</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>ID Code</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Joining Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                                No employee profiles matched your filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                            <?= strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <a href="<?= BASE_URL ?>employees/view.php?id=<?= $emp['id'] ?>" class="fw-semibold text-dark text-hover-primary text-decoration-none">
                                                <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                            </a>
                                            <div class="text-muted small"><?= htmlspecialchars($emp['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($emp['employee_code']) ?></span></td>
                                <td><?= htmlspecialchars($emp['department_name'] ?? 'Unassigned') ?></td>
                                <td><span class="text-secondary"><?= htmlspecialchars($emp['designation_name'] ?? 'General Staff') ?></span></td>
                                <td><?= formatDate($emp['joining_date']) ?></td>
                                <td><span class="badge bg-light text-secondary border text-capitalize"><?= str_replace('_', ' ', $emp['employment_type']) ?></span></td>
                                <td><?= renderStatusBadge($emp['status']) ?></td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                                            <li>
                                                <a class="dropdown-item py-2" href="<?= BASE_URL ?>employees/view.php?id=<?= $emp['id'] ?>">
                                                    <i class="bi bi-eye me-2 text-primary"></i> View Full Profile
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2" href="<?= BASE_URL ?>employees/edit.php?id=<?= $emp['id'] ?>">
                                                    <i class="bi bi-pencil me-2 text-warning"></i> Edit Details
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>employees/delete.php?id=<?= $emp['id'] ?>&csrf_token=<?= generateCsrfToken() ?>" data-confirm="Are you sure you want to deactivate or remove this employee? This will affect their portal access.">
                                                    <i class="bi bi-trash3 me-2"></i> Delete / Deactivate
                                                </a>
                                            </li>
                                        </ul>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>

