<?php
/**
 * Azure Blob Storage Functions
 */

// Prevent direct access
if (!defined('BACKSURE_SYSTEM')) {
    die('Direct access not permitted');
}

/**
 * Generate SAS token for Azure Blob Storage
 * 
 * @param string $container_name Container name
 * @param int $expiry_hours Hours until expiry (default: 1)
 * @param array $permissions Permissions to grant
 * @return string|false SAS token or false on failure
 */
function generate_azure_sas_token($container_name, $expiry_hours = 1, $permissions = ['read']) {
    // Get Azure settings from database
    $azure_account = get_setting('azure_storage_account', '');
    $azure_key = get_setting('azure_storage_key', '');
    
    if (empty($azure_account) || empty($azure_key)) {
        error_log('Azure Blob Storage credentials not configured');
        return false;
    }
    
    // Load Azure SDK or use Azure REST API
    if (function_exists('MicrosoftAzure\Storage\Blob\BlobRestProxy::createBlobService')) {
        return generate_sas_token_sdk($azure_account, $azure_key, $container_name, $expiry_hours, $permissions);
    } else {
        return generate_sas_token_rest($azure_account, $azure_key, $container_name, $expiry_hours, $permissions);
    }
}

/**
 * Generate SAS token using Azure SDK
 * 
 * @param string $account_name Azure Storage account name
 * @param string $account_key Azure Storage account key
 * @param string $container_name Container name
 * @param int $expiry_hours Hours until expiry
 * @param array $permissions Permissions to grant
 * @return string SAS token
 */
function generate_sas_token_sdk($account_name, $account_key, $container_name, $expiry_hours, $permissions) {
    // This requires the Azure Storage PHP SDK
    // Include SDK autoloader here if not using Composer
    
    use MicrosoftAzure\Storage\Blob\BlobRestProxy;
    use MicrosoftAzure\Storage\Common\Internal\StorageServiceSettings;
    use MicrosoftAzure\Storage\Common\Internal\Authentication\SharedKeyAuthScheme;
    use MicrosoftAzure\Storage\Common\Internal\Resources;
    use MicrosoftAzure\Storage\Common\Models\ServiceProperties;
    use MicrosoftAzure\Storage\Common\Internal\Utilities;
    use MicrosoftAzure\Storage\Common\SharedAccessSignatureHelper;
    
    $connectionString = "DefaultEndpointsProtocol=https;AccountName={$account_name};AccountKey={$account_key}";
    $blobClient = BlobRestProxy::createBlobService($connectionString);
    
    // Create SAS token
    $settings = StorageServiceSettings::createFromConnectionString($connectionString);
    $helper = new SharedAccessSignatureHelper(
        $settings->getName(),
        $settings->getKey()
    );
    
    // Set start time to now and expiry time to X hours from now
    $start = new \DateTime('now');
    $end = new \DateTime();
    $end->add(new \DateInterval('PT' . $expiry_hours . 'H')); // Add hours
    
    // Convert permissions array to string
    $perms = '';
    if (in_array('read', $permissions)) $perms .= 'r';
    if (in_array('write', $permissions)) $perms .= 'w';
    if (in_array('delete', $permissions)) $perms .= 'd';
    if (in_array('list', $permissions)) $perms .= 'l';
    
    $sas = $helper->generateBlobServiceSharedAccessSignatureToken(
        Resources::RESOURCE_TYPE_CONTAINER,
        $container_name,
        $perms,
        $end,
        $start
    );
    
    return $sas;
}

/**
 * Generate SAS token using Azure REST API
 * 
 * @param string $account_name Azure Storage account name
 * @param string $account_key Azure Storage account key
 * @param string $container_name Container name
 * @param int $expiry_hours Hours until expiry
 * @param array $permissions Permissions to grant
 * @return string SAS token
 */
function generate_sas_token_rest($account_name, $account_key, $container_name, $expiry_hours, $permissions) {
    // Set start time to now and expiry time to X hours from now
    $start = gmdate('Y-m-d\TH:i:s\Z', time());
    $expiry = gmdate('Y-m-d\TH:i:s\Z', time() + $expiry_hours * 3600);
    
    // Convert permissions array to string
    $perms = '';
    if (in_array('read', $permissions)) $perms .= 'r';
    if (in_array('write', $permissions)) $perms .= 'w';
    if (in_array('delete', $permissions)) $perms .= 'd';
    if (in_array('list', $permissions)) $perms .= 'l';
    
    // Create string to sign
    $stringToSign = $perms . "\n" .    // permissions
                   $start . "\n" .     // start time
                   $expiry . "\n" .    // expiry time
                   "/$account_name/$container_name" . "\n" .  // canonicalized resource
                   "" . "\n" .         // identifier
                   "2020-12-06" . "\n" . // version
                   "";                 // additional parameters
    
    // Decode the base64 encoded key
    $decodedKey = base64_decode($account_key);
    
    // Create signature with HMAC-SHA256
    $signature = hash_hmac('sha256', $stringToSign, $decodedKey, true);
    $signature = base64_encode($signature);
    
    // Build the SAS token
    $token = "sv=2020-12-06" .
             "&sr=c" .
             "&st=" . urlencode($start) .
             "&se=" . urlencode($expiry) .
             "&sp=" . $perms .
             "&sig=" . urlencode($signature);
    
    return $token;
}

