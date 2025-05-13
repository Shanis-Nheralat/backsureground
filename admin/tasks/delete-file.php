<?php
/**
 * Delete File Handler
 * 
 * Handles file deletion requests
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';

// Set content type to JSON
header('Content-Type: application/json');

// Ensure admin is logged in
if (!is_admin_logged_in() || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]);
    exit;
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]);
    exit;
}

// Get file ID
$file_id = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;

if (!$file_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file ID'
    ]);
    exit;
}

// Get file info
try {
    $stmt = $pdo->prepare("
        SELECT * FROM task_uploads 
        WHERE id = ? AND uploaded_by = 'admin'
    ");
    
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        echo json_encode([
            'success' => false,
            'message' => 'File not found or not owned by admin'
        ]);
        exit;
    }
    
    // Delete physical file
    $file_path = __DIR__ . '/../../' . $file['file_path'];
    
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM task_uploads WHERE id = ?");
    $stmt->execute([$file_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'File deleted successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Delete File Error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
    exit;
}