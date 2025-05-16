<?php
/**
 * Authentication System
 * 
 * Manages user authentication, session security, and permissions.
 * Core of the role-based authentication system.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('db_query')) {
    require_once __DIR__ . '/../../db.php';
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_auth() {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit;
    }
    log_action('page_access', $_SERVER['REQUEST_URI']);
}

function has_role($roles) {
    if (!is_logged_in()) return false;
    if (!is_array($roles)) $roles = [$roles];
    return in_array($_SESSION['user_role'], $roles);
}

function require_role($roles) {
    require_auth();
    if (!has_role($roles)) {
        log_action('unauthorized_access', 'Role required: ' . (is_array($roles) ? implode(', ', $roles) : $roles));
        redirect_to_dashboard();
        exit;
    }
}

function has_permission($permission) {
    if (!is_logged_in()) return false;
    if ($_SESSION['user_role'] === 'admin') return true;

    $query = "SELECT COUNT(*) FROM role_permissions rp
              JOIN roles r ON rp.role_id = r.id
              JOIN permissions p ON rp.permission_id = p.id
              WHERE r.name = ? AND p.name = ?";

    return db_query_value($query, [$_SESSION['user_role'], $permission]) > 0;
}

function require_permission($permission) {
    require_auth();
    if (!has_permission($permission)) {
        log_action('permission_denied', 'Permission required: ' . $permission);
        redirect_to_dashboard();
        exit;
    }
}

function login($username, $password) {
    $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    $query = "SELECT id, username, password, email, name, role, status, login_attempts, last_attempt_time FROM users WHERE $field = ?";
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

function redirect_to_dashboard() {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }

    $dashboards = [
        'admin' => '/admin/dashboard.php',
        'client' => '/client/dashboard.php',
        'employee' => '/employee/dashboard.php'
    ];

    $role = $_SESSION['user_role'];
    header('Location: ' . ($dashboards[$role] ?? '/login.php'));
    exit;
}

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

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Placeholder for future phase implementations
// function check_session_timeout() {}
// function validate_ip() {}
// function check_remember_me() {}
// function set_remember_me($user_id) {}
// function check_brute_force($username) {}
