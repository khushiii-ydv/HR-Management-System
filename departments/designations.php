<?php
/**
 * Designation Management
 * HR Management System
 */

$pageTitle = 'Designations';
$breadcrumbParent = 'People';
$activePage = 'designations';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$pdo = getDBConnection();
$currentUser = getCurrentUser();

$deptFilter = $_GET['dept_id'] ?? '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $deptId = (int)($_POST['department_id'] ?? 0);
        $name   = trim($_POST['designation_name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');

        if ($deptId && !empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO designations (department_id, designation_name, description) VALUES (?, ?, ?)");
                $stmt->execute([$deptId, $name, $desc]);
                logAudit($pdo, $currentUser['id'], 'Add Designation', 'Departments', "Added designation '$name'");
                setFlashMessage('success', "Designation '$name' created successfully!");
            } catch (Exception $e) {
                setFlashMessage('danger', 'Error adding designation: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'edit') {
        $id     = (int)($_POST['designation_id'] ?? 0);
        $deptId = (int)($_POST['department_id'] ?? 0);
        $name   = trim($_POST['designation_name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');

        if ($id && $deptId && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE designations SET department_id = ?, designation_name = ?, description = ? WHERE id = ?");
                $stmt->execute([$deptId, $name, $desc, $id]);
                logAudit($pdo, $currentUser['id'], 'Edit Designation', 'Departments', "Updated designation ID $id");
                setFlashMessage('success', "Designation updated successfully.");
            } catch (Exception $e) {
                setFlashMessage('danger', 'Error updating designation: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['designation_id'] ?? 0);
        if ($id) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE designation_id = ?");
            $chk->execute([$id]);
            $count = (int)$chk->fetchColumn();

            if ($count > 0) {
                setFlashMessage('danger', "Cannot delete designation: $count employee(s) hold this title.");
            } else {
                try {
                    $pdo->prepare("DELETE FROM designations WHERE id = ?")->execute([$id]);
                    logAudit($pdo, $currentUser['id'], 'Delete Designation', 'Departments', "Deleted designation ID $id");
                    setFlashMessage('success', 'Designation deleted successfully.');
                } catch (Exception $e) {
                    setFlashMessage('danger', 'Error deleting designation: ' . $e->getMessage());
                }
            }
        }
    }
    header("Location: " . BASE_URL . "departments/designations.php" . ($deptFilter ? "?dept_id=$deptFilter" : ""));
    exit;
}

// Fetch departments for filter & select
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();

// Fetch designations
$sql = "SELECT ds.*, d.department_name, COUNT(e.id) as emp_count 
        FROM designations ds
        JOIN departments d ON d.id = ds.department_id
        LEFT JOIN employees e ON e.designation_id = ds.id AND e.status = 'active' ";

if (!empty($deptFilter)) {
    $sql .= " WHERE ds.department_id = " . (int)$deptFilter;
}

$sql .= " GROUP BY ds.id ORDER BY d.department_name ASC, ds.designation_name ASC";
$designations = $pdo->query($sql)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Designations & Titles</h2>
            <p class="text-secondary small mb-0">Define organizational hierarchy, job roles, and career ladders by department.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDesigModal">
                <i class="bi bi-plus-lg me-1"></i> Add Designation
            </button>
            <a href="<?= BASE_URL ?>departments/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Departments
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form action="<?= BASE_URL ?>departments/designations.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <select name="dept_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($deptFilter == $d['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" placeholder="Live search titles..." data-table-search="#desigTable">
                </div>
                <div class="col-md-3">
                    <?php if (!empty($deptFilter)): ?>
                        <a href="<?= BASE_URL ?>departments/designations.php" class="btn btn-light border w-100">
                            Clear Filter
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Designation Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title"><i class="bi bi-award text-primary"></i> Job Titles</h5>
            <span class="badge bg-light text-secondary border"><?= count($designations) ?> Records</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle" id="desigTable">
                <thead>
                    <tr>
                        <th>Designation Title</th>
                        <th>Department</th>
                        <th>Description</th>
                        <th>Active Employees</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($designations)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No designations found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($designations as $ds): ?>
                            <tr>
                                <td class="fw-semibold text-dark fs-6"><?= htmlspecialchars($ds['designation_name']) ?></td>
                                <td>
                                    <span class="badge" style="background: var(--light-blue); color: var(--primary-blue); border: 1px solid rgba(4,84,217,0.15);">
                                        <i class="bi bi-diagram-3 me-1"></i><?= htmlspecialchars($ds['department_name']) ?>
                                    </span>
                                </td>
                                <td><span class="text-secondary small"><?= htmlspecialchars($ds['description'] ?: 'No role notes') ?></span></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= $ds['emp_count'] ?> Active</span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary me-1" onclick='editDesig(<?= json_encode($ds) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="<?= BASE_URL ?>departments/designations.php<?= $deptFilter ? "?dept_id=$deptFilter" : "" ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this designation?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="designation_id" value="<?= $ds['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" <?= $ds['emp_count'] > 0 ? 'disabled title="Cannot delete designation held by employees"' : '' ?>>
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

<!-- Modal: Add Designation -->
<div class="modal fade" id="addDesigModal" tabindex="-1" aria-labelledby="addDesigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addDesigModalLabel"><i class="bi bi-plus-circle text-primary me-2"></i> Add Designation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>departments/designations.php<?= $deptFilter ? "?dept_id=$deptFilter" : "" ?>" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="add_d_dept">Department <span class="required">*</span></label>
                        <select class="form-select" id="add_d_dept" name="department_id" required>
                            <option value="">-- Choose Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($deptFilter == $d['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="add_d_name">Designation Title <span class="required">*</span></label>
                        <input type="text" class="form-control" id="add_d_name" name="designation_name" placeholder="e.g. Senior Security Engineer" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="add_d_desc">Role Description</label>
                        <textarea class="form-control" id="add_d_desc" name="description" rows="3" placeholder="Key responsibilities and qualifications..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Designation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Designation -->
<div class="modal fade" id="editDesigModal" tabindex="-1" aria-labelledby="editDesigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editDesigModalLabel"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Designation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>departments/designations.php<?= $deptFilter ? "?dept_id=$deptFilter" : "" ?>" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="designation_id" id="edit_desig_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_d_dept">Department <span class="required">*</span></label>
                        <select class="form-select" id="edit_d_dept" name="department_id" required>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_d_name">Designation Title <span class="required">*</span></label>
                        <input type="text" class="form-control" id="edit_d_name" name="designation_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_d_desc">Role Description</label>
                        <textarea class="form-control" id="edit_d_desc" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editDesig(ds) {
    document.getElementById('edit_desig_id').value = ds.id;
    document.getElementById('edit_d_dept').value = ds.department_id;
    document.getElementById('edit_d_name').value = ds.designation_name;
    document.getElementById('edit_d_desc').value = ds.description || '';
    new bootstrap.Modal(document.getElementById('editDesigModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

