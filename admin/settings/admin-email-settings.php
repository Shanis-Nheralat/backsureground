<?php
/**
 * Email Settings
 * 
 * Manage email configuration
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
        
        // Handle test email
        if (isset($_POST['test_email'])) {
            // This will be implemented in Phase 2 once we add the mailer.php functionality
            $success_message = 'Test email functionality will be available in a future update.';
        } else {
            // Save settings
            $updated = save_settings($settings);
            
            if ($updated > 0) {
                $success_message = "$updated settings updated successfully.";
                
                // Log activity
                log_action('settings_update', "Updated email settings");
            } else {
                $error_message = 'No settings were updated.';
            }
        }
    }
    
    // Regenerate CSRF token
    csrf_regenerate();
}

// Get all settings in the 'email' group
$email_settings = db_query(
    "SELECT * FROM settings WHERE setting_group = 'email' ORDER BY setting_key"
);

// Set page variables
$page_title = 'Email Settings';
$active_page = 'settings';

// Include header template
include_once '../../shared/templates/admin-header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
    <h1 class="h2">Email Settings</h1>
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
            <a class="nav-link active" href="admin-email-settings.php">Email</a>
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
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Email Sender Settings</h5>
    </div>
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            
            <?php 
            // Display only sender settings from email group
            foreach ($email_settings as $setting):
                if (in_array($setting['setting_key'], ['email_sender_name', 'email_sender_email'])): 
            ?>
                <?php echo render_setting_field($setting); ?>
            <?php 
                endif;
            endforeach; 
            ?>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <button type="submit" name="test_email" class="btn btn-outline-secondary">Send Test Email</button>
            </div>
        </form>
    </div>
</div>

<!-- SMTP Settings -->
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="card-title mb-0">SMTP Configuration</h5>
    </div>
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            
            <?php 
            // Display only SMTP settings from email group
            foreach ($email_settings as $setting):
                if (!in_array($setting['setting_key'], ['email_sender_name', 'email_sender_email'])): 
            ?>
                <?php echo render_setting_field($setting); ?>
            <?php 
                endif;
            endforeach; 
            ?>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save SMTP Settings</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle SMTP fields based on use_smtp setting
        const useSmtpSwitch = document.getElementById('setting_use_smtp');
        const smtpFields = document.querySelectorAll('[id^="setting_smtp_"]');
        
        function toggleSmtpFields() {
            const isSmtpEnabled = useSmtpSwitch.checked;
            
            smtpFields.forEach(field => {
                if (field !== useSmtpSwitch) {
                    field.closest('.mb-3').style.display = isSmtpEnabled ? 'block' : 'none';
                }
            });
        }
        
        if (useSmtpSwitch) {
            toggleSmtpFields(); // Initial state
            useSmtpSwitch.addEventListener('change', toggleSmtpFields);
        }
    });
</script>

<?php 
// Include footer template
include_once '../../shared/templates/admin-footer.php';
?>