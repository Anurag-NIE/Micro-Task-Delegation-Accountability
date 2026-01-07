<?php
require_once __DIR__ . '/../config/database.php';

class Assignment {
    private $conn;
    private $table_name = "task_assignments";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function assign($task_id, $user_id) {
        // Option B lifecycle: started_at represents when the member actually starts work.
        // So we set started_at = NULL at assignment creation.
        $query = "INSERT INTO " . $this->table_name . " (task_id, user_id, status, started_at) VALUES (:task_id, :user_id, 'active', NULL)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':task_id', $task_id);
        $stmt->bindParam(':user_id', $user_id);
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            // e.g. unique_active_task triggers if another active assignment exists
            return false;
        }
    }

    public function start($assignment_id) {
        $query = "UPDATE " . $this->table_name . " a
                  JOIN tasks t ON a.task_id = t.task_id
                  SET a.started_at = NOW()
                  WHERE a.id = :id
                    AND a.status = 'active'
                    AND a.started_at IS NULL
                    AND t.expiry_at > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $assignment_id, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->rowCount() > 0;
    }

    public function getById($assignment_id) {
        $query = "SELECT a.*, t.expiry_at
                  FROM " . $this->table_name . " a
                  JOIN tasks t ON a.task_id = t.task_id
                  WHERE a.id = :id
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $assignment_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function taskHasAnyAssignment($task_id) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE task_id = :task_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function taskHasActiveAssignment($task_id) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE task_id = :task_id AND status = 'active' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Member volunteer flow: claim an unassigned task.
     * Returns:
     * - true on success
     * - 'already_assigned' if any assignment exists
     * - 'expired' if task is expired
     * - false on unexpected failure
     */
    public function claim($task_id, $user_id) {
        $task_id = (int)$task_id;
        $user_id = (int)$user_id;
        if ($task_id <= 0 || $user_id <= 0) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // Lock the task row so two claim requests can't race.
            $taskStmt = $this->conn->prepare("SELECT expiry_at FROM tasks WHERE task_id = :task_id FOR UPDATE");
            $taskStmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
            $taskStmt->execute();
            $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
            if (!$task) {
                $this->conn->rollBack();
                return false;
            }

            $expiryAt = $task['expiry_at'] ?? null;
            if ($expiryAt && strtotime((string)$expiryAt) !== false && strtotime((string)$expiryAt) <= time()) {
                $this->conn->rollBack();
                return 'expired';
            }

            // If an ACTIVE assignment exists for this task, do not allow claiming.
            $checkStmt = $this->conn->prepare("SELECT id FROM " . $this->table_name . " WHERE task_id = :task_id AND status = 'active' LIMIT 1 FOR UPDATE");
            $checkStmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
            $checkStmt->execute();
            if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $this->conn->rollBack();
                return 'already_assigned';
            }

            // Create assignment (Option B: started_at is NULL until member clicks Start)
            $insertStmt = $this->conn->prepare(
                "INSERT INTO " . $this->table_name . " (task_id, user_id, status, started_at) VALUES (:task_id, :user_id, 'active', NULL)"
            );
            $insertStmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
            $insertStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $ok = $insertStmt->execute();
            if (!$ok) {
                $this->conn->rollBack();
                return false;
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            // If the unique_active_task constraint triggers under race conditions,
            // treat as already assigned/claimed.
            if (($e->getCode() ?? '') === '23000') {
                return 'already_assigned';
            }

            return false;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    public function complete($assignment_id, $completion_comment = null, $proof_path = null) {
        $query = "UPDATE " . $this->table_name . " a
                  JOIN tasks t ON a.task_id = t.task_id
                  SET a.status = 'completed',
                      a.completed_at = NOW(),
                      a.completion_comment = :comment,
                      a.proof_path = :proof
                  WHERE a.id = :id
                    AND a.status = 'active'
                    AND t.expiry_at > NOW()";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $assignment_id, PDO::PARAM_INT);
        $stmt->bindParam(':comment', $completion_comment);
        $stmt->bindParam(':proof', $proof_path);
        $stmt->execute();
        return (int)$stmt->rowCount() > 0;
    }

    public function expireOverdue() {
        // Step 6.2: Auto-expiry logic
        // Find active assignments where task expiry < NOW
        $query = "UPDATE " . $this->table_name . " a
                  JOIN tasks t ON a.task_id = t.task_id
                  SET a.status = 'expired'
                  WHERE a.status = 'active' AND t.expiry_at < NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return (int)$stmt->rowCount();
    }
}
