<?php
/**
 * Department Management
 * HR Management System
 */

$pageTitle = 'Department Management';
$breadcrumbParent = 'People';
$activePage = 'departments';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$pdo = getDBConnection();
$currentUser = getCurrentUser();

// Handle Add / Edit / Delete Department
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['department_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO departments (department_name, description, status) VALUES (?, ?, 'active')");
                $stmt->execute([$name, $desc]);
                logAudit($pdo, $currentUser['id'], 'Add Department', 'Departments', "Created department: $name");
                setFlashMessage('success', "Department '$name' added successfully!");
            } catch (Exception $e) {
                setFlashMessage('danger', 'Error adding department: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['dept_id'] ?? 0);
        $name = trim($_POST['department_name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($id && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE departments SET department_name = ?, description = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $desc, $status, $id]);
                logAudit($pdo, $currentUser['id'], 'Edit Department', 'Departments', "Updated department ID $id: $name");
                setFlashMessage('success', "Department '$name' updated successfully!");
            } catch (Exception $e) {
                setFlashMessage('danger', 'Error updating department: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['dept_id'] ?? 0);
        if ($id) {
            // Check if employees or designations exist
            $empCount = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ?");
            $empCount->execute([$id]);
            $count = (int)$empCount->fetchColumn();

            if ($count > 0) {
                setFlashMessage('danger', "Cannot delete department: $count employee(s) are currently assigned to it. Reassign employees first.");
            } else {
                try {
                    $pdo->prepare("DELETE FROM designations WHERE department_id = ?")->execute([$id]);
                    $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
                    logAudit($pdo, $currentUser['id'], 'Delete Department', 'Departments', "Deleted department ID: $id");
                    setFlashMessage('success', 'Department deleted successfully.');
                } catch (Exception $e) {
                    setFlashMessage('danger', 'Error deleting department: ' . $e->getMessage());
                }
            }
        }
    }
    header("Location: " . BASE_URL . "departments/index.php");
    exit;
}

// Fetch all departments with employee counts and designation counts
$sql = "SELECT d.*, 
        COUNT(DISTINCT e.id) as employee_count,
        COUNT(DISTINCT ds.id) as designation_count
        FROM departments d
        LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
        LEFT JOIN designations ds ON ds.department_id = d.id
        GROUP BY d.id
        ORDER BY d.department_name ASC";
$departments = $pdo->query($sql)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Departments</h2>
            <p class="text-secondary small mb-0">Organize organizational units, branches, and monitor departmental headcounts.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                <i class="bi bi-plus-lg me-1"></i> Add Department
            </button>
            <a href="<?= BASE_URL ?>departments/designations.php" class="btn btn-outline-primary">
                <i class="bi bi-award me-1"></i> Manage Designations
            </a>
        </div>
    </div>

    <!-- Department Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title"><i class="bi bi-diagram-3 text-primary"></i> Company Departments</h5>
            <span class="badge bg-light text-secondary border"><?= count($departments) ?> Active Units</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Department Name</th>
                        <th>Description</th>
                        <th>Designations</th>
                        <th>Active Headcount</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No departments created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($dept['department_name']) ?></div>
                                    <small class="text-muted">Created <?= formatDate($dept['created_at']) ?></small>
                                </td>
                                <td>
                                    <span class="text-secondary small"><?= htmlspecialchars($dept['description'] ?: 'No description provided') ?></span>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>departments/designations.php?dept_id=<?= $dept['id'] ?>" class="badge bg-light text-primary border text-decoration-none">
                                        <?= $dept['designation_count'] ?> Designations &rarr;
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-people text-secondary"></i>
                                        <span class="fw-semibold text-dark"><?= $dept['employee_count'] ?></span>
                                        <small class="text-muted">members</small>
                                    </div>
                                </td>
                                <td><?= renderStatusBadge($dept['status']) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary me-1" 
                                            onclick='editDept(<?= json_encode($dept) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="<?= BASE_URL ?>departments/index.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this department? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="dept_id" value="<?= $dept['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" <?= $dept['employee_count'] > 0 ? 'disabled title="Cannot delete department with active employees"' : '' ?>>
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Department -->
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-labelledby="addDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addDeptModalLabel"><i class="bi bi-plus-circle text-primary me-2"></i> Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>departments/index.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="add_dept_name">Department Name <span class="required">*</span></label>
                        <input type="text" class="form-control" id="add_dept_name" name="department_name" placeholder="e.g. Quality Assurance" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="add_dept_desc">Department Description</label>
                        <textarea class="form-control" id="add_dept_desc" name="description" rows="3" placeholder="Brief scope and functional role..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Department -->
<div class="modal fade" id="editDeptModal" tabindex="-1" aria-labelledby="editDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editDeptModalLabel"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>departments/index.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="dept_id" id="edit_dept_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_dept_name">Department Name <span class="required">*</span></label>
                        <input type="text" class="form-control" id="edit_dept_name" name="department_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_dept_desc">Description</label>
                        <textarea class="form-control" id="edit_dept_desc" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_dept_status">Status</label>
                        <select class="form-select" id="edit_dept_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editDept(dept) {
    document.getElementById('edit_dept_id').value = dept.id;
    document.getElementById('edit_dept_name').value = dept.department_name;
    document.getElementById('edit_dept_desc').value = dept.description || '';
    document.getElementById('edit_dept_status').value = dept.status;
    new bootstrap.Modal(document.getElementById('editDeptModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

