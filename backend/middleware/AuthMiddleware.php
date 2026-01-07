<?php

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php'); // Or return 401 for API
        exit;
    }
}

function requireRole($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
}
