<?php
/**
 * Add / Conduct Employee Performance Appraisal
 * HR Management System
 */

$pageTitle = 'Conduct Performance Review';
$breadcrumbParent = 'Talent';
$activePage = 'performance';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$pdo = getDBConnection();
$currentUser = getCurrentUser();
$preSelectedEmpId = (int)($_GET['employee_id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empId      = (int)($_POST['employee_id'] ?? 0);
    $startDate  = $_POST['review_period_start'] ?? '';
    $endDate    = $_POST['review_period_end'] ?? '';
    $rating     = (float)($_POST['rating'] ?? 4.0);
    $strengths  = trim($_POST['strengths'] ?? '');
    $improvements = trim($_POST['improvements'] ?? '');
    $comments   = trim($_POST['comments'] ?? '');
    $reviewDate = $_POST['review_date'] ?? date('Y-m-d');

    if (!$empId || empty($startDate) || empty($endDate) || !$rating) {
        $error = 'Please complete all required appraisal evaluation fields.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO performance_reviews 
                (employee_id, reviewer_id, review_period_start, review_period_end, rating, strengths, improvements, comments, review_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$empId, $currentUser['id'], $startDate, $endDate, $rating, $strengths, $improvements, $comments, $reviewDate]);

            // Notify Employee
            $uStmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM employees WHERE id = ?");
            $uStmt->execute([$empId]);
            $empInfo = $uStmt->fetch();

            if ($empInfo) {
                createNotification($pdo, $empInfo['user_id'], 'Performance Review Completed', "Your evaluation score of $rating/5.0 has been finalized.", 'performance');
            }

            logAudit($pdo, $currentUser['id'], 'Performance Review', 'Performance', "Completed review for employee #$empId (Score: $rating)");
            setFlashMessage('success', "Performance appraisal successfully registered!");
            header("Location: " . BASE_URL . "performance/index.php");
            exit;
        } catch (Exception $e) {
            $error = 'Error saving review: ' . $e->getMessage();
        }
    }
}

// Fetch employees
$employees = $pdo->query("SELECT e.id, e.first_name, e.last_name, e.employee_code, d.department_name, ds.designation_name 
                         FROM employees e 
                         LEFT JOIN departments d ON d.id = e.department_id 
                         LEFT JOIN designations ds ON ds.id = e.designation_id 
                         WHERE e.status = 'active' ORDER BY e.first_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">New Performance Evaluation</h2>
            <p class="text-secondary small mb-0">Record formal assessment metrics, development goals, and key feedback.</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>performance/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Reviews
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border mx-auto" style="max-width: 800px;">
        <div class="card-header">
            <h5 class="card-title"><i class="bi bi-star-fill text-warning"></i> Evaluation Scorecard</h5>
        </div>
        <div class="card-body p-4">
            <form action="<?= BASE_URL ?>performance/add.php" method="POST">
                <div class="mb-3">
                    <label class="form-label" for="emp_select">Employee <span class="required">*</span></label>
                    <select class="form-select" id="emp_select" name="employee_id" required>
                        <option value="">-- Choose Employee to Review --</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= ($preSelectedEmpId == $e['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?> (<?= htmlspecialchars($e['employee_code']) ?> &bull; <?= htmlspecialchars($e['designation_name'] ?? 'Staff') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label" for="period_start">Review Period Start <span class="required">*</span></label>
                        <input type="date" class="form-control" id="period_start" name="review_period_start" value="<?= date('Y-m-d', strtotime('-6 months')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="period_end">Review Period End <span class="required">*</span></label>
                        <input type="date" class="form-control" id="period_end" name="review_period_end" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="rating_score">Score Rating (1.0 to 5.0) <span class="required">*</span></label>
                        <input type="number" step="0.1" min="1.0" max="5.0" class="form-control font-monospace fw-bold" id="rating_score" name="rating" value="4.5" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="rev_strengths">Key Strengths & Notable Accomplishments <span class="required">*</span></label>
                    <textarea class="form-control" id="rev_strengths" name="strengths" rows="3" placeholder="Identify outstanding deliverables, leadership, problem solving..." required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="rev_improvements">Areas for Growth & Development Goals <span class="required">*</span></label>
                    <textarea class="form-control" id="rev_improvements" name="improvements" rows="3" placeholder="Targeted improvements, training goals, upcoming focus..." required></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="rev_comments">Executive Reviewer Remarks & Verdict</label>
                    <textarea class="form-control" id="rev_comments" name="comments" rows="2" placeholder="General summary or promotional recommendations..."></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= BASE_URL ?>performance/index.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i> Finalize Performance Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

