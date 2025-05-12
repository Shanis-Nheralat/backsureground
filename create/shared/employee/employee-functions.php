<?php
/**
 * Employee-Client Assignment Core Functions
 */

// Prevent direct access
if (!defined('BACKSURE_SYSTEM')) {
    die('Direct access not permitted');
}

/**
 * Assign an employee to a client
 * 
 * @param int $employee_id Employee ID
 * @param int $client_id Client ID
 * @param string $notes Optional notes about the assignment
 * @return bool Success status
 */
function assign_employee_to_client($employee_id, $client_id, $notes = '') {
    global $pdo;
    
    // Check if the employee and client exist
    $employee = get_user_by_id($employee_id);
    $client = get_client_by_id($client_id);
    
    if (!$employee || $employee['role'] != 'employee' || !$client) {
        return false;
    }
    
    // Check if the assignment already exists
    $check_sql = "SELECT id FROM employee_client_assignments 
                  WHERE employee_id = :employee_id AND client_id = :client_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([
        'employee_id' => $employee_id,
        'client_id' => $client_id
    ]);
    
    $existing = $check_stmt->fetch();
    
    if ($existing) {
        // Update existing assignment
        $sql = "UPDATE employee_client_assignments 
                SET notes = :notes, is_active = 1, assigned_by = :assigned_by, assigned_at = NOW() 
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'notes' => $notes,
            'assigned_by' => $_SESSION['user_id'],
            'id' => $existing['id']
        ]);
    } else {
        // Create new assignment
        $sql = "INSERT INTO employee_client_assignments 
                (employee_id, client_id, assigned_by, notes) 
                VALUES (:employee_id, :client_id, :assigned_by, :notes)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            'employee_id' => $employee_id,
            'client_id' => $client_id,
            'assigned_by' => $_SESSION['user_id'],
            'notes' => $notes
        ]);
    }
    
    if ($result) {
        // Log the action
        log_admin_action(
            'assign_employee', 
            'client', 
            $client_id, 
            "Assigned employee #{$employee_id} to client #{$client_id}"
        );
        return true;
    }
    
    return false;
}

/**
 * Unassign an employee from a client
 * 
 * @param int $employee_id Employee ID
 * @param int $client_id Client ID
 * @return bool Success status
 */
function unassign_employee_from_client($employee_id, $client_id) {
    global $pdo;
    
    $sql = "UPDATE employee_client_assignments 
            SET is_active = 0 
            WHERE employee_id = :employee_id AND client_id = :client_id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'employee_id' => $employee_id,
        'client_id' => $client_id
    ]);
    
    if ($result) {
        // Log the action
        log_admin_action(
            'unassign_employee', 
            'client', 
            $client_id, 
            "Unassigned employee #{$employee_id} from client #{$client_id}"
        );
        return true;
    }
    
    return false;
}

/**
 * Get all clients assigned to an employee
 * 
 * @param int $employee_id Employee ID
 * @param bool $active_only Get only active assignments
 * @return array Client records
 */
function get_employee_assigned_clients($employee_id, $active_only = true) {
    global $pdo;
    
    $sql = "SELECT c.* 
            FROM clients c
            JOIN employee_client_assignments eca ON c.id = eca.client_id
            WHERE eca.employee_id = :employee_id";
    
    if ($active_only) {
        $sql .= " AND eca.is_active = 1";
    }
    
    $sql .= " ORDER BY c.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['employee_id' => $employee_id]);
    
    return $stmt->fetchAll();
}

/**
 * Get client IDs assigned to an employee
 * 
 * @param int $employee_id Employee ID
 * @param bool $active_only Get only active assignments
 * @return array Client IDs
 */
function get_employee_assigned_client_ids($employee_id, $active_only = true) {
    global $pdo;
    
    $sql = "SELECT client_id 
            FROM employee_client_assignments
            WHERE employee_id = :employee_id";
    
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['employee_id' => $employee_id]);
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

/**
 * Check if an employee is assigned to a client
 * 
 * @param int $employee_id Employee ID
 * @param int $client_id Client ID
 * @param bool $active_only Check only active assignments
 * @return bool Is assigned
 */