/**
 * Create Azure Blob Storage container
 * 
 * @param string $container_name Container name
 * @param int $client_id Client ID
 * @param string $description Optional description
 * @return bool Success status
 */
function create_azure_container($container_name, $client_id, $description = '') {
    global $pdo;
    
    // Check if container exists in our database
    $check_sql = "SELECT id FROM azure_blob_containers 
                  WHERE client_id = :client_id AND container_name = :container_name";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([
        'client_id' => $client_id,
        'container_name' => $container_name
    ]);
    
    if ($check_stmt->fetch()) {
        // Container already exists
        return true;
    }
    
    // Create container in Azure
    $azure_account = get_setting('azure_storage_account', '');
    $azure_key = get_setting('azure_storage_key', '');
    
    if (empty($azure_account) || empty($azure_key)) {
        error_log('Azure Blob Storage credentials not configured');
        return false;
    }
    
    // Try to create the container in Azure
    $result = create_azure_container_remote($azure_account, $azure_key, $container_name);
    
    if ($result) {
        // Container created in Azure, now register it in our database
        $sql = "INSERT INTO azure_blob_containers 
                (client_id, container_name, description) 
                VALUES (:client_id, :container_name, :description)";
        
        $stmt = $pdo->prepare($sql);
        $db_result = $stmt->execute([
            'client_id' => $client_id,
            'container_name' => $container_name,
            'description' => $description
        ]);
        
        if ($db_result) {
            // Log the action
            log_admin_action(
                'create_azure_container', 
                'client', 
                $client_id, 
                "Created Azure container '{$container_name}' for client #{$client_id}"
            );
            return true;
        }
    }
    
    return false;
}

/**
 * Create Azure Blob Storage container in Azure
 * 
 * @param string $account_name Azure Storage account name
 * @param string $account_key Azure Storage account key
 * @param string $container_name Container name
 * @return bool Success status
 */
function create_azure_container_remote($account_name, $account_key, $container_name) {
    // Using cURL to make a REST API call to Azure
    $date = gmdate('D, d M Y H:i:s \G\M\T');
    $url = "https://{$account_name}.blob.core.windows.net/{$container_name}?restype=container";
    
    // Create auth header
    $stringToSign = "PUT\n\n\n\n\n\n\n\n\n\n\n\nx-ms-date:$date\nx-ms-version:2020-12-06\n/$account_name/$container_name\nrestype:container";
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($account_key), true));
    $auth_header = "SharedKey $account_name:$signature";
    
    // Set up cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-ms-date: ' . $date,
        'x-ms-version: 2020-12-06',
        'Authorization: ' . $auth_header
    ]);
    
    // Execute request
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 201 Created or 409 Conflict (already exists)
    return ($status_code == 201 || $status_code == 409);
}

/**
 * Get client Azure containers
 * 
 * @param int $client_id Client ID
 * @return array Containers
 */
function get_client_azure_containers($client_id) {
    global $pdo;
    
    $sql = "SELECT * FROM azure_blob_containers 
            WHERE client_id = :client_id 
            ORDER BY container_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_id' => $client_id]);
    
    return $stmt->fetchAll();
}

/**
 * Get Azure container details
 * 
 * @param int $container_id Container ID
 * @return array|false Container details or false if not found
 */
function get_azure_container($container_id) {
    global $pdo;
    
    $sql = "SELECT * FROM azure_blob_containers WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $container_id]);
    
    return $stmt->fetch();
}

/**
 * Get Azure container URL with SAS token
 * 
 * @param int $container_id Container ID
 * @param int $expiry_hours Hours until expiry
 * @param array $permissions Permissions to grant
 * @return string|false Container URL with SAS token or false on failure
 */
function get_azure_container_url_with_sas($container_id, $expiry_hours = 1, $permissions = ['read']) {
    $container = get_azure_container($container_id);
    
    if (!$container) {
        return false;
    }
    
    // Check if user has access to this client
    if (!can_access_client($container['client_id'])) {
        return false;
    }
    
    // Log the access
    log_client_access(
        $container['client_id'],
        'access', 
        'azure_blob', 
        $container['container_name']
    );
    
    // Generate SAS token
    $sas_token = generate_azure_sas_token(
        $container['container_name'],
        $expiry_hours,
        $permissions
    );
    
    if (!$sas_token) {
        return false;
    }
    
    // Get account name from settings
    $azure_account = get_setting('azure_storage_account', '');
    
    // Return full URL with SAS token
    return "https://{$azure_account}.blob.core.windows.net/{$container['container_name']}?{$sas_token}";
}