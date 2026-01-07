<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Task Board</title>
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
    <style>
        .task-card { transition: transform 0.2s; }
                .task-card:hover { transform: translateY(-2px); box-shadow: var(--bs-box-shadow); }
        .timer { font-weight: bold; font-family: monospace; }
        .status-badge { font-size: 0.8em; }
    </style>
</head>
<body class="bg-body">

<?php
$members = [];
if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
    require_once __DIR__ . '/../models/User.php';
    $userModel = new User($db);
    $members = $userModel->getMembers();
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="#">MicroTask</a>
    <div class="navbar-nav ms-auto">
      <?php if($_SESSION['role'] === 'manager'): ?>
          <a class="nav-link" href="/create-task">Create Task</a>
          <a class="nav-link" href="/reviews">Reviews</a>
      <?php endif; ?>
      <a class="nav-link active" href="/board">Board</a>
      <a class="nav-link" href="/dashboard">Dashboard</a>
      <a class="nav-link text-danger" href="/logout">Logout (<?= $_SESSION['name'] ?>)</a>
            <button type="button" class="btn btn-outline-light btn-sm ms-2 d-inline-flex align-items-center justify-content-center" style="width: 2.25rem; height: 2.25rem;" data-theme-toggle aria-label="Toggle theme">☾</button>
    </div>
  </div>
</nav>

<div class="container">
    <h2 class="mb-4">Team Task Board</h2>
    
    <!-- Board Container -->
    <div id="board-container" class="row g-4">
        <!-- Tasks will be injected here via AJAX -->
        <div class="col-12 text-center">Loading tasks...</div>
    </div>
</div>

