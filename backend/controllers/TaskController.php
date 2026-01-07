<?php
require_once __DIR__ . '/../models/Task.php';

class TaskController {
    private $taskModel;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->taskModel = new Task($db);
    }

    public function getBoardData() {
        return $this->taskModel->getBoardData();
    }

    public function getTaskDetail($taskId) {
        $taskId = (int)$taskId;
        if ($taskId <= 0) {
            return null;
        }
        return $this->taskModel->getDetail($taskId);
    }

    public function create($data) {
        if (!isset($data['title']) || !isset($data['estimated_minutes']) || !isset($data['expiry_at'])) {
            return ['error' => 'Missing fields'];
        }

        // Normalize HTML datetime-local value (e.g. 2026-01-07T18:32)
        // into MySQL DATETIME format (YYYY-MM-DD HH:MM:SS).
        $expiryAt = $data['expiry_at'];
        if (is_string($expiryAt)) {
            $expiryAt = str_replace('T', ' ', $expiryAt);
            // If seconds are missing, append :00
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $expiryAt)) {
                $expiryAt .= ':00';
            }
        }

        $id = $this->taskModel->create(
            $data['title'],
            $data['estimated_minutes'],
            $data['priority'] ?? PRIORITY_MEDIUM,
            $expiryAt,
            $_SESSION['user_id'] // Creator
        );

        if ($id) {
            // If assignee provided, create assignment
            if (!empty($data['assignee_id'])) {
                require_once __DIR__ . '/../models/Assignment.php';
                $assignmentModel = new Assignment($this->db);
                $assignmentModel->assign($id, $data['assignee_id']);
            }
            
            return ['success' => true, 'id' => $id];
        }
        return ['error' => 'Failed to create task'];
    }

    public function assign($taskId, $assigneeId) {
        $taskId = (int)$taskId;
        $assigneeId = (int)$assigneeId;

        if ($taskId <= 0 || $assigneeId <= 0) {
            http_response_code(400);
            return ['error' => 'Invalid task or assignee'];
        }

        // Keep workflow simple: only assign if there is no assignment yet.
        require_once __DIR__ . '/../models/Assignment.php';
        $assignmentModel = new Assignment($this->db);

        if ($assignmentModel->taskHasActiveAssignment($taskId)) {
            http_response_code(400);
            return ['error' => 'Task is already assigned'];
        }

        if ($assignmentModel->assign($taskId, $assigneeId)) {
            return ['success' => true];
        }

        // Handle DB-level race: another request may have assigned in between checks.
        if ($assignmentModel->taskHasActiveAssignment($taskId)) {
            http_response_code(409);
            return ['error' => 'Task already claimed/assigned'];
        }

        http_response_code(500);
        return ['error' => 'Failed to assign'];
    }

    public function claim($taskId) {
        $taskId = (int)$taskId;
        if ($taskId <= 0) {
            http_response_code(400);
            return ['error' => 'Invalid task'];
        }

        if (($_SESSION['role'] ?? null) !== ROLE_MEMBER) {
            http_response_code(403);
            return ['error' => 'Forbidden: only members can claim tasks'];
        }

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        if ($currentUserId <= 0) {
            http_response_code(403);
            return ['error' => 'Forbidden'];
        }

        require_once __DIR__ . '/../models/Assignment.php';
        $assignmentModel = new Assignment($this->db);

        $result = $assignmentModel->claim($taskId, $currentUserId);
        if ($result === true) {
            return ['success' => true];
        }

        // claim() returns a string error reason or false
        if ($result === 'expired') {
            http_response_code(400);
            return ['error' => 'Task expired'];
        }
        if ($result === 'already_assigned') {
            http_response_code(409);
            return ['error' => 'Task already claimed/assigned'];
        }

        http_response_code(500);
        return ['error' => 'Failed to claim task'];
    }
}
