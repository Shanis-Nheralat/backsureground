<?php
/**
 * AJAX Handler for Task Details
 * 
 * Returns task details in JSON format
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/tasks/task-functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Ensure client is logged in and has the client role
if (!is_admin_logged_in() || $_SESSION['admin_role'] !== 'client') {
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

// Check if client owns this task
$client_id = $_SESSION['admin_user_id'];
if (!user_owns_task($task_id, $client_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to view this task'
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

// Get client files
$client_files = get_task_files($task_id, 'client');

// Get admin files
$admin_files = get_task_files($task_id, 'admin');

// Return JSON response
echo json_encode([
    'success' => true,
    'task' => $task,
    'client_files' => $client_files,
    'admin_files' => $admin_files
]);