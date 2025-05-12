<?php
/**
 * CRM Integration Functions
 */

// Prevent direct access
if (!defined('BACKSURE_SYSTEM')) {
    die('Direct access not permitted');
}

/**
 * Set up CRM integration for a client
 * 
 * @param int $client_id Client ID
 * @param string $crm_type CRM type (zoho, dynamics, salesforce, custom)
 * @param string $crm_identifier Client identifier in CRM
 * @param string $crm_url CRM URL
 * @param string $api_key Optional API key
 * @return bool Success status
 */
function setup_client_crm($client_id, $crm_type, $crm_identifier, $crm_url, $api_key = null) {
    global $pdo;
    
    // Check if client has CRM integration
    $check_sql = "SELECT id FROM client_crm_access WHERE client_id = :client_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute(['client_id' => $client_id]);
    
    $existing = $check_stmt->fetch();
    
    if ($existing) {
        // Update existing CRM integration
        $sql = "UPDATE client_crm_access 
                SET crm_type = :crm_type, 
                    crm_identifier = :crm_identifier, 
                    crm_url = :crm_url, 
                    api_key = :api_key,
                    updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'crm_type' => $crm_type,
            'crm_identifier' => $crm_identifier,
            'crm_url' => $crm_url,
            'api_key' => $api_key,
            'id' => $existing['id']
        ]);
    } else {
        // Create new CRM integration
        $sql = "INSERT INTO client_crm_access 
                (client_id, crm_type, crm_identifier, crm_url, api_key) 
                VALUES 
                (:client_id, :crm_type, :crm_identifier, :crm_url, :api_key)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'client_id' => $client_id,
            'crm_type' => $crm_type,
            'crm_identifier' => $crm_identifier,
            'crm_url' => $crm_url,
            'api_key' => $api_key
        ]);
    }
    
    if ($result) {
        // Log the action
        log_admin_action(
            'setup_crm', 
            'client', 
            $client_id, 
            "Set up {$crm_type} CRM integration for client #{$client_id}"
        );
        return true;
    }
    
    return false;
}

/**
 * Get client CRM details
 * 
 * @param int $client_id Client ID
 * @return array|false CRM details or false if not found
 */
function get_client_crm($client_id) {
    global $pdo;
    
    $sql = "SELECT * FROM client_crm_access WHERE client_id = :client_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_id' => $client_id]);
    
    return $stmt->fetch();
}

/**
 * Get CRM iframe URL for client
 * 
 * @param int $client_id Client ID
 * @return string|false CRM iframe URL or false if not available
 */
function get_crm_iframe_url($client_id) {
    $crm = get_client_crm($client_id);
    
    if (!$crm) {
        return false;
    }
    
    // Check if user has access to this client
    if (!can_access_client($client_id)) {
        return false;
    }
    
    // Log the access
    log_client_access(
        $client_id,
        'view', 
        'crm', 
        $crm['crm_identifier']
    );
    
    // If URL needs additional parameters, add them here
    $url = $crm['crm_url'];
    
    // For security, we might want to add a token to the URL
    // This depends on the CRM system's capabilities
    switch ($crm['crm_type']) {
        case 'zoho':
            // If Zoho supports embedding via token
            if (!empty($crm['api_key'])) {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . 'authtoken=' . urlencode($crm['api_key']);
            }
            break;
            
        case 'dynamics':
            // If Dynamics supports embedding via token
            if (!empty($crm['api_key'])) {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . 'token=' . urlencode($crm['api_key']);
            }
            break;
            
        case 'salesforce':
            // If Salesforce supports embedding via token
            if (!empty($crm['api_key'])) {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . 'session_id=' . urlencode($crm['api_key']);
            }
            break;
            
        case 'custom':
            // Handle custom CRM embedding
            break;
    }
    
    return $url;
}

/**
 * Call CRM API for client
 * 
 * @param int $client_id Client ID
 * @param string $endpoint API endpoint
 * @param string $method HTTP method (GET, POST, etc.)
 * @param array $data Data to send
 * @return array|false API response or false on failure
 */
function call_crm_api($client_id, $endpoint, $method = 'GET', $data = []) {
    $crm = get_client_crm($client_id);
    
    if (!$crm) {
        return false;
    }
    
    // Check if user has access to this client
    if (!can_access_client($client_id)) {
        return false;
    }
    
    // Log the access
    log_client_access(
        $client_id,
        'api', 
        'crm', 
        $endpoint
    );
    
    // Base URL for API calls
    $api_base_url = '';
    $headers = [];
    
    // Set up API call based on CRM type
    switch ($crm['crm_type']) {
        case 'zoho':
            $api_base_url = 'https://www.zohoapis.com/crm/v2/';
            $headers[] = 'Authorization: Bearer ' . $crm['api_key'];
            break;
            
        case 'dynamics':
            $api_base_url = 'https://api.dynamics.com/data/v9.0/';
            $headers[] = 'Authorization: Bearer ' . $crm['api_key'];
            break;
            
        case 'salesforce':
            $api_base_url = 'https://api.salesforce.com/services/data/v52.0/';
            $headers[] = 'Authorization: Bearer ' . $crm['api_key'];
            break;
            
        case 'custom':
            $api_base_url = $crm['crm_url'];
            if (!empty($crm['api_key'])) {
                $headers[] = 'Authorization: Bearer ' . $crm['api_key'];
            }
            break;
            
        default:
            return false;
    }
    
    // Execute API call using cURL
    $url = rtrim($api_base_url, '/') . '/' . ltrim($endpoint, '/');
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method != 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
    }
    
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status_code >= 200 && $status_code < 300) {
        return json_decode($response, true);
    }
    
    error_log("CRM API call failed with status code {$status_code}: {$response}");
    return false;
}