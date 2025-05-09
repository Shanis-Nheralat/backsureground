<?php
/**
 * Integrations Settings
 * 
 * Configure third-party integrations
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
            $success_message = "$updated integration settings updated successfully.";
            
            // Log activity
            log_action('settings_update', "Updated integration settings");
        } else {
            $error_message = 'No settings were updated.';
        }
    }
    
    // Regenerate CSRF token
    csrf_regenerate();
}

// Get all settings in the 'integrations' group
$integration_settings = db_query(
    "SELECT * FROM settings WHERE setting_group = 'integrations' ORDER BY setting_key"
);

// Set page variables
$page_title = 'Integration Settings';
$active_page = 'settings';

// Include header template
include_once '../../shared/templates/admin-header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
    <h1 class="h2">Integration Settings</h1>
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
            <a class="nav-link" href="admin-notification-settings.php">Notifications</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="admin-integrations.php">Integrations</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="admin-chat-settings.php">Chat</a>
        </li>
    </ul>
</div>

<!-- Integration Settings Cards -->
<div class="row">
    <!-- Zoho Integration -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Zoho Integration</h5>
                <img src="https://www.zohowebstatic.com/sites/default/files/zoho-logo.png" alt="Zoho" height="24">
            </div>
            <div class="card-body">
                <form method="post">
                    <?php echo csrf_field(); ?>
                    
                    <?php 
                    foreach ($integration_settings as $setting):
                        if (strpos($setting['setting_key'], 'zoho_') === 0):
                    ?>
                        <?php echo render_setting_field($setting); ?>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Save Zoho Settings</button>
                        <?php if (!empty(get_setting('zoho_api_key', ''))): ?>
                            <button type="button" class="btn btn-outline-secondary">Test Connection</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Tally Integration -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Tally Integration</h5>
                <img src="https://tallysolutions.com/wp-content/uploads/2022/01/Tally-Logo.png" alt="Tally" height="24">
            </div>
            <div class="card-body">
                <form method="post">
                    <?php echo csrf_field(); ?>
                    
                    <?php 
                    foreach ($integration_settings as $setting):
                        if (strpos($setting['setting_key'], 'tally_') === 0):
                    ?>
                        <?php echo render_setting_field($setting); ?>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Save Tally Settings</button>
                        <?php if (!empty(get_setting('tally_api_key', ''))): ?>
                            <button type="button" class="btn btn-outline-secondary">Test Connection</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Azure Blob Storage -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Azure Blob Storage</h5>
                <img src="https://azurecomcdn.azureedge.net/cvt-1e062bfe6cdc14e6dbd72f5d483e423a5b5ba17cdf1f62fe41f2e31f95e0ddfa/media/logos/azure.svg" alt="Azure" height="24">
            </div>
            <div class="card-body">
                <form method="post">
                    <?php echo csrf_field(); ?>
                    
                    <?php 
                    foreach ($integration_settings as $setting):
                        if (strpos($setting['setting_key'], 'azure_') === 0):
                    ?>
                        <?php echo render_setting_field($setting); ?>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Save Azure Settings</button>
                        <?php if (!empty(get_setting('azure_connection_string', ''))): ?>
                            <button type="button" class="btn btn-outline-secondary">Test Connection</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Payment Gateway (Future) -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Payment Gateways</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Payment gateway integration will be available in a future update.
                </div>
                
                <div class="text-center py-3">
                    <i class="bi bi-credit-card display-4 text-muted"></i>
                    <p class="mt-2 text-muted">Coming in a future phase</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Connection test buttons functionality will be implemented in Phase 3
        const testButtons = document.querySelectorAll('.btn-outline-secondary');
        
        testButtons.forEach(button => {
            button.addEventListener('click', function() {
                alert('Connection testing functionality will be available in a future update.');
            });
        });
    });
</script>

<?php 
// Include footer template
include_once '../../shared/templates/admin-footer.php';
?>