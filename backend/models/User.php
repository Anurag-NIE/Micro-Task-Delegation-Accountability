<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table_name = "users";

    public $user_id;
    public $name;
    public $role;
    public $email;
    public $password_hash;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getMembers() {
        $query = "SELECT user_id, name
                  FROM " . $this->table_name . "
                  WHERE role = :role
                  ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $role = ROLE_MEMBER;
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getScore($user_id) {
        // Placeholder for reliability score calculation logic (Step 6.4)
        // For now, return a dummy score or simple calculation
        return 95; 
    }
}
