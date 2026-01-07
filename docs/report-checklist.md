# Micro‑Task Delegation & Accountability System — Report Checklist

This checklist is designed to be pasted into a report (project submission / company-style handoff).

## 1) Workflow (end-to-end)

1. **Manager creates a task**
   - Sets title, estimated minutes, priority, and expiry time.
   - Optionally assigns immediately (or leaves unassigned).
2. **Manager assigns a task** (if unassigned)
   - Assigns a member from the Board.
3. **Member starts work**
   - Member clicks **Start** (records `started_at`).
4. **Member completes work**
   - Member clicks **Complete Task** (records `completed_at`, sets status to `completed`).
   - System blocks completion if the task is expired.
5. **Manager reviews completed work**
   - Manager records decision: `accepted` or `rejected`.
   - Duplicate reviews for the same assignment are prevented.
6. **Dashboard updates reliability scoring**
   - Dashboard reflects completed/expired tasks and accepted/rejected reviews.

## 2) Reliability Scoring Formula

Scoring is computed per member as a value from **0 to 100**.

Definitions:

- **Completion Rate** = $\frac{completed}{assigned}$ (0 if assigned = 0)
- **Acceptance Rate** = $\frac{accepted}{accepted + rejected}$
  - If no reviews yet, acceptance rate is treated as **1.0** (neutral).
- **On‑Time Rate** = $\frac{onTimeCompleted}{completed}$
  - If nothing completed yet, on-time rate is treated as **1.0** (neutral).

Final score:

$$
score = 50\cdot completionRate + 30\cdot acceptanceRate + 20\cdot onTimeRate
$$

Score label mapping:

- **Excellent**: $\ge 90$
- **Good**: $\ge 75$
- **Fair**: $\ge 60$
- **Needs Improvement**: otherwise

## 3) Expiry Scheduling (Auto-expire job)

- A CLI job marks overdue assignments as `expired`.
- Windows Task Scheduler runs it every 1 minute.

See: [docs/task-scheduler-expiry-job.md](task-scheduler-expiry-job.md)

Notes:

- The UI also treats tasks as expired based on `expiry_at` time (so behavior is correct even if the scheduler is temporarily not running).
- The API rejects start/complete actions after expiry.

## 4) Pages & Routes (as implemented)

### Pages

- `GET /login` — login page
- `POST /login` — login submit
- `GET /logout` — logout
- `GET /board` — team task board
- `GET /create-task` — manager-only task creation
- `GET /reviews` — manager-only review queue
- `GET /dashboard` — reliability dashboard
- `GET /task/{id}` — task detail page

### JSON APIs

- `GET /api/board` — board data (tasks + assignment info)
- `GET /api/task/{id}` — task detail JSON
- `GET /api/dashboard` — dashboard stats + scores
- `POST /api/tasks/create` — manager-only create task
- `POST /api/tasks/{id}/assign` — manager-only assign unassigned task
- `POST /api/assignments/{id}/start` — member-only start assignment (blocked if expired)
- `POST /api/assignments/{id}/complete` — member-only complete assignment (requires started; blocked if expired)
- `POST /api/reviews/{assignmentId}` — manager-only submit review (`accepted`/`rejected`)
