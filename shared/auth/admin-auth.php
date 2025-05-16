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

function is_admin_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function require_admin_auth() {
    if (!is_admin_logged_in()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit;
    }

    if (!check_session_validity()) {
        header('Location: /login.php');
        exit;
    }
}

function require_admin_role($allowed_roles = ['admin']) {
    if (!is_admin_logged_in()) {
        header('Location: /login.php');
        exit;
    }

    if (!in_array($_SESSION['role'], $allowed_roles)) {
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

function check_session_validity() {
    $timeout = intval(get_setting('session_timeout', 30)) * 60;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        admin_logout('Session expired due to inactivity');
        return false;
    }

    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        admin_logout('Session invalidated due to IP address change');
        return false;
    }

    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        admin_logout('Session invalidated due to browser change');
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

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

function admin_login($username, $password, $remember = false) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT login_attempts, last_attempt_time, status FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $lockout_minutes = intval(get_setting('login_lockout_duration', 15));
        $max_attempts = intval(get_setting('login_attempts', 5));

        if ($user['login_attempts'] >= $max_attempts) {
            $last_attempt = new DateTime($user['last_attempt_time']);
            $now = new DateTime();
            $diff = $now->diff($last_attempt);
            $minutes_passed = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

            if ($minutes_passed < $lockout_minutes) {
                $time_remaining = $lockout_minutes - $minutes_passed;
                log_admin_action('login_failed', 'Account locked', ['username' => $username, 'reason' => 'Too many attempts']);
                return ['success' => false, 'message' => "Account temporarily locked. Try again in {$time_remaining} minutes."];
            } else {
                $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0 WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
            }

        }

        if ($user['status'] === 'inactive') {
            log_admin_action('login_failed', 'Account inactive', ['username' => $username]);
            return ['success' => false, 'message' => 'Account is inactive. Please contact administrator.'];
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?)");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, last_attempt_time = NOW(), last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

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

        log_admin_action('login', 'Authentication', ['username' => $user['username'], 'role' => $user['role']]);
        return ['success' => true, 'user' => $user];
    } else {
        if ($user) {
            $stmt = $pdo->prepare("UPDATE users SET login_attempts = login_attempts + 1, last_attempt_time = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
        }
        log_admin_action('login_failed', 'Authentication', ['username' => $username, 'reason' => 'Invalid credentials']);
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
}

function admin_logout($reason = 'User logout') {
    if (isset($_SESSION['user_id'])) {
        log_admin_action('logout', 'Authentication', ['username' => $_SESSION['username'] ?? 'unknown', 'reason' => $reason]);
    }

    if (isset($_COOKIE['remember_token'])) {
        if (isset($_SESSION['user_id'])) {
            global $pdo;
            $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, token_expiry = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    session_unset();
    session_destroy();
    session_start();
}

function has_admin_permission($permission_key) {
    global $pdo;

    if ($_SESSION['role'] === 'admin') {
        return true;
    }

    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as has_permission FROM role_permissions rp JOIN roles r ON rp.role_id = r.id JOIN permissions p ON rp.permission_id = p.id WHERE r.name = ? AND p.name = ?");
    $stmt->execute([$role, $permission_key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['has_permission'] > 0;
}

function log_admin_action($action_type, $module, $details = []) {
    global $pdo;

    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $details_json = json_encode($details);

    try {
        $stmt = $pdo->prepare("INSERT INTO admin_activity_log (user_id, action_type, resource, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action_type, $module, $details_json, $ip_address]);

        if (function_exists('log_admin_extended_action')) {
            log_admin_extended_action($user_id, $action_type, $module, null, null, null, null, $details_json);
        }
        return true;
    } catch (Exception $e) {
        error_log('Error logging admin action: ' . $e->getMessage());
        return false;
    }
}
