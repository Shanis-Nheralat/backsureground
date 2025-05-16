<?php
/**
 * Admin - Support Ticket Management
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/utils/notifications.php';
require_once '../../shared/support/support-functions.php';

// Authentication check - will redirect to login if not authenticated
require_admin_auth();
require_admin_role(['admin']);

// Page variables
$page_title = 'Support Tickets';
$current_page = 'support_tickets';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/admin/dashboard.php'],
    ['title' => 'Support Tickets', 'url' => '#']
];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15; // Show more tickets per page for admin

// Filtering
$status = isset($_GET['status']) && in_array($_GET['status'], ['open', 'in_progress', 'closed', 'cancelled']) 
        ? $_GET['status'] 
        : null;

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get ticket counts by status
$ticket_counts = get_ticket_counts_by_status();

// Get all tickets with filtering and search
$tickets = get_all_tickets($status, $search, $page, $limit);

// Get employees for batch assignment
$employees = get_available_employees();

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
        <!-- Page header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Support Tickets</h1>
            <div class="d-flex">
                <?php if (!empty($tickets)): ?>
                <div class="dropdown me-2">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="batchActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        Batch Actions
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="batchActionsDropdown">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#batchAssignModal">Assign to Employee</a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#batchStatusModal">Change Status</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#batchDeleteModal">Delete Selected</a></li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <!-- Status filter cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Tickets</div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $ticket_counts['total']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-ticket-alt fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <a href="?status=open" class="text-decoration-none">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Open</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $ticket_counts['open']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-envelope-open fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <a href="?status=in_progress" class="text-decoration-none">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">In Progress</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $ticket_counts['in_progress']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-spinner fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <a href="?status=closed" class="text-decoration-none">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Closed</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $ticket_counts['closed']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Search and filter bar -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search by ID, subject, or client name..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="open" <?php if ($status === 'open') echo 'selected'; ?>>Open</option>
                            <option value="in_progress" <?php if ($status === 'in_progress') echo 'selected'; ?>>In Progress</option>
                            <option value="closed" <?php if ($status === 'closed') echo 'selected'; ?>>Closed</option>
                            <option value="cancelled" <?php if ($status === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <a href="all-tickets.php" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tickets table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">
                    <?php 
                    if ($status) {
                        echo ucfirst($status) . ' Tickets';
                    } else {
                        echo 'All Tickets';
                    }
                    ?>
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($tickets)): ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="fas fa-ticket-alt fa-4x text-gray-300"></i>
                        </div>
                        <h4 class="text-muted">No tickets found</h4>
                        <p>
                            <?php if ($status || $search): ?>
                                Try changing your search or filter criteria.
                            <?php else: ?>
                                No support tickets have been created yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <form id="batchForm" method="post" action="batch-actions.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" id="batchAction" value="">
                        <input type="hidden" name="employee_id" id="batchEmployeeId" value="">
                        <input type="hidden" name="status" id="batchStatus" value="">
                        <input type="hidden" name="message" id="batchMessage" value="">
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                                <label class="form-check-label" for="selectAll"></label>
                                            </div>
                                        </th>
                                        <th>ID</th>
                                        <th>Client</th>
                                        <th>Subject</th>
                                        <th>Assigned To</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Last Reply</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input ticket-checkbox" type="checkbox" name="ticket_ids[]" value="<?php echo $ticket['id']; ?>" id="ticket<?php echo $ticket['id']; ?>">
                                                    <label class="form-check-label" for="ticket<?php echo $ticket['id']; ?>"></label>
                                                </div>
                                            </td>
                                            <td>#<?php echo $ticket['id']; ?></td>
                                            <td><?php echo htmlspecialchars($ticket['user_name']); ?></td>
                                            <td>
                                                <a href="ticket-details.php?id=<?php echo $ticket['id']; ?>">
                                                    <?php echo htmlspecialchars($ticket['subject']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($ticket['assigned_name']): ?>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($ticket['assigned_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $priority_class = '';
                                                switch ($ticket['priority']) {
                                                    case 'low':
                                                        $priority_class = 'bg-success';
                                                        break;
                                                    case 'medium':
                                                        $priority_class = 'bg-info';
                                                        break;
                                                    case 'high':
                                                        $priority_class = 'bg-danger';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $priority_class; ?>">
                                                    <?php echo ucfirst($ticket['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                switch ($ticket['status']) {
                                                    case 'open':
                                                        $status_class = 'bg-success';
                                                        break;
                                                    case 'in_progress':
                                                        $status_class = 'bg-info';
                                                        break;
                                                    case 'closed':
                                                        $status_class = 'bg-secondary';
                                                        break;
                                                    case 'cancelled':
                                                        $status_class = 'bg-warning';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></td>
                                            <td>
                                                <?php 
                                                if ($ticket['last_reply_at']) {
                                                    echo date('M d, Y', strtotime($ticket['last_reply_at']));
                                                    
                                                    // Show who replied last
                                                    if ($ticket['last_reply_role'] === 'admin') {
                                                        echo ' <span class="badge bg-primary">Admin</span>';
                                                    } elseif ($ticket['last_reply_role'] === 'employee') {
                                                        echo ' <span class="badge bg-info">Staff</span>';
                                                    } else {
                                                        echo ' <span class="badge bg-success">Client</span>';
                                                    }
                                                } else {
                                                    echo 'No replies';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="action<?php echo $ticket['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu" aria-labelledby="action<?php echo $ticket['id']; ?>">
                                                        <li>
                                                            <a class="dropdown-item" href="ticket-details.php?id=<?php echo $ticket['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i> View
                                                            </a>
                                                        </li>
                                                        <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'cancelled'): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="ticket-details.php?id=<?php echo $ticket['id']; ?>&assign=1">
                                                                <i class="fas fa-user-plus me-2"></i> Assign
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="ticket-details.php?id=<?php echo $ticket['id']; ?>&close=1">
                                                                <i class="fas fa-check-circle me-2"></i> Close
                                                            </a>
                                                        </li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?php echo $ticket['id']; ?>)">
                                                                <i class="fas fa-trash-alt me-2"></i> Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                    
                    <!-- Pagination -->
                    <?php
                    // Simplified pagination for now - would need to calculate total pages
                    if ($page > 1 || count($tickets) >= $limit):
                    ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="Ticket pagination">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                        Previous
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <li class="page-item active">
                                    <span class="page-link"><?php echo $page; ?></span>
                                </li>
                                
                                <?php if (count($tickets) >= $limit): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                        Next
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Batch Assign Modal -->
<div class="modal fade" id="batchAssignModal" tabindex="-1" aria-labelledby="batchAssignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchAssignModalLabel">Assign Tickets to Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="employeeSelect" class="form-label">Select Employee</label>
                    <select class="form-select" id="employeeSelect">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="assignMessage" class="form-label">Assignment Message (Optional)</label>
                    <textarea class="form-control" id="assignMessage" rows="3" placeholder="Optional message to include with the assignment notification"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitBatchAssign()">Assign Tickets</button>
            </div>
        </div>
    </div>
</div>

<!-- Batch Status Modal -->
<div class="modal fade" id="batchStatusModal" tabindex="-1" aria-labelledby="batchStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchStatusModalLabel">Change Ticket Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="statusSelect" class="form-label">Select New Status</label>
                    <select class="form-select" id="statusSelect">
                        <option value="">-- Select Status --</option>
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="closed">Closed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="statusMessage" class="form-label">Status Change Message (Optional)</label>
                    <textarea class="form-control" id="statusMessage" rows="3" placeholder="Optional message to include with status change notification"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitBatchStatus()">Update Status</button>
            </div>
        </div>
    </div>
</div>

<!-- Batch Delete Modal -->
<div class="modal fade" id="batchDeleteModal" tabindex="-1" aria-labelledby="batchDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batchDeleteModalLabel">Delete Selected Tickets</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger">Warning: This action cannot be undone. All selected tickets and their replies will be permanently deleted.</p>
                <p>Are you sure you want to delete the selected tickets?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitBatchDelete()">Delete Tickets</button>
            </div>
        </div>
    </div>
</div>

<!-- Single Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger">Warning: This action cannot be undone.</p>
                <p>Are you sure you want to delete this ticket and all its replies?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Select/deselect all checkboxes
    document.getElementById('selectAll').addEventListener('change', function() {
        const isChecked = this.checked;
        document.querySelectorAll('.ticket-checkbox').forEach(checkbox => {
            checkbox.checked = isChecked;
        });
    });
    
    // Submit batch assign
    function submitBatchAssign() {
        const employeeId = document.getElementById('employeeSelect').value;
        const message = document.getElementById('assignMessage').value;
        
        if (!employeeId) {
            alert('Please select an employee.');
            return;
        }
        
        if (!atLeastOneSelected()) {
            alert('Please select at least one ticket.');
            return;
        }
        
        document.getElementById('batchAction').value = 'assign';
        document.getElementById('batchEmployeeId').value = employeeId;
        document.getElementById('batchMessage').value = message;
        document.getElementById('batchForm').submit();
    }
    
    // Submit batch status update
    function submitBatchStatus() {
        const status = document.getElementById('statusSelect').value;
        const message = document.getElementById('statusMessage').value;
        
        if (!status) {
            alert('Please select a status.');
            return;
        }
        
        if (!atLeastOneSelected()) {
            alert('Please select at least one ticket.');
            return;
        }
        
        document.getElementById('batchAction').value = 'status';
        document.getElementById('batchStatus').value = status;
        document.getElementById('batchMessage').value = message;
        document.getElementById('batchForm').submit();
    }
    
    // Submit batch delete
    function submitBatchDelete() {
        if (!atLeastOneSelected()) {
            alert('Please select at least one ticket.');
            return;
        }
        
        if (confirm('Are you absolutely sure you want to delete the selected tickets? This action cannot be undone.')) {
            document.getElementById('batchAction').value = 'delete';
            document.getElementById('batchForm').submit();
        }
    }
    
    // Check if at least one ticket is selected
    function atLeastOneSelected() {
        return document.querySelectorAll('.ticket-checkbox:checked').length > 0;
    }
    
    // Single ticket delete confirmation
    function confirmDelete(ticketId) {
        // Set the delete confirmation link
        document.getElementById('confirmDeleteBtn').href = 'batch-actions.php?action=delete&ticket_ids[]=' + ticketId + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>';
        
        // Show the modal
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        deleteModal.show();
    }
</script>

<?php include '../../shared/templates/admin-footer.php'; ?>