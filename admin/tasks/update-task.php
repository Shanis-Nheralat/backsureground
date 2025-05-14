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
require_once '../../shared/events/email-events.php';
require_once '../../shared/user-functions.php';

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
    set_flash_notification('error', 'Invalid form submission. Please try again.');
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
    set_flash_notification('error', 'Invalid task data.');
    header('Location: manage-tasks.php');
    exit;
}

// Get original task
$original_task = get_task($task_id);
if (!$original_task) {
    set_flash_notification('error', 'Task not found.');
    header('Location: manage-tasks.php');
    exit;
}

// Store old status for comparison
$old_status = $original_task['status'];
$client_id = $original_task['client_id'];
$task_title = $original_task['title'];

// Update admin notes
add_task_notes($task_id, $admin_notes);

// Update status if changed
if ($original_task['status'] !== $status) {
    $user_id = $_SESSION['admin_user_id'];
    $role = 'admin';
    
    $updated = update_task_status($task_id, $status, $user_id, $role, $remarks);
    
    if ($updated) {
        set_flash_notification('success', 'Task status updated successfully.');
        
        // When updating task status to "completed"
        if ($status === 'completed' && $old_status !== 'completed') {
            // Get client information
            $client = get_client_by_id($client_id);
            
            // Add notification for client
            add_notification(
                $client_id,
                'client',
                'success',
                'Task Completed',
                'Your task "' . $task_title . '" has been completed.',
                '/client/tasks/history.php?task_id=' . $task_id,
                ['task_id' => $task_id]
            );
            
            // Send email notification to client
            trigger_email_event(
                'task_completed',
                [
                    'title' => 'Task Completed',
                    'client_name' => $client['name'],
                    'task_title' => $task_title,
                    'admin_name' => $_SESSION['user_name'],
                    'completion_date' => date('F j, Y'),
                    'task_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/client/tasks/history.php?task_id=' . $task_id
                ],
                $client['email'],
                $client['name']
            );
            
            // Show success message
            set_flash_notification('success', 'Task marked as completed and client notified.');
        }
    } else {
        set_flash_notification('error', 'Failed to update task status.');
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
            set_flash_notification('error', 'File upload error: ' . $result['message']);
        }
    }
    
    if ($upload_success > 0) {
        set_flash_notification('success', $upload_success . ' file(s) uploaded successfully.');
    }
}

// Redirect back to task management
header('Location: manage-tasks.php');
exit;
