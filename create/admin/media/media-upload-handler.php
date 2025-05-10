<?php
/**
 * Media Upload Handler
 * 
 * AJAX endpoint for file uploads
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';

// Authentication check
require_admin_auth();

// Include media functions
require_once '../../shared/media/media-functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check for CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]);
    exit;
}

// Check for file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded or upload error'
    ]);
    exit;
}

// Get options from POST
$options = [
    'folder' => $_POST['folder'] ?? '/',
    'client_id' => isset($_POST['client_id']) && !empty($_POST['client_id']) ? intval($_POST['client_id']) : null,
    'tags' => $_POST['tags'] ?? ''
];

// Upload file
$result = upload_media_file($_FILES['file'], $options);

if ($result) {
    echo json_encode([
        'success' => true,
        'media' => $result
    ]);
    
    // Log action
    log_admin_action('media_upload', 'media', $result['id'], "Uploaded file: {$result['original_filename']}");
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upload file'
    ]);
}