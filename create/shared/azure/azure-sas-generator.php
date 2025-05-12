<?php
/**
 * Azure SAS Token Generator
 */

// Define system constant
define('BACKSURE_SYSTEM', true);

// Include required files
require_once '../db.php';
require_once '../auth/admin-auth.php';

// Authentication check
require_admin_auth();

// Include Azure functions
require_once './azure-access.php';
require_once '../employee/employee-functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check for container ID
if (!isset($_GET['container_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Container ID is required'
    ]);
    exit;
}

$container_id = intval($_GET['container_id']);
$container = get_azure_container($container_id);

if (!$container) {
    echo json_encode([
        'success' => false,
        'message' => 'Container not found'
    ]);
    exit;
}

// Check if user has access to this client
if (!can_access_client($container['client_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]);
    exit;
}

// Get permissions from request or use defaults
$permissions = [];
if (isset($_GET['read']) && $_GET['read'] === '1') $permissions[] = 'read';
if (isset($_GET['write']) && $_GET['write'] === '1') $permissions[] = 'write';
if (isset($_GET['delete']) && $_GET['delete'] === '1') $permissions[] = 'delete';
if (isset($_GET['list']) && $_GET['list'] === '1') $permissions[] = 'list';

// If no permissions specified, default to read access
if (empty($permissions)) {
    $permissions = ['read'];
}

// Get expiry hours from request or use default
$expiry_hours = isset($_GET['expiry']) ? intval($_GET['expiry']) : 1;

// Limit expiry hours to reasonable range (1-24 hours)
if ($expiry_hours < 1) $expiry_hours = 1;
if ($expiry_hours > 24) $expiry_hours = 24;

// Get container URL with SAS token
$url = get_azure_container_url_with_sas(
    $container_id,
    $expiry_hours,
    $permissions
);

if ($url) {
    echo json_encode([
        'success' => true,
        'url' => $url,
        'expiry_hours' => $expiry_hours,
        'permissions' => $permissions
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate SAS token'
    ]);
}