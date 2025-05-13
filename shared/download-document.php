<?php
/**
 * Secure Document Download Handler
 * 
 * Securely serves plan documents based on user permissions
 */

require_once 'db.php';
require_once 'auth/admin-auth.php';
require_once 'plans/plan-functions.php';

// Verify user is logged in
if (!is_admin_logged_in()) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

// Get document ID from request
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$document_id) {
    header('HTTP/1.0 404 Not Found');
    exit('Document not found');
}

// Get document information
try {
    $document = get_document($document_id);
    
    if (!$document) {
        header('HTTP/1.0 404 Not Found');
        exit('Document not found');
    }
    
    // Check permissions
    $current_user_id = $_SESSION['admin_user_id'];
    $current_user_role = $_SESSION['admin_role'];
    
    $has_access = false;
    
    // Admin has access to all documents
    if ($current_user_role === 'admin') {
        $has_access = true;
    } 
    // Client can only access their own documents
    else if ($current_user_role === 'client' && $document['client_id'] === $current_user_id) {
        $has_access = true;
    }
    
    if (!$has_access) {
        header('HTTP/1.0 403 Forbidden');
        exit('Access denied');
    }
    
    // File path and name
    $file_path = __DIR__ . '/../' . $document['file_path'];
    
    if (!file_exists($file_path)) {
        header('HTTP/1.0 404 Not Found');
        exit('File not found');
    }
    
    // Get file info
    $file_size = filesize($file_path);
    $file_name = $document['original_file_name'];
    
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
    error_log('Document Download Error: ' . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    exit('Server error');
}