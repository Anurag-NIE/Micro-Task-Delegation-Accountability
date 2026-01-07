CREATE DATABASE IF NOT EXISTS micro_task_db;
USE micro_task_db;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    role ENUM('manager', 'member') NOT NULL,
    -- Adding these for auth even if not in strict PDF schema, as they are essential for a real app
    email VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255),
    INDEX(role)
);

-- Tasks Table
CREATE TABLE IF NOT EXISTS tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    estimated_minutes INT NOT NULL,
    priority ENUM('low', 'medium', 'high') NOT NULL,
    expiry_at DATETIME NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX(expiry_at)
);

-- Task Assignments Table
CREATE TABLE IF NOT EXISTS task_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    completion_comment TEXT NULL,
    proof_path VARCHAR(255) NULL,
    status ENUM('active', 'completed', 'expired') DEFAULT 'active',
    active_task_id INT GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN task_id ELSE NULL END) STORED,
    FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE(task_id, user_id),
    UNIQUE KEY unique_active_task (active_task_id),
    INDEX(status)
);

-- Task Reviews Table
CREATE TABLE IF NOT EXISTS task_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    decision ENUM('accepted', 'rejected') NOT NULL,
    reviewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES task_assignments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment_review (assignment_id)
);