function is_employee_assigned_to_client($employee_id, $client_id, $active_only = true) {
    global $pdo;
    
    $sql = "SELECT 1 
            FROM employee_client_assignments
            WHERE employee_id = :employee_id 
            AND client_id = :client_id";
    
    if ($active_only) {
        $sql .= " AND is_active = 1";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'employee_id' => $employee_id,
        'client_id' => $client_id
    ]);
    
    return (bool)$stmt->fetchColumn();
}

/**
 * Get all employees assigned to a client
 * 
 * @param int $client_id Client ID
 * @param bool $active_only Get only active assignments
 * @return array Employee records
 */
function get_client_assigned_employees($client_id, $active_only = true) {
    global $pdo;
    
    $sql = "SELECT u.id, u.username, u.name, u.email, eca.assigned_at, eca.notes, eca.is_active  
            FROM users u
            JOIN employee_client_assignments eca ON u.id = eca.employee_id
            WHERE eca.client_id = :client_id";
    
    if ($active_only) {
        $sql .= " AND eca.is_active = 1";
    }
    
    $sql .= " ORDER BY u.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_id' => $client_id]);
    
    return $stmt->fetchAll();
}

/**
 * Log client access
 * 
 * @param int $client_id Client ID
 * @param string $access_type Type of access (view, edit, api, etc.)
 * @param string $resource_type Resource being accessed (crm, blob, etc.)
 * @param string $resource_id Optional resource identifier
 * @return bool Success status
 */
function log_client_access($client_id, $access_type, $resource_type, $resource_id = null) {
    global $pdo;
    
    $sql = "INSERT INTO client_access_logs 
            (user_id, user_role, client_id, access_type, resource_type, resource_id, ip_address, user_agent) 
            VALUES 
            (:user_id, :user_role, :client_id, :access_type, :resource_type, :resource_id, :ip_address, :user_agent)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'user_id' => $_SESSION['user_id'] ?? 0,
        'user_role' => $_SESSION['user_role'] ?? '',
        'client_id' => $client_id,
        'access_type' => $access_type,
        'resource_type' => $resource_type,
        'resource_id' => $resource_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    return $result;
}

/**
 * Get access logs for a client
 * 
 * @param int $client_id Client ID
 * @param int $limit Maximum number of logs to return
 * @param int $offset Offset for pagination
 * @return array Access logs
 */
function get_client_access_logs($client_id, $limit = 100, $offset = 0) {
    global $pdo;
    
    $sql = "SELECT cal.*, u.name as user_name 
            FROM client_access_logs cal
            LEFT JOIN users u ON cal.user_id = u.id
            WHERE cal.client_id = :client_id
            ORDER BY cal.created_at DESC
            LIMIT :offset, :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':client_id', $client_id, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Check if user can access client
 * 
 * @param int $client_id Client ID
 * @return bool Can access
 */
function can_access_client($client_id) {
    // Admin can access all clients
    if (has_admin_role(['admin'])) {
        return true;
    }
    
    // Employee can only access assigned clients
    if ($_SESSION['user_role'] == 'employee') {
        return is_employee_assigned_to_client($_SESSION['user_id'], $client_id);
    }
    
    // Client can only access their own client record
    if ($_SESSION['user_role'] == 'client') {
        $client_record = get_client_by_user_id($_SESSION['user_id']);
        return $client_record && $client_record['id'] == $client_id;
    }
    
    return false;
}

/**
 * Get client by user ID
 * 
 * @param int $user_id User ID
 * @return array|false Client record or false if not found
 */
function get_client_by_user_id($user_id) {
    global $pdo;
    
    $sql = "SELECT * FROM clients WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    
    return $stmt->fetch();
}

/**
 * Get client by ID
 * 
 * @param int $client_id Client ID
 * @return array|false Client record or false if not found
 */
function get_client_by_id($client_id) {
    global $pdo;
    
    $sql = "SELECT * FROM clients WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $client_id]);
    
    return $stmt->fetch();
}

/**
 * Get user by ID
 * 
 * @param int $user_id User ID
 * @return array|false User record or false if not found
 */
function get_user_by_id($user_id) {
    global $pdo;
    
    $sql = "SELECT * FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $user_id]);
    
    return $stmt->fetch();
}

/**
 * Get client ID from user ID
 * 
 * @param int $user_id User ID
 * @return int|false Client ID or false if not found
 */
function get_client_id_from_user($user_id) {
    $client = get_client_by_user_id($user_id);
    return $client ? $client['id'] : false;
}