<?php
/**
 * Secure Support File Download Handler
 */

// Include required files
require_once 'db.php';
require_once 'auth/admin-auth.php';

// Authentication check - will redirect to login if not authenticated
require_admin_auth();

// Check for attachment ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('Invalid attachment ID');
}

$attachment_id = intval($_GET['id']);

// Get the user's role and ID
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get attachment details
try {
    $stmt = $pdo->prepare("
        SELECT a.*, r.ticket_id, t.user_id as ticket_user_id, t.user_role as ticket_user_role, t.assigned_to
        FROM support_attachments a
        JOIN support_replies r ON a.reply_id = r.id
        JOIN support_tickets t ON r.ticket_id = t.id
        WHERE a.id = ?
    ");
    $stmt->execute([$attachment_id]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attachment) {
        http_response_code(404);
        exit('File not found');
    }
    
    // Check if the user has permission to download this file
    $has_permission = false;
    
    if ($user_role === 'admin') {
        // Admins can download all files
        $has_permission = true;
    } elseif ($user_role === 'client' && $attachment['ticket_user_id'] == $user_id && $attachment['ticket_user_role'] === 'client') {
        // Clients can download files from their own tickets
        $has_permission = true;
    } elseif ($user_role === 'employee' && 
             ($attachment['assigned_to'] == $user_id || 
              ($attachment['ticket_user_id'] == $user_id && $attachment['ticket_user_role'] === 'employee'))) {
        // Employees can download files from tickets they're assigned to or created
        $has_permission = true;
    }
    
    if (!$has_permission) {
        http_response_code(403);
        exit('You do not have permission to download this file');
    }
    
    // File path (ensure it's within the uploads directory)
    $file_path = __DIR__ . '/..' . $attachment['file_path'];
    
    // Security check - ensure the file is within the uploads directory
    $uploads_dir = realpath(__DIR__ . '/../uploads');
    $requested_file = realpath($file_path);
    
    if ($requested_file === false || strpos($requested_file, $uploads_dir) !== 0) {
        http_response_code(403);
        exit('Invalid file path');
    }
    
    // Check if the file exists
    if (!file_exists($file_path)) {
        http_response_code(404);
        exit('File not found');
    }
    
    // Log the download
    $stmt = $pdo->prepare("
        INSERT INTO admin_activity_log 
            (user_id, username, action_type, resource, resource_id, details, ip_address) 
        VALUES 
            (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $_SESSION['username'],
        'download',
        'support_attachments',
        $attachment_id,
        "Downloaded support attachment: {$attachment['file_name']}",
        $_SERVER['REMOTE_ADDR']
    ]);
    
    // Set appropriate headers
    header('Content-Type: ' . $attachment['file_type']);
    header('Content-Disposition: attachment; filename="' . $attachment['file_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output the file
    readfile($file_path);
    exit;
    
} catch (PDOException $e) {
    error_log('Error downloading support attachment: ' . $e->getMessage());
    http_response_code(500);
    exit('Error processing your request');
}