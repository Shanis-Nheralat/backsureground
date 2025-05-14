<?php
/**
 * Client Task Submission Form
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/tasks/task-functions.php';
require_once '../../shared/utils/notifications.php';
require_once '../../shared/events/email-events.php';
require_once '../../shared/user-functions.php';

// Ensure client is logged in and has the client role
require_admin_auth();
require_admin_role(['client']);

// Current user info
$client_id = $_SESSION['admin_user_id'];
$client_name = $_SESSION['user_name'] ?? 'Client';

// Page variables
$page_title = 'Submit New Task';
$current_page = 'task_submission';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Submit Task', 'url' => '#']
];

// Form submission processing
$form_submitted = false;
$submission_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_notification('error', 'Invalid form submission. Please try again.');
    } else {
        // Validate required fields
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        
        $errors = [];
        
        if (empty($title)) {
            $errors[] = 'Task title is required';
        }
        
        if (empty($description)) {
            $errors[] = 'Task description is required';
        }
        
        // If no errors, proceed with task creation
        if (empty($errors)) {
            // Create task
            $task_id = create_task(
                $client_id,
                $title,
                $description,
                $priority,
                $deadline
            );
            
            if ($task_id) {
                $form_submitted = true;
                
                // Process file uploads if present
                $file_count = 0;
                if (!empty($_FILES['task_files']['name'][0])) {
                    $file_count = count($_FILES['task_files']['name']);
                    
                    // Check if not exceeding max file count (5)
                    if ($file_count > 5) {
                        set_flash_notification('warning', 'Only the first 5 files were processed.');
                        $file_count = 5;
                    }
                    
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
                        $result = upload_task_file($task_id, $file, 'client');
                        
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
                
                // Add notification for admin
                $admins = get_admins(); // Function to get all admin users
                foreach ($admins as $admin) {
                    add_notification(
                        $admin['id'],
                        'admin',
                        'info',
                        'New Task Submitted',
                        'Client ' . $client_name . ' has submitted a new task: ' . $title,
                        '/admin/tasks/manage-tasks.php?task_id=' . $task_id,
                        ['task_id' => $task_id, 'client_id' => $client_id]
                    );
                }
                
                // Send email notification to admins
                foreach ($admins as $admin) {
                    trigger_email_event(
                        'task_submitted',
                        [
                            'title' => 'New Task Submitted',
                            'admin_name' => $admin['name'],
                            'client_name' => $client_name,
                            'task_title' => $title,
                            'task_priority' => $priority,
                            'task_deadline' => $deadline ?? 'Not specified',
                            'task_description' => $description,
                            'file_count' => $file_count,
                            'task_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/admin/tasks/manage-tasks.php?task_id=' . $task_id
                        ],
                        $admin['email'],
                        $admin['name']
                    );
                }
                
                set_flash_notification('success', 'Your task has been submitted successfully. We will process it shortly.');
                
                // Redirect to avoid form resubmission
                header('Location: history.php');
                exit;
            } else {
                set_flash_notification('error', 'Failed to create task. Please try again.');
            }
        } else {
            foreach ($errors as $error) {
                set_flash_notification('error', $error);
            }
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include template parts
include '../../shared/templates/client-head.php';
include '../../shared/templates/client-sidebar.php';
include '../../shared/templates/client-header.php';
?>

<main class="client-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
        </div>
        
        <?php display_flash_notifications(); ?>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Task Details</h6>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <!-- CSRF protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Task Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Task Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="deadline" class="form-label">Deadline (optional)</label>
                            <input type="date" class="form-control" id="deadline" name="deadline">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="task_files" class="form-label">Attachments (Max 5 files, 10MB each)</label>
                        <input type="file" class="form-control" id="task_files" name="task_files[]" multiple>
                        <small class="text-muted">
                            Allowed file types: PDF, DOCX, XLSX, JPG, PNG, ZIP
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Submit Task</button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include '../../shared/templates/client-footer.php'; ?>
