<?php
/**
 * System Diagnostic Tool
 * 
 * This script checks for common issues that might cause 500 errors.
 * Place this file in your root directory and access it via browser.
 */

// Enable detailed error reporting for this script
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to capture any errors
ob_start();

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        h2 { color: #444; margin-top: 30px; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>System Diagnostic Tool</h1>
    <p>This tool checks for common issues that might cause 500 errors.</p>';

// ===== 1. PHP INFORMATION =====
echo '<div class="section">
    <h2>PHP Environment</h2>';

// PHP Version
echo '<p>PHP Version: <strong>' . phpversion() . '</strong>';
if (version_compare(PHP_VERSION, '7.0.0', '<')) {
    echo ' <span class="error">(Warning: PHP 7+ recommended)</span>';
} else {
    echo ' <span class="success">(OK)</span>';
}
echo '</p>';

// Check extensions
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'session'];
echo '<p>Required Extensions:</p><ul>';
foreach ($required_extensions as $ext) {
    echo '<li>' . $ext . ': ';
    if (extension_loaded($ext)) {
        echo '<span class="success">Loaded</span>';
    } else {
        echo '<span class="error">Not loaded</span>';
    }
    echo '</li>';
}
echo '</ul>';

// Memory Limit
echo '<p>Memory Limit: <strong>' . ini_get('memory_limit') . '</strong>';
$memory_limit = ini_get('memory_limit');
$memory_limit_bytes = return_bytes($memory_limit);
if ($memory_limit_bytes < 64 * 1024 * 1024) { // Less than 64MB
    echo ' <span class="warning">(Low: 64M+ recommended)</span>';
} else {
    echo ' <span class="success">(OK)</span>';
}
echo '</p>';

// Max Execution Time
echo '<p>Max Execution Time: <strong>' . ini_get('max_execution_time') . '</strong>';
if (ini_get('max_execution_time') < 30) {
    echo ' <span class="warning">(Low: 30+ seconds recommended)</span>';
} else {
    echo ' <span class="success">(OK)</span>';
}
echo '</p>';

echo '</div>';

// ===== 2. ERROR LOGS =====
echo '<div class="section">
    <h2>Error Logs</h2>';

// Check if error logs are enabled
echo '<p>Error logging enabled: <strong>' . (ini_get('log_errors') ? 'Yes' : 'No') . '</strong></p>';
echo '<p>Error log path: <strong>' . ini_get('error_log') . '</strong></p>';

// Try to read recent error logs
$error_log_path = ini_get('error_log');
if ($error_log_path && file_exists($error_log_path) && is_readable($error_log_path)) {
    echo '<p class="success">Error log is readable.</p>';
    
    // Get last 20 lines of the error log
    echo '<p>Last 20 lines of error log:</p>';
    echo '<pre>';
    $log_content = shell_exec('tail -n 20 ' . escapeshellarg($error_log_path));
    echo htmlspecialchars($log_content ?: 'Unable to read log content');
    echo '</pre>';
} else {
    echo '<p class="warning">Cannot read error log file. It may not exist, or the path may be incorrect, or permissions are insufficient.</p>';
    
    // Alternative: check for Apache error logs
    if (function_exists('apache_get_modules')) {
        echo '<p>Server is running Apache. You may check Apache error logs for more information.</p>';
    }
}

echo '</div>';

// ===== 3. DATABASE CONNECTION =====
echo '<div class="section">
    <h2>Database Connection</h2>';

// Try to include database connection file without executing the actual connection
echo '<p>Looking for database configuration file...</p>';
$db_files = ['shared/db.php', 'db.php', 'config/database.php', 'includes/db.php'];
$db_file_found = false;

