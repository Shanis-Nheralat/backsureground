<?php
// /admin/system/download-backup.php
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/settings-functions.php';

// Require admin authentication
require_admin_auth();
require_admin_role(['admin']);

// Validate request
if (!isset($_GET['file']) || !isset($_GET['token'])) {
    http_response_code(400);
    die('Invalid request');
}

$filename = $_GET['file'];
$token = $_GET['token'];

// Security check - validate the filename format
if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.zip$/', $filename)) {
    http_response_code(400);
    die('Invalid filename');
}

// Validate the download token
if (!validate_download_token($token, $filename)) {
    log_admin_action('access_denied', 'Backup download', [
        'file' => $filename,
        'reason' => 'Invalid token'
    ]);
    http_response_code(403);
    die('Access denied');
}

// Path to the backup file
$backup_dir = dirname(dirname(__DIR__)) . '/backups';
$file_path = $backup_dir . '/' . $filename;

// Ensure the file exists
if (!file_exists($file_path) || !is_file($file_path)) {
    http_response_code(404);
    die('File not found');
}

// Log the download
log_admin_action('download', 'Backup', [
    'file' => $filename
]);

// Send the file to the browser
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');
readfile($file_path);
exit;

/**
 * Validate the download token
 */
function validate_download_token($token, $filename) {
    // Parse token (format: timestamp|hash)
    $parts = explode('|', $token);
    if (count($parts) !== 2) {
        return false;
    }
    
    list($timestamp, $hash) = $parts;
    
    // Check if token is expired (10 minutes)
    if (time() - intval($timestamp) > 600) {
        return false;
    }
    
    // Get the secret key
    $secret = get_setting('download_token_key', '');
    if (empty($secret)) {
        return false;
    }
    
    // Verify the hash
    $expected_hash = hash_hmac('sha256', $filename . '|' . $timestamp, $secret);
    return hash_equals($expected_hash, $hash);
}