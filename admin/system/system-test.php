<?php
// /admin/system/system-test.php
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';

// Require admin authentication
require_admin_auth();
require_admin_role(['admin']);

// Page variables
$page_title = 'System Test';
$current_page = 'system_test';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/admin/dashboard.php'],
    ['title' => 'System', 'url' => '#'],
    ['title' => 'System Test', 'url' => '#']
];

// Include template parts
include '../../shared/templates/admin-head.php';
include '../../shared/templates/admin-sidebar.php';
include '../../shared/templates/admin-header.php';

// Run tests if requested
$test_results = [];
if (isset($_GET['run_tests']) && $_GET['run_tests'] == 1) {
    $test_results = run_system_tests();
}
?>

<main class="admin-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            
            <div>
                <a href="?run_tests=1" class="btn btn-primary">
                    <i class="fas fa-vial me-1"></i> Run All Tests
                </a>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <?php if (!empty($test_results)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Test Results</h6>
                
                <?php
                $total_tests = count($test_results);
                $passed_tests = count(array_filter($test_results, function($test) { return $test['status'] === 'pass'; }));
                ?>
                
                <span class="badge <?php echo ($passed_tests === $total_tests) ? 'bg-success' : 'bg-warning'; ?>">
                    <?php echo $passed_tests; ?>/<?php echo $total_tests; ?> Tests Passed
                </span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($test_results as $test): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($test['name']); ?></td>
                                <td>
                                    <?php if ($test['status'] === 'pass'): ?>
                                    <span class="badge bg-success">Pass</span>
                                    <?php elseif ($test['status'] === 'warning'): ?>
                                    <span class="badge bg-warning">Warning</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Fail</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $test['details']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card shadow mb-4">
            <div class="card-body">
                <p class="mb-0">Click "Run All Tests" to perform a comprehensive system check.</p>
                <p class="text-muted">This will test your database, file permissions, PHP configuration, and more.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Test Categories -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Database Tests</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Database Connection
                                <a href="?run_tests=1&test=db_connection" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Table Structure
                                <a href="?run_tests=1&test=db_tables" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Default Settings
                                <a href="?run_tests=1&test=db_settings" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">File System Tests</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Directory Permissions
                                <a href="?run_tests=1&test=file_permissions" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                File Upload
                                <a href="?run_tests=1&test=file_upload" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                .htaccess Files
                                <a href="?run_tests=1&test=htaccess" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">PHP Configuration Tests</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Required Extensions
                                <a href="?run_tests=1&test=php_extensions" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                PHP Settings
                                <a href="?run_tests=1&test=php_settings" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Email Functionality
                                <a href="?run_tests=1&test=email" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Security Tests</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                CSRF Protection
                                <a href="?run_tests=1&test=csrf" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                SSL Configuration
                                <a href="?run_tests=1&test=ssl" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                File Access Control
                                <a href="?run_tests=1&test=file_access" class="btn btn-sm btn-outline-primary">Run Test</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../../shared/templates/admin-footer.php'; ?>

<?php
/**
 * Run system tests
 * 
 * @return array Test results
 */
function run_system_tests() {
    $results = [];
    
    // Check if a specific test was requested
    $specific_test = isset($_GET['test']) ? $_GET['test'] : null;
    
    // Database Tests
    if (!$specific_test || $specific_test === 'db_connection') {
        $results[] = test_database_connection();
    }
    
    if (!$specific_test || $specific_test === 'db_tables') {
        $results[] = test_database_tables();
    }
    
    if (!$specific_test || $specific_test === 'db_settings') {
        $results[] = test_default_settings();
    }
    
    // File System Tests
    if (!$specific_test || $specific_test === 'file_permissions') {
        $results[] = test_directory_permissions();
    }
    
    if (!$specific_test || $specific_test === 'file_upload') {
        $results[] = test_file_upload();
    }
    
    if (!$specific_test || $specific_test === 'htaccess') {
        $results[] = test_htaccess_files();
    }
    
    // PHP Configuration Tests
    if (!$specific_test || $specific_test === 'php_extensions') {
        $results[] = test_php_extensions();
    }
    
    if (!$specific_test || $specific_test === 'php_settings') {
        $results[] = test_php_settings();
    }
    
    if (!$specific_test || $specific_test === 'email') {
        $results[] = test_email_functionality();
    }
    
    // Security Tests
    if (!$specific_test || $specific_test === 'csrf') {
        $results[] = test_csrf_protection();
    }
    
    if (!$specific_test || $specific_test === 'ssl') {
        $results[] = test_ssl_configuration();
    }
    
    if (!$specific_test || $specific_test === 'file_access') {
        $results[] = test_file_access_control();
    }
    
    return $results;
}

/**
 * Test database connection
 */
function test_database_connection() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT 1");
        return [
            'name' => 'Database Connection',
            'status' => 'pass',
            'details' => 'Successfully connected to the database.'
        ];
    } catch (Exception $e) {
        return [
            'name' => 'Database Connection',
            'status' => 'fail',
            'details' => 'Failed to connect to the database: ' . $e->getMessage()
        ];
    }
}

