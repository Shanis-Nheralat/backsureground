<?php
/**
 * Email Templates
 * 
 * Manage email templates
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
            $success_message = "$updated email templates updated successfully.";
            
            // Log activity
            log_action('settings_update', "Updated email templates");
        } else {
            $error_message = 'No templates were updated.';
        }
    }
    
    // Regenerate CSRF token
    csrf_regenerate();
}

// Get all settings in the 'email_templates' group
$email_templates = db_query(
    "SELECT * FROM settings WHERE setting_group = 'email_templates' ORDER BY setting_key"
);

// Set page variables
$page_title = 'Email Templates';
$active_page = 'settings';

// Include header template
include_once '../../shared/templates/admin-header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
    <h1 class="h2">Email Templates</h1>
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
            <a class="nav-link active" href="admin-email-templates.php">Email Templates</a>
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

<!-- Template Variables Help -->
<div class="alert alert-info mb-4">
    <h5 class="alert-heading">Template Variables</h5>
    <p>Use these variables in your email templates. They will be replaced with actual values when the email is sent.</p>
    <div class="row">
        <div class="col-md-4">
            <ul class="mb-0">
                <li><code>{{site_name}}</code> - Website name</li>
                <li><code>{{name}}</code> - Recipient's name</li>
                <li><code>{{username}}</code> - Username</li>
            </ul>
        </div>
        <div class="col-md-4">
            <ul class="mb-0">
                <li><code>{{reset_link}}</code> - Password reset link</li>
                <li><code>{{subject}}</code> - Email subject</li>
                <li><code>{{message}}</code> - Message content</li>
            </ul>
        </div>
        <div class="col-md-4">
            <ul class="mb-0">
                <li><code>{{task_id}}</code> - Task ID</li>
                <li><code>{{task_title}}</code> - Task title</li>
                <li><code>{{current_year}}</code> - Current year</li>
            </ul>
        </div>
    </div>
</div>

<!-- Email Templates Accordion -->
<div class="accordion shadow-sm" id="emailTemplatesAccordion">
    <?php $counter = 0; foreach ($email_templates as $template): $counter++; ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?php echo $counter; ?>">
                <button class="accordion-button <?php echo ($counter > 1) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $counter; ?>" aria-expanded="<?php echo ($counter === 1) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $counter; ?>">
                    <?php echo htmlspecialchars($template['setting_label']); ?>
                </button>
            </h2>
            <div id="collapse<?php echo $counter; ?>" class="accordion-collapse collapse <?php echo ($counter === 1) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $counter; ?>" data-bs-parent="#emailTemplatesAccordion">
                <div class="accordion-body">
                    <form method="post">
                        <?php echo csrf_field(); ?>
                        
                        <div class="template-editor mb-3">
                            <textarea class="form-control" id="setting_<?php echo htmlspecialchars($template['setting_key']); ?>" name="settings[<?php echo htmlspecialchars($template['setting_key']); ?>]" rows="10"><?php echo htmlspecialchars($template['setting_value']); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <div>
                                <button type="submit" class="btn btn-primary">Save Template</button>
                                <button type="button" class="btn btn-outline-secondary preview-button" data-template-id="<?php echo htmlspecialchars($template['setting_key']); ?>">Preview</button>
                            </div>
                            <button type="button" class="btn btn-link text-secondary btn-sm reset-template" data-template-id="<?php echo htmlspecialchars($template['setting_key']); ?>" data-bs-toggle="tooltip" title="Reset to default template">Reset to Default</button>
                        </div>
                        
                        <div class="mt-3">
                            <p class="text-muted small mb-1"><?php echo htmlspecialchars($template['setting_description']); ?></p>
                            <div class="small mb-0">
                                <strong>Template ID:</strong> <?php echo htmlspecialchars($template['setting_key']); ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Template Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Email Template Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="border p-3 rounded">
                    <div id="templatePreview"></div>
                </div>
                <div class="alert alert-warning mt-3">
                    <small class="text-muted">This is a preview with placeholder values. The actual email may appear differently when sent.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Template preview
        const previewButtons = document.querySelectorAll('.preview-button');
        const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
        
        previewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const templateId = this.getAttribute('data-template-id');
                const templateContent = document.getElementById('setting_' + templateId).value;
                
                // Replace template variables with sample values
                let previewContent = templateContent
                    .replace(/{{site_name}}/g, 'Backsure Global Support')
                    .replace(/{{name}}/g, 'John Doe')
                    .replace(/{{username}}/g, 'johndoe')
                    .replace(/{{reset_link}}/g, '#')
                    .replace(/{{subject}}/g, 'Sample Subject')
                    .replace(/{{message}}/g, 'This is a sample message content.')
                    .replace(/{{task_id}}/g, 'TASK-123')
                    .replace(/{{task_title}}/g, 'Sample Task Title')
                    .replace(/{{current_year}}/g, new Date().getFullYear());
                
                // Display preview
                document.getElementById('templatePreview').innerHTML = previewContent;
                document.getElementById('previewModalLabel').textContent = 'Preview: ' + 
                    document.querySelector(`[data-template-id="${templateId}"]`).closest('.accordion-item')
                    .querySelector('.accordion-button').textContent.trim();
                
                previewModal.show();
            });
        });
        
        // Reset template to default
        // Note: This is a placeholder for Phase 2. In a real implementation, you would 
        // have default templates stored somewhere or have an AJAX call to a reset endpoint.
        const resetButtons = document.querySelectorAll('.reset-template');
        
        resetButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (!confirm('Are you sure you want to reset this template to default? All your changes will be lost.')) {
                    return;
                }
                
                const templateId = this.getAttribute('data-template-id');
                alert('Template reset functionality will be implemented in a future update.');
                // In a real implementation:
                // 1. Fetch the default template from server
                // 2. Update the textarea with the default content
            });
        });
    });
</script>

<?php 
// Include footer template
include_once '../../shared/templates/admin-footer.php';
?>