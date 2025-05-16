<?php
/**
 * Admin - Support Ticket Details
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/utils/notifications.php';
require_once '../../shared/support/support-functions.php';

// Authentication check - will redirect to login if not authenticated
require_admin_auth();
require_admin_role(['admin']);

// Check for ticket ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_notification('error', 'Invalid ticket ID');
    header('Location: all-tickets.php');
    exit;
}

$ticket_id = intval($_GET['id']);

// Get ticket details
$ticket = get_ticket($ticket_id);
if (!$ticket) {
    set_notification('error', 'Ticket not found');
    header('Location: all-tickets.php');
    exit;
}

// Get employees for assignment
$employees = get_available_employees();

// Initialize variables
$message = '';
$is_internal = false;
$errors = [];

// Process form submission for reply, status change, or assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
        header('Location: ticket-details.php?id=' . $ticket_id);
        exit;
    }
    
    // Check for type of action
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_status') {
            // Update status
            $new_status = $_POST['status'] ?? '';
            
            if (in_array($new_status, ['open', 'in_progress', 'closed', 'cancelled'])) {
                if (update_ticket_status($ticket_id, $new_status, $_SESSION['user_id'], 'admin')) {
                    set_notification('success', 'Ticket status has been updated to ' . ucfirst(str_replace('_', ' ', $new_status)));
                    
                    // Reload the ticket to get updated data
                    $ticket = get_ticket($ticket_id);
                } else {
                    set_notification('error', 'Failed to update ticket status');
                }
            }
        } elseif ($_POST['action'] === 'assign_ticket') {
            // Assign ticket to employee
            $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
            
            if ($employee_id > 0) {
                if (assign_ticket_to_employee($ticket_id, $employee_id, $_SESSION['user_id'])) {
                    
                    // Add optional assignment message as internal note
                    if (!empty($_POST['assignment_message'])) {
                        add_ticket_reply(
                            $ticket_id,
                            $_SESSION['user_id'],
                            'admin',
                            trim($_POST['assignment_message']),
                            1, // internal note
                            []
                        );
                    }
                    
                    set_notification('success', 'Ticket has been assigned');
                    
                    // Reload the ticket to get updated data
                    $ticket = get_ticket($ticket_id);
                } else {
                    set_notification('error', 'Failed to assign ticket');
                }
            } else {
                set_notification('error', 'Please select a valid employee');
            }
        }
    } else {
        // This is a reply
        $message = trim($_POST['message'] ?? '');
        $is_internal = isset($_POST['is_internal']) && $_POST['is_internal'] === '1';
        
        if (empty($message)) {
            $errors[] = 'Reply message is required';
        }
        
        if (empty($errors)) {
            // Add reply
            $reply_id = add_ticket_reply(
                $ticket_id, 
                $_SESSION['user_id'], 
                'admin', 
                $message, 
                $is_internal ? 1 : 0,
                isset($_FILES['attachments']) ? $_FILES['attachments'] : []
            );
            
            if ($reply_id) {
                set_notification('success', $is_internal ? 'Internal note added' : 'Your reply has been added');
                $message = ''; // Clear the form
                $is_internal = false;
            } else {
                $errors[] = 'Failed to add reply. Please try again.';
            }
        }
    }
}

// Get ticket replies (including internal notes for admin)
$replies = get_ticket_replies($ticket_id, true);

// Page variables
$page_title = 'Ticket #' . $ticket_id;
$current_page = 'support_tickets';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/admin/dashboard.php'],
    ['title' => 'Support Tickets', 'url' => '/admin/support/all-tickets.php'],
    ['title' => 'Ticket #' . $ticket_id, 'url' => '#']
];

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for quick action flags in URL
$show_assign_modal = isset($_GET['assign']) && $_GET['assign'] == 1;
$show_close_modal = isset($_GET['close']) && $_GET['close'] == 1;

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
                <a href="all-tickets.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Tickets
                </a>
                
                <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'cancelled'): ?>
                <div class="btn-group ms-2">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog me-2"></i> Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#assignModal">
                                <i class="fas fa-user-plus me-2"></i> Assign to Employee
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#statusModal">
                                <i class="fas fa-exchange-alt me-2"></i> Change Status
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-trash-alt me-2"></i> Delete Ticket
                            </a>
                        </li>
                    </ul>
                </div>
                <?php else: ?>
                <button type="button" class="btn btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#statusModal">
                    <i class="fas fa-redo me-2"></i> Reopen Ticket
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <!-- Ticket details card -->
        <div class="row mb-4">
            <!-- Ticket information -->
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold"><?php echo htmlspecialchars($ticket['subject']); ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
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
                                <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Client:</strong> <?php echo htmlspecialchars($ticket['user_name']); ?></p>
                                <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($ticket['user_email']); ?>"><?php echo htmlspecialchars($ticket['user_email']); ?></a></p>
                                <p><strong>Assigned To:</strong> 
                                    <?php if ($ticket['assigned_name']): ?>
                                        <?php echo htmlspecialchars($ticket['assigned_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not Assigned</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Response tools -->
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <button type="button" class="btn btn-primary btn-block" data-bs-toggle="modal" data-bs-target="#replyModal">
                                <i class="fas fa-reply me-2"></i> Reply to Client
                            </button>
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-secondary btn-block" data-bs-toggle="modal" data-bs-target="#internalNoteModal">
                                <i class="fas fa-sticky-note me-2"></i> Add Internal Note
                            </button>
                        </div>
                        <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'cancelled'): ?>
                        <div class="mb-3">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="status" value="closed">
                                <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('Are you sure you want to close this ticket?')">
                                    <i class="fas fa-times-circle me-2"></i> Close Ticket
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
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
                            <div class="ticket-reply mb-4 <?php 
                                if ($reply['is_internal_note']) {
                                    echo 'ticket-reply-internal';
                                } elseif ($reply['sender_role'] === 'client') {
                                    echo 'ticket-reply-client';
                                } elseif ($reply['sender_role'] === 'employee') {
                                    echo 'ticket-reply-employee';
                                } else {
                                    echo 'ticket-reply-admin';
                                }
                            ?>">
                                <div class="ticket-reply-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if ($reply['is_internal_note']): ?>
                                            <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i> Internal Note</span>
                                        <?php elseif ($reply['sender_role'] === 'admin'): ?>
                                            <span class="badge bg-primary">Support Team</span>
                                        <?php elseif ($reply['sender_role'] === 'employee'): ?>
                                            <span class="badge bg-info">Staff: <?php echo htmlspecialchars($reply['sender_name']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Client: <?php echo htmlspecialchars($reply['sender_name']); ?></span>
                                        <?php endif; ?>
                                        <span class="text-muted ms-2 small">
                                            <?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="ticket-reply-id text-muted small">
                                        #<?php echo $reply['id']; ?>
                                    </div>
                                </div>
                                
                                <div class="ticket-reply-body mt-2 p-3 rounded <?php echo $reply['is_internal_note'] ? 'bg-warning-subtle' : 'bg-light'; ?>">
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
        
        <!-- Quick reply form -->
        <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'cancelled'): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Quick Reply</h6>
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
                            <textarea class="form-control" name="message" rows="4" placeholder="Type your reply here..."><?php echo htmlspecialchars($message); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="input-group">
                                    <input type="file" class="form-control" name="attachments[]" multiple>
                                </div>
                                <small class="text-muted">Max 3 files, 10MB each (.pdf, .docx, .xlsx, .jpg, .png, .zip)</small>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="form-check form-check-inline float-start mt-2">
                                    <input class="form-check-input" type="checkbox" id="internalCheck" name="is_internal" value="1" <?php if ($is_internal) echo 'checked'; ?>>
                                    <label class="form-check-label" for="internalCheck">Internal Note</label>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i> Send
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> This ticket is closed. You can reopen it from the Actions menu if needed.
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="replyModalLabel">Reply to Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="is_internal" value="0">
                    
                    <div class="mb-3">
                        <label for="modalMessage" class="form-label">Message</label>
                        <textarea class="form-control" id="modalMessage" name="message" rows="10" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modalAttachments" class="form-label">Attachments</label>
                        <input type="file" class="form-control" id="modalAttachments" name="attachments[]" multiple>
                        <small class="text-muted">Max 3 files, 10MB each (.pdf, .docx, .xlsx, .jpg, .png, .zip)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Internal Note Modal -->
<div class="modal fade" id="internalNoteModal" tabindex="-1" aria-labelledby="internalNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="internalNoteModalLabel">Add Internal Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="is_internal" value="1">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-lock me-2"></i> Internal notes are only visible to admin and employees, not to clients.
                    </div>
                    
                    <div class="mb-3">
                        <label for="internalModalMessage" class="form-label">Internal Note</label>
                        <textarea class="form-control" id="internalModalMessage" name="message" rows="10" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="internalModalAttachments" class="form-label">Attachments</label>
                        <input type="file" class="form-control" id="internalModalAttachments" name="attachments[]" multiple>
                        <small class="text-muted">Max 3 files, 10MB each (.pdf, .docx, .xlsx, .jpg, .png, .zip)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Add Internal Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignModalLabel">Assign Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="assign_ticket">
                    
                    <div class="mb-3">
                        <label for="employeeSelect" class="form-label">Assign to Employee</label>
                        <select class="form-select" id="employeeSelect" name="employee_id" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php if ($ticket['assigned_to'] == $employee['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($employee['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assignmentMessage" class="form-label">Assignment Message (Internal Note)</label>
                        <textarea class="form-control" id="assignmentMessage" name="assignment_message" rows="3" placeholder="Optional message about this assignment"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusModalLabel">Change Ticket Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_status">
                    
                    <div class="mb-3">
                        <label for="statusSelect" class="form-label">New Status</label>
                        <select class="form-select" id="statusSelect" name="status" required>
                            <option value="">-- Select Status --</option>
                            <option value="open" <?php if ($ticket['status'] === 'open') echo 'selected'; ?>>Open</option>
                            <option value="in_progress" <?php if ($ticket['status'] === 'in_progress') echo 'selected'; ?>>In Progress</option>
                            <option value="closed" <?php if ($ticket['status'] === 'closed') echo 'selected'; ?>>Closed</option>
                            <option value="cancelled" <?php if ($ticket['status'] === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Delete Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> Warning: This action cannot be undone.
                </div>
                <p>Are you sure you want to delete this ticket and all its replies?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="batch-actions.php?action=delete&ticket_ids[]=<?php echo $ticket_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-danger">Delete Ticket</a>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function for file sizes
function human_filesize($bytes, $decimals = 2) {
    $size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
}
?>

<script>
    // Show assign modal if URL parameter is set
    <?php if ($show_assign_modal): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var assignModal = new bootstrap.Modal(document.getElementById('assignModal'));
        assignModal.show();
    });
    <?php endif; ?>
    
    // Show close modal if URL parameter is set
    <?php if ($show_close_modal): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
        document.getElementById('statusSelect').value = 'closed';
        statusModal.show();
    });
    <?php endif; ?>
</script>

<style>
    /* Custom styling for ticket replies */
    .ticket-reply {
        margin-bottom: 1.5rem;
    }
    
    .ticket-reply-client .ticket-reply-body {
        background-color: #e8f4f8 !important;
        border-left: 4px solid #28a745;
    }
    
    .ticket-reply-admin .ticket-reply-body {
        background-color: #f0f5ff !important;
        border-left: 4px solid #007bff;
    }
    
    .ticket-reply-employee .ticket-reply-body {
        background-color: #fff8e8 !important;
        border-left: 4px solid #fd7e14;
    }
    
    .ticket-reply-internal .ticket-reply-body {
        background-color: #fffde7 !important;
        border-left: 4px solid #6c757d;
        border: 1px solid #ffeeba;
    }
</style>

<?php include '../../shared/templates/admin-footer.php'; ?>