<?php
/**
 * Recruitment: Interview Scheduling & Feedback
 * HR Management System
 */

$pageTitle = 'Interviews';
$breadcrumbParent = 'Talent';
$activePage = 'interviews';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireRole(['admin', 'hr']);

$pdo = getDBConnection();
$currentUser = getCurrentUser();

// Handle Schedule & Feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'schedule') {
        $candidateId   = (int)($_POST['candidate_id'] ?? 0);
        $interviewerId = !empty($_POST['interviewer_id']) ? (int)$_POST['interviewer_id'] : $currentUser['id'];
        $date          = $_POST['interview_date'] ?? date('Y-m-d');
        $time          = $_POST['interview_time'] ?? '10:00:00';
        $mode          = $_POST['interview_mode'] ?? 'online';
        $meetingLink   = trim($_POST['meeting_link'] ?? '');

        if ($candidateId && !empty($date) && !empty($time)) {
            try {
                $sql = "INSERT INTO interviews 
                    (candidate_id, interviewer_id, interview_date, interview_time, interview_mode, meeting_link, interview_status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'scheduled')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$candidateId, $interviewerId, $date, $time, $mode, $meetingLink]);

                // Also update candidate status to 'interview'
                $pdo->prepare("UPDATE candidates SET status = 'interview' WHERE id = ?")->execute([$candidateId]);

                logAudit($pdo, $currentUser['id'], 'Schedule Interview', 'Recruitment', "Scheduled interview for candidate #$candidateId on $date at $time");
                setFlashMessage('success', "Interview scheduled successfully!");
            } catch (Exception $e) {
                setFlashMessage('danger', 'Error scheduling interview: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'feedback') {
        $interviewId = (int)($_POST['interview_id'] ?? 0);
        $rating      = (float)($_POST['rating'] ?? 4.0);
        $feedback    = trim($_POST['feedback'] ?? '');
        $status      = $_POST['interview_status'] ?? 'completed';

        if ($interviewId) {
            try {
                $stmt = $pdo->prepare("UPDATE interviews SET rating = ?, feedback = ?, interview_status = ? WHERE id = ?");
                $stmt->execute([$rating, $feedback, $status, $interviewId]);
                logAudit($pdo, $currentUser['id'], 'Submit Feedback', 'Recruitment', "Logged interview feedback for interview #$interviewId (Rating: $rating)");
                setFlashMessage('success', "Interview feedback and evaluation recorded.");
            } catch (Exception $e) {
                setFlashMessage('danger', 'Error submitting feedback: ' . $e->getMessage());
            }
        }
    }
    header("Location: " . BASE_URL . "recruitment/interviews.php");
    exit;
}

