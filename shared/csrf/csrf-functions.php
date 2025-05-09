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
        log_action('login', "User {$user['username']} logged in");
        
        return true;
    }
    
    // Incorrect password
    log_action('failed_login', "Failed login attempt for {$username}");
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
function log_action($action_type, $details, $resource = null, $resource_id = null) {
    $data = [
        'action_type' => $action_type,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'resource' => $resource,
        'resource_id' => $resource_id
    ];
    
    // Add user info if logged in
    if (is_logged_in()) {
        $data['user_id'] = $_SESSION['user_id'];
        $data['username'] = $_SESSION['username'];
    }
    
    // Insert into activity log
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

// Check for session timeout - will be implemented in Phase 2
// function check_session_timeout() {}

// IP validation - will be implemented in Phase 2
// function validate_ip() {}

// Remember me functionality - will be implemented in Phase 2
// function check_remember_me() {}
// function set_remember_me($user_id) {}

// Brute force protection - will be implemented in Phase 2
// function check_brute_force($username) {}