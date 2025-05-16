<?php
/**
 * Client - View Support Ticket
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/utils/notifications.php';
require_once '../../shared/support/support-functions.php';

// Authentication check - will redirect to login if not authenticated
require_admin_auth();
require_admin_role(['client']);

// Get client ID
$client_id = $_SESSION['user_id'];

// Check for ticket ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_notification('error', 'Invalid ticket ID');
    header('Location: my-tickets.php');
    exit;
}

$ticket_id = intval($_GET['id']);

// Verify client can access this ticket
if (!can_access_ticket($ticket_id, $client_id, 'client')) {
    set_notification('error', 'You do not have permission to view this ticket');
    header('Location: my-tickets.php');
    exit;
}

// Get ticket details
$ticket = get_ticket($ticket_id);
if (!$ticket) {
    set_notification('error', 'Ticket not found');
    header('Location: my-tickets.php');
    exit;
}

// Initialize variables
$message = '';
$errors = [];

// Process form submission for reply or status change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
        header('Location: view-ticket.php?id=' . $ticket_id);
        exit;
    }
    
    // Check if this is a status update
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        
        // Only allow client to close their own tickets
        if ($new_status === 'closed') {
            if (update_ticket_status($ticket_id, 'closed', $client_id, 'client')) {
                set_notification('success', 'Ticket has been marked as closed');
                
                // Reload the ticket to get updated data
                $ticket = get_ticket($ticket_id);
            } else {
                set_notification('error', 'Failed to update ticket status');
            }
        }
    } else {
        // This is a reply
        $message = trim($_POST['message'] ?? '');
        
        if (empty($message)) {
            $errors[] = 'Reply message is required';
        }
        
        if (empty($errors)) {
            // Add reply
            $reply_id = add_ticket_reply(
                $ticket_id, 
                $client_id, 
                'client', 
                $message, 
                0, // not an internal note
                isset($_FILES['attachments']) ? $_FILES['attachments'] : []
            );
            
            if ($reply_id) {
                set_notification('success', 'Your reply has been added');
                $message = ''; // Clear the form
            } else {
                $errors[] = 'Failed to add reply. Please try again.';
            }
        }
    }
}

// Get ticket replies (excluding internal notes)
$replies = get_ticket_replies($ticket_id, false);

// Page variables
$page_title = 'View Ticket #' . $ticket_id;
$current_page = 'my_tickets';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/client/dashboard.php'],
    ['title' => 'Support Tickets', 'url' => '/client/support/my-tickets.php'],
    ['title' => 'Ticket #' . $ticket_id, 'url' => '#']
];

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
        <!-- Ticket header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                Ticket #<?php echo $ticket_id; ?>
                <span class="badge <?php 
                    switch($ticket['status']) {
                        case 'open': echo 'bg-success'; break;
                        case 'in_progress': echo 'bg-info'; break;
                        case 'closed': echo 'bg-secondary'; break;
                        case 'cancelled': echo 'bg-warning'; break;
                    }
                ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                </span>
            </h1>
            <div>
                <a href="my-tickets.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Tickets
                </a>
                
                <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'cancelled'): ?>
                <form method="post" class="d-inline-block ms-2">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="status" value="closed">
                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to close this ticket?')">
                        <i class="fas fa-times-circle me-2"></i> Close Ticket
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <!-- Ticket details card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold"><?php echo htmlspecialchars($ticket['subject']); ?></h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <p><strong>Status:</strong> 
                            <span class="badge <?php 
                                switch($ticket['status']) {
                                    case 'open': echo 'bg-success'; break;
                                    case 'in_progress': echo 'bg-info'; break;
                                    case 'closed': echo 'bg-secondary'; break;
                                    case 'cancelled': echo 'bg-warning'; break;
                                }
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Priority:</strong> 
                            <span class="badge <?php 
                                switch($ticket['priority']) {
                                    case 'low': echo 'bg-success'; break;
                                    case 'medium': echo 'bg-info'; break;
                                    case 'high': echo 'bg-danger'; break;
                                }
                            ?>">
                                <?php echo ucfirst($ticket['priority']); ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></p>
                    </div>
                    <div class="col-md-3">
                        <p><strong>Last Updated:</strong> 
                            <?php 
                            echo $ticket['last_reply_at'] 
                                ? date('M d, Y H:i', strtotime($ticket['last_reply_at'])) 
                                : date('M d, Y H:i', strtotime($ticket['created_at'])); 
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Conversation thread -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Conversation</h6>
            </div>
            <div class="card-body">
                <?php if (empty($replies)): ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No replies yet. Add a message below to start the conversation.</p>
                    </div>
                <?php else: ?>
                    <div class="ticket-conversation">
                        <?php foreach ($replies as $reply): ?>
                            <div class="ticket-reply mb-4 <?php echo ($reply['sender_role'] === 'client') ? 'ticket-reply-client' : 'ticket-reply-staff'; ?>">
                                <div class="ticket-reply-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if ($reply['sender_role'] === 'admin'): ?>
                                            <span class="badge bg-primary">Support Team</span>
                                        <?php elseif ($reply['sender_role'] === 'employee'): ?>
                                            <span class="badge bg-info">Staff: <?php echo htmlspecialchars($reply['sender_name']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">You</span>
                                        <?php endif; ?>
                                        <span class="text-muted ms-2 small">
                                            <?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="ticket-reply-id text-muted small">
                                        #<?php echo $reply['id']; ?>
                                    </div>
                                </div>
                                
                                <div class="ticket-reply-body mt-2 p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                                </div>
                                
                                <?php if (!empty($reply['attachments'])): ?>
                                    <div class="ticket-reply-attachments mt-2">
                                        <p class="mb-2"><strong>Attachments:</strong></p>
                                        <div class="list-group">
                                            <?php foreach ($reply['attachments'] as $attachment): ?>
                                                <a href="../../shared/download-support-file.php?id=<?php echo $attachment['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="fas <?php 
                                                            $ext = pathinfo($attachment['file_name'], PATHINFO_EXTENSION);
                                                            switch(strtolower($ext)) {
                                                                case 'pdf': echo 'fa-file-pdf'; break;
                                                                case 'doc':
                                                                case 'docx': echo 'fa-file-word'; break;
                                                                case 'xls':
                                                                case 'xlsx': echo 'fa-file-excel'; break;
                                                                case 'jpg':
                                                                case 'jpeg':
                                                                case 'png': echo 'fa-file-image'; break;
                                                                case 'zip': echo 'fa-file-archive'; break;
                                                                default: echo 'fa-file';
                                                            }
                                                        ?> me-2"></i>
                                                        <?php echo htmlspecialchars($attachment['file_name']); ?>
                                                    </div>
                                                    <span class="badge bg-primary rounded-pill">
                                                        <?php echo human_filesize($attachment['file_size']); ?>
                                                    </span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Reply form -->
        <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'cancelled'): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Add Reply</h6>
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
                            <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($message); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="attachments" class="form-label">Attachments</label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                            <small class="text-muted">Allowed file types: PDF, Word, Excel, ZIP, JPG, PNG (Max 5MB each)</small>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i> Send Reply
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> This ticket is closed. If you need further assistance, please create a new ticket.
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
// Helper function for file sizes
function human_filesize($bytes, $decimals = 2) {
    $size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
}
?>

<?php include '../../shared/templates/admin-footer.php'; ?>