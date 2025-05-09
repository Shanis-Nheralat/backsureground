<?php
/**
 * Admin Settings
 * 
 * General site settings management
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
            $success_message = "$updated settings updated successfully.";
            
            // Log activity
            log_action('settings_update', "Updated general settings");
        } else {
            $error_message = 'No settings were updated.';
        }
    }
    
    // Regenerate CSRF token
    csrf_regenerate();
}

// Get all settings in the 'general' group
$general_settings = db_query(
    "SELECT * FROM settings WHERE setting_group = 'general' ORDER BY setting_key"
);

// Set page variables
$page_title = 'General Settings';
$active_page = 'settings';

// Include header template
include_once '../../shared/templates/admin-header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
    <h1 class="h2">General Settings</h1>
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
            <a class="nav-link active" href="admin-settings.php">General</a>
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
            <a class="nav-link" href="admin-notification-settings.php">Notifications</a>
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
            
            <?php foreach ($general_settings as $setting): ?>
                <?php echo render_setting_field($setting); ?>
            <?php endforeach; ?>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for handling media selection -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Media browser modal functionality will be added here
    });
</script>

<?php 
// Include footer template
include_once '../../shared/templates/admin-footer.php';
?>