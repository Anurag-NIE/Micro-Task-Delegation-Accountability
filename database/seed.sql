USE micro_task_db;

-- Seed Users (Password is 'password123' hashed with DEFAULT_PASSWORD_HASH for demo purposes if needed, or we just insert raw for simple logic)
-- Assuming we might use simple login or hashing. Let's put placeholders.

INSERT INTO users (name, role, email, password_hash) VALUES 
('Alice Manager', 'manager', 'alice@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), -- password
('Bob Member', 'member', 'bob@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),   -- password
('Charlie Member', 'member', 'charlie@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password

-- Seed Tasks
INSERT INTO tasks (title, estimated_minutes, priority, expiry_at, created_by) VALUES
('Fix Login Bug', 60, 'high', DATE_ADD(NOW(), INTERVAL 2 HOUR), 1),
('Update Documentation', 30, 'medium', DATE_ADD(NOW(), INTERVAL 1 DAY), 1),
('Clean Database', 45, 'low', DATE_ADD(NOW(), INTERVAL 30 MINUTE), 1);

-- Seed Assignments
INSERT INTO task_assignments (task_id, user_id, status) VALUES
(1, 2, 'active'); -- Bob assigned to Fix Login Bug

INSERT INTO task_assignments (task_id, user_id, status, completed_at) VALUES
(2, 3, 'completed', NOW()); -- Charlie completed Update Documentation
