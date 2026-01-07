<?php
// We need to fetch pending reviews server-side or via AJAX. 
// Server side is easier for the view setup, but let's stick to the pattern we used: 
// Controller has the logic, but since we are "including" this view from index.php, 
// we can instantiate the controller and pass data, or fetch via AJAX. 
// For consistency with "AJAX + JS" requirement, I'll allow the view to fetch via internal API 
// or I'll just put the PHP logic at the top since we are in a PHP file included by the router.

require_once __DIR__ . '/../models/Review.php';
$reviewModel = new Review($db);
$pendingReviews = $reviewModel->getPendingReviews();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Reviews</title>
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
        <a class="nav-link" href="/create-task">Create Task</a>
        <a class="nav-link active" href="/reviews">Reviews</a>
        <a class="nav-link" href="/board">Board</a>
        <a class="nav-link" href="/dashboard">Dashboard</a>
                <button type="button" class="btn btn-outline-light btn-sm ms-2 d-inline-flex align-items-center justify-content-center" style="width: 2.25rem; height: 2.25rem;" data-theme-toggle aria-label="Toggle theme">☾</button>
    </div>
  </div>
</nav>

<div class="container">
    <h2 class="mb-4">Pending Reviews</h2>
    
    <?php if (empty($pendingReviews)): ?>
        <div class="alert alert-info">No pending reviews. Good job!</div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($pendingReviews as $rev): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center" id="review-row-<?= $rev['id'] ?>">
                <div>
                    <h5 class="mb-1"><?= htmlspecialchars($rev['title']) ?></h5>
                    <p class="mb-1">Completed by: <strong><?= htmlspecialchars($rev['assignee_name']) ?></strong></p>
                    <small class="text-muted">Completed at: <?= $rev['completed_at'] ?></small>
                </div>
                <div>
                    <button onclick="submitReview(<?= $rev['id'] ?>, 'accepted')" class="btn btn-success btn-sm me-2">Accept</button>
                    <button onclick="submitReview(<?= $rev['id'] ?>, 'rejected')" class="btn btn-danger btn-sm">Reject</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function submitReview(assignmentId, decision) {
    if(!confirm("Confirm " + decision + "?")) return;
    
    fetch(`/api/reviews/${assignmentId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ decision: decision })
    })
    .then(res => res.json())
    .then(res => {
        if(res.success) {
            document.getElementById(`review-row-${assignmentId}`).remove();
            // Trigger instant dashboard refresh in another tab/window
            try {
                localStorage.setItem('dashboard_refresh', String(Date.now()));
            } catch (e) {}
        } else {
            alert("Error");
        }
    });
}
</script>
</body>
</html>
