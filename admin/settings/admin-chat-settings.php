<?php
/**
 * Chat Settings
 * 
 * Configure chat functionality
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
            $success_message = "$updated chat settings updated successfully.";
            
            // Log activity
            log_action('settings_update', "Updated chat settings");
        } else {
            $error_message = 'No settings were updated.';
        }
    }
    
    // Regenerate CSRF token
    csrf_regenerate();
}

// Get all settings in the 'chat' group
$chat_settings = db_query(
    "SELECT * FROM settings WHERE setting_group = 'chat' ORDER BY setting_key"
);

// Set page variables
$page_title = 'Chat Settings';
$active_page = 'settings';

// Include header template
include_once '../../shared/templates/admin-header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
    <h1 class="h2">Chat Settings</h1>
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
            <a class="nav-link" href="admin-integrations.php">Integrations</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="admin-chat-settings.php">Chat</a>
        </li>
    </ul>
</div>

<!-- Chat Settings Card -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Chat Configuration</h5>
    </div>
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            
            <?php 
            // Display main chat settings
            $main_settings = array_filter($chat_settings, function($setting) {
                return in_array($setting['setting_key'], ['chat_enabled', 'chat_provider', 'chat_welcome_message', 'chat_bot_name']);
            });
            
            foreach ($main_settings as $setting):
            ?>
                <?php echo render_setting_field($setting); ?>
            <?php endforeach; ?>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Chat Settings</button>
            </div>
        </form>
    </div>
</div>

<!-- Provider-specific Settings -->
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="card-title mb-0">Chat Provider Settings</h5>
    </div>
    <div class="card-body">
        <div id="providerSettings">
            <div class="alert alert-info" id="providerInfo">
                <i class="bi bi-info-circle me-2"></i>
                Select a chat provider above to see provider-specific settings.
            </div>
            
            <!-- Custom Provider Settings -->
            <div id="customSettings" class="provider-settings" style="display: none;">
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    Using the built-in chat system. No additional configuration required.
                </div>
            </div>
            
            <!-- Intercom Provider Settings -->
            <div id="intercomSettings" class="provider-settings" style="display: none;">
                <form method="post">
                    <?php echo csrf_field(); ?>
                    
                    <?php 
                    // Display Intercom settings
                    $intercom_settings = array_filter($chat_settings, function($setting) {
                        return strpos($setting['setting_key'], 'intercom_') === 0;
                    });
                    
                    foreach ($intercom_settings as $setting):
                    ?>
                        <?php echo render_setting_field($setting); ?>
                    <?php endforeach; ?>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Save Intercom Settings</button>
                    </div>
                </form>
            </div>
            
            <!-- Crisp Provider Settings -->
            <div id="crispSettings" class="provider-settings" style="display: none;">
                <form method="post">
                    <?php echo csrf_field(); ?>
                    
                    <?php 
                    // Display Crisp settings
                    $crisp_settings = array_filter($chat_settings, function($setting) {
                        return strpos($setting['setting_key'], 'crisp_') === 0;
                    });
                    
                    foreach ($crisp_settings as $setting):
                    ?>
                        <?php echo render_setting_field($setting); ?>
                    <?php endforeach; ?>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Save Crisp Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Chat Preview -->
<div class="card shadow-sm mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Chat Preview</h5>
    </div>
    <div class="card-body">
        <?php $chat_enabled = filter_var(get_setting('chat_enabled', 'false'), FILTER_VALIDATE_BOOLEAN); ?>
        
        <?php if ($chat_enabled): ?>
            <div class="chat-preview-container">
                <div class="chat-preview">
                    <div class="chat-header">
                        <div class="chat-avatar">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <div class="chat-info">
                            <div class="chat-name"><?php echo htmlspecialchars(get_setting('chat_bot_name', 'Chat Support')); ?></div>
                            <div class="chat-status">Online</div>
                        </div>
                        <div class="chat-actions">
                            <i class="bi bi-x-lg"></i>
                        </div>
                    </div>
                    <div class="chat-body">
                        <div class="chat-message bot">
                            <div class="message-content">
                                <?php echo htmlspecialchars(get_setting('chat_welcome_message', 'Welcome! How can we help you today?')); ?>
                            </div>
                            <div class="message-time">Just now</div>
                        </div>
                        <div class="chat-message user">
                            <div class="message-content">
                                Hello, I have a question about my account.
                            </div>
                            <div class="message-time">Just now</div>
                        </div>
                    </div>
                    <div class="chat-footer">
                        <input type="text" class="chat-input" placeholder="Type your message..." disabled>
                        <button class="chat-send-btn" disabled><i class="bi bi-send"></i></button>
                    </div>
                </div>
                
                <div class="chat-widget">
                    <i class="bi bi-chat-dots-fill"></i>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-circle me-2"></i>
                Chat is currently disabled. Enable it to see the preview.
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Chat Preview Styles */
    .chat-preview-container {
        position: relative;
        height: 400px;
        border: 1px dashed #ccc;
        border-radius: 8px;
        padding: 20px;
        background-color: #f8f9fa;
    }
    
    .chat-preview {
        position: absolute;
        bottom: 80px;
        right: 20px;
        width: 300px;
        height: 400px;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
        background-color: #fff;
    }
    
    .chat-header {
        background-color: #0d6efd;
        color: white;
        padding: 10px;
        display: flex;
        align-items: center;
    }
    
    .chat-avatar {
        width: 36px;
        height: 36px;
        margin-right: 10px;
        font-size: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .chat-info {
        flex-grow: 1;
    }
    
    .chat-name {
        font-weight: bold;
    }
    
    .chat-status {
        font-size: 12px;
        opacity: 0.8;
    }
    
    .chat-actions {
        cursor: pointer;
    }
    
    .chat-body {
        flex-grow: 1;
        padding: 10px;
        overflow-y: auto;
        background-color: #f5f5f5;
    }
    
    .chat-message {
        margin-bottom: 10px;
        max-width: 80%;
    }
    
    .chat-message.bot {
        margin-right: auto;
    }
    
    .chat-message.user {
        margin-left: auto;
    }
    
    .message-content {
        padding: 8px 12px;
        border-radius: 8px;
        word-break: break-word;
    }
    
    .chat-message.bot .message-content {
        background-color: #fff;
        border: 1px solid #ddd;
    }
    
    .chat-message.user .message-content {
        background-color: #0d6efd;
        color: white;
    }
    
    .message-time {
        font-size: 10px;
        color: #999;
        margin-top: 2px;
        text-align: right;
    }
    
    .chat-footer {
        padding: 10px;
        display: flex;
        border-top: 1px solid #eee;
    }
    
    .chat-input {
        flex-grow: 1;
        border: 1px solid #ddd;
        border-radius: 20px;
        padding: 8px 12px;
        margin-right: 8px;
    }
    
    .chat-send-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: #0d6efd;
        color: white;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .chat-widget {
        position: absolute;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: #0d6efd;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        cursor: pointer;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Chat provider selection
        const providerSelect = document.getElementById('setting_chat_provider');
        const providerSettings = document.querySelectorAll('.provider-settings');
        const providerInfo = document.getElementById('providerInfo');
        
        function updateProviderSettings() {
            const provider = providerSelect.value;
            
            // Hide all provider settings
            providerSettings.forEach(setting => {
                setting.style.display = 'none';
            });
            
            // Hide provider info alert
            providerInfo.style.display = 'none';
            
            // Show selected provider settings
            if (provider === 'custom') {
                document.getElementById('customSettings').style.display = 'block';
            } else if (provider === 'intercom') {
                document.getElementById('intercomSettings').style.display = 'block';
            } else if (provider === 'crisp') {
                document.getElementById('crispSettings').style.display = 'block';
            } else {
                // Show info alert if no provider selected
                providerInfo.style.display = 'block';
            }
        }
        
        if (providerSelect) {
            updateProviderSettings(); // Initial update
            providerSelect.addEventListener('change', updateProviderSettings);
        }
        
        // Chat enabled toggle
        const chatEnabledToggle = document.getElementById('setting_chat_enabled');
        const chatProviderField = document.getElementById('setting_chat_provider').closest('.mb-3');
        const chatWelcomeField = document.getElementById('setting_chat_welcome_message').closest('.mb-3');
        const chatBotNameField = document.getElementById('setting_chat_bot_name').closest('.mb-3');
        const providerSettingsCard = document.getElementById('providerSettings').closest('.card');
        
        function updateChatEnabledFields() {
            const isEnabled = chatEnabledToggle.checked;
            
            // Toggle provider settings visibility
            chatProviderField.style.display = isEnabled ? 'block' : 'none';
            chatWelcomeField.style.display = isEnabled ? 'block' : 'none';
            chatBotNameField.style.display = isEnabled ? 'block' : 'none';
            providerSettingsCard.style.display = isEnabled ? 'block' : 'none';
        }
        
        if (chatEnabledToggle) {
            updateChatEnabledFields(); // Initial update
            chatEnabledToggle.addEventListener('change', updateChatEnabledFields);
        }
        
        // Live preview update
        const chatWelcomeMsg = document.getElementById('setting_chat_welcome_message');
        const chatBotName = document.getElementById('setting_chat_bot_name');
        const previewWelcomeMsg = document.querySelector('.chat-message.bot .message-content');
        const previewBotName = document.querySelector('.chat-name');
        
        if (chatWelcomeMsg && previewWelcomeMsg) {
            chatWelcomeMsg.addEventListener('input', function() {
                previewWelcomeMsg.textContent = this.value || 'Welcome! How can we help you today?';
            });
        }
        
        if (chatBotName && previewBotName) {
            chatBotName.addEventListener('input', function() {
                previewBotName.textContent = this.value || 'Chat Support';
            });
        }
    });
</script>

<?php 
// Include footer template
include_once '../../shared/templates/admin-footer.php';
?>