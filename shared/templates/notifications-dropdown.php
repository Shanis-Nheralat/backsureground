<?php
/**
 * Notification dropdown component for header
 * Include this in admin-header.php, employee-header.php,client-header.php, etc.
 */

// Get current user role and ID
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_user_role = $_SESSION['user_role'] ?? '';

// Get unread notifications count
$unread_count = 0;//get_unread_count($current_user_id, $current_user_role);

// Get latest notifications (limit to 5)
$notifications = [];//get_notifications($current_user_id, $current_user_role, true, 5);
?>

<div class="dropdown">
    <a class="nav-link dropdown-toggle" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell"></i>
        <?php if ($unread_count > 0): ?>
            <span class="badge bg-danger"><?php echo $unread_count; ?></span>
        <?php endif; ?>
    </a>
    
    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
        <li><h6 class="dropdown-header">Notifications</h6></li>
        
        <?php if (empty($notifications)): ?>
            <li><div class="dropdown-item text-muted">No new notifications</div></li>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <li>
                    <a class="dropdown-item notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" 
                       href="<?php echo $notification['link'] ?: '#'; ?>"
                       data-notification-id="<?php echo $notification['id']; ?>">
                        <div class="d-flex">
                            <?php 
                            $icon_class = 'bi-info-circle text-info';
                            switch ($notification['type']) {
                                case 'success': $icon_class = 'bi-check-circle text-success'; break;
                                case 'warning': $icon_class = 'bi-exclamation-triangle text-warning'; break;
                                case 'error': $icon_class = 'bi-x-circle text-danger'; break;
                            }
                            ?>
                            <div class="notification-icon me-2">
                                <i class="bi <?php echo $icon_class; ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                <div class="notification-time text-muted">
                                    <?php 
                                    $created = new DateTime($notification['created_at']);
                                    $now = new DateTime();
                                    $diff = $created->diff($now);
                                    
                                    if ($diff->d > 0) {
                                        echo $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
                                    } elseif ($diff->h > 0) {
                                        echo $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                    } elseif ($diff->i > 0) {
                                        echo $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                                    } else {
                                        echo 'Just now';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item text-center" href="/<?php echo $current_user_role; ?>/notifications.php">
                    View All Notifications
                </a>
            </li>
            <li>
                <a class="dropdown-item text-center mark-all-read" href="#" data-action="mark-all-read">
                    Mark All as Read
                </a>
            </li>
        <?php endif; ?>
    </ul>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mark notification as read when clicked
    document.querySelectorAll('.notification-item').forEach(function(item) {
        item.addEventListener('click', function(e) {
            const notificationId = this.dataset.notificationId;
            
            fetch('/shared/ajax/mark-notification-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
            });
        });
    });
    
    // Mark all notifications as read
    document.querySelector('.mark-all-read').addEventListener('click', function(e) {
        e.preventDefault();
        
        fetch('/shared/ajax/mark-all-notifications-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
        }).then(function() {
            // Reload the page to update notification count
            window.location.reload();
        });
    });
});
</script>
