# Micro‑Task Delegation & Accountability System

A lightweight PHP + MySQL web app for delegating micro‑tasks, tracking completion, manager review (accept/reject), and computing member reliability scores.

## Tech Stack

- Backend: PHP (session auth), PDO (MySQL)
- Frontend: Bootstrap 5 + JavaScript (AJAX polling)
- Database: MySQL
- Ops: CLI expiry job (Windows Task Scheduler)

## Core Workflow (End‑to‑End)

1. **Manager creates a task** (title, estimated minutes, priority, expiry)
2. **Manager assigns** a member **or leaves unassigned** (open for volunteers)
3. **Member claims** unassigned tasks (volunteer flow)
4. **Member starts** work (records `started_at`)
5. **Member completes** work (records `completed_at`, optional comment + proof upload)
6. **Manager reviews** completed work (`accepted` / `rejected`)
7. **Dashboard updates** reliability scoring (0–100) per member

Important rule: tasks cannot be started/completed after expiry (enforced by API and reflected in UI).

## Features

- Role‑based access control (manager vs member)
- Team task board with polling refresh (near real‑time)
- Assign flow (manager) + claim flow (member volunteers)
- Start → Complete lifecycle (completion requires start)
- Proof attachment support (optional) + completion comment (optional)
- Manager review queue (accept/reject)
- Rejected tasks return to the pool (can be assigned/claimed again)
- Auto‑expiry job to mark overdue active assignments as `expired`
- Reliability dashboard with defensible scoring formula
- Dark mode (Bootstrap 5.3 theme toggle)

## Quick Start (Windows + XAMPP)

### 1) Prerequisites

- XAMPP (Apache + MySQL + PHP)
- A MySQL client (phpMyAdmin is included with XAMPP)

### 2) Create the database

1. Start **Apache** and **MySQL** in XAMPP Control Panel.
2. Create DB + tables by importing:

- `database/schema.sql`

Optionally insert demo data (accounts + sample tasks):

- `database/seed.sql`

> Note: `database/schema.sql` creates `micro_task_db` automatically.

### 3) Configure DB credentials

Update the connection settings in:

- `backend/config/database.php`

Defaults are:

- host: `127.0.0.1`
- db: `micro_task_db`
- user: `root`
- pass: *(empty)*

### 4) Run the web app

You have two easy options.

#### Option A (Recommended): PHP built‑in server

From PowerShell in the project folder:

```powershell
cd D:\college\aman_project\project
& "C:\xampp\php\php.exe" -S localhost:8000 -t public
```

Open:

- http://localhost:8000

#### Option B: Apache (XAMPP)

- Point Apache’s DocumentRoot (or a VirtualHost) to the `public/` folder.

Example idea:

- Project path: `D:\college\aman_project\project`
- Web root should be: `D:\college\aman_project\project\public`

Then open your configured URL (e.g. `http://localhost/`).

### 5) Demo login accounts

If you imported `database/seed.sql`:

- Manager: `alice@example.com`
- Member: `bob@example.com`
- Member: `charlie@example.com`

Password for seeded accounts:

- `password`

## Pages & Routes

### Pages

- `GET /login` — login page
- `POST /login` — login submit
- `GET /logout` — logout
- `GET /board` — team task board
- `GET /create-task` — manager‑only task creation
- `GET /reviews` — manager‑only review queue
- `GET /dashboard` — reliability dashboard
- `GET /task/{id}` — task detail page

### JSON APIs

- `GET /api/board` — board data
- `GET /api/task/{id}` — task detail JSON
- `GET /api/dashboard` — dashboard stats + scores
- `POST /api/tasks/create` — manager‑only create task
- `POST /api/tasks/{id}/assign` — manager‑only assign
- `POST /api/tasks/{id}/claim` — member‑only claim (volunteer flow)
- `POST /api/assignments/{id}/start` — member‑only start
- `POST /api/assignments/{id}/complete` — member‑only complete (supports proof upload)
- `POST /api/reviews/{assignmentId}` — manager‑only submit review

## Reliability Scoring

Each member receives a score from **0 to 100**.

Definitions:

- Completion Rate = $\frac{completed}{assigned}$ (0 if assigned = 0)
- Acceptance Rate = $\frac{accepted}{accepted + rejected}$
  - If no reviews exist yet, acceptance rate is treated as **1.0** (neutral).
- On‑Time Rate = $\frac{onTimeCompleted}{completed}$
  - If nothing completed yet, on‑time rate is treated as **1.0** (neutral).

Final score:

$$
score = 50\cdot completionRate + 30\cdot acceptanceRate + 20\cdot onTimeRate
$$

Label mapping:

- Excellent: $\ge 90$
- Good: $\ge 75$
- Fair: $\ge 60$
- Needs Improvement: otherwise

Implementation:

- `backend/models/DashboardStats.php`

## Auto‑Expiry Job (Windows Task Scheduler)

The app includes a CLI job that marks overdue active assignments as `expired`.

- Script: `backend/cli/expire.php`
- Log file: `backend/cli/logs/expiry.log`

Manual test:

```powershell
cd D:\college\aman_project\project
& "C:\xampp\php\php.exe" backend\cli\expire.php
```

Scheduler setup guide:

- `docs/task-scheduler-expiry-job.md`

## Architecture (MVC‑ish)

This project uses a simple, readable PHP structure:

- **Front controller / router**: `public/index.php`
  - Resolves routes to views or controllers.
- **Controllers**: `backend/controllers/*`
  - Small HTTP handlers (role checks, validation, return JSON).
- **Models**: `backend/models/*`
  - PDO queries and core business rules.
- **Views**: `backend/views/*`
  - Bootstrap UI + JS for calling the JSON APIs.
- **Middleware**: `backend/middleware/AuthMiddleware.php`
  - `requireAuth()` and `requireRole()` guards.

Polling:

- Board and dashboard use AJAX polling to refresh state periodically.

## Feature → Implementation Map (Where to Look)

- Routing + endpoints: `public/index.php`
- Auth (session login): `backend/controllers/AuthController.php`, `backend/models/User.php`
- Board UI + polling + assign/claim buttons: `backend/views/board.php`
- Task creation: `backend/views/create_task.php`, `backend/controllers/TaskController.php`, `backend/models/Task.php`
- Claim flow (volunteer): `backend/controllers/TaskController.php`, `backend/models/Assignment.php`
- Start/complete lifecycle + expiry guards + uploads: `backend/controllers/AssignmentController.php`, `backend/models/Assignment.php`
- Task detail page (comment + proof display): `backend/views/task_detail.php`, `backend/models/Task.php`
- Review queue (accept/reject): `backend/views/review.php`, `backend/controllers/ReviewController.php`, `backend/models/Review.php`
- Dashboard scoring + stats: `backend/views/dashboard.php`, `backend/controllers/DashboardController.php`, `backend/models/DashboardStats.php`
- DB schema: `database/schema.sql`

## STAR Method (for viva / report)

**Situation**
- Teams need a lightweight system to delegate micro‑tasks with accountability and measurable performance.

**Task**
- Build a PHP + MySQL system with task delegation, expiry, completion verification, and reliability tracking.

**Action**
- Implemented an MVC‑ish PHP app with session auth and role‑based permissions.
- Added task lifecycle: assign/claim → start → complete → review.
- Implemented expiry protections in both UI and API, plus a scheduler‑friendly CLI expiry job.
- Prevented data integrity issues (one active assignment per task; one review per assignment).
- Built an AJAX‑polled board + dashboard for near real‑time updates.

**Result**
- Managers can delegate and review tasks efficiently.
- Members can volunteer for unassigned tasks, track deadlines, and attach proof.
- The dashboard provides a defensible reliability score (0–100) to support accountability.

## Troubleshooting

- **Blank page / 500 error**: check Apache/PHP error logs and ensure PDO MySQL extension is enabled in XAMPP.
- **DB connection error**: verify credentials in `backend/config/database.php`.
- **404 on routes** (Apache): ensure the web root is the `public/` folder (not the project root).
- **Proof upload issues**: ensure `public/uploads/proofs/` exists and is writable.

## Docs

- Report checklist (workflow, scoring, routes): `docs/report-checklist.md`
- Task Scheduler setup for expiry job: `docs/task-scheduler-expiry-job.md`