foreach ($db_files as $file) {
    if (file_exists($file)) {
        echo '<p class="success">Found database file: ' . $file . '</p>';
        $db_file_found = true;
        
        // Get DB details by parsing the file (safer than including it)
        $db_content = file_get_contents($file);
        $db_details = [];
        
        // Extract database name
        if (preg_match('/\$db_name\s*=\s*[\'"](.+?)[\'"]/', $db_content, $matches)) {
            $db_details['name'] = $matches[1];
        }
        
        // Extract database host
        if (preg_match('/\$db_host\s*=\s*[\'"](.+?)[\'"]/', $db_content, $matches)) {
            $db_details['host'] = $matches[1];
        }
        
        // Extract database user
        if (preg_match('/\$db_user\s*=\s*[\'"](.+?)[\'"]/', $db_content, $matches)) {
            $db_details['user'] = $matches[1];
        }
        
        echo '<p>Database details (from file):</p>';
        echo '<ul>';
        echo '<li>Database Name: <strong>' . ($db_details['name'] ?? 'Not found') . '</strong></li>';
        echo '<li>Database Host: <strong>' . ($db_details['host'] ?? 'Not found') . '</strong></li>';
        echo '<li>Database User: <strong>' . ($db_details['user'] ?? 'Not found') . '</strong></li>';
        echo '</ul>';
        
        // Now try an actual connection
        try {
            // Include the file but catch any errors
            include_once $file;
            
            if (isset($pdo) && $pdo instanceof PDO) {
                echo '<p class="success">Database connection established successfully!</p>';
                
                // Test query
                try {
                    $result = $pdo->query("SELECT 'Connection working' AS result")->fetch();
                    echo '<p class="success">Test query successful: ' . $result['result'] . '</p>';
                    
                    // Check if users table exists
                    try {
                        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                        echo '<p class="success">Users table exists and has ' . $count . ' records</p>';
                    } catch (PDOException $e) {
                        echo '<p class="error">Error accessing users table: ' . $e->getMessage() . '</p>';
                        echo '<p>This may indicate your database tables are not set up correctly.</p>';
                    }
                } catch (PDOException $e) {
                    echo '<p class="error">Test query failed: ' . $e->getMessage() . '</p>';
                }
            } else {
                echo '<p class="error">Database connection failed. PDO object not found after including database file.</p>';
            }
        } catch (Throwable $e) {
            echo '<p class="error">Error including database file: ' . $e->getMessage() . '</p>';
        }
        
        break;
    }
}

if (!$db_file_found) {
    echo '<p class="error">No database configuration file found in common locations.</p>';
}

echo '</div>';

// ===== 4. FILE STRUCTURE CHECK =====
echo '<div class="section">
    <h2>Critical Files Check</h2>';

$critical_files = [
    'login.php' => 'Main login script',
    'shared/auth/admin-auth.php' => 'Authentication logic',
    'shared/csrf/csrf-functions.php' => 'CSRF protection',
    'shared/templates/admin-header.php' => 'Admin template header',
    'shared/templates/admin-sidebar.php' => 'Admin template sidebar',
    'shared/templates/admin-footer.php' => 'Admin template footer',
    'admin/dashboard.php' => 'Admin dashboard',
    'client/dashboard.php' => 'Client dashboard',
    'employee/dashboard.php' => 'Employee dashboard'
];

echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">
    <tr>
        <th>File</th>
        <th>Status</th>
        <th>Size</th>
        <th>Permissions</th>
        <th>Last Modified</th>
    </tr>';

foreach ($critical_files as $file => $description) {
    echo '<tr>';
    echo '<td title="' . $description . '">' . $file . '</td>';
    
    if (file_exists($file)) {
        echo '<td class="success">Found</td>';
        echo '<td>' . filesize($file) . ' bytes</td>';
        echo '<td>' . substr(sprintf('%o', fileperms($file)), -4) . '</td>';
        echo '<td>' . date('Y-m-d H:i:s', filemtime($file)) . '</td>';
    } else {
        echo '<td class="error" colspan="4">Not found</td>';
    }
    
    echo '</tr>';
}

echo '</table>';

// Check upload directory
echo '<h3>Upload Directory</h3>';
$upload_dir = 'uploads';
if (is_dir($upload_dir)) {
    echo '<p class="success">Uploads directory exists.</p>';
    
    // Check if writable
    if (is_writable($upload_dir)) {
        echo '<p class="success">Uploads directory is writable.</p>';
    } else {
        echo '<p class="error">Uploads directory is not writable. Permissions should be 755 or 775.</p>';
    }
} else {
    echo '<p class="warning">Uploads directory does not exist. It will be needed for file uploads.</p>';
}

echo '</div>';

// ===== 5. AUTH FUNCTION TEST =====
echo '<div class="section">
    <h2>Authentication Test</h2>';

