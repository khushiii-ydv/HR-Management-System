<?php
/**
 * Recruitment: Job Openings
 * HR Management System
 */

$pageTitle = 'Job Openings';
$breadcrumbParent = 'Talent';
$activePage = 'job_openings';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$pdo = getDBConnection();
$currentUser = getCurrentUser();

// Handle Add / Edit / Close Job Opening
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title       = trim($_POST['job_title'] ?? '');
        $deptId      = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $desigId     = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
        $empType     = $_POST['employment_type'] ?? 'full_time';
        $vacancies   = (int)($_POST['vacancies'] ?? 1);
        $location    = trim($_POST['location'] ?? 'Remote');
        $salaryRange = trim($_POST['salary_range'] ?? '');
        $closingDate = !empty($_POST['closing_date']) ? $_POST['closing_date'] : null;
        $desc        = trim($_POST['job_description'] ?? '');

        if (!empty($title) && !empty($desc)) {
            try {
                $sql = "INSERT INTO job_openings 
                    (department_id, designation_id, job_title, job_description, employment_type, vacancies, location, salary_range, posted_date, closing_date, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'open', ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$deptId, $desigId, $title, $desc, $empType, $vacancies, $location, $salaryRange, $closingDate, $currentUser['id']]);

                logAudit($pdo, $currentUser['id'], 'Post Job Opening', 'Recruitment', "Posted position: $title ($vacancies vacancies)");
                setFlashMessage('success', "Job opening '$title' posted successfully!");
            } catch (Exception $e) {
                setFlashMessage('danger', 'Error creating job opening: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'change_status') {
        $id     = (int)($_POST['job_id'] ?? 0);
        $status = $_POST['status'] ?? 'open';
        if ($id && in_array($status, ['open', 'closed', 'on_hold'])) {
            $stmt = $pdo->prepare("UPDATE job_openings SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            logAudit($pdo, $currentUser['id'], 'Update Job Status', 'Recruitment', "Changed status of job ID $id to $status");
            setFlashMessage('success', "Job status updated to " . ucfirst($status) . ".");
        }
    }
    header("Location: " . BASE_URL . "recruitment/index.php");
    exit;
}

// Fetch departments & designations for modal
$departments = $pdo->query("SELECT * FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();
$designations = $pdo->query("SELECT * FROM designations ORDER BY designation_name ASC")->fetchAll();

// Fetch job openings with applicant counts
$sql = "SELECT j.*, d.department_name, ds.designation_name, COUNT(c.id) as candidate_count 
        FROM job_openings j
        LEFT JOIN departments d ON d.id = j.department_id
        LEFT JOIN designations ds ON ds.id = j.designation_id
        LEFT JOIN candidates c ON c.job_id = j.id
        GROUP BY j.id
        ORDER BY FIELD(j.status, 'open', 'on_hold', 'closed'), j.posted_date DESC";
$jobs = $pdo->query($sql)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Recruitment: Job Openings</h2>
            <p class="text-secondary small mb-0">Publish employment requisitions, track candidate flow, and manage hiring pipelines.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJobModal">
                <i class="bi bi-plus-lg me-1"></i> Post New Job Opening
            </button>
            <a href="<?= BASE_URL ?>recruitment/candidates.php" class="btn btn-outline-primary" style="border-color: var(--recruitment-purple); color: var(--recruitment-purple);">
                <i class="bi bi-people me-1"></i> View Candidates Pipeline
            </a>
        </div>
    </div>

    <!-- Active Jobs Cards Grid -->
    <div class="row g-3 mb-4">
        <?php foreach ($jobs as $job): ?>
            <div class="col-lg-6 col-xl-4">
                <div class="card h-100 shadow-sm border">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge" style="background: var(--recruitment-purple-bg); color: var(--recruitment-purple); border: 1px solid rgba(124,58,237,0.2);">
                                <?= htmlspecialchars($job['department_name'] ?? 'General') ?>
                            </span>
                            <?= renderStatusBadge($job['status']) ?>
                        </div>

                        <h5 class="fw-bold text-dark mb-1">
                            <?= htmlspecialchars($job['job_title']) ?>
                        </h5>
                        <div class="text-muted small mb-3">
                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($job['location'] ?? 'Remote') ?> &bull; 
                            <span class="text-capitalize"><?= str_replace('_', ' ', $job['employment_type']) ?></span>
                        </div>

                        <p class="text-secondary small mb-3" style="min-height: 40px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= htmlspecialchars($job['job_description']) ?>
                        </p>

                        <div class="p-2 bg-light rounded-2 small d-flex justify-content-between mb-3">
                            <span><i class="bi bi-person-workspace text-primary me-1"></i> Vacancies: <strong><?= $job['vacancies'] ?></strong></span>
                            <span><i class="bi bi-cash-stack text-success me-1"></i> <?= htmlspecialchars($job['salary_range'] ?: 'Competitive') ?></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <a href="<?= BASE_URL ?>recruitment/candidates.php?job_id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-people-fill me-1"></i> <?= $job['candidate_count'] ?> Applicants
                            </a>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    Status
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li>
                                        <form action="<?= BASE_URL ?>recruitment/index.php" method="POST">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                            <input type="hidden" name="status" value="open">
                                            <button type="submit" class="dropdown-item py-2 text-success"><i class="bi bi-door-open me-2"></i> Mark Open</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="<?= BASE_URL ?>recruitment/index.php" method="POST">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                            <input type="hidden" name="status" value="on_hold">
                                            <button type="submit" class="dropdown-item py-2 text-warning"><i class="bi bi-pause-circle me-2"></i> Put On Hold</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="<?= BASE_URL ?>recruitment/index.php" method="POST">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                            <input type="hidden" name="status" value="closed">
                                            <button type="submit" class="dropdown-item py-2 text-secondary"><i class="bi bi-door-closed me-2"></i> Close Position</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal: Post Job Opening -->
<div class="modal fade" id="addJobModal" tabindex="-1" aria-labelledby="addJobModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addJobModalLabel">
                    <i class="bi bi-briefcase text-primary me-2"></i> Post New Job Requisition
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>recruitment/index.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label" for="job_title">Job Title <span class="required">*</span></label>
                            <input type="text" class="form-control" id="job_title" name="job_title" placeholder="e.g. Senior Frontend Engineer (React/Vue)" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="job_dept">Department</label>
                            <select class="form-select" id="job_dept" name="department_id">
                                <option value="">-- Choose Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="job_type">Employment Contract</label>
                            <select class="form-select" id="job_type" name="employment_type">
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="job_vacancies">Number of Vacancies</label>
                            <input type="number" class="form-control" id="job_vacancies" name="vacancies" min="1" value="1">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="job_location">Location / Workplace</label>
                            <input type="text" class="form-control" id="job_location" name="location" placeholder="e.g. San Francisco, CA / Remote" value="Remote">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="job_salary">Offered Salary Range</label>
                            <input type="text" class="form-control" id="job_salary" name="salary_range" placeholder="e.g. $80k - $110k">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label" for="job_desc">Job Description & Core Requirements <span class="required">*</span></label>
                            <textarea class="form-control" id="job_desc" name="job_description" rows="4" placeholder="Detail the key technical skills, requirements, and responsibilities..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Publish Job Opening</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

