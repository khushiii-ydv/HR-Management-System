<?php
/**
 * Add New Employee
 * HR Management System
 */

$pageTitle = 'Add New Employee';
$breadcrumbParent = 'Employees';
$activePage = 'employees';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$pdo = getDBConnection();
$error = '';
$currentUser = getCurrentUser();

// Auto-suggest next employee code
$maxCodeStmt = $pdo->query("SELECT employee_code FROM employees ORDER BY id DESC LIMIT 1");
$lastCode = $maxCodeStmt->fetchColumn();
$nextCode = 'EMP-1007';
if ($lastCode && preg_match('/EMP-(\d+)/', $lastCode, $m)) {
    $nextCode = 'EMP-' . str_pad((int)$m[1] + 1, 4, '0', STR_PAD_LEFT);
}

// Fetch departments and designations
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();
$designations = $pdo->query("SELECT * FROM designations ORDER BY designation_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName       = trim($_POST['first_name'] ?? '');
    $lastName        = trim($_POST['last_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? 'emp123';
    $role            = $_POST['role'] ?? 'employee';
    $employeeCode    = trim($_POST['employee_code'] ?? $nextCode);
    $gender          = $_POST['gender'] ?? 'male';
    $dob             = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $phone           = trim($_POST['phone'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $emergencyContact= trim($_POST['emergency_contact'] ?? '');
    $departmentId    = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $designationId   = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
    $joiningDate     = $_POST['joining_date'] ?? date('Y-m-d');
    $employmentType  = $_POST['employment_type'] ?? 'full_time';
    $basicSalary     = !empty($_POST['basic_salary']) ? (float)$_POST['basic_salary'] : 0.00;

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($employeeCode) || empty($joiningDate)) {
        $error = 'Please fill out all required fields marked with an asterisk (*).';
    } else {
        try {
            // Check if email already exists
            $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                throw new Exception("The email address '$email' is already registered in the system.");
            }

            // Check if employee code already exists
            $checkCode = $pdo->prepare("SELECT id FROM employees WHERE employee_code = ?");
            $checkCode->execute([$employeeCode]);
            if ($checkCode->fetch()) {
                throw new Exception("The employee code '$employeeCode' is already assigned to another staff member.");
            }

            $pdo->beginTransaction();

            // 1. Insert into users
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $userStmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
            $userStmt->execute([$firstName . ' ' . $lastName, $email, $hashedPassword, $role]);
            $newUserId = $pdo->lastInsertId();

            // 2. Insert into employees
            $empStmt = $pdo->prepare("INSERT INTO employees 
                (user_id, employee_code, first_name, last_name, gender, date_of_birth, phone, address, 
                 department_id, designation_id, joining_date, employment_type, basic_salary, emergency_contact, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            
            $empStmt->execute([
                $newUserId, $employeeCode, $firstName, $lastName, $gender, $dob, $phone, $address,
                $departmentId, $designationId, $joiningDate, $employmentType, $basicSalary, $emergencyContact
            ]);
            $newEmpId = $pdo->lastInsertId();

            // 3. Document attachment if provided
            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['document']['tmp_name'];
                $fileName = basename($_FILES['document']['name']);
                $cleanFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fileName);
                $uploadPath = __DIR__ . '/../uploads/documents/' . $cleanFileName;

                if (move_uploaded_file($fileTmp, $uploadPath)) {
                    $docStmt = $pdo->prepare("INSERT INTO employee_documents (employee_id, document_name, document_type, file_path, uploaded_by) VALUES (?, ?, 'Contract', ?, ?)");
                    $docStmt->execute([$newEmpId, $fileName, 'uploads/documents/' . $cleanFileName, $currentUser['id']]);
                }
            }

            $pdo->commit();

            logAudit($pdo, $currentUser['id'], 'Create Employee', 'Employees', "Added new employee: $firstName $lastName ($employeeCode)");
            setFlashMessage('success', "Employee $firstName $lastName ($employeeCode) created successfully!");

            header("Location: " . BASE_URL . "employees/view.php?id=" . $newEmpId);
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
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Add New Employee</h2>
            <p class="text-secondary small mb-0">Create personal record, issue portal credentials, and assign job designations.</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>employees/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Directory
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>employees/add.php" method="POST" enctype="multipart/form-data">
        <div class="row g-4">
            <!-- Left Column: Personal & Account Details -->
            <div class="col-lg-7">
                <!-- Section 1: Personal Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-person-vcard text-primary"></i> Personal Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" placeholder="e.g. John">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" placeholder="e.g. Doe">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="gender">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="date_of_birth">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="emergency_contact">Emergency Contact Phone</label>
                                <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" placeholder="+1 (555) 999-9999" value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address">Residential Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" placeholder="Street address, City, State, Postal code"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: User Account & Authentication -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-shield-lock text-primary"></i> Portal Account Credentials</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="email">Work Email <span class="required">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="john.doe@company.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password">Initial Password <span class="required">*</span></label>
                                <input type="text" class="form-control" id="password" name="password" required value="<?= htmlspecialchars($_POST['password'] ?? 'emp123') ?>">
                                <small class="text-muted" style="font-size: 0.72rem;">User can change their password anytime after signing in.</small>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label" for="role">System Role <span class="required">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="employee" selected>Employee (Standard Self-Service Access)</option>
                                    <option value="hr">HR Specialist (Workforce & Recruitment Access)</option>
                                    <option value="admin">Administrator (Full System & Financial Controls)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Employment & Compensation -->
            <div class="col-lg-5">
                <!-- Section 3: Employment Placement -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-briefcase text-primary"></i> Job & Placement</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="employee_code">Employee Code / ID <span class="required">*</span></label>
                                <input type="text" class="form-control font-monospace" id="employee_code" name="employee_code" required value="<?= htmlspecialchars($_POST['employee_code'] ?? $nextCode) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="department_id">Department</label>
                                <select class="form-select" id="department_id" name="department_id" onchange="filterDesignations(this.value)">
                                    <option value="">-- Select Department --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="designation_id">Designation / Job Title</label>
                                <select class="form-select" id="designation_id" name="designation_id">
                                    <option value="">-- Select Designation --</option>
                                    <?php foreach ($designations as $ds): ?>
                                        <option value="<?= $ds['id'] ?>" data-dept="<?= $ds['department_id'] ?>" <?= (isset($_POST['designation_id']) && $_POST['designation_id'] == $ds['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ds['designation_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="joining_date">Date of Joining <span class="required">*</span></label>
                                <input type="date" class="form-control" id="joining_date" name="joining_date" required value="<?= htmlspecialchars($_POST['joining_date'] ?? date('Y-m-d')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="employment_type">Employment Contract</label>
                                <select class="form-select" id="employment_type" name="employment_type">
                                    <option value="full_time">Full Time</option>
                                    <option value="part_time">Part Time</option>
                                    <option value="contract">Contract</option>
                                    <option value="intern">Intern</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Compensation & Documents -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-cash-stack text-success"></i> Compensation & Documents</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="basic_salary">Monthly Basic Salary (<?= htmlspecialchars($_SESSION['company_currency'] ?? 'INR') ?>)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><?= ($_SESSION['company_currency'] ?? 'INR') === 'INR' ? '₹' : '$' ?></span>
                                <input type="number" step="0.01" class="form-control" id="basic_salary" name="basic_salary" placeholder="0.00" value="<?= htmlspecialchars($_POST['basic_salary'] ?? '75000.00') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="document">Employment Contract / Offer Letter</label>
                            <input type="file" class="form-control" id="document" name="document" accept=".pdf,.doc,.docx,.png,.jpg">
                            <small class="text-muted" style="font-size: 0.72rem;">Optional. PDF or Word document up to 10MB.</small>
                        </div>
                    </div>
                </div>

                <!-- Submit Button Bar -->
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1 py-2 fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i> Save & Onboard Employee
                    </button>
                    <a href="<?= BASE_URL ?>employees/index.php" class="btn btn-light border py-2">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function filterDesignations(deptId) {
    const desigSelect = document.getElementById('designation_id');
    const options = desigSelect.querySelectorAll('option');

    options.forEach(opt => {
        if (!opt.value) return; // Skip placeholder
        const optDept = opt.getAttribute('data-dept');
        if (!deptId || optDept === deptId) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });

    if (deptId) {
        // If current selection is hidden, reset to blank
        const selected = desigSelect.selectedOptions[0];
        if (selected && selected.getAttribute('data-dept') !== deptId) {
            desigSelect.value = '';
        }
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