// Try to load admin-auth.php for testing
if (file_exists('shared/auth/admin-auth.php')) {
    try {
        require_once 'shared/auth/admin-auth.php';
        
        echo '<p class="success">Authentication file loaded successfully.</p>';
        
        // Check if login function exists
        if (function_exists('login')) {
            echo '<p class="success">Login function exists.</p>';
            
            // Check the login function implementation
            $login_function = new ReflectionFunction('login');
            $login_file = $login_function->getFileName();
            $login_start_line = $login_function->getStartLine();
            $login_end_line = $login_function->getEndLine();
            
            // Get login function content
            $file_content = file($login_file);
            $login_content = implode('', array_slice($file_content, $login_start_line - 1, $login_end_line - $login_start_line + 1));
            
            echo '<p>Login function implementation:</p>';
            echo '<pre>' . htmlspecialchars($login_content) . '</pre>';
            
            // Check if password_verify is used
            if (strpos($login_content, 'password_verify') !== false) {
                echo '<p class="success">Login function uses password_verify() for password checking.</p>';
            } else {
                echo '<p class="error">Warning: Login function does not appear to use password_verify().</p>';
            }
            
            // Try a login with test credentials
            echo '<p>Test the login function directly:</p>';
            echo '<form method="post" action="">
                <input type="hidden" name="test_login" value="1">
                <div>
                    <label>Username: <input type="text" name="username" required></label>
                </div>
                <div>
                    <label>Password: <input type="password" name="password" required></label>
                </div>
                <div>
                    <button type="submit">Test Login</button>
                </div>
            </form>';
            
            // Process login test
            if (isset($_POST['test_login'])) {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                
                echo '<h3>Login Test Results:</h3>';
                
                try {
                    // Check if we can get user from database
                    $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
                    $query = "SELECT id, username, password, email, name, role, status FROM users WHERE $field = ?";
                    
                    if (isset($pdo)) {
                        $stmt = $pdo->prepare($query);
                        $stmt->execute([$username]);
                        $user = $stmt->fetch();
                        
                        if ($user) {
                            echo '<p class="success">User found in database: ' . htmlspecialchars($user['username']) . ' (Role: ' . $user['role'] . ', Status: ' . $user['status'] . ')</p>';
                            
                            // Check password manually
                            if (password_verify($password, $user['password'])) {
                                echo '<p class="success">Password is correct!</p>';
                            } else {
                                echo '<p class="error">Password is incorrect.</p>';
                                echo '<p>Database hash: ' . substr($user['password'], 0, 20) . '...</p>';
                            }
                            
                            // Try actual login function
                            try {
                                $login_result = login($username, $password);
                                if ($login_result) {
                                    echo '<p class="success">Login function returned true - login successful!</p>';
                                } else {
                                    echo '<p class="error">Login function returned false - login failed.</p>';
                                }
                            } catch (Throwable $e) {
                                echo '<p class="error">Error in login function: ' . $e->getMessage() . '</p>';
                            }
                        } else {
                            echo '<p class="error">User not found in database.</p>';
                        }
                    } else {
                        echo '<p class="error">Cannot test user lookup - database connection not available.</p>';
                    }
                } catch (Throwable $e) {
                    echo '<p class="error">Error testing login: ' . $e->getMessage() . '</p>';
                }
            }
        } else {
            echo '<p class="error">Login function does not exist in the auth file.</p>';
        }
    } catch (Throwable $e) {
        echo '<p class="error">Error loading authentication file: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p class="error">Authentication file not found.</p>';
}

echo '</div>';

// ===== 6. RECOMMENDATIONS =====
echo '<div class="section">
    <h2>Recommendations to Fix 500 Error</h2>
    <ol>
        <li>Check the error logs for specific error messages.</li>
        <li>Verify that your database connection credentials are correct.</li>
        <li>Make sure all required tables exist in your database.</li>
        <li>Ensure file permissions are correct (644 for files, 755 for directories).</li>
        <li>Check .htaccess file for any configuration issues.</li>
        <li>Temporarily enable error display by adding these lines to the top of login.php:
            <pre>ini_set(\'display_errors\', 1);
ini_set(\'display_startup_errors\', 1);
error_reporting(E_ALL);</pre>
        </li>
        <li>If using Apache, check the Apache error logs for more details.</li>
        <li>Make sure all required PHP extensions are installed.</li>
        <li>Verify that your server meets minimum PHP version requirements.</li>
    </ol>
</div>';

echo '</body></html>';

// Helper function to convert memory value to bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    
    return $val;
}
