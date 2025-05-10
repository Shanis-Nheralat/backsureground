<?php
/**
 * Media Delete Handler
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

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Check for CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]);
    exit;
}

// Check for media ID
if (!isset($_POST['media_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Media ID is required'
    ]);
    exit;
}

$media_id = intval($_POST['media_id']);

// Delete media
$result = delete_media($media_id);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'Media deleted successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete media or insufficient permissions'
    ]);
}