/**
 * Test database tables
 */
function test_database_tables() {
    global $pdo;
    
    try {
        // Required tables to check
        $required_tables = [
            'users', 'roles', 'permissions', 'role_permissions',
            'password_resets', 'admin_activity_log', 'settings',
            'support_tickets', 'support_replies', 'support_attachments',
            'on_demand_tasks', 'task_uploads', 'task_logs',
            'employee_time_logs', 'notifications', 'email_log',
            'admin_extended_log', 'dashboard_widgets'
        ];
        
        // Get list of tables in the database
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Check for missing tables
        $missing_tables = array_diff($required_tables, $tables);
        
        if (empty($missing_tables)) {
            return [
                'name' => 'Database Tables',
                'status' => 'pass',
                'details' => 'All required tables exist in the database.'
            ];
        } else {
            return [
                'name' => 'Database Tables',
                'status' => 'fail',
                'details' => 'Missing tables: ' . implode(', ', $missing_tables)
            ];
        }
    } catch (Exception $e) {
        return [
            'name' => 'Database Tables',
            'status' => 'fail',
            'details' => 'Failed to check database tables: ' . $e->getMessage()
        ];
    }
}

/**
 * Test default settings
 */
function test_default_settings() {
    global $pdo;
    
    try {
        // Required settings to check
        $required_settings = [
            'site_name', 'email_sender_name', 'email_sender_email',
            'session_timeout', 'login_attempts', 'login_lockout_duration',
            'backup_retention_days', 'support_ticket_auto_close_days'
        ];
        
        // Get list of settings in the database
        $stmt = $pdo->query("SELECT setting_key FROM settings");
        $settings = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Check for missing settings
        $missing_settings = array_diff($required_settings, $settings);
        
        if (empty($missing_settings)) {
            return [
                'name' => 'Default Settings',
                'status' => 'pass',
                'details' => 'All required settings exist in the database.'
            ];
        } else {
            return [
                'name' => 'Default Settings',
                'status' => 'warning',
                'details' => 'Missing settings: ' . implode(', ', $missing_settings)
            ];
        }
    } catch (Exception $e) {
        return [
            'name' => 'Default Settings',
            'status' => 'fail',
            'details' => 'Failed to check default settings: ' . $e->getMessage()
        ];
    }
}

/**
 * Test directory permissions
 */
function test_directory_permissions() {
    $root_dir = dirname(dirname(__DIR__));
    
    // Directories that should be writable
    $directories = [
        '/uploads',
        '/uploads/support',
        '/uploads/on_demand',
        '/uploads/plans/documents',
        '/logs',
        '/backups',
    ];
    
    $not_writable = [];
    
    foreach ($directories as $dir) {
        $path = $root_dir . $dir;
        
        if (!is_dir($path)) {
            $not_writable[] = $dir . ' (directory does not exist)';
        } elseif (!is_writable($path)) {
            $not_writable[] = $dir . ' (not writable)';
        }
    }
    
    if (empty($not_writable)) {
        return [
            'name' => 'Directory Permissions',
            'status' => 'pass',
            'details' => 'All required directories exist and are writable.'
        ];
    } else {
        return [
            'name' => 'Directory Permissions',
            'status' => 'fail',
            'details' => 'Issues found: ' . implode(', ', $not_writable)
        ];
    }
}

/**
 * Test file upload
 */