// Fetch candidates eligible for interview (applied, shortlisted, interview)
$candidates = $pdo->query("SELECT c.id, c.first_name, c.last_name, j.job_title 
                           FROM candidates c 
                           JOIN job_openings j ON j.id = c.job_id 
                           ORDER BY c.first_name ASC")->fetchAll();

// Fetch interviewers (users who are admin or hr)
$interviewers = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('admin', 'hr') ORDER BY name ASC")->fetchAll();

// Fetch all interviews
$sql = "SELECT i.*, c.first_name, c.last_name, c.email as candidate_email, j.job_title, u.name as interviewer_name 
        FROM interviews i
        JOIN candidates c ON c.id = i.candidate_id
        JOIN job_openings j ON j.id = c.job_id
        LEFT JOIN users u ON u.id = i.interviewer_id
        ORDER BY FIELD(i.interview_status, 'scheduled', 'completed', 'cancelled'), i.interview_date ASC, i.interview_time ASC";
$interviews = $pdo->query($sql)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="fw-bold text-dark mb-1">Interviews Schedule</h2>
            <p class="text-secondary small mb-0">Coordinate technical evaluations, manage interview links, and record ratings.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                <i class="bi bi-calendar-plus me-1"></i> Schedule Interview
            </button>
            <a href="<?= BASE_URL ?>recruitment/candidates.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Candidates
            </a>
        </div>
    </div>

    <!-- Interviews Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title"><i class="bi bi-camera-video text-primary"></i> Scheduled & Completed Sessions</h5>
            <span class="badge bg-light text-secondary border"><?= count($interviews) ?> Sessions</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Candidate</th>
                        <th>Target Role</th>
                        <th>Date & Time</th>
                        <th>Interviewer</th>
                        <th>Mode / Meeting Link</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($interviews)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No interviews scheduled.</td></tr>
                    <?php else: ?>
                        <?php foreach ($interviews as $iv): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($iv['first_name'] . ' ' . $iv['last_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($iv['candidate_email']) ?></small>
                                </td>
                                <td><span class="small fw-medium text-secondary"><?= htmlspecialchars($iv['job_title']) ?></span></td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?= formatDate($iv['interview_date']) ?></div>
                                    <small class="text-primary font-monospace"><?= formatTime($iv['interview_time']) ?></small>
                                </td>
                                <td><span class="small text-dark"><?= htmlspecialchars($iv['interviewer_name'] ?? 'HR Panel') ?></span></td>
                                <td>
                                    <?php if ($iv['interview_mode'] === 'online' && !empty($iv['meeting_link'])): ?>
                                        <a href="<?= htmlspecialchars($iv['meeting_link']) ?>" target="_blank" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none">
                                            <i class="bi bi-link-45deg me-1"></i> Google Meet / Link
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border text-capitalize"><?= $iv['interview_mode'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($iv['rating']): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                            <i class="bi bi-star-fill text-warning me-1"></i><?= number_format($iv['rating'], 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= renderStatusBadge($iv['interview_status']) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick='openFeedbackModal(<?= json_encode($iv) ?>)'>
                                        <i class="bi bi-chat-left-text me-1"></i> Feedback
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Schedule Interview -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="scheduleModalLabel"><i class="bi bi-calendar-event text-primary me-2"></i> Schedule Interview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>recruitment/interviews.php" method="POST">
                <input type="hidden" name="action" value="schedule">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="iv_candidate">Candidate <span class="required">*</span></label>
                        <select class="form-select" id="iv_candidate" name="candidate_id" required>
                            <option value="">-- Choose Candidate --</option>
                            <?php foreach ($candidates as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?> (<?= htmlspecialchars($c['job_title']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="iv_interviewer">Interviewer <span class="required">*</span></label>
                        <select class="form-select" id="iv_interviewer" name="interviewer_id" required>
                            <?php foreach ($interviewers as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($u['id'] == $currentUser['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="iv_date">Date <span class="required">*</span></label>
                            <input type="date" class="form-control" id="iv_date" name="interview_date" min="<?= date('Y-m-d') ?>" required value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="iv_time">Time <span class="required">*</span></label>
                            <input type="time" class="form-control" id="iv_time" name="interview_time" value="11:00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="iv_mode">Interview Mode</label>
                        <select class="form-select" id="iv_mode" name="interview_mode">
                            <option value="online">Online Video Call</option>
                            <option value="offline">In-Person at Office</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="iv_link">Meeting Link / Room Location</label>
                        <input type="text" class="form-control" id="iv_link" name="meeting_link" placeholder="e.g. https://meet.google.com/xyz-abcd-efg" value="https://meet.google.com/hrms-interview">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save & Dispatch Invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Feedback & Rating -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="feedbackModalLabel"><i class="bi bi-star text-warning me-2"></i> Evaluation & Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>recruitment/interviews.php" method="POST">
                <input type="hidden" name="action" value="feedback">
                <input type="hidden" name="interview_id" id="fb_interview_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="fb_rating">Rating Score (1.0 to 5.0) <span class="required">*</span></label>
                        <input type="number" step="0.1" min="1.0" max="5.0" class="form-control" id="fb_rating" name="rating" required value="4.5">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="fb_status">Interview Outcome Status</label>
                        <select class="form-select" id="fb_status" name="interview_status">
                            <option value="completed">Completed</option>
                            <option value="scheduled">Keep Scheduled</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="fb_text">Evaluator Notes & Assessment</label>
                        <textarea class="form-control" id="fb_text" name="feedback" rows="3" placeholder="Technical proficiency, problem solving, communication..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Evaluation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openFeedbackModal(iv) {
    document.getElementById('fb_interview_id').value = iv.id;
    document.getElementById('fb_rating').value = iv.rating || '4.5';
    document.getElementById('fb_status').value = iv.interview_status;
    document.getElementById('fb_text').value = iv.feedback || '';
    new bootstrap.Modal(document.getElementById('feedbackModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

