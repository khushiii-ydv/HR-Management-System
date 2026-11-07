<?php
/**
 * Recruitment: Candidate Pipeline
 * HR Management System
 */

$pageTitle = 'Candidates Pipeline';
$breadcrumbParent = 'Talent';
$activePage = 'candidates';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$pdo = getDBConnection();
$currentUser = getCurrentUser();

$jobFilter   = $_GET['job_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Handle Add Candidate / Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $jobId     = (int)($_POST['job_id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $exp       = (float)($_POST['experience_years'] ?? 0.0);
        $qual      = trim($_POST['qualification'] ?? '');

        if ($jobId && !empty($firstName) && !empty($email)) {
            try {
                $sql = "INSERT INTO candidates 
                    (job_id, first_name, last_name, email, phone, experience_years, qualification, application_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'applied')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$jobId, $firstName, $lastName, $email, $phone, $exp, $qual]);

                logAudit($pdo, $currentUser['id'], 'Add Candidate', 'Recruitment', "Added candidate $firstName $lastName for job ID $jobId");
                setFlashMessage('success', "Candidate $firstName $lastName registered successfully!");
            } catch (Exception $e) {
                setFlashMessage('danger', 'Error registering candidate: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'update_status') {
        $candidateId = (int)($_POST['candidate_id'] ?? 0);
        $newStatus   = $_POST['status'] ?? 'applied';

        if ($candidateId && in_array($newStatus, ['applied', 'shortlisted', 'interview', 'selected', 'rejected', 'hired'])) {
            try {
                $stmt = $pdo->prepare("UPDATE candidates SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $candidateId]);
                logAudit($pdo, $currentUser['id'], 'Update Candidate Status', 'Recruitment', "Advanced candidate #$candidateId to $newStatus");
                setFlashMessage('success', "Candidate status updated to " . ucfirst($newStatus) . ".");
            } catch (Exception $e) {
                setFlashMessage('danger', 'Error updating status: ' . $e->getMessage());
            }
        }
    }
    header("Location: " . BASE_URL . "recruitment/candidates.php" . ($jobFilter ? "?job_id=$jobFilter" : ""));
    exit;
}

// Fetch active job openings for filter & select
$jobs = $pdo->query("SELECT id, job_title FROM job_openings ORDER BY job_title ASC")->fetchAll();

// Build query
$where = ["1=1"];
$params = [];

if (!empty($jobFilter)) {
    $where[] = "c.job_id = ?";
    $params[] = $jobFilter;
}

if (!empty($statusFilter)) {
    $where[] = "c.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(" AND ", $where);

$sql = "SELECT c.*, j.job_title, d.department_name 
        FROM candidates c
        JOIN job_openings j ON j.id = c.job_id
        LEFT JOIN departments d ON d.id = j.department_id
        WHERE $whereClause
        ORDER BY c.application_date DESC, c.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

// Pipeline stages counts
$stageCountsStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM candidates GROUP BY status");
$stageCounts = $stageCountsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Candidate Pipeline</h2>
            <p class="text-secondary small mb-0">Screen incoming talent, advance pipeline stages, and coordinate evaluation.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                <i class="bi bi-person-plus-fill me-1"></i> Register Candidate
            </button>
            <a href="<?= BASE_URL ?>recruitment/interviews.php" class="btn btn-outline-primary">
                <i class="bi bi-camera-video me-1"></i> Interviews Schedule
            </a>
        </div>
    </div>

    <!-- Pipeline Stages Horizontal Progress Strip -->
    <div class="row g-2 mb-4">
        <?php 
        $stages = [
            'applied'     => ['label' => 'Applied', 'color' => '#D99A16', 'bg' => '#FFF8E5', 'icon' => 'bi-inbox'],
            'shortlisted' => ['label' => 'Shortlisted', 'color' => '#7C3AED', 'bg' => '#F2ECFF', 'icon' => 'bi-star'],
            'interview'   => ['label' => 'Interview', 'color' => '#0454D9', 'bg' => '#EAF2FF', 'icon' => 'bi-camera-video'],
            'selected'    => ['label' => 'Selected', 'color' => '#14B8A6', 'bg' => '#E6FFFA', 'icon' => 'bi-check2-circle'],
            'hired'       => ['label' => 'Hired', 'color' => '#16A36A', 'bg' => '#E8F7EF', 'icon' => 'bi-award'],
            'rejected'    => ['label' => 'Rejected', 'color' => '#DC4545', 'bg' => '#FDECEC', 'icon' => 'bi-x-circle']
        ];
        foreach ($stages as $key => $meta): 
            $count = $stageCounts[$key] ?? 0;
        ?>
            <div class="col-md-2 col-4">
                <a href="<?= BASE_URL ?>recruitment/candidates.php?status=<?= $key ?>" class="text-decoration-none">
                    <div class="p-2 text-center rounded-3 border bg-white h-100 <?= ($statusFilter === $key) ? 'border-primary shadow-sm' : '' ?>">
                        <span class="badge mb-1" style="background: <?= $meta['bg'] ?>; color: <?= $meta['color'] ?>;">
                            <i class="bi <?= $meta['icon'] ?> me-1"></i> <?= $meta['label'] ?>
                        </span>
                        <div class="fs-5 fw-bold text-dark"><?= $count ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter Bar -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form action="<?= BASE_URL ?>recruitment/candidates.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <select name="job_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Job Positions</option>
                        <?php foreach ($jobs as $j): ?>
                            <option value="<?= $j['id'] ?>" <?= ($jobFilter == $j['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($j['job_title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Pipeline Stages</option>
                        <option value="applied" <?= ($statusFilter === 'applied') ? 'selected' : '' ?>>Applied</option>
                        <option value="shortlisted" <?= ($statusFilter === 'shortlisted') ? 'selected' : '' ?>>Shortlisted</option>
                        <option value="interview" <?= ($statusFilter === 'interview') ? 'selected' : '' ?>>Interview</option>
                        <option value="selected" <?= ($statusFilter === 'selected') ? 'selected' : '' ?>>Selected</option>
                        <option value="hired" <?= ($statusFilter === 'hired') ? 'selected' : '' ?>>Hired</option>
                        <option value="rejected" <?= ($statusFilter === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" placeholder="Search candidate name or email..." data-table-search="#candidateTable">
                </div>
            </form>
        </div>
    </div>

    <!-- Candidates Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title"><i class="bi bi-people text-primary"></i> Candidate Directory</h5>
            <span class="badge bg-light text-secondary border"><?= count($candidates) ?> Records</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle" id="candidateTable">
                <thead>
                    <tr>
                        <th>Candidate Name</th>
                        <th>Target Position</th>
                        <th>Experience</th>
                        <th>Qualification</th>
                        <th>Applied Date</th>
                        <th>Stage</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($candidates)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No candidate profiles in this view.</td></tr>
                    <?php else: ?>
                        <?php foreach ($candidates as $cand): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($cand['first_name'] . ' ' . $cand['last_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($cand['email']) ?> &bull; <?= htmlspecialchars($cand['phone'] ?? '') ?></small>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark small"><?= htmlspecialchars($cand['job_title']) ?></div>
                                    <small class="text-secondary"><?= htmlspecialchars($cand['department_name'] ?? 'General') ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= $cand['experience_years'] ?> yrs</span></td>
                                <td><span class="small text-secondary"><?= htmlspecialchars($cand['qualification'] ?? 'N/A') ?></span></td>
                                <td><?= formatDate($cand['application_date']) ?></td>
                                <td><?= renderStatusBadge($cand['status']) ?></td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Stage
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li><h6 class="dropdown-header">Move Stage</h6></li>
                                            <?php foreach (['shortlisted', 'interview', 'selected', 'hired', 'rejected'] as $stg): ?>
                                                <li>
                                                    <form action="<?= BASE_URL ?>recruitment/candidates.php<?= $jobFilter ? "?job_id=$jobFilter" : "" ?>" method="POST">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="candidate_id" value="<?= $cand['id'] ?>">
                                                        <input type="hidden" name="status" value="<?= $stg ?>">
                                                        <button type="submit" class="dropdown-item py-1 small text-capitalize"><?= $stg ?></button>
                                                    </form>
                                                </li>
                                            <?php endforeach; ?>
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

<!-- Modal: Register Candidate -->
<div class="modal fade" id="addCandidateModal" tabindex="-1" aria-labelledby="addCandidateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addCandidateModalLabel"><i class="bi bi-person-plus text-primary me-2"></i> Register New Applicant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>recruitment/candidates.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="cand_job">Job Position <span class="required">*</span></label>
                        <select class="form-select" id="cand_job" name="job_id" required>
                            <option value="">-- Select Job Opening --</option>
                            <?php foreach ($jobs as $j): ?>
                                <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['job_title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="cand_first_name">First Name <span class="required">*</span></label>
                            <input type="text" class="form-control" id="cand_first_name" name="first_name" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="cand_last_name">Last Name</label>
                            <input type="text" class="form-control" id="cand_last_name" name="last_name">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="cand_email">Email <span class="required">*</span></label>
                            <input type="email" class="form-control" id="cand_email" name="email" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="cand_phone">Phone</label>
                            <input type="text" class="form-control" id="cand_phone" name="phone">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="cand_exp">Experience (Years)</label>
                            <input type="number" step="0.5" class="form-control" id="cand_exp" name="experience_years" value="2.0">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="cand_qual">Highest Qualification</label>
                            <input type="text" class="form-control" id="cand_qual" name="qualification" placeholder="e.g. B.Tech / MBA">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Candidate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

