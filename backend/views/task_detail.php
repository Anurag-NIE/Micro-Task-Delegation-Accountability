<?php
require_once __DIR__ . '/../controllers/TaskController.php';

$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
$controller = new TaskController($db);
$detail = $controller->getTaskDetail($taskId);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fileLink($path) {
    if (!$path) return '';
    $p = (string)$path;
    // Only allow linking to known uploads location to avoid unsafe links.
    if (strpos($p, '/uploads/proofs/') !== 0) return '';
    return $p;
}

$isManager = ($_SESSION['role'] ?? '') === ROLE_MANAGER;
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$assignmentId = $detail['assignment_id'] ?? null;
$assigneeId = isset($detail['assignee_id']) ? (int)$detail['assignee_id'] : null;
$assignmentStatus = $detail['assignment_status'] ?? null;

$isExpired = false;
if ($detail && !empty($detail['expiry_at'])) {
    $ts = strtotime((string)$detail['expiry_at']);
    $isExpired = ($ts !== false) && ($ts <= time());
}

$canStart = (!$isManager)
    && $assignmentId
    && $assigneeId
    && $assigneeId === $currentUserId
    && $assignmentStatus === ASSIGNMENT_ACTIVE
    && empty($detail['started_at'])
    && !$isExpired;

$canComplete = (!$isManager)
    && $assignmentId
    && $assigneeId
    && $assigneeId === $currentUserId
    && $assignmentStatus === ASSIGNMENT_ACTIVE
    && !$isExpired;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Task Detail</title>
        <script>
            (function(){
                try {
                    const t = localStorage.getItem('theme');
                    const theme = (t === 'light' || t === 'dark') ? t : ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light');
                    document.documentElement.setAttribute('data-bs-theme', theme);
                } catch (e) {}
            })();
        </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="/assets/js/theme.js" defer></script>
</head>
<body class="bg-body">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="#">MicroTask</a>
    <div class="navbar-nav ms-auto">
      <?php if(($_SESSION['role'] ?? '') === ROLE_MANAGER): ?>
          <a class="nav-link" href="/create-task">Create Task</a>
          <a class="nav-link" href="/reviews">Reviews</a>
      <?php endif; ?>
      <a class="nav-link" href="/board">Board</a>
      <a class="nav-link" href="/dashboard">Dashboard</a>
      <a class="nav-link text-danger" href="/logout">Logout (<?= h($_SESSION['name'] ?? 'User') ?>)</a>
            <button type="button" class="btn btn-outline-light btn-sm ms-2 d-inline-flex align-items-center justify-content-center" style="width: 2.25rem; height: 2.25rem;" data-theme-toggle aria-label="Toggle theme">☾</button>
    </div>
  </div>
</nav>

