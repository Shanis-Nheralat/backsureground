<?php
/**
 * API Integration Functions
 * 
 * Handles external API integrations for Business Care Plans
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth/admin-auth.php';

/**
 * Get all API integrations
 */
function get_api_integrations($active_only = true) {
    global $pdo;
    
    try {
        $sql = "SELECT ais.*, s.name as service_name 
                FROM api_integration_settings ais
                JOIN services s ON ais.service_id = s.id";
        
        if ($active_only) {
            $sql .= " WHERE ais.is_active = 1";
        }
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get API Integrations Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get API integration by ID
 */
function get_api_integration($integration_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT ais.*, s.name as service_name 
            FROM api_integration_settings ais
            JOIN services s ON ais.service_id = s.id
            WHERE ais.id = ?
        ");
        
        $stmt->execute([$integration_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get API Integration Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get API integrations by service
 */
function get_api_integrations_by_service($service_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM api_integration_settings
            WHERE service_id = ? AND is_active = 1
        ");
        
        $stmt->execute([$service_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get API Integrations By Service Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Create API integration
 */
function create_api_integration($service_id, $integration_type, $configuration, $created_by) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO api_integration_settings 
            (service_id, integration_type, configuration, created_by)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $service_id,
            $integration_type,
            json_encode($configuration),
            $created_by
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Create API Integration Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update API integration
 */
function update_api_integration($integration_id, $configuration, $is_active) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE api_integration_settings 
            SET configuration = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            json_encode($configuration),
            $is_active ? 1 : 0,
            $integration_id
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log('Update API Integration Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get client API integrations
 */
function get_client_api_integrations($client_id) {
    global $pdo;
    
    try {
        $sql = "SELECT cai.*, ais.integration_type, ais.service_id, s.name as service_name
                FROM client_api_integrations cai
                JOIN api_integration_settings ais ON cai.integration_id = ais.id
                JOIN services s ON ais.service_id = s.id
                WHERE cai.client_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$client_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Client API Integrations Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Setup client API integration
 */
function setup_client_api_integration($client_id, $integration_id, $api_key = null, $access_token = null, $refresh_token = null, $token_expires_at = null) {
    global $pdo;
    
    try {
        // Check if integration already exists
        $stmt = $pdo->prepare("
            SELECT id FROM client_api_integrations
            WHERE client_id = ? AND integration_id = ?
        ");
        
        $stmt->execute([$client_id, $integration_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing integration
            $stmt = $pdo->prepare("
                UPDATE client_api_integrations 
                SET api_key = ?, access_token = ?, refresh_token = ?, token_expires_at = ?, 
                    connection_status = 'connected', updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $api_key,
                $access_token,
                $refresh_token,
                $token_expires_at,
                $existing['id']
            ]);
            
            return $existing['id'];
        } else {
            // Create new integration
            $stmt = $pdo->prepare("
                INSERT INTO client_api_integrations 
                (client_id, integration_id, api_key, access_token, refresh_token, token_expires_at, connection_status)
                VALUES (?, ?, ?, ?, ?, ?, 'connected')
            ");
            
            $stmt->execute([
                $client_id,
                $integration_id,
                $api_key,
                $access_token,
                $refresh_token,
                $token_expires_at
            ]);
            
            return $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        error_log('Setup Client API Integration Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update client API integration status
 */
function update_client_api_status($client_integration_id, $status, $sync_status = null) {
    global $pdo;
    
    try {
        $sql = "UPDATE client_api_integrations 
                SET connection_status = ?, updated_at = NOW()";
        
        $params = [$status];
        
        if ($sync_status !== null) {
            $sql .= ", sync_status = ?, last_sync_at = NOW()";
            $params[] = $sync_status;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $client_integration_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return true;
    } catch (PDOException $e) {
        error_log('Update Client API Status Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get Zoho API integration by client
 */
function get_zoho_integration($client_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT cai.*, ais.configuration
            FROM client_api_integrations cai
            JOIN api_integration_settings ais ON cai.integration_id = ais.id
            WHERE cai.client_id = ? 
            AND ais.integration_type = 'zoho'
            AND cai.connection_status = 'connected'
            LIMIT 1
        ");
        
        $stmt->execute([$client_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Zoho Integration Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get Tally integration by client
 */
function get_tally_integration($client_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT cai.*, ais.configuration
            FROM client_api_integrations cai
            JOIN api_integration_settings ais ON cai.integration_id = ais.id
            WHERE cai.client_id = ? 
            AND ais.integration_type = 'tally'
            AND cai.connection_status = 'connected'
            LIMIT 1
        ");
        
        $stmt->execute([$client_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Tally Integration Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Refresh OAuth token
 */
function refresh_oauth_token($client_integration_id) {
    global $pdo;
    
    try {
        // Get integration details
        $stmt = $pdo->prepare("
            SELECT cai.*, ais.integration_type, ais.configuration
            FROM client_api_integrations cai
            JOIN api_integration_settings ais ON cai.integration_id = ais.id
            WHERE cai.id = ?
        ");
        
        $stmt->execute([$client_integration_id]);
        $integration = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$integration || empty($integration['refresh_token'])) {
            return false;
        }
        
        $config = json_decode($integration['configuration'], true);
        
        // Different refresh logic based on integration type
        switch ($integration['integration_type']) {
            case 'zoho':
                $result = refresh_zoho_token($integration['refresh_token'], $config);
                break;
                
            case 'quickbooks':
                $result = refresh_quickbooks_token($integration['refresh_token'], $config);
                break;
                
            case 'xero':
                $result = refresh_xero_token($integration['refresh_token'], $config);
                break;
                
            default:
                return false;
        }
        
        if ($result && isset($result['access_token'])) {
            // Update token in database
            $expires_at = date('Y-m-d H:i:s', time() + ($result['expires_in'] ?? 3600));
            
            $stmt = $pdo->prepare("
                UPDATE client_api_integrations 
                SET access_token = ?, 
                    refresh_token = ?, 
                    token_expires_at = ?,
                    connection_status = 'connected',
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $result['access_token'],
                $result['refresh_token'] ?? $integration['refresh_token'],
                $expires_at,
                $client_integration_id
            ]);
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Refresh OAuth Token Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Refresh Zoho token
 */
function refresh_zoho_token($refresh_token, $config) {
    // This would be implemented with actual API calls to Zoho
    // For demonstration, we'll simulate a successful response
    
    return [
        'access_token' => 'new_simulated_access_token_' . time(),
        'refresh_token' => $refresh_token, // Zoho keeps the same refresh token
        'expires_in' => 3600
    ];
}

/**
 * Refresh QuickBooks token
 */
function refresh_quickbooks_token($refresh_token, $config) {
    // This would be implemented with actual API calls to QuickBooks
    // For demonstration, we'll simulate a successful response
    
    return [
        'access_token' => 'new_simulated_qb_token_' . time(),
        'refresh_token' => 'new_refresh_token_' . time(), // QuickBooks issues new refresh tokens
        'expires_in' => 3600
    ];
}

/**
 * Refresh Xero token
 */
function refresh_xero_token($refresh_token, $config) {
    // This would be implemented with actual API calls to Xero
    // For demonstration, we'll simulate a successful response
    
    return [
        'access_token' => 'new_simulated_xero_token_' . time(),
        'refresh_token' => 'new_refresh_token_' . time(), // Xero issues new refresh tokens
        'expires_in' => 1800
    ];
}