<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
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
        <?php if($_SESSION['role'] === 'manager'): ?>
          <a class="nav-link" href="/create-task">Create Task</a>
          <a class="nav-link" href="/reviews">Reviews</a>
      <?php endif; ?>
      <a class="nav-link" href="/board">Board</a>
      <a class="nav-link active" href="/dashboard">Dashboard</a>
            <button type="button" class="btn btn-outline-light btn-sm ms-2 d-inline-flex align-items-center justify-content-center" style="width: 2.25rem; height: 2.25rem;" data-theme-toggle aria-label="Toggle theme">☾</button>
    </div>
  </div>
</nav>

<div class="container">
    <h2 class="mb-4">Performance Dashboard</h2>

    <div id="loading" class="alert alert-secondary">Loading real dashboard stats...</div>

    <div class="row g-4" id="dashboard-content" style="display:none;">
        <div class="col-lg-4">
            <div class="card bg-primary text-white text-center p-4 h-100">
                <h3>Avg Reliability</h3>
                <h1 class="display-1 fw-bold" id="avg-score">0</h1>
                <p class="mb-0" id="avg-score-label">—</p>
                <small class="text-white-50">(Members with assignments)</small>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card p-3 text-center border-secondary">
                        <h6 class="text-muted">Members</h6>
                        <h2 class="fw-bold" id="member-count">0</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 text-center border-info">
                        <h6 class="text-info">Assigned</h6>
                        <h2 class="fw-bold" id="assigned-count">0</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 text-center border-success">
                        <h6 class="text-success">Completed</h6>
                        <h2 class="fw-bold" id="completed-count">0</h2>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 text-center border-danger">
                        <h6 class="text-danger">Expired</h6>
                        <h2 class="fw-bold" id="expired-count">0</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 text-center border-primary">
                        <h6 class="text-primary">On-time</h6>
                        <h2 class="fw-bold" id="on-time-count">0</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 text-center border-warning">
                        <h6 class="text-warning">Late</h6>
                        <h2 class="fw-bold" id="late-count">0</h2>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-3 text-center border-success">
                        <h6 class="text-success">Accepted</h6>
                        <h2 class="fw-bold" id="accepted-count">0</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 text-center border-danger">
                        <h6 class="text-danger">Rejected</h6>
                        <h2 class="fw-bold" id="rejected-count">0</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-3 text-center border-secondary">
                        <h6 class="text-muted">Pending Reviews</h6>
                        <h2 class="fw-bold" id="pending-reviews-count">0</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Member Reliability</span>
                    <small class="text-muted">Auto-refreshes every 5s</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Member</th>
                                    <th class="text-end">Assigned</th>
                                    <th class="text-end">Completed</th>
                                    <th class="text-end">Expired</th>
                                    <th class="text-end">On-time</th>
                                    <th class="text-end">Late</th>
                                    <th class="text-end">Accepted</th>
                                    <th class="text-end">Rejected</th>
                                    <th class="text-end">Score</th>
                                </tr>
                            </thead>
                            <tbody id="members-tbody">
                                <tr><td colspan="9" class="text-center text-muted p-4">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function scoreLabel(score) {
    if (score >= 90) return 'Excellent';
    if (score >= 75) return 'Good';
    if (score >= 60) return 'Fair';
    return 'Needs Improvement';
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = value;
}

function renderMembers(members) {
    const tbody = document.getElementById('members-tbody');
    if (!tbody) return;

    if (!members || members.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted p-4">No members found.</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    members.forEach(m => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${m.name}</td>
            <td class="text-end">${m.assigned_count}</td>
            <td class="text-end">${m.completed_count}</td>
            <td class="text-end">${m.expired_count}</td>
            <td class="text-end">${m.on_time_completed_count}</td>
            <td class="text-end">${m.late_completed_count}</td>
            <td class="text-end">${m.accepted_count}</td>
            <td class="text-end">${m.rejected_count}</td>
            <td class="text-end fw-bold">${m.score}</td>
        `;
        tbody.appendChild(row);
    });
}

function fetchDashboard() {
    fetch('/api/dashboard')
        .then(r => r.json())
        .then(data => {
            const overview = data.overview || {};

            // Use avg over members with assignments (more meaningful)
            const avg = overview.avg_score_active_members ?? 0;
            setText('avg-score', avg);
            setText('avg-score-label', scoreLabel(avg));

            setText('member-count', overview.member_count ?? 0);
            setText('assigned-count', overview.assigned_count ?? 0);
            setText('completed-count', overview.completed_count ?? 0);
            setText('expired-count', overview.expired_count ?? 0);
            setText('on-time-count', overview.on_time_completed_count ?? 0);
            setText('late-count', overview.late_completed_count ?? 0);
            setText('accepted-count', overview.accepted_count ?? 0);
            setText('rejected-count', overview.rejected_count ?? 0);
            setText('pending-reviews-count', overview.pending_reviews_count ?? 0);

            renderMembers(data.members || []);

            document.getElementById('loading').style.display = 'none';
            document.getElementById('dashboard-content').style.display = '';
        })
        .catch(() => {
            document.getElementById('loading').className = 'alert alert-danger';
            document.getElementById('loading').textContent = 'Failed to load dashboard stats. Check DB connection.';
        });
}

fetchDashboard();
setInterval(fetchDashboard, 5000);

// If manager submits a review in another tab, refresh instantly.
window.addEventListener('storage', (e) => {
    if (e && e.key === 'dashboard_refresh') {
        fetchDashboard();
    }
});
</script>

</body>
</html>
