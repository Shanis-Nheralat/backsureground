<?php
/**
 * Secure Media File Server
 * 
 * Serves media files with access control and token validation
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';

// Authentication check - will redirect to login if not authenticated
require_admin_auth();

// Include media functions
require_once '../../shared/media/media-functions.php';

// Check for required parameters
if (!isset($_GET['id'])) {
    die('Media ID is required');
}

$media_id = intval($_GET['id']);

// Validate access token if provided
if (isset($_GET['token'])) {
    $provided_token = $_GET['token'];
    $expected_token = generate_media_access_token($media_id);
    
    if ($provided_token !== $expected_token) {
        die('Invalid access token');
    }
} else {
    // If no token, validate through normal access control
    $media = get_media($media_id);
    
    if (!$media) {
        die('Media not found or insufficient permissions');
    }
}

// Get the media record
$sql = "SELECT * FROM media_library WHERE id = :id AND is_deleted = 0";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $media_id]);
$media = $stmt->fetch();

if (!$media) {
    die('Media not found');
}

// Get file path
$filepath = $media['filepath'];

if (!file_exists($filepath)) {
    die('File not found on disk');
}

// Determine mime type
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'csv' => 'text/csv',
    'txt' => 'text/plain',
    'zip' => 'application/zip'
];

$extension = strtolower(pathinfo($media['filename'], PATHINFO_EXTENSION));
$mime_type = $mime_types[$extension] ?? 'application/octet-stream';

// Set headers for download or display
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . $media['filesize']);

// For non-image files, set content disposition to attachment (download) with original filename
if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
    header('Content-Disposition: attachment; filename="' . $media['original_filename'] . '"');
}

// Log the access
log_admin_action('media_access', 'media', $media_id, "Accessed file: {$media['original_filename']}");

// Output file and exit
readfile($filepath);
exit;