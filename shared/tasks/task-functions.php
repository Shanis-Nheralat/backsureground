<?php
/**
 * Task Functions - On-Demand Service Support
 * 
 * Core functions for managing on-demand tasks
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth/admin-auth.php';
require_once __DIR__ . '/../utils/notifications.php';

/**
 * Create a new task
 */
function create_task($client_id, $title, $description, $priority, $deadline) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO on_demand_tasks 
            (client_id, title, description, priority, deadline) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$client_id, $title, $description, $priority, $deadline]);
        $task_id = $pdo->lastInsertId();
        
        // Log the task creation
        log_task_action($task_id, 'submitted', $client_id, 'client', 'Task submitted');
        
        // Notify admin
        set_admin_notification('info', 'New task submitted: ' . $title, 'admin-tasks.php');
        
        return $task_id;
    } catch (PDOException $e) {
        error_log('Task Creation Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update task status
 */
function update_task_status($task_id, $status, $user_id, $role, $remarks = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE on_demand_tasks 
            SET status = ?, 
                completed_at = CASE WHEN ? = 'completed' THEN NOW() ELSE completed_at END
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $status, $task_id]);
        
        // Log the status change
        log_task_action($task_id, $status, $user_id, $role, $remarks);
        
        // If task is completed, notify client
        if ($status === 'completed') {
            $client_id = get_task_client_id($task_id);
            if ($client_id) {
                // This would be implemented in the notifications module
                // notify_client($client_id, 'Task Completed', 'Your task has been completed.');
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log('Task Status Update Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get task client ID
 */
function get_task_client_id($task_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT client_id FROM on_demand_tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['client_id'] : null;
    } catch (PDOException $e) {
        error_log('Get Task Client Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Log task action
 */
function log_task_action($task_id, $status, $user_id, $role, $remarks = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO task_logs 
            (task_id, status, updated_by, role, remarks)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$task_id, $status, $user_id, $role, $remarks]);
        return true;
    } catch (PDOException $e) {
        error_log('Task Log Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Add admin notes to a task
 */
function add_task_notes($task_id, $notes) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE on_demand_tasks 
            SET admin_notes = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$notes, $task_id]);
        return true;
    } catch (PDOException $e) {
        error_log('Task Notes Update Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get task details
 */
function get_task($task_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM on_demand_tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Task Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get tasks for a client
 */
function get_client_tasks($client_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM on_demand_tasks 
            WHERE client_id = ? 
            ORDER BY submitted_at DESC
        ");
        
        $stmt->execute([$client_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Client Tasks Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all tasks for admin
 */
function get_all_tasks($filter = []) {
    global $pdo;
    
    $sql = "SELECT t.*, u.username, u.name as client_name 
            FROM on_demand_tasks t
            JOIN users u ON t.client_id = u.id";
    
    $where_clauses = [];
    $params = [];
    
    if (!empty($filter['status'])) {
        $where_clauses[] = "t.status = ?";
        $params[] = $filter['status'];
    }
    if (!empty($filter['client_id'])) {
        $where_clauses[] = "t.client_id = ?";
        $params[] = $filter['client_id'];
    }
    if (!empty($filter['priority'])) {
        $where_clauses[] = "t.priority = ?";
        $params[] = $filter['priority'];
    }
    
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql .= " ORDER BY 
              CASE WHEN t.status = 'submitted' THEN 1
                   WHEN t.status = 'in_progress' THEN 2
                   WHEN t.status = 'completed' THEN 3
                   WHEN t.status = 'cancelled' THEN 4 END, 
              CASE WHEN t.priority = 'high' THEN 1
                   WHEN t.priority = 'medium' THEN 2
                   WHEN t.priority = 'low' THEN 3 END, 
              t.submitted_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get All Tasks Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Upload a file for a task
 */
function upload_task_file($task_id, $file, $uploaded_by) {
    $upload_dir = __DIR__ . '/../../../uploads/on_demand/' . $task_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $filename = time() . '_' . basename($file['name']);
    $filepath = $upload_dir . $filename;
    $allowed_types = ['pdf', 'docx', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File size exceeds limit (10MB)'];
    }
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $db_path = 'uploads/on_demand/' . $task_id . '/' . $filename;
        save_file_to_db($task_id, $uploaded_by, $file['name'], $db_path);
        return ['success' => true, 'file_path' => $db_path];
    } else {
        return ['success' => false, 'message' => 'Failed to upload file'];
    }
}

function save_file_to_db($task_id, $uploaded_by, $filename, $filepath) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO task_uploads 
            (task_id, uploaded_by, file_name, file_path)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$task_id, $uploaded_by, $filename, $filepath]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Save File DB Error: ' . $e->getMessage());
        return false;
    }
}

function get_task_files($task_id, $uploaded_by = null) {
    global $pdo;
    try {
        $sql = "SELECT * FROM task_uploads WHERE task_id = ?";
        $params = [$task_id];
        if ($uploaded_by !== null) {
            $sql .= " AND uploaded_by = ?";
            $params[] = $uploaded_by;
        }
        $sql .= " ORDER BY uploaded_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Task Files Error: ' . $e->getMessage());
        return [];
    }
}

function count_task_files($task_id, $uploaded_by) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM task_uploads 
            WHERE task_id = ? AND uploaded_by = ?
        ");
        $stmt->execute([$task_id, $uploaded_by]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['count'] : 0;
    } catch (PDOException $e) {
        error_log('Count Task Files Error: ' . $e->getMessage());
        return 0;
    }
}

function user_owns_task($task_id, $client_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM on_demand_tasks 
            WHERE id = ? AND client_id = ?
        ");
        $stmt->execute([$task_id, $client_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('User Owns Task Check Error: ' . $e->getMessage());
        return false;
    }
}

function get_pending_task_counts($role = null, $user_id = null) {
    global $pdo;
    $role = $role ?? $_SESSION['user_role'];
    $user_id = $user_id ?? $_SESSION['user_id'];
    $counts = [
        'total' => 0,
        'submitted' => 0,
        'in_progress' => 0,
        'high_priority' => 0,
        'overdue' => 0,
        'due_soon' => 0
    ];
    try {
        $sql = "SELECT status, priority, COUNT(*) as count FROM on_demand_tasks WHERE status IN ('submitted', 'in_progress')";
        if ($role == 'client') {
            $sql .= " AND client_id = :user_id";
        }
        $sql .= " GROUP BY status, priority";
        $stmt = $pdo->prepare($sql);
        if ($role == 'client') {
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            if ($row['status'] == 'submitted') {
                $counts['submitted'] += $row['count'];
            } elseif ($row['status'] == 'in_progress') {
                $counts['in_progress'] += $row['count'];
            }
            if ($row['priority'] == 'high') {
                $counts['high_priority'] += $row['count'];
            }
            $counts['total'] += $row['count'];
        }
        $overdue_sql = "SELECT COUNT(*) as count FROM on_demand_tasks WHERE status IN ('submitted', 'in_progress') AND deadline < CURRENT_DATE()";
        if ($role == 'client') {
            $overdue_sql .= " AND client_id = :user_id";
        }
        $overdue_stmt = $pdo->prepare($overdue_sql);
        if ($role == 'client') {
            $overdue_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        }
        $overdue_stmt->execute();
        $counts['overdue'] = $overdue_stmt->fetchColumn();
        $due_soon_sql = "SELECT COUNT(*) as count FROM on_demand_tasks WHERE status IN ('submitted', 'in_progress') AND deadline BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY)";
        if ($role == 'client') {
            $due_soon_sql .= " AND client_id = :user_id";
        }
        $due_soon_stmt = $pdo->prepare($due_soon_sql);
        if ($role == 'client') {
            $due_soon_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        }
        $due_soon_stmt->execute();
        $counts['due_soon'] = $due_soon_stmt->fetchColumn();
        return $counts;
    } catch (PDOException $e) {
        error_log('Get Task Counts Error: ' . $e->getMessage());
        return $counts;
    }
}

function get_completed_task_count($role = null, $user_id = null, $days = 7) {
    global $pdo;
    $role = $role ?? $_SESSION['user_role'];
    $user_id = $user_id ?? $_SESSION['user_id'];
    try {
        $sql = "SELECT COUNT(*) as count FROM on_demand_tasks WHERE status = 'completed' AND completed_at >= DATE_SUB(CURRENT_DATE(), INTERVAL :days DAY)";
        if ($role == 'client') {
            $sql .= " AND client_id = :user_id";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        if ($role == 'client') {
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Get Completed Tasks Error: ' . $e->getMessage());
        return 0;
    }
}

function get_dashboard_task_summary($role = null, $user_id = null) {
    $pending_counts = get_pending_task_counts($role, $user_id);
    $completed_count = get_completed_task_count($role, $user_id, 7);
    return [
        'pending' => $pending_counts,
        'completed' => $completed_count
    ];
}
