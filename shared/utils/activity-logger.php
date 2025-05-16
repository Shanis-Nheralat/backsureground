<?php
// /shared/utils/activity-logger.php

/**
 * Advanced Admin Activity Logger
 * 
 * Records detailed information about admin actions for security and audit purposes
 */

/**
 * Log an admin action with detailed information
 * 
 * @param int $admin_id User ID of the admin
 * @param string $action_type Type of action (login, logout, create, update, delete, etc.)
 * @param string $module System module (users, tickets, tasks, settings, etc.)
 * @param int $item_id ID of the affected item (optional)
 * @param string $item_type Type of item (user, ticket, task, etc.) (optional)
 * @param mixed $old_value Old value before change (optional)
 * @param mixed $new_value New value after change (optional)
 * @param string $details Additional details about the action
 * @return bool Success status
 */
function log_admin_extended_action($admin_id, $action_type, $module, $item_id = null, $item_type = null, $old_value = null, $new_value = null, $details = '') {
    global $pdo;
    
    try {
        // Convert complex values to JSON for storage
        if (is_array($old_value) || is_object($old_value)) {
            $old_value = json_encode($old_value);
        }
        
        if (is_array($new_value) || is_object($new_value)) {
            $new_value = json_encode($new_value);
        }
        
        // Get client IP and user agent
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Prepare and execute the query
        $stmt = $pdo->prepare("
            INSERT INTO admin_extended_log 
            (admin_id, action_type, module, item_id, item_type, old_value, new_value, ip_address, user_agent, details) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $admin_id,
            $action_type,
            $module,
            $item_id,
            $item_type,
            $old_value,
            $new_value,
            $ip_address,
            $user_agent,
            $details
        ]);
        
        return $result;
    } catch (Exception $e) {
        error_log('Error logging admin action: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get recent admin activity logs
 * 
 * @param array $filters Optional filters for the logs
 * @param int $limit Number of records to return
 * @param int $offset Offset for pagination
 * @return array Logs matching the criteria
 */
function get_admin_activity_logs($filters = [], $limit = 50, $offset = 0) {
    global $pdo;
    
    try {
        $where_clauses = [];
        $params = [];
        
        // Apply filters
        if (!empty($filters['admin_id'])) {
            $where_clauses[] = "admin_id = ?";
            $params[] = $filters['admin_id'];
        }
        
        if (!empty($filters['action_type'])) {
            $where_clauses[] = "action_type = ?";
            $params[] = $filters['action_type'];
        }
        
        if (!empty($filters['module'])) {
            $where_clauses[] = "module = ?";
            $params[] = $filters['module'];
        }
        
        if (!empty($filters['item_id'])) {
            $where_clauses[] = "item_id = ?";
            $params[] = $filters['item_id'];
        }
        
        if (!empty($filters['item_type'])) {
            $where_clauses[] = "item_type = ?";
            $params[] = $filters['item_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Build the query
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        $sql = "
            SELECT l.*, u.username, u.name
            FROM admin_extended_log l
            LEFT JOIN users u ON l.admin_id = u.id
            {$where_sql}
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        // Add limit and offset
        $params[] = $limit;
        $params[] = $offset;
        
        // Execute the query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error retrieving admin logs: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get a count of admin activity logs matching the given filters
 * 
 * @param array $filters Optional filters for the logs
 * @return int Count of matching logs
 */
function count_admin_activity_logs($filters = []) {
    global $pdo;
    
    try {
        $where_clauses = [];
        $params = [];
        
        // Apply the same filters as the get function
        if (!empty($filters['admin_id'])) {
            $where_clauses[] = "admin_id = ?";
            $params[] = $filters['admin_id'];
        }
        
        if (!empty($filters['action_type'])) {
            $where_clauses[] = "action_type = ?";
            $params[] = $filters['action_type'];
        }
        
        if (!empty($filters['module'])) {
            $where_clauses[] = "module = ?";
            $params[] = $filters['module'];
        }
        
        if (!empty($filters['item_id'])) {
            $where_clauses[] = "item_id = ?";
            $params[] = $filters['item_id'];
        }
        
        if (!empty($filters['item_type'])) {
            $where_clauses[] = "item_type = ?";
            $params[] = $filters['item_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Build the query
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        $sql = "
            SELECT COUNT(*) as count
            FROM admin_extended_log
            {$where_sql}
        ";
        
        // Execute the query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log('Error counting admin logs: ' . $e->getMessage());
        return 0;
    }
}