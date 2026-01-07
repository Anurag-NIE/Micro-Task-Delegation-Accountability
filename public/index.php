<?php
session_start();

require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/config/constants.php';
require_once __DIR__ . '/../backend/middleware/AuthMiddleware.php';

// Simple Router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Database Connection
$database = new Database();
$db = $database->getConnection();

// Route Dispatcher
if ($uri === '/' || $uri === '/index.php') {
    // Check if logged in, else redirect to login
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
    header('Location: /board');
    exit;
}

// Auth Routes
if ($uri === '/login') {
    if ($method === 'POST') {
        require_once __DIR__ . '/../backend/controllers/AuthController.php';
        $auth = new AuthController($db);
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Handle form submit vs JSON API
        $email = $_POST['email'] ?? $data['email'] ?? '';
        $pass = $_POST['password'] ?? $data['password'] ?? '';
        
        if ($auth->login($email, $pass)) {
             if (isset($_POST['email'])) { // Form submit
                 header('Location: /board');
             } else {
                 echo json_encode(['success' => true]);
             }
        } else {
             if (isset($_POST['email'])) {
                 echo "Login failed. <a href='/login'>Try again</a>";
             } else {
                 echo json_encode(['success' => false]);
             }
        }
    } else {
        include __DIR__ . '/../backend/views/login.php';
    }
    exit;
}

if ($uri === '/logout') {
    session_destroy();
    header('Location: /login');
    exit;
}

// Protected Routes
requireAuth(); // Global guard for below routes

if ($uri === '/board') {
    include __DIR__ . '/../backend/views/board.php';
    exit;
}

if ($uri === '/create-task') {
    requireRole(ROLE_MANAGER);
    include __DIR__ . '/../backend/views/create_task.php';
    exit;
}

if ($uri === '/reviews') {
    requireRole(ROLE_MANAGER);
    include __DIR__ . '/../backend/views/review.php';
    exit;
}

if ($uri === '/dashboard') {
    include __DIR__ . '/../backend/views/dashboard.php';
    exit;
}

// Task Detail Page
if (preg_match('#^/task/(\d+)$#', $uri, $matches)) {
    $taskId = (int)$matches[1];
    $_GET['task_id'] = $taskId;
    include __DIR__ . '/../backend/views/task_detail.php';
    exit;
}

// API Routes
header('Content-Type: application/json');

if ($uri === '/api/board') {
    require_once __DIR__ . '/../backend/controllers/TaskController.php';
    $controller = new TaskController($db);
    echo json_encode($controller->getBoardData());
    exit;
}

if ($uri === '/api/dashboard' && $method === 'GET') {
    require_once __DIR__ . '/../backend/controllers/DashboardController.php';
    $controller = new DashboardController($db);
    echo json_encode($controller->getDashboardData());
    exit;
}

if (preg_match('#^/api/task/(\d+)$#', $uri, $matches) && $method === 'GET') {
    require_once __DIR__ . '/../backend/controllers/TaskController.php';
    $controller = new TaskController($db);
    $taskId = (int)$matches[1];
    $detail = $controller->getTaskDetail($taskId);
    if (!$detail) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
    } else {
        echo json_encode($detail);
    }
    exit;
}

if ($uri === '/api/tasks/create' && $method === 'POST') {
    requireRole(ROLE_MANAGER);
    require_once __DIR__ . '/../backend/controllers/TaskController.php';
    $controller = new TaskController($db);
    $data = json_decode(file_get_contents('php://input'), true);
    echo json_encode($controller->create($data));
    exit;
}

if (preg_match('#^/api/tasks/(\d+)/assign$#', $uri, $matches) && $method === 'POST') {
    requireRole(ROLE_MANAGER);
    require_once __DIR__ . '/../backend/controllers/TaskController.php';
    $controller = new TaskController($db);
    $taskId = (int)$matches[1];
    $data = json_decode(file_get_contents('php://input'), true);
    $assigneeId = is_array($data) ? (int)($data['assignee_id'] ?? 0) : 0;
    echo json_encode($controller->assign($taskId, $assigneeId));
    exit;
}

if (preg_match('#^/api/tasks/(\d+)/claim$#', $uri, $matches) && $method === 'POST') {
    requireRole(ROLE_MEMBER);
    require_once __DIR__ . '/../backend/controllers/TaskController.php';
    $controller = new TaskController($db);
    $taskId = (int)$matches[1];
    echo json_encode($controller->claim($taskId));
    exit;
}

if (strpos($uri, '/api/assignments/') === 0) {
    require_once __DIR__ . '/../backend/controllers/AssignmentController.php';
    $controller = new AssignmentController($db);
    
    // Extract ID and action
    // /api/assignments/123/start
    $parts = explode('/', $uri);
    $id = $parts[3];
    $action = $parts[4] ?? '';
    
    if ($action === 'start') {
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
        } else {
            echo json_encode($controller->start($id));
        }
    } elseif ($action === 'complete') {
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
        } else {
            echo json_encode($controller->complete($id));
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }
    exit;
}

if (strpos($uri, '/api/reviews/') === 0 && $method === 'POST') {
    requireRole(ROLE_MANAGER);
    require_once __DIR__ . '/../backend/controllers/ReviewController.php';
    $controller = new ReviewController($db);
    $parts = explode('/', $uri);
    $assignmentId = $parts[3];
    $data = json_decode(file_get_contents('php://input'), true);
    $decision = is_array($data) ? ($data['decision'] ?? null) : null;
    echo json_encode($controller->submitReview($assignmentId, $decision));
    exit;
}

// 404
http_response_code(404);
echo json_encode(['error' => 'Not Found']);
