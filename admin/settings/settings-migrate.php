<?php
/**
 * Settings Migration Script
 * 
 * Migrate hardcoded config from static files to settings database.
 * This is a one-time script that should be executed manually.
 */

// Ensure this is run by an admin or via command line
if (php_sapi_name() !== 'cli') {
    // If not running from command line, require authentication
    require_once '../../shared/db.php';
    require_once '../../shared/auth/admin-auth.php';
    require_once '../../shared/csrf/csrf-functions.php';
    
    // Ensure user is authenticated and has admin role
    require_role('admin');
} else {
    // Command line execution
    require_once __DIR__ . '/../../shared/db.php';
}

// Include settings functions
require_once __DIR__ . '/../../shared/settings-functions.php';

// Check for dry-run mode
$dry_run = isset($_GET['dry-run']) || (isset($argv) && in_array('--dry-run', $argv));

// Check for confirmation
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'true';
$cli_confirmed = isset($argv) && in_array('--confirm', $argv);

if (!$dry_run && !$confirmed && !$cli_confirmed) {
    if (php_sapi_name() === 'cli') {
        echo "WARNING: This will migrate settings to the database. Use --dry-run to test or --confirm to execute.\n";
        exit(1);
    } else {
        // Web interface
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Settings Migration</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        </head>
        <body class="bg-light">
            <div class="container py-5">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title">Settings Migration</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This script will migrate hardcoded settings to the database.
                        </div>
                        <p>This is a one-time operation that should be performed only once during setup.</p>
                        <p>If you\'ve already run this script before, running it again may overwrite any customized settings.</p>
                        <div class="mt-4">
                            <a href="?dry-run=true" class="btn btn-secondary me-2">Test Run (Dry Run)</a>
                            <a href="?confirm=true" class="btn btn-primary">Proceed with Migration</a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        exit;
    }
}

// Output header for web interface
if (php_sapi_name() !== 'cli') {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Settings Migration ' . ($dry_run ? '(Dry Run)' : '') . '</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    </head>
    <body class="bg-light">
        <div class="container py-5">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Settings Migration ' . ($dry_run ? '(Dry Run)' : '') . '</h3>
                    <a href="admin-settings.php" class="btn btn-sm btn-outline-secondary">Back to Settings</a>
                </div>
                <div class="card-body">
                    <div class="alert alert-' . ($dry_run ? 'info' : 'primary') . '">
                        <i class="bi bi-' . ($dry_run ? 'info-circle' : 'check-circle') . ' me-2"></i>
                        Running in ' . ($dry_run ? 'DRY RUN mode. No changes will be made.' : 'LIVE mode. Settings will be updated.') . '
                    </div>
                    <div class="migration-log p-3 bg-light border rounded" style="max-height: 400px; overflow-y: auto;">
                        <pre style="margin-bottom: 0;">';
} else {
    echo "Settings Migration " . ($dry_run ? "(Dry Run)\n" : "(LIVE)\n");
    echo "=============================================\n\n";
}

/**
 * Log a migration message
 * 
 * @param string $message Log message
 * @param string $type Message type (info, success, warning, error)
 */
function log_migration($message, $type = 'info') {
    if (php_sapi_name() === 'cli') {
        $prefix = '';
        switch ($type) {
            case 'success': $prefix = "[SUCCESS] "; break;
            case 'warning': $prefix = "[WARNING] "; break;
            case 'error': $prefix = "[ERROR] "; break;
            default: $prefix = "[INFO] ";
        }
        echo $prefix . $message . "\n";
    } else {
        $color = '';
        switch ($type) {
            case 'success': $color = 'text-success'; break;
            case 'warning': $color = 'text-warning'; break;
            case 'error': $color = 'text-danger'; break;
            default: $color = 'text-secondary';
        }
        echo "<span class=\"{$color}\">{$message}</span>\n";
    }
}

/**
 * Migrate a setting from a file to database
 * 
 * @param string $source_file Source file path
 * @param string $pattern Regex pattern to extract the value
 * @param string $setting_key Setting key in database
 * @param mixed $default Default value if not found
 * @return bool Success or failure
 */
function migrate_from_file($source_file, $pattern, $setting_key, $default = '') {
    global $dry_run;
    
    // Check if file exists
    if (!file_exists($source_file)) {
        log_migration("File not found: $source_file", 'warning');
        return false;
    }
    
    // Read file contents
    $content = file_get_contents($source_file);
    
    // Extract value using pattern
    if (preg_match($pattern, $content, $matches)) {
        $value = $matches[1];
        log_migration("Found value for '$setting_key': " . (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value));
        
        // Update setting
        if (!$dry_run) {
            if (set_setting($setting_key, $value)) {
                log_migration("Updated setting: $setting_key", 'success');
                return true;
            } else {
                log_migration("Failed to update setting: $setting_key", 'error');
                return false;
            }
        }
        return true;
    } else {
        log_migration("Pattern not found for '$setting_key' in $source_file", 'warning');
        
        // Use default value if provided
        if ($default !== '') {
            log_migration("Using default value for '$setting_key': " . (strlen($default) > 50 ? substr($default, 0, 50) . '...' : $default));
            
            // Update setting with default value
            if (!$dry_run) {
                if (set_setting($setting_key, $default)) {
                    log_migration("Updated setting with default: $setting_key", 'success');
                    return true;
                } else {
                    log_migration("Failed to update setting with default: $setting_key", 'error');
                    return false;
                }
            }
            return true;
        }
        
        return false;
    }
}

// Start migration
log_migration("Starting settings migration...");

// 1. Site Name from header.php
log_migration("\nMigrating Site Information:");
migrate_from_file(
    __DIR__ . '/../../shared/templates/admin-header.php',
    '/<title>(?:.*)\|\s*(.*?)<\/title>/',
    'site_name',
    'Backsure Global Support'
);

