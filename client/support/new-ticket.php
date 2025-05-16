<?php
/**
 * Client - Create New Support Ticket
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/utils/notifications.php';
require_once '../../shared/support/support-functions.php';

// Authentication check - will redirect to login if not authenticated
require_admin_auth();
require_admin_role(['client']);

// Page variables
$page_title = 'Create New Support Ticket';
$current_page = 'new_ticket';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/client/dashboard.php'],
    ['title' => 'Support', 'url' => '/client/support/my-tickets.php'],
    ['title' => 'New Ticket', 'url' => '#']
];

// Initialize variables
$subject = '';
$description = '';
$priority = 'medium';
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
        header('Location: new-ticket.php');
        exit;
    }

    // Validate input
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    
    // Validation checks
    if (empty($subject)) {
        $errors[] = 'Subject is required';
    } elseif (strlen($subject) > 255) {
        $errors[] = 'Subject must be 255 characters or less';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    
    if (!in_array($priority, ['low', 'medium', 'high'])) {
        $priority = 'medium';
    }
    
    // If no errors, create the ticket
    if (empty($errors)) {
        $user_id = $_SESSION['user_id'];
        $user_role = 'client';
        
        // Create the ticket
        $ticket_id = create_support_ticket($user_id, $user_role, $subject, $description, $priority);
        
        if ($ticket_id) {
            // Process file uploads if present
            if (!empty($_FILES['attachments']['name'][0])) {
                $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                                  'image/jpeg', 'image/png', 'application/zip', 'application/vnd.ms-excel', 
                                  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                
                $files = $_FILES['attachments'];
                
                // Create initial reply with attachments
                $initial_message = "Ticket created with the following description:\n\n$description";
                add_ticket_reply($ticket_id, $user_id, $user_role, $initial_message, 0, $files);
            }
            
            // Set success message
            set_notification('success', 'Your support ticket has been created successfully. We will respond shortly.');
            
            // Redirect to the ticket view
            header("Location: view-ticket.php?id=$ticket_id");
            exit;
        } else {
            $errors[] = 'There was a problem creating your ticket. Please try again.';
        }
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include template parts
include '../../shared/templates/admin-head.php';
include '../../shared/templates/admin-sidebar.php';
include '../../shared/templates/admin-header.php';
?>

<main class="admin-main">
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Create New Support Ticket</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data">
                            <!-- CSRF protection -->
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($subject); ?>" required>
                                <small class="text-muted">Please be specific about your issue</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low" <?php if ($priority === 'low') echo 'selected'; ?>>Low</option>
                                    <option value="medium" <?php if ($priority === 'medium') echo 'selected'; ?>>Medium</option>
                                    <option value="high" <?php if ($priority === 'high') echo 'selected'; ?>>High</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="6" required><?php echo htmlspecialchars($description); ?></textarea>
                                <small class="text-muted">Please provide as much detail as possible</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="attachments" class="form-label">Attachments</label>
                                <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                                <small class="text-muted">Allowed file types: PDF, Word, Excel, ZIP, JPG, PNG (Max 5MB each)</small>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="my-tickets.php" class="btn btn-light me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Submit Ticket</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../../shared/templates/admin-footer.php'; ?>