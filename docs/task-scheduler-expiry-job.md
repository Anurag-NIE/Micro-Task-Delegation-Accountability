# Windows Task Scheduler: Auto-expiry job (every 1 minute)

For a report-ready project checklist (workflow, scoring, routes), see:

- [docs/report-checklist.md](report-checklist.md)

This project includes a CLI expiry job that marks overdue assignments as `expired`.

Script:

- `backend/cli/expire.php`

It logs each run to:

- `backend/cli/logs/expiry.log`

## Prerequisites

- XAMPP installed (or any PHP CLI)
- MySQL running
- Project DB configured correctly in `backend/config/database.php`

## Test manually (recommended)

From PowerShell in the project folder:

- `cd D:\college\aman_project\project`
- `& "C:\xampp\php\php.exe" backend\cli\expire.php`

You should see output like:

- `[2026-01-07 12:34:00] expiry_job expired=0`

## Create scheduled task

1. Open **Task Scheduler**
2. Click **Create Task...** (not “Basic Task”) for better control
3. **General** tab:
   - Name: `MicroTask Expiry Job`
   - Select: **Run whether user is logged on or not**
   - Check: **Run with highest privileges** (optional but recommended)
4. **Triggers** tab → **New...**
   - Begin the task: **On a schedule**
   - Settings: **Daily**
   - Advanced settings: Check **Repeat task every:** `1 minute`
   - For a duration of: **Indefinitely**
5. **Actions** tab → **New...**
   - Action: **Start a program**
   - Program/script:
     - `C:\xampp\php\php.exe`
   - Add arguments:
     - `backend\cli\expire.php`
   - Start in:
     - `D:\college\aman_project\project`
6. **Conditions** tab (recommended):
   - Uncheck **Start the task only if the computer is on AC power** (if on laptop and you want it always)
7. **Settings** tab:
   - Check **Allow task to be run on demand**
   - If task is already running: **Do not start a new instance**

## Verify it’s working

- Run the task manually (right-click task → **Run**)
- Check the log file:
  - `backend/cli/logs/expiry.log`

Each line includes the timestamp and how many rows were expired in that run.

## One-time DB update (recommended)

To prevent duplicate reviews for the same assignment in an existing database, add a unique index on `task_reviews.assignment_id`.

First, check for duplicates (this must return 0 rows):

```sql
SELECT assignment_id, COUNT(*) AS c
FROM task_reviews
GROUP BY assignment_id
HAVING c > 1;
```

Then apply the unique index:

```sql
ALTER TABLE task_reviews
   ADD UNIQUE KEY unique_assignment_review (assignment_id);
```
