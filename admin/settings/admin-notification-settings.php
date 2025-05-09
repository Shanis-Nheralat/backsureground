<?php
/**
 * Notification Settings
 * 
 * Configure system notifications
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/csrf/csrf-functions.php';
require_once '../../shared/settings-functions.php';

// Ensure user is authenticated and has admin role
require_role('admin');

// Initialize variables
$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid form submission. Please try again.';
    } else {
        // Get form data
        $settings = isset($_POST['settings']) ? $_POST['settings'] : [];
        
        // Save settings
        $updated = save_settings($settings);
        
        if ($updated > 0) {
            $success_message = "$updated notification settings updated successfully.";
            
            // Log activity
            log_action('settings_update', "Updated notification settings");
        } else {
            $error_message = 'No settings were updated.';
        }
    }
    
    // Regenerate CSRF token
    csrf_regenerate();
}

// Get all settings in the 'notifications' group
$notification_settings = db_query(
    "SELECT * FROM settings WHERE setting_group = 'notifications' ORDER BY setting_key"
);

// Set page variables
$page_title = 'Notification Settings';
$active_page = 'settings';

// Include header template
include_once '../../shared/templates/admin-header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
    <h1 class="h2">Notification Settings</h1>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- Settings Tabs -->
<div class="mb-4">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link" href="admin-settings.php">General</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="admin-seo.php">SEO</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="admin-email-settings.php">Email</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="admin-email-templates.php">Email Templates</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="admin-notification-settings.php">Notifications</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="admin-integrations.php">Integrations</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="admin-chat-settings.php">Chat</a>
        </li>
    </ul>
</div>

<!-- Settings Form -->
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            
            <?php foreach ($notification_settings as $setting): ?>
                <?php echo render_setting_field($setting); ?>
            <?php endforeach; ?>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<!-- Notification Preview -->
<div class="card shadow-sm mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Notification Preview</h5>
    </div>
    <div class="card-body">
        <p class="mb-3">Here's a preview of how notifications will appear:</p>
        
        <div class="notification-examples">
            <div class="alert alert-success">
                <strong>Success!</strong> This is a success notification.
            </div>
            
            <div class="alert alert-info">
                <strong>Info!</strong> This is an information notification.
            </div>
            
            <div class="alert alert-warning">
                <strong>Warning!</strong> This is a warning notification.
            </div>
            
            <div class="alert alert-danger">
                <strong>Error!</strong> This is an error notification.
            </div>
        </div>
        
        <div class="mt-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="testNotificationBtn">
                Test Notification
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Test notification
        document.getElementById('testNotificationBtn').addEventListener('click', function() {
            // Get notification duration
            const duration = document.getElementById('setting_notification_popup_duration').value || 5000;
            
            // Create test notification
            const notification = document.createElement('div');
            notification.className = 'alert alert-info notification-popup';
            notification.innerHTML = '<strong>Test!</strong> This is a test notification.';
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.style.padding = '15px';
            notification.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
            notification.style.transition = 'opacity 0.5s';
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.className = 'btn-close';
            closeBtn.style.float = 'right';
            closeBtn.addEventListener('click', function() {
                document.body.removeChild(notification);
            });
            notification.prepend(closeBtn);
            
            // Add to body
            document.body.appendChild(notification);
            
            // Auto-remove after duration
            setTimeout(function() {
                if (document.body.contains(notification)) {
                    notification.style.opacity = '0';
                    setTimeout(function() {
                        if (document.body.contains(notification)) {
                            document.body.removeChild(notification);
                        }
                    }, 500);
                }
            }, parseInt(duration));
        });
    });
</script>

<?php 
// Include footer template
include_once '../../shared/templates/admin-footer.php';
?>