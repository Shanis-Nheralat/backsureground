<?php
/**
 * Authentication System
 * 
 * Manages user authentication, session security, and permissions.
 * Core of the role-based authentication system.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    session_start();
}

// Include database connection if not already included
if (!function_exists('db_query')) {
    require_once __DIR__ . '/../../db.php';
}

/**
 * Check if a user is logged in
 * 
 * @return bool True if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require authentication to access a page
 * Redirects to login page if not authenticated
 */
function require_auth() {
    if (!is_logged_in()) {
        // Store the requested URL for redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page
        header('Location: /login.php');
        exit;
    }
    
    // Log activity
    log_action('page_access', $_SERVER['REQUEST_URI']);
}

/**
 * Check if user has a specific role
 * 
 * @param string|array $roles Role or array of roles to check
 * @return bool True if user has at least one of the specified roles
 */
function has_role($roles) {
    if (!is_logged_in()) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Require specific role to access a page
 * Redirects to dashboard if user doesn't have the required role
 * 
 * @param string|array $roles Role or array of roles required
 */
function require_role($roles) {
    require_auth(); // First check if logged in
    
    if (!has_role($roles)) {
        // Log unauthorized access attempt
        log_action('unauthorized_access', 'Role required: ' . (is_array($roles) ? implode(', ', $roles) : $roles));
        
        // Redirect to appropriate dashboard based on user's role
        redirect_to_dashboard();
        exit;
    }
}

/**
 * Check if user has a specific permission
 * 
 * @param string $permission Permission to check
 * @return bool True if user has the permission
 */
function has_permission($permission) {
    if (!is_logged_in()) {
        return false;
    }
    
    // Get user role
    $role = $_SESSION['user_role'];
    
    // Admin has all permissions
    if ($role === 'admin') {
        return true;
    }
    
    // Check if role has the specific permission
    $query = "SELECT COUNT(*) FROM role_permissions rp
              JOIN roles r ON rp.role_id = r.id
              JOIN permissions p ON rp.permission_id = p.id
              WHERE r.name = ? AND p.name = ?";
    
    return db_query_value($query, [$role, $permission]) > 0;
}

/**
 * Require specific permission to access a page
 * Redirects to dashboard if user doesn't have the required permission
 * 
 * @param string $permission Permission required
 */
function require_permission($permission) {
    require_auth(); // First check if logged in
    
    if (!has_permission($permission)) {
        // Log unauthorized access attempt
        log_action('permission_denied', 'Permission required: ' . $permission);
        
        // Redirect to appropriate dashboard based on user's role
        redirect_to_dashboard();
        exit;
    }
}

/**
 * Authenticate user
 * 
 * @param string $username Username or email
 * @param string $password Password
 * @return bool True on successful login
 */
function login($username, $password) {
    // Check if input is email or username
    $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    
    // Get user from database
    $query = "SELECT id, username, password, email, name, role, status, login_attempts, last_attempt_time
              FROM users WHERE $field = ?";
    $user = db_query_row($query, [$username]);
    
    // If user not found or inactive
    if (!$user || $user['status'] !== 'active') {
        return false;
    }
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        
        // Reset login attempts
        db_update('users', 
            ['login_attempts' => 0, 'last_login' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$user['id']]
        );
        
        // Log successful login
     //   log_action('login', "User {$user['username']} logged in");
        
        return true;
    }
    
    // Incorrect password
    //log_action('failed_login', "Failed login attempt for {$username}");
    return false;
}

/**
 * Log out the current user
 */
