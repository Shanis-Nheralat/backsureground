<?php
/**
 * CRM Proxy - Acts as a bridge between the frontend and CRM APIs
 */

// Define system constant
define('BACKSURE_SYSTEM', true);

// Include required files
require_once '../db.php';
require_once '../auth/admin-auth.php';

// Authentication check
require_admin_auth();

// Include CRM functions
require_once './crm-functions.php';
require_once '../employee/employee-functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check for client ID
if (!isset($_GET['client_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Client ID is required'
    ]);
    exit;
}

$client_id = intval($_GET['client_id']);

// Check if user has access to this client
if (!can_access_client($client_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]);
    exit;
}

// Get action from request
$action = $_GET['action'] ?? 'get_data';

switch ($action) {
    case 'iframe_url':
        // Get CRM iframe URL
        $url = get_crm_iframe_url($client_id);
        
        if ($url) {
            echo json_encode([
                'success' => true,
                'url' => $url
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'CRM URL not available for this client'
            ]);
        }
        break;
        
    case 'get_data':
        // Get data from CRM API
        $endpoint = $_GET['endpoint'] ?? '';
        
        if (empty($endpoint)) {
            echo json_encode([
                'success' => false,
                'message' => 'API endpoint is required'
            ]);
            exit;
        }
        
        $result = call_crm_api($client_id, $endpoint, 'GET');
        
        if ($result !== false) {
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to retrieve data from CRM'
            ]);
        }
        break;
        
    case 'post_data':
        // Post data to CRM API
        $endpoint = $_GET['endpoint'] ?? '';
        
        if (empty($endpoint)) {
            echo json_encode([
                'success' => false,
                'message' => 'API endpoint is required'
            ]);
            exit;
        }
        
        // Get data from POST body
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($data === null) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON data'
            ]);
            exit;
        }
        
        $result = call_crm_api($client_id, $endpoint, 'POST', $data);
        
        if ($result !== false) {
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to post data to CRM'
            ]);
        }
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
}