<?php
/**
 * Update Task Handler
 * 
 * Processes task updates from admin
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/tasks/task-functions.php';
require_once '../../shared/utils/notifications.php';

// Authentication check
require_admin_auth();
require_admin_role(['admin']);

// Redirect if not POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-tasks.php');
    exit;
}

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    set_notification('error', 'Invalid form submission. Please try again.');
    header('Location: manage-tasks.php');
    exit;
}

// Get form data
$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$status = $_POST['status'] ?? '';
$admin_notes = $_POST['admin_notes'] ?? '';
$remarks = $_POST['remarks'] ?? '';

// Validate data
if (!$task_id || !in_array($status, ['submitted', 'in_progress', 'completed', 'cancelled'])) {
    set_notification('error', 'Invalid task data.');
    header('Location: manage-tasks.php');
    exit;
}

// Get original task
$original_task = get_task($task_id);
if (!$original_task) {
    set_notification('error', 'Task not found.');
    header('Location: manage-tasks.php');
    exit;
}

// Update admin notes
add_task_notes($task_id, $admin_notes);

// Update status if changed
if ($original_task['status'] !== $status) {
    $user_id = $_SESSION['admin_user_id'];
    $role = 'admin';
    
    $updated = update_task_status($task_id, $status, $user_id, $role, $remarks);
    
    if ($updated) {
        set_notification('success', 'Task status updated successfully.');
        
        // Notify client if task completed
        if ($status === 'completed') {
            // This would be implemented in the notifications module
            // notify_client($original_task['client_id'], 'Task Completed', 'Your task has been completed.');
            
            set_notification('info', 'Client has been notified about the completed task.');
        }
    } else {
        set_notification('error', 'Failed to update task status.');
    }
}

// Process file uploads if present
if (!empty($_FILES['task_files']['name'][0])) {
    $file_count = count($_FILES['task_files']['name']);
    $upload_success = 0;
    
    for ($i = 0; $i < $file_count; $i++) {
        $file = [
            'name' => $_FILES['task_files']['name'][$i],
            'type' => $_FILES['task_files']['type'][$i],
            'tmp_name' => $_FILES['task_files']['tmp_name'][$i],
            'error' => $_FILES['task_files']['error'][$i],
            'size' => $_FILES['task_files']['size'][$i]
        ];
        
        // Skip empty files
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        // Upload file
        $result = upload_task_file($task_id, $file, 'admin');
        
        if ($result['success']) {
            $upload_success++;
        } else {
            set_notification('error', 'File upload error: ' . $result['message']);
        }
    }
    
    if ($upload_success > 0) {
        set_notification('success', $upload_success . ' file(s) uploaded successfully.');
    }
}

// Redirect back to task management
header('Location: manage-tasks.php');
exit;