function logout() {
    // Log the logout action before destroying the session
    if (is_logged_in()) {
        log_action('logout', "User {$_SESSION['username']} logged out");
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Redirect to login page
    header('Location: /login.php');
    exit;
}

/**
 * Redirect user to appropriate dashboard based on role
 */
function redirect_to_dashboard() {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
    
    switch ($_SESSION['user_role']) {
        case 'admin':
            header('Location: /admin/dashboard.php');
            break;
        case 'client':
            header('Location: /client/dashboard.php');
            break;
        case 'employee':
            header('Location: /employee/dashboard.php');
            break;
        default:
            header('Location: /login.php');
    }
    exit;
}

/**
 * Log user action to the activity log
 * 
 * @param string $action_type Type of action
 * @param string $details Details of the action
 * @param string $resource Resource affected
 * @param int $resource_id Resource ID
 */
function log_action($action_type, $details) {
    $data = [
        'action_type' => $action_type,
        'action_details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];

    if (is_logged_in()) {
        $data['admin_id'] = $_SESSION['user_id'];
    }

    db_insert('admin_activity_log', $data);
}

/**
 * Generate CSRF token and store in session
 * 
 * @return string CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool True if token is valid
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check for "remember me" token and auto-login
 */
function check_remember_me() {
    global $pdo;
    
    // Check if the remember token cookie exists
    if (isset($_COOKIE['remember_token']) && !is_admin_logged_in()) {
        $token = $_COOKIE['remember_token'];
        
        // Look up the token in the database
        $stmt = $pdo->prepare("
            SELECT id, username, role, name, email 
            FROM users 
            WHERE remember_token = ? AND token_expiry > NOW() AND status = 'active'
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Auto-login the user
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            
            // Generate a new token for security
            $new_token = bin2hex(random_bytes(32));
            $expires = new DateTime('+30 days');
            
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
            $stmt->execute([$new_token, $expires->format('Y-m-d H:i:s'), $user['id']]);
            
            // Update the cookie
            setcookie('remember_token', $new_token, [
                'expires' => $expires->getTimestamp(),
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            // Log the auto-login
            log_admin_action('login', 'Authentication', ['username' => $user['username'], 'method' => 'remember_me']);
            
            return true;
        }
    }
    
    return false;
}

/**
 * Authentication and Security for Admin Panel
 */

/**
 * Check if admin is logged in
 */
function is_admin_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Require admin authentication
 */
function require_admin_auth() {
    if (!is_admin_logged_in()) {
        // Store intended URL for redirection after login
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page
        header('Location: /login.php');
        exit;
    }
    
    // Verify session validity
    if (!check_session_validity()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Require specific admin role
 */
function require_admin_role($allowed_roles = ['admin']) {
    if (!is_admin_logged_in()) {
        header('Location: /login.php');
        exit;
    }
    
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        // Log unauthorized access attempt
        log_admin_extended_action(
            $_SESSION['user_id'], 
            'access_denied', 
            'Authorization', 
            null, 
            null, 
            null, 
            null, 
            'Attempted to access restricted area requiring roles: ' . implode(', ', $allowed_roles)
        );
        
        // Redirect to appropriate dashboard based on role
        switch ($_SESSION['role']) {
            case 'client':
                header('Location: /client/dashboard.php');
                break;
            case 'employee':
                header('Location: /employee/dashboard.php');
                break;
            default:
                header('Location: /login.php');
        }
        exit;
    }
}

/**
 * Check session validity (Includes session timeout and IP validation)
 */
function check_session_validity() {
    // Session timeout check
    $timeout = intval(get_setting('session_timeout', 30)) * 60; // Convert minutes to seconds
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        // Session has expired
        admin_logout('Session expired due to inactivity');
        return false;
    }
    
    // IP address check to prevent session hijacking
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        admin_logout('Session invalidated due to IP address change');
        return false;
    }
    
    // User agent check
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        admin_logout('Session invalidated due to browser change');
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Check for "remember me" token and auto-login
 */
function check_remember_me() {
    global $pdo;
    
    // Check if the remember token cookie exists
    if (isset($_COOKIE['remember_token']) && !is_admin_logged_in()) {
        $token = $_COOKIE['remember_token'];
        
        // Look up the token in the database
        $stmt = $pdo->prepare("
            SELECT id, username, role, name, email 
            FROM users 
            WHERE remember_token = ? AND token_expiry > NOW() AND status = 'active'
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Auto-login the user
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            
            // Generate a new token for security
            $new_token = bin2hex(random_bytes(32));
            $expires = new DateTime('+30 days');
            
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
            $stmt->execute([$new_token, $expires->format('Y-m-d H:i:s'), $user['id']]);
            
            // Update the cookie
            setcookie('remember_token', $new_token, [
                'expires' => $expires->getTimestamp(),
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            // Log the auto-login
            log_admin_action('login', 'Authentication', ['username' => $user['username'], 'method' => 'remember_me']);
            
            return true;
        }
    }
    
    return false;
}

/**
 * Admin login function with brute force protection
 */
function admin_login($username, $password, $remember = false) {
    global $pdo;
    
    // Check for too many failed attempts
    $stmt = $pdo->prepare("SELECT login_attempts, last_attempt_time, status FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Get lockout duration from settings
        $lockout_minutes = intval(get_setting('login_lockout_duration', 15));
        $max_attempts = intval(get_setting('login_attempts', 5));
        
        // Check if account is locked
        if ($user['login_attempts'] >= $max_attempts) {
            $last_attempt = new DateTime($user['last_attempt_time']);
            $now = new DateTime();
            $diff = $now->diff($last_attempt);
            $minutes_passed = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
            
            if ($minutes_passed < $lockout_minutes) {
                // Account is still locked
                $time_remaining = $lockout_minutes - $minutes_passed;
                log_admin_action('login_failed', 'Account locked', ['username' => $username, 'reason' => 'Too many attempts']);
                return ['success' => false, 'message' => "Account temporarily locked. Try again in {$time_remaining} minutes."];
            } else {
                // Reset attempts after lockout period
                $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0 WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
            }
        }
        
        // Check if account is inactive
        if ($user['status'] === 'inactive') {
            log_admin_action('login_failed', 'Account inactive', ['username' => $username]);
            return ['success' => false, 'message' => 'Account is inactive. Please contact administrator.'];
        }
    }
    
    // Continue with normal login logic
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?)");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Login successful - reset login attempts
        $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, last_attempt_time = NOW(), last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Set up session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        
        // Generate CSRF token
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        // Handle remember me
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = new DateTime('+30 days');
            
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
            $stmt->execute([$token, $expires->format('Y-m-d H:i:s'), $user['id']]);
            
            setcookie('remember_token', $token, [
                'expires' => $expires->getTimestamp(),
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
        
        // Log the successful login
        log_admin_action('login', 'Authentication', ['username' => $user['username'], 'role' => $user['role']]);
        
        return ['success' => true, 'user' => $user];
    } else {
        // Login failed - increment attempts
        if ($user) {
            $stmt = $pdo->prepare("UPDATE users SET login_attempts = login_attempts + 1, last_attempt_time = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
        }
        
        log_admin_action('login_failed', 'Authentication', ['username' => $username, 'reason' => 'Invalid credentials']);
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
}

/**
 * Admin logout function
 */
function admin_logout($reason = 'User logout') {
    // Log the logout action if user was logged in
    if (isset($_SESSION['user_id'])) {
        log_admin_action('logout', 'Authentication', ['username' => $_SESSION['username'] ?? 'unknown', 'reason' => $reason]);
    }
    
    // Clear remember token if it exists
    if (isset($_COOKIE['remember_token'])) {
        // Remove from database if user is logged in
        if (isset($_SESSION['user_id'])) {
            global $pdo;
            $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, token_expiry = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        
        // Clear the cookie
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    
    // Destroy the session
    session_unset();
    session_destroy();
    
    // Restart the session to allow for flash messages
    session_start();
}

/**
 * Check if user has a specific permission
 */
function has_admin_permission($permission_key) {
    global $pdo;
    
    // Admin role has all permissions
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    // Get user's role
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Check if the role has the permission
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as has_permission
        FROM role_permissions rp
        JOIN roles r ON rp.role_id = r.id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE r.name = ? AND p.name = ?
    ");
    $stmt->execute([$role, $permission_key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['has_permission'] > 0;
}

/**
 * Log admin actions
 */
function log_admin_action($action_type, $module, $details = []) {
    global $pdo;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $details_json = json_encode($details);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log 
            (user_id, action_type, resource, details, ip_address) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $action_type, $module, $details_json, $ip_address]);
        
        // Also log to the extended log if it exists
        if (function_exists('log_admin_extended_action')) {
            log_admin_extended_action(
                $user_id,
                $action_type,
                $module,
                null,
                null,
                null,
                null,
                $details_json
            );
        }
        
        return true;
    } catch (Exception $e) {
        error_log('Error logging admin action: ' . $e->getMessage());
        return false;
    }
}
?>
