<?php
/**
 * SEO Settings
 * 
 * Manage site SEO settings
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
            log_action('settings_update', "Updated SEO settings");
        } else {
            $error_message = 'No settings were updated.';
        }
    }
    
    // Regenerate CSRF token
    csrf_regenerate();
}

// Get all settings in the 'seo' group
$seo_settings = db_query(
    "SELECT * FROM settings WHERE setting_group = 'seo' ORDER BY setting_key"
);

// Set page variables
$page_title = 'SEO Settings';
$active_page = 'settings';

// Include header template
include_once '../../shared/templates/admin-header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
    <h1 class="h2">SEO Settings</h1>
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
            <a class="nav-link active" href="admin-seo.php">SEO</a>
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
            
            <?php foreach ($seo_settings as $setting): ?>
                <?php echo render_setting_field($setting); ?>
            <?php endforeach; ?>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Section -->
<div class="card shadow-sm mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Search Result Preview</h5>
    </div>
    <div class="card-body">
        <div class="search-preview p-3 border rounded bg-light">
            <h3 class="h5 text-primary mb-1" id="previewTitle">
                <?php echo htmlspecialchars(get_setting('meta_title', 'Backsure Global Support')); ?>
            </h3>
            <div class="small text-success mb-2"><?php echo $_SERVER['HTTP_HOST'] ?? 'www.backsureglobalsupport.com'; ?></div>
            <p class="small text-muted mb-0" id="previewDescription">
                <?php echo htmlspecialchars(get_setting('meta_description', '')); ?>
            </p>
        </div>
        <div class="mt-3 small text-muted">
            This is an approximation of how your site might appear in search engine results.
        </div>
    </div>
</div>

<!-- JavaScript for live preview -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Update preview when meta title changes
        const titleInput = document.getElementById('setting_meta_title');
        const titlePreview = document.getElementById('previewTitle');
        
        if (titleInput && titlePreview) {
            titleInput.addEventListener('input', function() {
                titlePreview.textContent = this.value;
            });
        }
        
        // Update preview when meta description changes
        const descInput = document.getElementById('setting_meta_description');
        const descPreview = document.getElementById('previewDescription');
        
        if (descInput && descPreview) {
            descInput.addEventListener('input', function() {
                descPreview.textContent = this.value;
            });
        }
    });
</script>

<?php 
// Include footer template
include_once '../../shared/templates/admin-footer.php';
?>