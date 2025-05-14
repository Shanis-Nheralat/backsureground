<?php
/**
 * Helper functions for user, client and employee management
 * Used by time tracking system and notification systems
 */

/**
 * Get all employees
 * 
 * @return array List of employees
 */
function get_all_employees() {
    global $pdo;
    
    try {
        $sql = "SELECT id, name, email 
                FROM users 
                WHERE role = 'employee' AND status = 'active'
                ORDER BY name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get all employees error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all clients
 * 
 * @return array List of clients
 */
function get_all_clients() {
    global $pdo;
    
    try {
        $sql = "SELECT id, name, email 
                FROM clients 
                WHERE status = 'active'
                ORDER BY name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get all clients error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get clients assigned to an employee
 * 
 * @param int $employee_id Employee ID
 * @return array List of assigned clients
 */
function get_assigned_clients($employee_id) {
    global $pdo;
    
    try {
        $sql = "SELECT c.id, c.name, c.email 
                FROM clients c
                INNER JOIN dedicated_assignments a ON c.id = a.client_id
                WHERE a.employee_id = :employee_id AND c.status = 'active'
                ORDER BY c.name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':employee_id' => $employee_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get assigned clients error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get active tasks for an employee
 * 
 * @param int $employee_id Employee ID
 * @return array List of active tasks
 */
function get_employee_active_tasks($employee_id) {
    global $pdo;
    
    try {
        // Get tasks from on demand tasks
        $sql = "SELECT t.id, t.title, t.client_id 
                FROM on_demand_tasks t
                INNER JOIN dedicated_assignments a ON t.client_id = a.client_id
                WHERE a.employee_id = :employee_id 
                AND t.status IN ('submitted', 'in_progress')
                ORDER BY t.deadline ASC, t.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':employee_id' => $employee_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get employee active tasks error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get client by ID
 * 
 * @param int $client_id Client ID
 * @return array|false Client data or false if not found
 */
function get_client_by_id($client_id) {
    global $pdo;
    
    try {
        $sql = "SELECT id, name, email 
                FROM clients 
                WHERE id = :client_id
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':client_id' => $client_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get client by ID error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all admin users
 * 
 * @return array List of admin users
 */
function get_admins() {
    global $pdo;
    
    try {
        $sql = "SELECT id, name, email 
                FROM users 
                WHERE role = 'admin' AND status = 'active'
                ORDER BY name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get admins error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get employee by ID
 * 
 * @param int $employee_id Employee ID
 * @return array|false Employee data or false if not found
 */
function get_employee_by_id($employee_id) {
    global $pdo;
    
    try {
        $sql = "SELECT id, name, email 
                FROM users 
                WHERE id = :employee_id AND role = 'employee'
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':employee_id' => $employee_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get employee by ID error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get user by ID - works for any user type
 * 
 * @param int $user_id User ID
 * @return array|false User data or false if not found
 */
function get_user_by_id($user_id) {
    global $pdo;
    
    try {
        $sql = "SELECT id, name, email, role
                FROM users 
                WHERE id = :user_id
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get user by ID error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get users by role
 * 
 * @param string $role Role (admin, client, employee)
 * @return array List of users with the specified role
 */
function get_users_by_role($role) {
    global $pdo;
    
    try {
        $sql = "SELECT id, name, email 
                FROM users 
                WHERE role = :role AND status = 'active'
                ORDER BY name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':role' => $role]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get users by role error: ' . $e->getMessage());
        return [];
    }
}