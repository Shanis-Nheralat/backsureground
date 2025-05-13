<?php
/**
 * Secure File Download Handler
 * 
 * Securely serves files based on user permissions
 */

require_once 'db.php';
require_once 'auth/admin-auth.php';
require_once 'tasks/task-functions.php';

// Verify user is logged in
if (!is_admin_logged_in()) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

// Get file ID from request
$file_id = isset($_GET['file_id']) ? (int)$_GET['file_id'] : 0;

if (!$file_id) {
    header('HTTP/1.0 404 Not Found');
    exit('File not found');
}

// Get file information
try {
    $stmt = $pdo->prepare("
        SELECT f.*, t.client_id 
        FROM task_uploads f
        JOIN on_demand_tasks t ON f.task_id = t.id
        WHERE f.id = ?
    ");
    
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        header('HTTP/1.0 404 Not Found');
        exit('File not found');
    }
    
    // Check permissions
    $current_user_id = $_SESSION['admin_user_id'];
    $current_user_role = $_SESSION['admin_role'];
    
    $has_access = false;
    
    // Admin has access to all files
    if ($current_user_role === 'admin') {
        $has_access = true;
    } 
    // Client can only access their own task files
    else if ($current_user_role === 'client' && $file['client_id'] === $current_user_id) {
        $has_access = true;
    }
    
    if (!$has_access) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access denied');
    }
    
    // File path and name
    $file_path = __DIR__ . '/../' . $file['file_path'];
    
    if (!file_exists($file_path)) {
        header('HTTP/1.0 404 Not Found');
        exit('File not found');
    }
    
    // Get file info
    $file_size = filesize($file_path);
    $file_name = $file['file_name'];
    
    // Determine MIME type
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $mime_types = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'zip' => 'application/zip'
    ];
    
    $mime_type = isset($mime_types[$file_ext]) ? $mime_types[$file_ext] : 'application/octet-stream';
    
    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . $file_size);
    
    // Clean output buffer
    ob_clean();
    flush();
    
    // Read file and output
    readfile($file_path);
    exit;
    
} catch (PDOException $e) {
    error_log('File Download Error: ' . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    exit('Server error');
}