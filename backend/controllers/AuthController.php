<?php
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $db;
    private $userModel;

    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($db);
    }

    public function login($email, $password) {
        $user = $this->userModel->findByEmail($email);
        
        if ($user) {
            // For demo, checking password hash. 
            // In strict PDF mode, maybe we skip hash, but creating a secure habit is better.
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                return true;
            }
        }
        return false;
    }

    public function logout() {
        session_destroy();
    }
}
