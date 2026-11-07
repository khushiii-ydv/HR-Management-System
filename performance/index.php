<?php
/**
 * Performance Management Overview
 * HR Management System
 */

$pageTitle = 'Performance Management';
$breadcrumbParent = 'Talent';
$activePage = 'performance';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireLogin();

$currentUser = getCurrentUser();
$role = $currentUser['role'];
$pdo = getDBConnection();

if ($role === 'admin' || $role === 'hr') {
    // Admin / HR: View all reviews
    $sql = "SELECT pr.*, e.first_name, e.last_name, e.employee_code, d.department_name, ds.designation_name, u.name as reviewer_name 
            FROM performance_reviews pr
            JOIN employees e ON e.id = pr.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN designations ds ON ds.id = e.designation_id
            LEFT JOIN users u ON u.id = pr.reviewer_id
            ORDER BY pr.review_date DESC";
    $reviews = $pdo->query($sql)->fetchAll();

    // Average organization score
    $avgRating = (float)$pdo->query("SELECT AVG(rating) FROM performance_reviews")->fetchColumn() ?: 0.0;
} else {
    // Employee: View self reviews
    $empId = $currentUser['employee_id'];
    $stmt = $pdo->prepare("SELECT pr.*, e.first_name, e.last_name, e.employee_code, d.department_name, ds.designation_name, u.name as reviewer_name 
                          FROM performance_reviews pr
                          JOIN employees e ON e.id = pr.employee_id
                          LEFT JOIN departments d ON d.id = e.department_id
                          LEFT JOIN designations ds ON ds.id = e.designation_id
                          LEFT JOIN users u ON u.id = pr.reviewer_id
                          WHERE pr.employee_id = ?
                          ORDER BY pr.review_date DESC");
    $stmt->execute([$empId]);
    $reviews = $stmt->fetchAll();

    $avgStmt = $pdo->prepare("SELECT AVG(rating) FROM performance_reviews WHERE employee_id = ?");
    $avgStmt->execute([$empId]);
    $avgRating = (float)$avgStmt->fetchColumn() ?: 0.0;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <?= ($role === 'employee') ? 'My Performance Appraisals' : 'Performance Management' ?>
            </h2>
            <p class="text-secondary small mb-0">Evaluate competency metrics, track career progression, and document qualitative feedback.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <?php if (hasRole(['admin', 'hr'])): ?>
                <a href="<?= BASE_URL ?>performance/add.php" class="btn btn-primary">
                    <i class="bi bi-star-fill me-1"></i> Conduct New Evaluation
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rating Summary Tile -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 h-100 bg-white border shadow-sm">
                <span class="text-secondary small fw-semibold text-uppercase">Average Rating Score</span>
                <div class="d-flex align-items-center gap-2 my-2">
                    <h1 class="display-5 fw-bold text-dark mb-0"><?= number_format($avgRating, 1) ?></h1>
                    <div class="text-warning fs-3">
                        <?php 
                        $fullStars = floor($avgRating);
                        for ($i = 0; $i < 5; $i++) {
                            echo ($i < $fullStars) ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                        }
                        ?>
                    </div>
                </div>
                <small class="text-muted">Out of 5.0 total score rating scale</small>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card p-3 h-100 bg-white border shadow-sm d-flex flex-column justify-content-center">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-check2-circle text-success me-1"></i> Continuous Performance Feedback Loop</h6>
                <p class="text-secondary small mb-0">
                    Appraisals focus on objective milestones, code reliability, design fidelity, leadership mentorship, and cross-functional communication. Reviews are permanently preserved in employee personnel records.
                </p>
            </div>
        </div>
    </div>

    <!-- Reviews Grid -->
    <div class="row g-4">
        <?php if (empty($reviews)): ?>
            <div class="col-12">
                <div class="card text-center py-5">
                    <i class="bi bi-star fs-1 text-muted d-block mb-2"></i>
                    <h5 class="text-secondary">No appraisals registered.</h5>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $rev): ?>
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border">
                        <div class="card-header d-flex justify-content-between align-items-center bg-white">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($rev['designation_name'] ?? 'Staff') ?> &bull; <?= htmlspecialchars($rev['department_name'] ?? 'General') ?></small>
                            </div>
                            <div class="d-flex align-items-center bg-warning-subtle text-warning-emphasis px-3 py-1 rounded-pill border border-warning-subtle fw-bold">
                                <i class="bi bi-star-fill text-warning me-1"></i> <?= number_format($rev['rating'], 1) ?> / 5.0
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="p-2 mb-3 bg-light rounded text-muted small d-flex justify-content-between">
                                <span>Cycle: <strong><?= formatDate($rev['review_period_start']) ?> &ndash; <?= formatDate($rev['review_period_end']) ?></strong></span>
                                <span>Date: <strong><?= formatDate($rev['review_date']) ?></strong></span>
                            </div>

                            <div class="mb-3">
                                <strong class="text-success small d-block mb-1"><i class="bi bi-hand-thumbs-up-fill me-1"></i> Key Strengths</strong>
                                <p class="small text-secondary mb-0"><?= nl2br(htmlspecialchars($rev['strengths'] ?? '')) ?></p>
                            </div>

                            <div class="mb-3">
                                <strong class="text-warning-emphasis small d-block mb-1"><i class="bi bi-arrow-up-right-circle-fill me-1"></i> Development Opportunities</strong>
                                <p class="small text-secondary mb-0"><?= nl2br(htmlspecialchars($rev['improvements'] ?? '')) ?></p>
                            </div>

                            <?php if (!empty($rev['comments'])): ?>
                                <div class="p-2 bg-light rounded-2 border-start border-3 border-primary">
                                    <small class="text-muted d-block">Evaluator Remarks:</small>
                                    <span class="small text-dark italic">"<?= htmlspecialchars($rev['comments']) ?>"</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer bg-light d-flex justify-content-between align-items-center small text-muted">
                            <span>Reviewed by: <strong><?= htmlspecialchars($rev['reviewer_name'] ?? 'HR Admin') ?></strong></span>
                            <span class="badge bg-white text-secondary border font-monospace"><?= htmlspecialchars($rev['employee_code']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

