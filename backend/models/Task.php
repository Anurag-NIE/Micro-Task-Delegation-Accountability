<?php
require_once __DIR__ . '/../config/database.php';

class Task {
    private $conn;
    private $table_name = "tasks";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($title, $estimated_minutes, $priority, $expiry_at, $created_by) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (title, estimated_minutes, priority, expiry_at, created_by) 
                  VALUES (:title, :minutes, :priority, :expiry, :creator)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':minutes', $estimated_minutes);
        $stmt->bindParam(':priority', $priority);
        $stmt->bindParam(':expiry', $expiry_at);
        $stmt->bindParam(':creator', $created_by);

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function getAllActive() {
        $query = "SELECT t.*, u.name as creator_name 
                  FROM " . $this->table_name . " t
                  LEFT JOIN users u ON t.created_by = u.user_id
                  WHERE t.expiry_at > NOW()
                  ORDER BY t.expiry_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getBoardData() {
        // Board should show a single row per task. We select the latest assignment (if any)
        // plus its latest review decision (if reviewed). This supports returning rejected
        // tasks back to the pool while preserving assignment/review history.
        $query = "SELECT
                    t.*, u.name as creator_name,
                    a.id as assignment_id,
                    a.status as assignment_status,
                    au.name as assignee_name,
                    a.user_id as assignee_id,
                    a.started_at as assignment_started_at,
                    a.completed_at as assignment_completed_at,
                    r.decision as review_decision,
                    r.reviewed_at as review_reviewed_at
                  FROM " . $this->table_name . " t
                  LEFT JOIN users u ON t.created_by = u.user_id
                  LEFT JOIN (
                      SELECT a1.*
                      FROM task_assignments a1
                      INNER JOIN (
                          SELECT task_id, MAX(id) AS max_id
                          FROM task_assignments
                          GROUP BY task_id
                      ) la ON la.task_id = a1.task_id AND la.max_id = a1.id
                  ) a ON t.task_id = a.task_id
                  LEFT JOIN users au ON a.user_id = au.user_id
                  LEFT JOIN task_reviews r ON a.id = r.assignment_id
                  ORDER BY t.expiry_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getDetail($task_id) {
        $query = "SELECT
                    t.task_id,
                    t.title,
                    t.estimated_minutes,
                    t.priority,
                    t.expiry_at,
                    t.created_at,
                    t.created_by,
                    u.name as creator_name,
                    a.id as assignment_id,
                    a.user_id as assignee_id,
                    au.name as assignee_name,
                    a.started_at,
                    a.completed_at,
                    a.completion_comment,
                    a.proof_path,
                    a.status as assignment_status,
                    r.review_id,
                    r.decision as review_decision,
                    r.reviewed_at
                  FROM " . $this->table_name . " t
                  LEFT JOIN users u ON t.created_by = u.user_id
                  LEFT JOIN task_assignments a ON t.task_id = a.task_id
                  LEFT JOIN users au ON a.user_id = au.user_id
                  LEFT JOIN task_reviews r ON a.id = r.assignment_id
                  WHERE t.task_id = :task_id
                  ORDER BY a.id DESC
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