<script>
const currentUserId = <?= $_SESSION['user_id'] ?>;
const userRole = "<?= $_SESSION['role'] ?>";
const members = <?= json_encode($members, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatRemaining(ms) {
    if (Number.isNaN(ms) || ms <= 0) return 'Expired';
    const totalMinutes = Math.floor(ms / 60000);
    const days = Math.floor(totalMinutes / 1440);
    const hours = Math.floor((totalMinutes % 1440) / 60);
    const minutes = totalMinutes % 60;

    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${minutes}m`;
}

function fetchBoard() {
    fetch('/api/board')
        .then(response => response.json())
        .then(data => renderBoard(data));
}

function renderBoard(tasks) {
    const container = document.getElementById('board-container');
    container.innerHTML = '';

    if (tasks.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted">No active tasks found.</div>';
        return;
    }

    tasks.forEach(task => {
        let actionBtn = '';
        let detailBtn = '';
        let assignUi = '';
        let claimUi = '';
        let statusBadge = '<span class="badge bg-secondary">Unassigned</span>';
        let cardClass = 'border-secondary';
        
        // Expiry logic for display
        // MySQL DATETIME often comes as "YYYY-MM-DD HH:MM:SS" which is not consistently parsed by JS.
        // Convert to ISO-like "YYYY-MM-DDTHH:MM:SS" for reliable parsing.
        const expiryRaw = (task.expiry_at || '').toString();
        const expiryIsoLike = expiryRaw.includes('T') ? expiryRaw : expiryRaw.replace(' ', 'T');
        const expiryDate = new Date(expiryIsoLike);
        const now = new Date();
        const timeDiff = expiryDate - now;
        const isExpiredByTime = !Number.isNaN(timeDiff) && timeDiff <= 0;
        
        // Timer text: PDF semantics
        // Countdown begins when the assignment is actually started.
        // Until then, show a neutral label (due date remains visible in footer).
        const startedForTimer = !!task.assignment_started_at;
        let timerText = startedForTimer ? formatRemaining(timeDiff) : 'Not started';

        // Display expired clearly even if DB status hasn't been updated yet.
        // Completed tasks should remain "Done".
        if (isExpiredByTime && task.assignment_status !== 'completed') {
            timerText = 'Expired';
            statusBadge = '<span class="badge bg-danger">Expired</span>';
            cardClass = 'border-danger';
        }

        // If the latest completed assignment was rejected, treat the task as back in the pool.
        // We keep history in DB, but the board should show it as available again.
        const returnedToPool = task.assignment_status === 'completed' && task.review_decision === 'rejected';
        if (returnedToPool && !isExpiredByTime) {
            statusBadge = '<span class="badge bg-warning text-dark">Returned to pool</span>';
            cardClass = 'border-warning';
        }

        if (task.assignment_status && !returnedToPool) {
            if (task.assignment_status === 'active') {
            const started = !!task.assignment_started_at;

                if (!isExpiredByTime) {
                    if (started) {
                        statusBadge = '<span class="badge bg-primary">In Progress</span>';
                        cardClass = 'border-primary';
                    } else {
                        statusBadge = '<span class="badge bg-info">Assigned</span>';
                        cardClass = 'border-info';
                    }
                }

                // If I am the assignee, show Start/Complete actions
                if (task.assignee_id == currentUserId) {
                    if (!started) {
                        if (timeDiff > 0) {
                            actionBtn = `<button onclick="startAssignment(${task.assignment_id})" class="btn btn-sm btn-outline-primary w-100">Start</button>`;
                        }
                    } else {
                        if (timeDiff > 0) {
                            actionBtn = `<button onclick="completeTask(${task.assignment_id})" class="btn btn-sm btn-success w-100">Complete Task</button>`;
                        } else {
                            actionBtn = `<button class="btn btn-sm btn-success w-100" disabled title="Task expired">Complete Task</button>`;
                        }
                    }
                }
            } else if (task.assignment_status === 'completed') {
                statusBadge = '<span class="badge bg-success">Completed</span>';
                cardClass = 'border-success';
                timerText = "Done";
            } else if (task.assignment_status === 'expired') {
                statusBadge = '<span class="badge bg-danger">Expired</span>';
                cardClass = 'border-danger';
                timerText = 'Expired';
            }
        } else {
            // Unassigned task
            // Manager-only assign UI for unassigned tasks
            if (userRole === 'manager' && timeDiff > 0 && Array.isArray(members) && members.length > 0) {
                const options = members
                    .map(m => `<option value="${m.user_id}">${escapeHtml(m.name)}</option>`)
                    .join('');

                assignUi = `
                    <div class="mt-2">
                        <select class="form-select form-select-sm" id="assign-select-${task.task_id}" aria-label="Assign member">
                            <option value="">Assign to...</option>
                            ${options}
                        </select>
                        <button onclick="assignTask(${task.task_id})" class="btn btn-sm btn-primary w-100 mt-2">Assign</button>
                        <div id="assign-msg-${task.task_id}" class="small mt-2" aria-live="polite"></div>
                    </div>
                `;
            }

            // Member-only volunteer claim UI
            if (userRole === 'member' && timeDiff > 0) {
                claimUi = `
                    <div class="mt-2">
                        <button onclick="claimTask(${task.task_id})" class="btn btn-sm btn-outline-primary w-100">Claim</button>
                        <div id="claim-msg-${task.task_id}" class="small mt-2" aria-live="polite"></div>
                    </div>
                `;
            }
        }

        detailBtn = `<a href="/task/${task.task_id}" class="btn btn-sm btn-outline-secondary w-100 mt-2">View Details</a>`;
        
        // Priority Badge
        let priorityColor = task.priority === 'high' ? 'danger' : (task.priority === 'medium' ? 'warning' : 'info');

        const dueText = Number.isNaN(expiryDate.getTime())
            ? 'N/A'
            : expiryDate.toLocaleString();

        const html = `
            <div class="col-md-4">
                <div class="card h-100 ${cardClass} task-card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center bg-transparent">
                         <span class="badge bg-${priorityColor}">${task.priority}</span>
                         <small class="timer ${timeDiff < 0 ? 'text-danger' : ''}">${timerText}${timerText !== 'Expired' && timerText !== 'Done' && timerText !== 'Not started' ? ' left' : ''}</small>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">${task.title}</h5>
                        <p class="card-text small text-muted">Est: ${task.estimated_minutes} mins</p>
                        <p class="mb-2">${statusBadge}</p>
                        <small class="text-muted d-block mb-3">
                            ${task.assignee_name ? 'Assigned to: ' + task.assignee_name : 'Unassigned'}
                        </small>
                        ${actionBtn}
                        ${assignUi}
                        ${claimUi}
                        ${detailBtn}
                    </div>
                    <div class="card-footer bg-transparent small text-muted">
                        Due: ${dueText}
                    </div>
                </div>
            </div>
        `;
        container.innerHTML += html;
    });
}

function startAssignment(id) {
    if(!confirm("Start this assignment now?")) return;

    fetch(`/api/assignments/${id}/start`, { method: 'POST' })
        .then(res => res.json())
        .then(res => {
            if (res.success) fetchBoard();
            else alert(res.error || "Failed to start");
        });
}

function completeTask(id) {
    if(!confirm("Mark this task as completed?")) return;
    
    fetch(`/api/assignments/${id}/complete`, { method: 'POST' })
        .then(res => res.json())
        .then(res => {
            if (res.success) fetchBoard();
            else alert("Error failing task");
        });
}

function assignTask(taskId) {
    if (userRole !== 'manager') return;

    const select = document.getElementById(`assign-select-${taskId}`);
    if (!select) return;

    const msg = document.getElementById(`assign-msg-${taskId}`);
    const setMsg = (text, type) => {
        if (!msg) return;
        msg.className = `small mt-2 ${type === 'success' ? 'text-success' : 'text-danger'}`;
        msg.textContent = text;
    };

    const assigneeId = parseInt(select.value, 10);
    if (!assigneeId) {
        setMsg('Select a member first.', 'error');
        return;
    }

    setMsg('Assigning…', 'success');

    fetch(`/api/tasks/${taskId}/assign`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ assignee_id: assigneeId })
    })
        .then(async res => {
            let data = null;
            try {
                data = await res.json();
            } catch (e) {
                // ignore parse error
            }
            return { ok: res.ok, data };
        })
        .then(res => {
            if (res.ok && res.data && res.data.success) {
                setMsg('Assigned.', 'success');
                // Let the user see the message briefly before the board re-renders.
                setTimeout(fetchBoard, 650);
                return;
            }

            const errorText = (res.data && (res.data.error || res.data.message)) ? (res.data.error || res.data.message) : 'Failed to assign.';
            setMsg(errorText, 'error');
        });
}

function claimTask(taskId) {
    if (userRole !== 'member') return;

    const msg = document.getElementById(`claim-msg-${taskId}`);
    const setMsg = (text, type) => {
        if (!msg) return;
        msg.className = `small mt-2 ${type === 'success' ? 'text-success' : 'text-danger'}`;
        msg.textContent = text;
    };

    setMsg('Claiming…', 'success');

    fetch(`/api/tasks/${taskId}/claim`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
        .then(async res => {
            let data = null;
            try {
                data = await res.json();
            } catch (e) {
                // ignore parse error
            }
            return { ok: res.ok, data };
        })
        .then(res => {
            if (res.ok && res.data && res.data.success) {
                setMsg('Claimed.', 'success');
                setTimeout(fetchBoard, 650);
                return;
            }

            const errorText = (res.data && (res.data.error || res.data.message)) ? (res.data.error || res.data.message) : 'Failed to claim.';
            setMsg(errorText, 'error');
        });
}

// Initial Load
fetchBoard();
// Poll every 5 seconds
setInterval(fetchBoard, 5000);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
