<?php
/**
 * Employee - Assigned Support Tickets
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/utils/notifications.php';
require_once '../../shared/support/support-functions.php';

// Authentication check - will redirect to login if not authenticated
require_admin_auth();
require_admin_role(['employee']);

// Page variables
$page_title = 'Assigned Support Tickets';
$current_page = 'assigned_tickets';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/employee/dashboard.php'],
    ['title' => 'Support Tickets', 'url' => '#']
];

// Get employee ID
$employee_id = $_SESSION['user_id'];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;

// Filtering
$status = isset($_GET['status']) && in_array($_GET['status'], ['open', 'in_progress', 'closed', 'cancelled']) 
        ? $_GET['status'] 
        : null;

// Get ticket counts by status
$ticket_counts = get_ticket_counts_by_status($employee_id, 'employee');

// Get assigned tickets
$tickets = get_employee_tickets($employee_id, $status, $page, $limit);

// Include template parts
include '../../shared/templates/admin-head.php';
include '../../shared/templates/admin-sidebar.php';
include '../../shared/templates/admin-header.php';
?>

<main class="admin-main">
    <div class="container-fluid py-4">
        <!-- Page header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Assigned Support Tickets</h1>
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
                    <div class="col-md-4">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="open" <?php if ($status === 'open') echo 'selected'; ?>>Open</option>
                            <option value="in_progress" <?php if ($status === 'in_progress') echo 'selected'; ?>>In Progress</option>
                            <option value="closed" <?php if ($status === 'closed') echo 'selected'; ?>>Closed</option>
                            <option value="cancelled" <?php if ($status === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <a href="assigned-tickets.php" class="btn btn-outline-secondary w-100">Reset</a>
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
                        echo 'All Assigned Tickets';
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
                            <?php if ($status): ?>
                                Try changing your filter criteria or check back later.
                            <?php else: ?>
                                You have not been assigned to any support tickets yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Subject</th>
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
                                        <td>#<?php echo $ticket['id']; ?></td>
                                        <td><?php echo htmlspecialchars($ticket['user_name']); ?></td>
                                        <td>
                                            <a href="view-ticket.php?id=<?php echo $ticket['id']; ?>">
                                                <?php echo htmlspecialchars($ticket['subject']); ?>
                                            </a>
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
                                            <a href="view-ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>">
                                        Previous
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <li class="page-item active">
                                    <span class="page-link"><?php echo $page; ?></span>
                                </li>
                                
                                <?php if (count($tickets) >= $limit): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>">
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

<?php include '../../shared/templates/admin-footer.php'; ?>