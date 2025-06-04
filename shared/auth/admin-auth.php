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
          header('Location: /login.php');         // absolute path
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
         header('Location: /login.php');         // absolute path
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

// ================= NEW UPDATE BELOW - Admin Security Section =================

/**
 * Check for "remember me" token and auto-login
 */
function check_remember_me() {
    global $pdo;

    if (isset($_COOKIE['remember_token']) && !is_admin_logged_in()) {
        $token = $_COOKIE['remember_token'];

        $stmt = $pdo->prepare("SELECT id, username, role, name, email FROM users WHERE remember_token = ? AND token_expiry > NOW() AND status = 'active'");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

            $new_token = bin2hex(random_bytes(32));
            $expires = new DateTime('+30 days');

            $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
            $stmt->execute([$new_token, $expires->format('Y-m-d H:i:s'), $user['id']]);

            setcookie('remember_token', $new_token, [
                'expires' => $expires->getTimestamp(),
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            log_admin_action('login', 'Authentication', ['username' => $user['username'], 'method' => 'remember_me']);
            return true;
        }
    }
    return false;
}