// 2. Logo paths from header.php
migrate_from_file(
    __DIR__ . '/../../shared/templates/admin-header.php',
    '/<img src="(.*?)" alt="Backsure Global Support"/',
    'logo',
    '/assets/img/logo.png'
);
migrate_from_file(
    __DIR__ . '/../../shared/templates/admin-header.php',
    '/<img src="(.*?)" alt="Backsure Global Support".*?navbar-brand/',
    'logo_white',
    '/assets/img/logo-white.png'
);

// 3. Meta information from head components
log_migration("\nMigrating SEO Information:");
migrate_from_file(
    __DIR__ . '/../../shared/templates/admin-header.php',
    '/<meta name="description" content="(.*?)">/',
    'meta_description',
    'Backsure Global Support provides dedicated employee, on-demand services, and business care plans for global businesses'
);
migrate_from_file(
    __DIR__ . '/../../shared/templates/admin-header.php',
    '/<meta name="keywords" content="(.*?)">/',
    'meta_keywords',
    'global support, outsourcing, business services'
);

// 4. Email settings from mailer.php
log_migration("\nMigrating Email Information:");
// Check if mailer.php exists (it might not in Phase 1)
if (file_exists(__DIR__ . '/../../shared/mailer/mailer.php')) {
    migrate_from_file(
        __DIR__ . '/../../shared/mailer/mailer.php',
        '/\$mail->setFrom\(\'(.*?)\'/',
        'email_sender_email',
        'noreply@backsureglobalsupport.com'
    );
    migrate_from_file(
        __DIR__ . '/../../shared/mailer/mailer.php',
        '/\$mail->setFrom\(.*?, \'(.*?)\'/',
        'email_sender_name',
        'Backsure Global Support'
    );
    migrate_from_file(
        __DIR__ . '/../../shared/mailer/mailer.php',
        '/\$mail->Host = \'(.*?)\'/',
        'smtp_host',
        'smtp.zoho.com'
    );
    migrate_from_file(
        __DIR__ . '/../../shared/mailer/mailer.php',
        '/\$mail->Username = \'(.*?)\'/',
        'smtp_username',
        ''
    );
    migrate_from_file(
        __DIR__ . '/../../shared/mailer/mailer.php',
        '/\$mail->Password = \'(.*?)\'/',
        'smtp_password',
        ''
    );
    migrate_from_file(
        __DIR__ . '/../../shared/mailer/mailer.php',
        '/\$mail->Port = (.*?);/',
        'smtp_port',
        '465'
    );
} else {
    log_migration("Mailer.php not found. Email settings will be set to defaults.", 'warning');
    
    if (!$dry_run) {
        set_setting('email_sender_email', 'noreply@backsureglobalsupport.com');
        set_setting('email_sender_name', 'Backsure Global Support');
        set_setting('smtp_host', 'smtp.zoho.com');
        set_setting('smtp_port', '465');
        set_setting('smtp_encryption', 'ssl');
        set_setting('use_smtp', 'false');
        
        log_migration("Set default email settings", 'success');
    }
}

// 5. Email templates from email templates directory
log_migration("\nMigrating Email Templates:");
$template_dir = __DIR__ . '/../../shared/email_templates/';

// Check if email templates directory exists
if (is_dir($template_dir)) {
    // Password reset template
    if (file_exists($template_dir . 'password_reset.html')) {
        $template = file_get_contents($template_dir . 'password_reset.html');
        if (!$dry_run) {
            set_setting('password_reset_template', $template);
            log_migration("Migrated password reset template", 'success');
        } else {
            log_migration("Found password reset template (not applied in dry run)");
        }
    }
    
    // Welcome email template
    if (file_exists($template_dir . 'welcome_user.html')) {
        $template = file_get_contents($template_dir . 'welcome_user.html');
        if (!$dry_run) {
            set_setting('welcome_template', $template);
            log_migration("Migrated welcome email template", 'success');
        } else {
            log_migration("Found welcome email template (not applied in dry run)");
        }
    }
    
    // Inquiry confirmation template
    if (file_exists($template_dir . 'inquiry_confirmation.html')) {
        $template = file_get_contents($template_dir . 'inquiry_confirmation.html');
        if (!$dry_run) {
            set_setting('inquiry_template', $template);
            log_migration("Migrated inquiry confirmation template", 'success');
        } else {
            log_migration("Found inquiry confirmation template (not applied in dry run)");
        }
    }
} else {
    log_migration("Email templates directory not found. Using default templates.", 'warning');
}

// 6. Write migration flag to prevent running again
if (!$dry_run) {
    $migration_flag_file = __DIR__ . '/migration_complete.flag';
    if (file_put_contents($migration_flag_file, date('Y-m-d H:i:s'))) {
        log_migration("\nWrote migration flag file to prevent accidental re-run", 'success');
    } else {
        log_migration("\nFailed to write migration flag file", 'error');
    }
}

// Output footer for web interface
if (php_sapi_name() !== 'cli') {
    echo '</pre>
                    </div>
                    <div class="mt-4 d-flex justify-content-between">
                        <div>
                            ' . ($dry_run ? '<a href="?confirm=true" class="btn btn-primary">Proceed with Migration</a>' : '<div class="alert alert-success mb-0">Migration completed successfully!</div>') . '
                        </div>
                        <a href="admin-settings.php" class="btn btn-' . ($dry_run ? 'secondary' : 'primary') . '">Back to Settings</a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>';
} else {
    echo "\nMigration process " . ($dry_run ? "test completed. Run without --dry-run to apply changes." : "completed successfully!");
}