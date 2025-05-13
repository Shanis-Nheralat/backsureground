<?php
/**
 * Login Debug Script
 * 
 * This script helps diagnose issues with the login system.
 * IMPORTANT: Use this script only for debugging and remove it after resolving the issue.
 */

// Include database connection
require_once 'shared/db.php';

// For security, we'll require a specific debug parameter
$debug_key = 'debug12345'; // Change this to a random value for security
if (!isset($_GET['debug']) || $_GET['debug'] !== $debug_key) {
    die('Debug access denied. Provide the correct debug key to access this script.');
}

// Test database connection
echo "<h2>Database Connection Test</h2>";
try {
    $test_query = $pdo->query("SELECT 'Database connection successful' AS result")->fetch();
    echo "<p style='color:green;'>✓ {$test_query['result']}</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    die("Fix database connection issues before proceeding.");
}

// Check if the users table exists and has records
echo "<h2>Users Table Check</h2>";
try {
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "<p style='color:green;'>✓ Users table exists and has $count records</p>";
    
    // Check default admin user
    $admin = $pdo->query("SELECT * FROM users WHERE username = 'admin'")->fetch();
    if ($admin) {
        echo "<p style='color:green;'>✓ Admin user exists</p>";
        echo "<p>Username: {$admin['username']}</p>";
        echo "<p>Email: {$admin['email']}</p>";
        echo "<p>Role: {$admin['role']}</p>";
        echo "<p>Status: {$admin['status']}</p>";
        echo "<p>Password hash: " . substr($admin['password'], 0, 20) . "...</p>";
        
        // Check if the hash is valid for password_verify
        if (password_get_info($admin['password'])['algoName'] !== 'unknown') {
            echo "<p style='color:green;'>✓ Password hash is in a valid format for password_verify()</p>";
        } else {
            echo "<p style='color:red;'>✗ Password hash is NOT in a valid format for password_verify()</p>";
        }
    } else {
        echo "<p style='color:red;'>✗ Admin user does not exist</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red;'>✗ Users table check failed: " . $e->getMessage() . "</p>";
}

// Password verification test
echo "<h2>Password Verification Test</h2>";
echo "<form method='post' action=''>";
echo "<p><label>Username: <input type='text' name='username'></label></p>";
echo "<p><label>Password: <input type='password' name='password'></label></p>";
echo "<p><button type='submit' name='test_login'>Test Login</button></p>";
echo "</form>";

if (isset($_POST['test_login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Get user from database
    $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    $query = "SELECT id, username, password, email, name, role, status FROM users WHERE $field = ?";
    $user = db_query_row($query, [$username]);
    
    echo "<h3>Login Attempt Details:</h3>";
    
    if (!$user) {
        echo "<p style='color:red;'>✗ User not found: No user with $field = '$username'</p>";
    } else {
        echo "<p>User found: {$user['username']} (Role: {$user['role']}, Status: {$user['status']})</p>";
        
        if ($user['status'] !== 'active') {
            echo "<p style='color:red;'>✗ User account is not active</p>";
        }
        
        // Verify password
        $password_match = password_verify($password, $user['password']);
        echo "<p>" . ($password_match ? 
            "<span style='color:green;'>✓ Password matches!</span>" : 
            "<span style='color:red;'>✗ Password does not match</span>") . "</p>";
        
        // Show more details for debugging
        echo "<p>Hash from DB: " . substr($user['password'], 0, 20) . "...</p>";
        
        // Try to create a new hash with the same password
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        echo "<p>New hash with PASSWORD_DEFAULT: " . substr($new_hash, 0, 20) . "...</p>";
        
        // Test if raw comparison would work (this is just for debugging, never use in production)
        if ($password === $user['password']) {
            echo "<p style='color:orange;'>⚠ The password matches if compared directly (without hashing)</p>";
        }
    }
}

// Check login.php for potential issues
echo "<h2>Login File Check</h2>";
if (file_exists('login.php')) {
    $login_content = file_get_contents('login.php');
    echo "<p style='color:green;'>✓ login.php exists</p>";
    
    // Check if the login function is called correctly
    if (strpos($login_content, 'login(') !== false) {
        echo "<p style='color:green;'>✓ login() function is called</p>";
    } else {
        echo "<p style='color:red;'>✗ login() function may not be called correctly</p>";
    }
    
    // Check for other potential issues
    if (strpos($login_content, 'password_verify') !== false) {
        echo "<p style='color:green;'>✓ password_verify() is used somewhere in the file</p>";
    } else {
        echo "<p style='color:orange;'>⚠ password_verify() not found in login.php</p>";
    }
} else {
    echo "<p style='color:red;'>✗ login.php does not exist</p>";
}

// Check auth file
if (file_exists('shared/auth/admin-auth.php')) {
    $auth_content = file_get_contents('shared/auth/admin-auth.php');
    echo "<p style='color:green;'>✓ admin-auth.php exists</p>";
    
    // Check login function
    if (strpos($auth_content, 'function login') !== false) {
        echo "<p style='color:green;'>✓ login() function is defined</p>";
        
        // Check password verification
        if (strpos($auth_content, 'password_verify') !== false) {
            echo "<p style='color:green;'>✓ password_verify() is used in admin-auth.php</p>";
        } else {
            echo "<p style='color:red;'>✗ password_verify() not found in admin-auth.php</p>";
        }
    } else {
        echo "<p style='color:red;'>✗ login() function may not be defined correctly</p>";
    }
} else {
    echo "<p style='color:red;'>✗ admin-auth.php does not exist</p>";
}

// Suggest fixes
echo "<h2>Possible Solutions</h2>";
echo "<ul>";
echo "<li>If the password hash is invalid, reset the admin user password in the database manually:
<pre>
UPDATE users SET password = '".password_hash('admin123', PASSWORD_DEFAULT)."' WHERE username = 'admin';
</pre>
</li>";
echo "<li>Check for PHP version compatibility - password_hash/verify requires PHP 5.5+</li>";
echo "<li>Make sure the login function in admin-auth.php is correctly implemented</li>";
echo "<li>Verify database connection settings in db.php</li>";
echo "</ul>";
