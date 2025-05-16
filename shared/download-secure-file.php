<?php
// /shared/download-secure-file.php
require_once 'db.php';
require_once 'auth/admin-auth.php';
require_once 'utils/security-functions.php';

// Require authentication
require_admin_auth();

// Validate request
if (!isset($_GET['file']) || !isset($_GET['type']) || !isset($_GET['id'])) {
    http_response_code(400);
    die('Invalid request');
}

// Get parameters
$file_id = intval($_GET['id']);
$file_type = sanitize_input($_GET['type']);
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Validate token
if (!validate_download_token($token, $file_id, $file_type)) {
    log_admin_action('access_denied', 'File download', [
        'file_id' => $file_id, 
        'file_type' => $file_type,
        'reason' => 'Invalid token'
    ]);
    http_response_code(403);
    die('Access denied');
}

// Fetch file information based on type
switch ($file_type) {
    case 'task':
        $table = 'task_uploads';
        $id_field = 'task_id';
        $path_field = 'file_path';
        $name_field = 'file_name';
        break;
    case 'support':
        $table = 'support_attachments';
        $id_field = 'reply_id'; // We'll need to join to get ticket info
        $path_field = 'file_path';
        $name_field = 'file_name';
        break;
    case 'plan':
        $table = 'client_plan_documents';
        $id_field = 'id';
        $path_field = 'file_path';
        $name_field = 'file_name';
        break;
    default:
        http_response_code(400);
        die('Invalid file type');
}

// Get file information
try {
    global $pdo;
    
    if ($file_type === 'support') {
        // For support attachments, we need to join tables to check permissions
        $stmt = $pdo->prepare("
            SELECT a.file_path, a.file_name, a.file_type, r.ticket_id, t.user_id, t.user_role
            FROM support_attachments a
            JOIN support_replies r ON a.reply_id = r.id
            JOIN support_tickets t ON r.ticket_id = t.id
            WHERE a.id = ?
        ");
        $stmt->execute([$file_id]);
    } else {
        $stmt = $pdo->prepare("SELECT file_path, file_name, file_type FROM {$table} WHERE id = ?");
        $stmt->execute([$file_id]);
    }
    
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        die('File not found');
    }
    
    // Additional access control check based on user role
    if (!can_access_file($file, $file_type)) {
        log_admin_action('access_denied', 'File download', [
            'file_id' => $file_id, 
            'file_type' => $file_type,
            'reason' => 'Unauthorized access'
        ]);
        http_response_code(403);
        die('Access denied');
    }
    
    // Get the file path
    $file_path = $file['file_path'];
    
    // Security check to prevent directory traversal
    if (strpos($file_path, '..') !== false) {
        log_admin_action('security_violation', 'File download', [
            'file_id' => $file_id,
            'reason' => 'Directory traversal attempt'
        ]);
        http_response_code(403);
        die('Invalid file path');
    }
    
    // Ensure the file exists
    $full_path = realpath(dirname(__DIR__) . '/' . $file_path);
    if (!$full_path || !file_exists($full_path) || !is_file($full_path)) {
        http_response_code(404);
        die('File not found on server');
    }
    
    // Log the download
    log_admin_action('download', 'File', [
        'file_id' => $file_id,
        'file_type' => $file_type,
        'file_name' => $file['file_name']
    ]);
    
    // Send the file to the browser
    header('Content-Description: File Transfer');
    header('Content-Type: ' . (isset($file['file_type']) ? $file['file_type'] : mime_content_type($full_path)));
    header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($full_path));
    readfile($full_path);
    exit;
    
} catch (Exception $e) {
    error_log('File download error: ' . $e->getMessage());
    http_response_code(500);
    die('Server error occurred');
}

/**
 * Check if current user can access the specified file
 */
function can_access_file($file, $file_type) {
    // Admin can access all files
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    global $pdo;
    
    switch ($file_type) {
        case 'task':
            // For tasks, clients can only access their own task files
            if ($_SESSION['role'] === 'client') {
                $stmt = $pdo->prepare("
                    SELECT t.client_id
                    FROM on_demand_tasks t
                    JOIN task_uploads u ON t.id = u.task_id
                    WHERE u.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);
                
                return $task && $task['client_id'] === $_SESSION['user_id'];
            }
            break;
            
        case 'support':
            // For support, check if this is the client's own ticket
            if ($_SESSION['role'] === 'client') {
                return $file['user_role'] === 'client' && $file['user_id'] === $_SESSION['user_id'];
            }
            // For employees, check if assigned to this ticket
            else if ($_SESSION['role'] === 'employee') {
                $stmt = $pdo->prepare("
                    SELECT assigned_to
                    FROM support_tickets
                    WHERE id = ?
                ");
                $stmt->execute([$file['ticket_id']]);
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                
                return $ticket && $ticket['assigned_to'] === $_SESSION['user_id'];
            }
            break;
            
        case 'plan':
            // For plan documents, clients can only access their own
            if ($_SESSION['role'] === 'client') {
                $stmt = $pdo->prepare("
                    SELECT client_id
                    FROM client_plan_documents
                    WHERE id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                
                return $doc && $doc['client_id'] === $_SESSION['user_id'];
            }
            break;
    }
    
    return false;
}

/**
 * Validate the download token
 */
function validate_download_token($token, $file_id, $file_type) {
    if (empty($token)) {
        return false;
    }
    
    // Decode the token (format: timestamp|hash)
    $parts = explode('|', $token);
    if (count($parts) !== 2) {
        return false;
    }
    
    list($timestamp, $hash) = $parts;
    
    // Check if token is expired (10 minutes)
    if (time() - intval($timestamp) > 600) {
        return false;
    }
    
    // Verify the hash
    $expected_hash = hash_hmac('sha256', $file_id . '|' . $file_type . '|' . $timestamp, get_download_secret_key());
    return hash_equals($expected_hash, $hash);
}

/**
 * Get the secret key for download tokens
 */
function get_download_secret_key() {
    $key = get_setting('download_token_key', '');
    if (empty($key)) {
        // Generate and save a new key if none exists
        $key = bin2hex(random_bytes(32));
        set_setting('download_token_key', $key);
    }
    return $key;
}
?>