<div class="container">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="mb-0">Task Detail</h2>
        <a class="btn btn-outline-secondary btn-sm" href="/board">Back to Board</a>
    </div>

    <?php if (!$detail): ?>
        <div class="alert alert-danger">Task not found.</div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">Task Info</div>
                    <div class="card-body">
                        <h5 class="card-title mb-3" id="task-title"><?= h($detail['title']) ?></h5>
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Task ID</dt>
                            <dd class="col-sm-7" id="task-id"><?= (int)$detail['task_id'] ?></dd>

                            <dt class="col-sm-5">Estimated Minutes</dt>
                            <dd class="col-sm-7" id="estimated-minutes"><?= h($detail['estimated_minutes']) ?></dd>

                            <dt class="col-sm-5">Priority</dt>
                            <dd class="col-sm-7" id="priority"><?= h($detail['priority']) ?></dd>

                            <dt class="col-sm-5">Expiry</dt>
                            <dd class="col-sm-7" id="expiry-at"><?= h($detail['expiry_at']) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">Assignment Info</div>
                    <div class="card-body">
                        <?php if (!$detail['assignment_id']): ?>
                            <div class="alert alert-secondary mb-0">This task is not assigned yet.</div>
                        <?php else: ?>
                            <dl class="row mb-3">
                                <dt class="col-sm-5">Assignee</dt>
                                <dd class="col-sm-7" id="assignee"><?= h($detail['assignee_name'] ?? 'N/A') ?></dd>

                                <dt class="col-sm-5">Status</dt>
                                <dd class="col-sm-7" id="assignment-status"><?= h($detail['assignment_status']) ?></dd>

                                <dt class="col-sm-5">Started At</dt>
                                <dd class="col-sm-7" id="started-at"><?= h($detail['started_at'] ?? 'N/A') ?></dd>

                                <dt class="col-sm-5">Completed At</dt>
                                <dd class="col-sm-7" id="completed-at"><?= h($detail['completed_at'] ?? 'N/A') ?></dd>

                                <dt class="col-sm-5">Completion Comment</dt>
                                <dd class="col-sm-7" id="completion-comment"><?= h($detail['completion_comment'] ?? 'N/A') ?></dd>

                                <dt class="col-sm-5">Proof</dt>
                                <dd class="col-sm-7" id="proof-link">
                                    <?php $proof = fileLink($detail['proof_path'] ?? null); ?>
                                    <?php if ($proof): ?>
                                        <a href="<?= h($proof) ?>" target="_blank" rel="noopener">View uploaded proof</a>
                                    <?php else: ?>
                                        <?= h('N/A') ?>
                                    <?php endif; ?>
                                </dd>
                            </dl>

                            <div class="border-top pt-3">
                                <div class="small text-muted mb-2">Actions</div>

                                <?php if ($isExpired): ?>
                                    <div class="alert alert-danger mb-2">This task has expired. Actions are disabled.</div>
                                    <button class="btn btn-success" disabled>Complete Task</button>
                                <?php elseif ($canStart): ?>
                                    <button class="btn btn-outline-primary" onclick="startAssignment(<?= (int)$detail['assignment_id'] ?>)">Start</button>
                                <?php elseif ($canComplete && !empty($detail['started_at'])): ?>
                                    <div class="mb-2">
                                        <label class="form-label small text-muted">Optional comment</label>
                                        <textarea class="form-control" id="complete-comment" rows="2" placeholder="Optional completion notes..."></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small text-muted">Optional proof attachment (JPG/PNG/GIF/PDF, max 5MB)</label>
                                        <input type="file" class="form-control" id="complete-proof" accept="image/*,application/pdf">
                                    </div>
                                    <div class="small" id="complete-msg" aria-live="polite"></div>
                                    <button class="btn btn-success mt-2" onclick="completeAssignment(<?= (int)$detail['assignment_id'] ?>)">Complete Task</button>
                                <?php elseif ($isManager): ?>
                                    <div class="alert alert-info mb-0">Manager view only. Use the Board to manage tasks.</div>
                                <?php else: ?>
                                    <div class="alert alert-secondary mb-0">No actions available for you on this assignment.</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">Review Info</div>
                    <div class="card-body">
                        <?php if (!($detail['assignment_id'] ?? null)): ?>
                            <div class="text-muted">No assignment yet, so no review.</div>
                        <?php elseif (!($detail['review_id'] ?? null)): ?>
                            <div class="text-muted">No review submitted yet.</div>
                        <?php else: ?>
                            <dl class="row mb-0">
                                <dt class="col-sm-3">Decision</dt>
                                <dd class="col-sm-9" id="review-decision"><?= h($detail['review_decision']) ?></dd>

                                <dt class="col-sm-3">Reviewed At</dt>
                                <dd class="col-sm-9" id="reviewed-at"><?= h($detail['reviewed_at'] ?? 'N/A') ?></dd>
                            </dl>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const taskId = <?= (int)$taskId ?>;

function refreshDetail() {
    fetch(`/api/task/${taskId}`)
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data || data.error) return;

            const setText = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.textContent = value ?? '';
            };

            setText('task-title', data.title);
            setText('task-id', data.task_id);
            setText('estimated-minutes', data.estimated_minutes);
            setText('priority', data.priority);
            setText('expiry-at', data.expiry_at);

            setText('assignee', data.assignee_name ?? 'N/A');
            setText('assignment-status', data.assignment_status ?? 'N/A');
            setText('started-at', data.started_at ?? 'N/A');
            setText('completed-at', data.completed_at ?? 'N/A');
            setText('completion-comment', data.completion_comment ?? 'N/A');

            const proofEl = document.getElementById('proof-link');
            if (proofEl) {
                const p = (data.proof_path || '').toString();
                if (p.startsWith('/uploads/proofs/')) {
                    proofEl.innerHTML = `<a href="${p}" target="_blank" rel="noopener">View uploaded proof</a>`;
                } else {
                    proofEl.textContent = 'N/A';
                }
            }

            setText('review-decision', data.review_decision ?? '');
            setText('reviewed-at', data.reviewed_at ?? '');
        })
        .catch(() => {});
}

function completeAssignment(assignmentId) {
    if (!confirm('Mark this task as completed?')) return;

    const msg = document.getElementById('complete-msg');
    const setMsg = (text, ok) => {
        if (!msg) return;
        msg.className = ok ? 'small text-success' : 'small text-danger';
        msg.textContent = text;
    };

    const fd = new FormData();
    const comment = document.getElementById('complete-comment');
    const proof = document.getElementById('complete-proof');
    if (comment && comment.value && comment.value.trim() !== '') fd.append('comment', comment.value.trim());
    if (proof && proof.files && proof.files[0]) fd.append('proof', proof.files[0]);

    setMsg('Submitting…', true);
    fetch(`/api/assignments/${assignmentId}/complete`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res && res.success) {
                setMsg('Completed.', true);
                refreshDetail();
                setTimeout(() => location.reload(), 350);
            } else {
                setMsg(res.error || 'Failed to complete.', false);
            }
        })
        .catch(() => setMsg('Failed to complete.', false));
}

function startAssignment(assignmentId) {
    if (!confirm('Start this assignment now?')) return;

    fetch(`/api/assignments/${assignmentId}/start`, { method: 'POST' })
        .then(r => r.json())
        .then(res => {
            if (res && res.success) {
                refreshDetail();
                setTimeout(() => location.reload(), 300);
            } else {
                alert(res.error || 'Failed to start.');
            }
        });
}

// Keep consistent with the Board polling behavior
setInterval(refreshDetail, 5000);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
