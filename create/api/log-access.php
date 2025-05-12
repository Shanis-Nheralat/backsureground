<?php
/**
 * API: Log Client Access
 * 
 * Endpoint for logging client access events
 */

// Define system constant
define('BACKSURE_SYSTEM', true);

// Include required files
require_once '../shared/db.php';
require_once '../shared/auth/admin-auth.php';

// Authentication check
require_admin_auth();

// Include employee functions
require_once '../shared/employee/employee-functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get JSON data from request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data'
    ]);
    exit;
}

// Extract required parameters
$client_id = isset($input['client_id']) ? intval($input['client_id']) : 0;
$access_type = isset($input['access_type']) ? $input['access_type'] : '';
$resource_type = isset($input['resource_type']) ? $input['resource_type'] : '';
$resource_id = isset($input['resource_id']) ? $input['resource_id'] : null;

// Validate parameters
if ($client_id <= 0 || empty($access_type) || empty($resource_type)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

// Check if user has access to this client
if (!can_access_client($client_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]);
    exit;
}

// Log the access
$result = log_client_access(
    $client_id,
    $access_type,
    $resource_type,
    $resource_id
);

// Return response
if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'Access logged successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to log access'
    ]);
}