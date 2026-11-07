<?php
/**
 * Edit Employee Details
 * HR Management System
 */

$pageTitle = 'Edit Employee';
$breadcrumbParent = 'Employees';
$activePage = 'employees';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$pdo = getDBConnection();
$currentUser = getCurrentUser();
$empId = (int)($_GET['id'] ?? 0);

if (!$empId) {
    header("Location: " . BASE_URL . "employees/index.php");
    exit;
}

// Fetch employee
$stmt = $pdo->prepare("SELECT e.*, u.name as user_name, u.email, u.role as user_role 
                       FROM employees e 
                       JOIN users u ON u.id = e.user_id 
                       WHERE e.id = ?");
$stmt->execute([$empId]);
$emp = $stmt->fetch();

if (!$emp) {
    setFlashMessage('danger', 'Employee record not found.');
    header("Location: " . BASE_URL . "employees/index.php");
    exit;
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll();
$designations = $pdo->query("SELECT * FROM designations ORDER BY designation_name ASC")->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName       = trim($_POST['first_name'] ?? '');
    $lastName        = trim($_POST['last_name'] ?? '');
    $gender          = $_POST['gender'] ?? 'male';
    $dob             = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $phone           = trim($_POST['phone'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $emergencyContact= trim($_POST['emergency_contact'] ?? '');
    $departmentId    = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $designationId   = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
    $joiningDate     = $_POST['joining_date'] ?? $emp['joining_date'];
    $employmentType  = $_POST['employment_type'] ?? 'full_time';
    $basicSalary     = !empty($_POST['basic_salary']) ? (float)$_POST['basic_salary'] : 0.00;
    $status          = $_POST['status'] ?? 'active';

    if (empty($firstName) || empty($lastName)) {
        $error = 'First name and last name are required.';
    } else {
        try {
            $pdo->beginTransaction();

            // Update user's name
            $updUser = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            $updUser->execute([$firstName . ' ' . $lastName, $emp['user_id']]);

            // Update employee record
            $updEmp = $pdo->prepare("UPDATE employees SET 
                first_name = ?, last_name = ?, gender = ?, date_of_birth = ?, phone = ?, 
                address = ?, emergency_contact = ?, department_id = ?, designation_id = ?, 
                joining_date = ?, employment_type = ?, basic_salary = ?, status = ? 
                WHERE id = ?");
            $updEmp->execute([
                $firstName, $lastName, $gender, $dob, $phone,
                $address, $emergencyContact, $departmentId, $designationId,
                $joiningDate, $employmentType, $basicSalary, $status, $empId
            ]);

            $pdo->commit();

            logAudit($pdo, $currentUser['id'], 'Update Employee', 'Employees', "Updated profile for employee: $firstName $lastName ({$emp['employee_code']})");
            setFlashMessage('success', "Employee profile updated successfully.");

            header("Location: " . BASE_URL . "employees/view.php?id=" . $empId);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Edit Employee Profile</h2>
            <p class="text-secondary small mb-0">Update information for <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= htmlspecialchars($emp['employee_code']) ?>)</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>employees/view.php?id=<?= $empId ?>" class="btn btn-outline-secondary">
                <i class="bi bi-eye me-1"></i> View Profile
            </a>
            <a href="<?= BASE_URL ?>employees/index.php" class="btn btn-light border">
                Directory
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>employees/edit.php?id=<?= $empId ?>" method="POST">
        <div class="row g-4">
            <!-- Left Column -->
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-person text-primary"></i> Personal Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required value="<?= htmlspecialchars($emp['first_name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required value="<?= htmlspecialchars($emp['last_name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="gender">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="male" <?= $emp['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= $emp['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= $emp['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="date_of_birth">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($emp['date_of_birth'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($emp['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="emergency_contact">Emergency Contact</label>
                                <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" value="<?= htmlspecialchars($emp['emergency_contact'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($emp['address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-briefcase text-primary"></i> Employment Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Employee Code</label>
                                <input type="text" class="form-control bg-light font-monospace" value="<?= htmlspecialchars($emp['employee_code']) ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="department_id">Department</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= ($emp['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="designation_id">Designation</label>
                                <select class="form-select" id="designation_id" name="designation_id">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach ($designations as $ds): ?>
                                        <option value="<?= $ds['id'] ?>" <?= ($emp['designation_id'] == $ds['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ds['designation_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="joining_date">Joining Date</label>
                                <input type="date" class="form-control" id="joining_date" name="joining_date" value="<?= htmlspecialchars($emp['joining_date']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="employment_type">Type</label>
                                <select class="form-select" id="employment_type" name="employment_type">
                                    <option value="full_time" <?= $emp['employment_type'] === 'full_time' ? 'selected' : '' ?>>Full Time</option>
                                    <option value="part_time" <?= $emp['employment_type'] === 'part_time' ? 'selected' : '' ?>>Part Time</option>
                                    <option value="contract" <?= $emp['employment_type'] === 'contract' ? 'selected' : '' ?>>Contract</option>
                                    <option value="intern" <?= $emp['employment_type'] === 'intern' ? 'selected' : '' ?>>Intern</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="basic_salary">Basic Salary (<?= htmlspecialchars($_SESSION['company_currency'] ?? 'INR') ?>)</label>
                                <input type="number" step="0.01" class="form-control" id="basic_salary" name="basic_salary" value="<?= htmlspecialchars($emp['basic_salary']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="status">Account Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?= $emp['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $emp['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="terminated" <?= $emp['status'] === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1 py-2 fw-semibold">
                        <i class="bi bi-save me-1"></i> Update Changes
                    </button>
                    <a href="<?= BASE_URL ?>employees/view.php?id=<?= $empId ?>" class="btn btn-light border py-2">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

