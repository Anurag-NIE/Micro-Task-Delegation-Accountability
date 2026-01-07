<?php
require_once __DIR__ . '/../config/database.php';

class Review {
    private $conn;
    private $table_name = "task_reviews";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function hasReviewForAssignment($assignment_id) {
        $query = "SELECT review_id FROM " . $this->table_name . " WHERE assignment_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $assignment_id, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($assignment_id, $decision) {
        // Prevent duplicate reviews for the same assignment
        if ($this->hasReviewForAssignment((int)$assignment_id)) {
            return false;
        }

        $query = "INSERT INTO " . $this->table_name . " (assignment_id, decision) VALUES (:id, :decision)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $assignment_id);
        $stmt->bindParam(':decision', $decision);
        return $stmt->execute();
    }

    public function getPendingReviews() {
        // Find completed assignments that don't have a review yet
        $query = "SELECT a.*, t.title, u.name as assignee_name 
                  FROM task_assignments a
                  JOIN tasks t ON a.task_id = t.task_id
                  JOIN users u ON a.user_id = u.user_id
                  LEFT JOIN task_reviews r ON a.id = r.assignment_id
                  WHERE a.status = 'completed' AND r.review_id IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
