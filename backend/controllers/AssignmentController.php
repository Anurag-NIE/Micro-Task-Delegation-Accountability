<?php
require_once __DIR__ . '/../models/Assignment.php';

class AssignmentController {
    private $assignmentModel;

    public function __construct($db) {
        $this->assignmentModel = new Assignment($db);
    }

    public function start($id) {
        if (($_SESSION['role'] ?? null) !== ROLE_MEMBER) {
            http_response_code(403);
            return ['error' => 'Forbidden: only members can start assignments'];
        }

        $assignmentId = (int)$id;
        if ($assignmentId <= 0) {
            http_response_code(400);
            return ['error' => 'Invalid assignment id'];
        }

        $assignment = $this->assignmentModel->getById($assignmentId);
        if (!$assignment) {
            http_response_code(404);
            return ['error' => 'Assignment not found'];
        }

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        if ((int)$assignment['user_id'] !== $currentUserId) {
            http_response_code(403);
            return ['error' => 'Forbidden: you can only start your own assignment'];
        }

        if (($assignment['status'] ?? null) !== ASSIGNMENT_ACTIVE) {
            http_response_code(400);
            return ['error' => 'Only active assignments can be started'];
        }

        if (!empty($assignment['started_at'])) {
            return ['success' => true, 'message' => 'Already started'];
        }

        // Prevent starting work after task expiry (handles the gap before the expiry job runs)
        $expiryAt = $assignment['expiry_at'] ?? null;
        if ($expiryAt && strtotime((string)$expiryAt) !== false && strtotime((string)$expiryAt) <= time()) {
            http_response_code(400);
            return ['error' => 'Task expired'];
        }

        if ($this->assignmentModel->start($assignmentId)) {
            return ['success' => true];
        }

        http_response_code(500);
        return ['error' => 'Failed to start (maybe expired)'];
    }

    public function complete($id) {
        if (($_SESSION['role'] ?? null) !== ROLE_MEMBER) {
            http_response_code(403);
            return ['error' => 'Forbidden: only members can complete assignments'];
        }

        $assignmentId = (int)$id;
        if ($assignmentId <= 0) {
            http_response_code(400);
            return ['error' => 'Invalid assignment id'];
        }

        $assignment = $this->assignmentModel->getById($assignmentId);
        if (!$assignment) {
            http_response_code(404);
            return ['error' => 'Assignment not found'];
        }

        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        if ((int)$assignment['user_id'] !== $currentUserId) {
            http_response_code(403);
            return ['error' => 'Forbidden: you can only complete your own assignment'];
        }

        if (($assignment['status'] ?? null) !== ASSIGNMENT_ACTIVE) {
            http_response_code(400);
            return ['error' => 'Only active assignments can be completed'];
        }

        // Option B lifecycle: must start before completing
        if (empty($assignment['started_at'])) {
            http_response_code(400);
            return ['error' => 'Start the assignment before completing it'];
        }

        // Prevent completing after expiry (handles the gap before the expiry job runs)
        $expiryAt = $assignment['expiry_at'] ?? null;
        if ($expiryAt && strtotime((string)$expiryAt) !== false && strtotime((string)$expiryAt) <= time()) {
            http_response_code(400);
            return ['error' => 'Task expired'];
        }

        // Optional completion comment and proof upload
        $completionComment = null;

        // Support JSON body (board) and multipart/form-data (task detail proof upload)
        $raw = file_get_contents('php://input');
        $json = null;
        if (is_string($raw) && strlen($raw) > 0) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        if (isset($_POST['comment'])) {
            $completionComment = trim((string)$_POST['comment']);
        } elseif (is_array($json) && isset($json['comment'])) {
            $completionComment = trim((string)$json['comment']);
        }

        if ($completionComment === '') {
            $completionComment = null;
        }

        $proofPath = null;
        if (isset($_FILES['proof']) && is_array($_FILES['proof']) && ($_FILES['proof']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($_FILES['proof']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                http_response_code(400);
                return ['error' => 'Proof upload failed'];
            }

            $maxBytes = 5 * 1024 * 1024; // 5 MB
            if (($_FILES['proof']['size'] ?? 0) > $maxBytes) {
                http_response_code(400);
                return ['error' => 'Proof file too large (max 5MB)'];
            }

            $tmpName = (string)($_FILES['proof']['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                http_response_code(400);
                return ['error' => 'Invalid proof upload'];
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmpName) ?: '';
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'application/pdf' => 'pdf',
            ];
            if (!isset($allowed[$mime])) {
                http_response_code(400);
                return ['error' => 'Unsupported proof file type'];
            }

            $ext = $allowed[$mime];
            $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
            $uploadDir = __DIR__ . '/../../public/uploads/proofs';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $dest = $uploadDir . '/' . $fileName;

            if (!move_uploaded_file($tmpName, $dest)) {
                http_response_code(500);
                return ['error' => 'Failed to store proof file'];
            }

            $proofPath = '/uploads/proofs/' . $fileName;
        }

        if ($this->assignmentModel->complete($assignmentId, $completionComment, $proofPath)) {
            return ['success' => true];
        }

        http_response_code(400);
        return ['error' => 'Failed to complete (maybe expired)'];
    }
}
