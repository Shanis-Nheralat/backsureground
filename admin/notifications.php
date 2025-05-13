<?php
/**
 * Admin Notifications Page
 * Shows all notifications for the admin
 */

// Include required files
require_once '../shared/db.php';
require_once '../shared/auth/admin-auth.php';
require_once '../shared/utils/notifications.php';

// Authentication check
require_admin_auth();

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_notification('error', 'Invalid form submission. Please try again.');
    } else {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'mark_all_read') {
                if (mark_all_notifications_read($_SESSION['user_id'], 'admin')) {
                    set_flash_notification('success', 'All notifications marked as read.');
                } else {
                    set_flash_notification('error', 'Failed to mark notifications as read.');
                }
            } elseif ($_POST['action'] === 'delete_all') {
                if (delete_all_notifications($_SESSION['user_id'], 'admin')) {
                    set_flash_notification('success', 'All notifications deleted.');
                } else {
                    set_flash_notification('error', 'Failed to delete notifications.');
                }
            } elseif ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
                if (mark_notification_read($_POST['notification_id'], $_SESSION['user_id'])) {
                    set_flash_notification('success', 'Notification marked as read.');
                } else {
                    set_flash_notification('error', 'Failed to mark notification as read.');
                }
            } elseif ($_POST['action'] === 'delete' && isset($_POST['notification_id'])) {
                if (delete_notification($_POST['notification_id'], $_SESSION['user_id'])) {
                    set_flash_notification('success', 'Notification deleted.');
                } else {
                    set_flash_notification('error', 'Failed to delete notification.');
                }
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: notifications.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filter by read status
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$unread_only = ($filter === 'unread');

// Get notifications
$notifications = get_notifications($_SESSION['user_id'], 'admin', $unread_only, $per_page, $offset);

// Get total count for pagination
global $pdo;
$count_sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND user_role = :user_role";
if ($unread_only) {
    $count_sql .= " AND is_read = 0";
}
$stmt = $pdo->prepare($count_sql);
$stmt->execute([':user_id' => $_SESSION['user_id'], ':user_role' => 'admin']);
$total_notifications = $stmt->fetchColumn();
$total_pages = ceil($total_notifications / $per_page);

// Page variables
$page_title = 'Notifications';
$current_page = 'notifications';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'dashboard.php'],
    ['title' => 'Notifications', 'url' => '#']
];

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include template parts
include '../shared/templates/admin-head.php';
include '../shared/templates/admin-sidebar.php';
include '../shared/templates/admin-header.php';
?>

<main class="admin-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <div>
                <div class="btn-group" role="group">
                    <a href="?filter=all" class="btn btn-outline-secondary <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                    <a href="?filter=unread" class="btn btn-outline-secondary <?php echo $filter === 'unread' ? 'active' : ''; ?>">Unread</a>
                </div>
                <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#notificationActionsModal">
                    Actions
                </button>
            </div>
        </div>
        
        <?php echo display_flash_notifications(); ?>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Your Notifications</h6>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <p class="text-center py-4">No notifications found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="15%">Type</th>
                                    <th width="20%">Title</th>
                                    <th width="35%">Message</th>
                                    <th width="15%">Date</th>
                                    <th width="10%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr class="<?php echo $notification['is_read'] ? '' : 'table-light'; ?>">
                                        <td><?php echo $notification['id']; ?></td>
                                        <td>
                                            <?php 
                                            $badge_class = 'bg-info';
                                            switch ($notification['type']) {
                                                case 'success': $badge_class = 'bg-success'; break;
                                                case 'warning': $badge_class = 'bg-warning'; break;
                                                case 'error': $badge_class = 'bg-danger'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo ucfirst($notification['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!$notification['is_read']): ?>
                                                <i class="bi bi-circle-fill text-primary me-1" style="font-size: 0.5rem;"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($notification['message']); ?></td>
                                        <td><?php echo date('M j, Y g:i a', strtotime($notification['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <?php if ($notification['link']): ?>
                                                    <a href="<?php echo $notification['link']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-link"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if (!$notification['is_read']): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="mark_read">
                                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="post" class="d-inline delete-notification-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-notification">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Notification pagination">
                            <ul class="pagination justify-content-center mt-4">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>">
                                            Previous
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Previous</span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $start_page + 4);
                                if ($end_page - $start_page < 4 && $total_pages > 4) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>">
                                            Next
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Next</span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Notification Actions Modal -->
<div class="modal fade" id="notificationActionsModal" tabindex="-1" aria-labelledby="notificationActionsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationActionsModalLabel">Notification Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-success w-100 mb-3">Mark All as Read</button>
                    </form>
                    
                    <form method="post" id="deleteAllForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="delete_all">
                        <button type="button" class="btn btn-danger w-100" id="deleteAllBtn">Delete All Notifications</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete confirmation for individual notifications
    document.querySelectorAll('.delete-notification').forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (confirm('Are you sure you want to delete this notification?')) {
                this.closest('form').submit();
            }
        });
    });
    
    // Delete all notifications confirmation
    document.getElementById('deleteAllBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to delete all notifications? This action cannot be undone.')) {
            document.getElementById('deleteAllForm').submit();
        }
    });
});
</script>

<?php include '../shared/templates/admin-footer.php'; ?>