<?php
require_once __DIR__ . '/../models/Review.php';

class ReviewController {
    private $reviewModel;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->reviewModel = new Review($db);
    }

    public function submitReview($assignmentId, $decision) {
        if (($_SESSION['role'] ?? null) !== ROLE_MANAGER) {
            http_response_code(403);
            return ['error' => 'Forbidden: only managers can submit reviews'];
        }

        $assignmentId = (int)$assignmentId;
        if ($assignmentId <= 0) {
            http_response_code(400);
            return ['error' => 'Invalid assignment'];
        }

        // Enforce exact PDF enum values: 'accepted' | 'rejected'
        if (!in_array($decision, [REVIEW_ACCEPTED, REVIEW_REJECTED], true)) {
            http_response_code(400);
            return ['error' => 'Invalid decision'];
        }

        if ($this->reviewModel->hasReviewForAssignment($assignmentId)) {
            http_response_code(409);
            return ['error' => 'This assignment is already reviewed'];
        }

        if ($this->reviewModel->create($assignmentId, $decision)) {
            // Return fresh dashboard stats so UI can refresh immediately
            require_once __DIR__ . '/DashboardController.php';
            $dashboard = new DashboardController($this->db);
            return ['success' => true, 'dashboard' => $dashboard->getDashboardData()];
        }

        http_response_code(500);
        return ['error' => 'Failed to submit review'];
    }
}
