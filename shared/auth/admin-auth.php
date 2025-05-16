<?php
/**
 * Cleaned Authentication System
 * Unified and secure authentication for both admin and general users.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
if (!function_exists('db_query')) {
    require_once __DIR__ . '/../../db.php';
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user authentication
 */
function require_auth() {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit;
    }
    log_action('page_access', $_SERVER['REQUEST_URI']);
}

/**
 * Check if user has a specific role
 */
function has_role($roles) {
    if (!is_logged_in()) return false;
    if (!is_array($roles)) $roles = [$roles];
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Require a specific role
 */
function require_role($roles) {
    require_auth();
    if (!has_role($roles)) {
        log_action('unauthorized_access', 'Required role: ' . (is_array($roles) ? implode(', ', $roles) : $roles));
        redirect_to_dashboard();
    }
}

/**
 * Check user permission
 */
function has_permission($permission) {
    if (!is_logged_in()) return false;
    if ($_SESSION['user_role'] === 'admin') return true;

    $query = "SELECT COUNT(*) FROM role_permissions rp
              JOIN roles r ON rp.role_id = r.id
              JOIN permissions p ON rp.permission_id = p.id
              WHERE r.name = ? AND p.name = ?";

    return db_query_value($query, [$_SESSION['user_role'], $permission]) > 0;
}

/**
 * Require specific permission
 */
function require_permission($permission) {
    require_auth();
    if (!has_permission($permission)) {
        log_action('permission_denied', 'Permission required: ' . $permission);
        redirect_to_dashboard();
    }
}

/**
 * Authenticate user
 */
function login($username, $password) {
    $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    $query = "SELECT * FROM users WHERE $field = ?";
    $user = db_query_row($query, [$username]);

    if (!$user || $user['status'] !== 'active') return false;

    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

        db_update('users', [
            'login_attempts' => 0,
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = ?', [$user['id']]);

        return true;
    }

    return false;
}

/**
 * Logout user
 */
function logout() {
    if (is_logged_in()) {
        log_action('logout', "User {$_SESSION['username']} logged out");
    }

    if (isset($_COOKIE['remember_token'])) {
        db_update('users', ['remember_token' => null, 'token_expiry' => null], 'id = ?', [$_SESSION['user_id']]);
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    session_unset();
    session_destroy();
    header('Location: /login.php');
    exit;
}

/**
 * Redirect to dashboard
 */
function redirect_to_dashboard() {
    $role = $_SESSION['user_role'] ?? '';
    $dashboards = [
        'admin' => '/admin/dashboard.php',
        'client' => '/client/dashboard.php',
        'employee' => '/employee/dashboard.php'
    ];
    header('Location: ' . ($dashboards[$role] ?? '/login.php'));
    exit;
}

/**
 * CSRF Protection
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log actions
 */
function log_action($action_type, $details) {
    $data = [
        'action_type' => $action_type,
        'action_details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'admin_id' => $_SESSION['user_id'] ?? null
    ];
    db_insert('admin_activity_log', $data);
}

/**
 * Session Validity Check
 */
function check_session_validity() {
    $timeout = intval(get_setting('session_timeout', 30)) * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        logout();
        return false;
    }
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        logout();
        return false;
    }
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        logout();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

