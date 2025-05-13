<?php
/**
 * AJAX Handler for Admin Task Details
 * 
 * Returns task details in JSON format
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/tasks/task-functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Ensure admin is logged in
if (!is_admin_logged_in() || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]);
    exit;
}

// Get task ID from request
$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;

if (!$task_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid task ID'
    ]);
    exit;
}

// Get task details
$task = get_task($task_id);

if (!$task) {
    echo json_encode([
        'success' => false,
        'message' => 'Task not found'
    ]);
    exit;
}

// Get client name
try {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$task['client_id']]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    $client_name = $client ? $client['name'] : 'Unknown Client';
} catch (PDOException $e) {
    error_log('Get Client Name Error: ' . $e->getMessage());
    $client_name = 'Unknown Client';
}

// Get client files
$client_files = get_task_files($task_id, 'client');

// Get admin files
$admin_files = get_task_files($task_id, 'admin');

// Return JSON response
echo json_encode([
    'success' => true,
    'task' => $task,
    'client_name' => $client_name,
    'client_files' => $client_files,
    'admin_files' => $admin_files
]);