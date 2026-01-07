<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Task</title>
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

<?php
require_once __DIR__ . '/../models/User.php';
$userModel = new User($db);
$members = $userModel->getMembers();
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="#">MicroTask</a>
    <div class="navbar-nav ms-auto">
        <a class="nav-link active" href="/create-task">Create Task</a>
        <a class="nav-link" href="/reviews">Reviews</a>
        <a class="nav-link" href="/board">Board</a>
        <a class="nav-link" href="/dashboard">Dashboard</a>
                <button type="button" class="btn btn-outline-light btn-sm ms-2 d-inline-flex align-items-center justify-content-center" style="width: 2.25rem; height: 2.25rem;" data-theme-toggle aria-label="Toggle theme">☾</button>
    </div>
  </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Create New Micro-Task</h4>
                </div>
                <div class="card-body">
                    <form id="createTaskForm">
                        <div class="mb-3">
                            <label class="form-label">Task Title</label>
                            <input type="text" class="form-control" name="title" required placeholder="e.g. Fix Navigation Bug">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Est. Minutes</label>
                                <input type="number" class="form-control" name="estimated_minutes" value="30" min="5" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Expiry Time</label>
                            <!-- Simple datetime input for demo -->
                            <input type="datetime-local" class="form-control" name="expiry_at" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Assign To (Member)</label>
                            <select class="form-select" name="assignee_id">
                                <option value="">Unassigned</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= (int)$m['user_id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Optional. You can assign now or later.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Publish Task</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('createTaskForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    fetch('/api/tasks/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(res => {
        if(res.success) {
            alert("Task Created!");
            window.location.href = '/board';
        } else {
            alert("Error: " + (res.error || "Unknown"));
        }
    });
});
</script>
</body>
</html>