function test_file_upload() {
    $upload_dir = dirname(dirname(__DIR__)) . '/uploads';
    $test_file = $upload_dir . '/test_upload.txt';
    
    try {
        // Try to create a test file
        $success = file_put_contents($test_file, 'Test file for upload functionality');
        
        if ($success) {
            // Try to delete the test file
            unlink($test_file);
            
            return [
                'name' => 'File Upload',
                'status' => 'pass',
                'details' => 'File upload test successful.'
            ];
        } else {
            return [
                'name' => 'File Upload',
                'status' => 'fail',
                'details' => 'Failed to create test file in uploads directory.'
            ];
        }
    } catch (Exception $e) {
        return [
            'name' => 'File Upload',
            'status' => 'fail',
            'details' => 'File upload test failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Test .htaccess files
 */
function test_htaccess_files() {
    $root_dir = dirname(dirname(__DIR__));
    
    // .htaccess files that should exist
    $htaccess_files = [
        '/.htaccess', // Root .htaccess
        '/uploads/.htaccess',
        '/logs/.htaccess',
        '/backups/.htaccess'
    ];
    
    $missing_files = [];
    
    foreach ($htaccess_files as $file) {
        $path = $root_dir . $file;
        
        if (!file_exists($path)) {
            $missing_files[] = $file;
        }
    }
    
    if (empty($missing_files)) {
        return [
            'name' => '.htaccess Files',
            'status' => 'pass',
            'details' => 'All required .htaccess files exist.'
        ];
    } else {
        return [
            'name' => '.htaccess Files',
            'status' => 'warning',
            'details' => 'Missing .htaccess files: ' . implode(', ', $missing_files)
        ];
    }
}

/**
 * Test PHP extensions
 */
function test_php_extensions() {
    // Required PHP extensions
    $required_extensions = [
        'pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo', 'gd',
        'zip', 'openssl', 'curl'
    ];
    
    $missing_extensions = [];
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    if (empty($missing_extensions)) {
        return [
            'name' => 'PHP Extensions',
            'status' => 'pass',
            'details' => 'All required PHP extensions are loaded.'
        ];
    } else {
        return [
            'name' => 'PHP Extensions',
            'status' => 'fail',
            'details' => 'Missing PHP extensions: ' . implode(', ', $missing_extensions)
        ];
    }
}

/**
 * Test PHP settings
 */
function test_php_settings() {
    $settings_to_check = [
        'file_uploads' => true,
        'post_max_size' => '8M',
        'upload_max_filesize' => '8M',
        'memory_limit' => '128M',
        'max_execution_time' => 30,
    ];
    
    $issues = [];
    
    foreach ($settings_to_check as $setting => $min_value) {
        $current_value = ini_get($setting);
        
        if ($setting === 'file_uploads') {
            if ($current_value != $min_value) {
                $issues[] = "$setting is set to $current_value, should be $min_value";
            }
        } else {
            // Convert memory values to bytes for comparison
            $current_bytes = return_bytes($current_value);
            $min_bytes = return_bytes($min_value);
            
            if ($current_bytes < $min_bytes) {
                $issues[] = "$setting is set to $current_value, should be at least $min_value";
            }
        }
    }
    
    if (empty($issues)) {
        return [
            'name' => 'PHP Settings',
            'status' => 'pass',
            'details' => 'All PHP settings meet the requirements.'
        ];
    } else {
        return [
            'name' => 'PHP Settings',
            'status' => 'warning',
            'details' => implode('<br>', $issues)
        ];
    }
}

/**
 * Convert memory value string to bytes
 */
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = intval($val);
    
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    
    return $val;
}

/**
 * Test email functionality
 */
function test_email_functionality() {
    // Check if mail() function is available
    if (!function_exists('mail')) {
        return [
            'name' => 'Email Functionality',
            'status' => 'warning',
            'details' => 'The mail() function is not available. Email sending may not work correctly.'
        ];
    }
    
    // Check if PHPMailer is available
    if (!file_exists(dirname(dirname(__DIR__)) . '/shared/mailer/mailer.php')) {
        return [
            'name' => 'Email Functionality',
            'status' => 'warning',
            'details' => 'The mailer.php file is missing. Email sending may not work correctly.'
        ];
    }
    
    return [
        'name' => 'Email Functionality',
        'status' => 'pass',
        'details' => 'Email functionality appears to be properly configured. To fully test, send a test email from the system.'
    ];
}

/**
 * Test CSRF protection
 */
function test_csrf_protection() {
    // Check if CSRF token is set in session
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        return [
            'name' => 'CSRF Protection',
            'status' => 'fail',
            'details' => 'CSRF token is not set in the session.'
        ];
    }
    
    return [
        'name' => 'CSRF Protection',
        'status' => 'pass',
        'details' => 'CSRF protection is properly configured.'
    ];
}

/**
 * Test SSL configuration
 */
function test_ssl_configuration() {
    // Check if the current connection is secure
    $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    if (!$is_secure) {
        return [
            'name' => 'SSL Configuration',
            'status' => 'warning',
            'details' => 'The current connection is not using HTTPS. SSL should be enabled for production.'
        ];
    }
    
    return [
        'name' => 'SSL Configuration',
        'status' => 'pass',
        'details' => 'SSL is properly configured.'
    ];
}

/**
 * Test file access control
 */
function test_file_access_control() {
    $root_dir = dirname(dirname(__DIR__));
    
    // Try to access a few protected directories via HTTP
    $urls_to_check = [
        '/uploads/',
        '/logs/',
        '/backups/',
        '/shared/'
    ];
    
    $issues = [];
    
    foreach ($urls_to_check as $url) {
        $full_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $url;
        
        $ch = curl_init($full_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status !== 403 && $status !== 404) {
            $issues[] = "Directory $url returned status $status (should be 403 or 404)";
        }
    }
    
    if (empty($issues)) {
        return [
            'name' => 'File Access Control',
            'status' => 'pass',
            'details' => 'Protected directories are properly secured.'
        ];
    } else {
        return [
            'name' => 'File Access Control',
            'status' => 'warning',
            'details' => implode('<br>', $issues)
        ];
    }
}
?>