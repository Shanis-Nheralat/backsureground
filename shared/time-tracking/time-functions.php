<?php
/**
 * Time Tracking Functions
 * Handles time log management for employees
 */

// Include required files
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth/admin-auth.php';

/**
 * Start a time tracking session
 * 
 * @param int $employee_id Employee ID
 * @param int|null $client_id Client ID (optional)
 * @param int|null $task_id Task ID (optional)
 * @param int|null $activity_id Activity ID (optional)
 * @param string $description Description of work
 * @return int|false The time log ID or false on failure
 */
function start_time_tracking($employee_id, $client_id = null, $task_id = null, $activity_id = null, $description = '') {
    global $pdo;
    
    // Check if there's an active session for this employee
    $active_session = get_active_time_session($employee_id);
    if ($active_session) {
        return false; // Can't start a new session when one is active
    }
    
    try {
        $sql = "INSERT INTO employee_time_logs 
                (employee_id, client_id, task_id, activity_id, start_time, description) 
                VALUES (:employee_id, :client_id, :task_id, :activity_id, NOW(), :description)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employee_id,
            ':client_id' => $client_id,
            ':task_id' => $task_id,
            ':activity_id' => $activity_id,
            ':description' => $description
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Start time tracking error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Stop a time tracking session
 * 
 * @param int $time_log_id Time log ID
 * @param int $employee_id Employee ID (for security)
 * @param string $description Updated description (optional)
 * @return bool Success status
 */
function stop_time_tracking($time_log_id, $employee_id, $description = null) {
    global $pdo;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get the time log
        $sql = "SELECT * FROM employee_time_logs 
                WHERE id = :id AND employee_id = :employee_id AND end_time IS NULL 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $time_log_id,
            ':employee_id' => $employee_id
        ]);
        
        $time_log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$time_log) {
            $pdo->rollBack();
            return false; // Time log not found or already stopped
        }
        
        // Update the description if provided
        $update_fields = "end_time = NOW()";
        $params = [
            ':id' => $time_log_id,
            ':employee_id' => $employee_id
        ];
        
        if ($description !== null) {
            $update_fields .= ", description = :description";
            $params[':description'] = $description;
        }
        
        // Update the time log
        $sql = "UPDATE employee_time_logs 
                SET {$update_fields} 
                WHERE id = :id AND employee_id = :employee_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Commit transaction
        $pdo->commit();
        
        return true;
    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        error_log('Stop time tracking error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Add a manual time entry
 * 
 * @param int $employee_id Employee ID
 * @param string $start_time Start time (Y-m-d H:i:s format)
 * @param string $end_time End time (Y-m-d H:i:s format)
 * @param int|null $client_id Client ID (optional)
 * @param int|null $task_id Task ID (optional)
 * @param int|null $activity_id Activity ID (optional)
 * @param string $description Description of work
 * @return int|false The time log ID or false on failure
 */
function add_manual_time_entry($employee_id, $start_time, $end_time, $client_id = null, $task_id = null, $activity_id = null, $description = '') {
    global $pdo;
    
    try {
        // Validate the times
        $start = new DateTime($start_time);
        $end = new DateTime($end_time);
        
        if ($start >= $end) {
            return false; // End time must be after start time
        }
        
        // Calculate duration in minutes
        $interval = $start->diff($end);
        $duration_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        
        $sql = "INSERT INTO employee_time_logs 
                (employee_id, client_id, task_id, activity_id, start_time, end_time, duration_minutes, description, is_manual_entry) 
                VALUES (:employee_id, :client_id, :task_id, :activity_id, :start_time, :end_time, :duration_minutes, :description, 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employee_id,
            ':client_id' => $client_id,
            ':task_id' => $task_id,
            ':activity_id' => $activity_id,
            ':start_time' => $start_time,
            ':end_time' => $end_time,
            ':duration_minutes' => $duration_minutes,
            ':description' => $description
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Add manual time entry error: ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log('Date parsing error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update a time log entry
 * 
 * @param int $time_log_id Time log ID
 * @param int $employee_id Employee ID (for security)
 * @param array $data Updated data
 * @return bool Success status
 */
function update_time_log($time_log_id, $employee_id, $data) {
    global $pdo;
    
    // Check if the time log exists and belongs to this employee
    $sql = "SELECT * FROM employee_time_logs 
            WHERE id = :id AND employee_id = :employee_id 
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $time_log_id,
        ':employee_id' => $employee_id
    ]);
    
    $time_log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$time_log) {
        return false; // Time log not found or doesn't belong to this employee
    }
    
    try {
        // Build the update SQL based on provided data
        $update_fields = [];
        $params = [
            ':id' => $time_log_id,
            ':employee_id' => $employee_id
        ];
        
        // Only allow updating certain fields
        $allowed_fields = ['client_id', 'task_id', 'activity_id', 'description', 'start_time', 'end_time'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        
        // If start_time or end_time was updated, recalculate duration
        if (isset($data['start_time']) || isset($data['end_time'])) {
            $start_time = isset($data['start_time']) ? $data['start_time'] : $time_log['start_time'];
            $end_time = isset($data['end_time']) ? $data['end_time'] : $time_log['end_time'];
            
            if ($start_time && $end_time) {
                $start = new DateTime($start_time);
                $end = new DateTime($end_time);
                
                if ($start >= $end) {
                    return false; // End time must be after start time
                }
                
                // Calculate duration in minutes
                $interval = $start->diff($end);
                $duration_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
                
                $update_fields[] = "duration_minutes = :duration_minutes";
                $params[':duration_minutes'] = $duration_minutes;
            }
        }
        
        // If no fields to update, return true (no changes needed)
        if (empty($update_fields)) {
            return true;
        }
        
        // Update the time log
        $sql = "UPDATE employee_time_logs 
                SET " . implode(', ', $update_fields) . " 
                WHERE id = :id AND employee_id = :employee_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return true;
    } catch (PDOException $e) {
        error_log('Update time log error: ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log('Date parsing error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete a time log entry
 * 
 * @param int $time_log_id Time log ID
 * @param int $employee_id Employee ID (for security)
 * @return bool Success status
 */
function delete_time_log($time_log_id, $employee_id) {
    global $pdo;
    
    try {
        $sql = "DELETE FROM employee_time_logs 
                WHERE id = :id AND employee_id = :employee_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $time_log_id,
            ':employee_id' => $employee_id
        ]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('Delete time log error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get active time tracking session for an employee
 * 
 * @param int $employee_id Employee ID
 * @return array|false Active time log or false if none
 */
function get_active_time_session($employee_id) {
    global $pdo;
    
    try {
        $sql = "SELECT tl.*, c.name as client_name, t.title as task_title 
                FROM employee_time_logs tl
                LEFT JOIN clients c ON tl.client_id = c.id
                LEFT JOIN on_demand_tasks t ON tl.task_id = t.id
                WHERE tl.employee_id = :employee_id AND tl.end_time IS NULL 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':employee_id' => $employee_id]);
        
        $time_log = $stmt->fetch(PDO::FETCH_ASSOC);
        return $time_log ?: false;
    } catch (PDOException $e) {
        error_log('Get active time session error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get time logs for an employee
 * 
 * @param int $employee_id Employee ID
 * @param array $filters Optional filters (date range, client, task, etc.)
 * @param int $limit Max number of logs
 * @param int $offset Pagination offset
 * @return array Time logs
 */
function get_employee_time_logs($employee_id, $filters = [], $limit = 20, $offset = 0) {
    global $pdo;
    
    try {
        $where_clauses = ["tl.employee_id = :employee_id"];
        $params = [':employee_id' => $employee_id];
        
        // Add date range filters
        if (!empty($filters['start_date'])) {
            $where_clauses[] = "DATE(tl.start_time) >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_clauses[] = "DATE(tl.start_time) <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        // Add client filter
        if (!empty($filters['client_id'])) {
            $where_clauses[] = "tl.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }
        
        // Add task filter
        if (!empty($filters['task_id'])) {
            $where_clauses[] = "tl.task_id = :task_id";
            $params[':task_id'] = $filters['task_id'];
        }
        
        // Add activity filter
        if (!empty($filters['activity_id'])) {
            $where_clauses[] = "tl.activity_id = :activity_id";
            $params[':activity_id'] = $filters['activity_id'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT tl.*, c.name as client_name, t.title as task_title 
                FROM employee_time_logs tl
                LEFT JOIN clients c ON tl.client_id = c.id
                LEFT JOIN on_demand_tasks t ON tl.task_id = t.id
                WHERE {$where_sql}
                ORDER BY tl.start_time DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get employee time logs error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get total time logged for an employee
 * 
 * @param int $employee_id Employee ID
 * @param array $filters Optional filters (date range, client, task, etc.)
 * @return int Total minutes
 */
function get_employee_total_time($employee_id, $filters = []) {
    global $pdo;
    
    try {
        $where_clauses = ["tl.employee_id = :employee_id", "tl.end_time IS NOT NULL"];
        $params = [':employee_id' => $employee_id];
        
        // Add date range filters
        if (!empty($filters['start_date'])) {
            $where_clauses[] = "DATE(tl.start_time) >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_clauses[] = "DATE(tl.start_time) <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        // Add client filter
        if (!empty($filters['client_id'])) {
            $where_clauses[] = "tl.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }
        
        // Add task filter
        if (!empty($filters['task_id'])) {
            $where_clauses[] = "tl.task_id = :task_id";
            $params[':task_id'] = $filters['task_id'];
        }
        
        // Add activity filter
        if (!empty($filters['activity_id'])) {
            $where_clauses[] = "tl.activity_id = :activity_id";
            $params[':activity_id'] = $filters['activity_id'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT 
                  SUM(
                    COALESCE(
                      duration_minutes, 
                      TIMESTAMPDIFF(MINUTE, start_time, end_time)
                    )
                  ) as total_minutes
                FROM employee_time_logs tl
                WHERE {$where_sql}";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Get employee total time error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get time logs for admin report
 * 
 * @param array $filters Optional filters (date range, employee, client, task, etc.)
 * @param int $limit Max number of logs
 * @param int $offset Pagination offset
 * @return array Time logs
 */
function get_admin_time_logs($filters = [], $limit = 50, $offset = 0) {
    global $pdo;
    
    try {
        $where_clauses = ["tl.end_time IS NOT NULL"];
        $params = [];
        
        // Add employee filter
        if (!empty($filters['employee_id'])) {
            $where_clauses[] = "tl.employee_id = :employee_id";
            $params[':employee_id'] = $filters['employee_id'];
        }
        
        // Add date range filters
        if (!empty($filters['start_date'])) {
            $where_clauses[] = "DATE(tl.start_time) >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_clauses[] = "DATE(tl.start_time) <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        // Add client filter
        if (!empty($filters['client_id'])) {
            $where_clauses[] = "tl.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }
        
        // Add task filter
        if (!empty($filters['task_id'])) {
            $where_clauses[] = "tl.task_id = :task_id";
            $params[':task_id'] = $filters['task_id'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT tl.*, 
                  e.name as employee_name,
                  c.name as client_name, 
                  t.title as task_title,
                  COALESCE(
                    tl.duration_minutes, 
                    TIMESTAMPDIFF(MINUTE, tl.start_time, tl.end_time)
                  ) as minutes_logged
                FROM employee_time_logs tl
                JOIN users e ON tl.employee_id = e.id
                LEFT JOIN clients c ON tl.client_id = c.id
                LEFT JOIN on_demand_tasks t ON tl.task_id = t.id
                WHERE {$where_sql}
                ORDER BY tl.start_time DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get admin time logs error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get summary report by employee
 * 
 * @param array $filters Optional filters (date range, client, etc.)
 * @return array Employee summary report
 */
function get_employee_summary_report($filters = []) {
    global $pdo;
    
    try {
        $where_clauses = ["tl.end_time IS NOT NULL"];
        $params = [];
        
        // Add date range filters
        if (!empty($filters['start_date'])) {
            $where_clauses[] = "DATE(tl.start_time) >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_clauses[] = "DATE(tl.start_time) <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        // Add client filter
        if (!empty($filters['client_id'])) {
            $where_clauses[] = "tl.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT 
                  e.id as employee_id,
                  e.name as employee_name,
                  COUNT(tl.id) as entries_count,
                  SUM(
                    COALESCE(
                      tl.duration_minutes, 
                      TIMESTAMPDIFF(MINUTE, tl.start_time, tl.end_time)
                    )
                  ) as total_minutes
                FROM employee_time_logs tl
                JOIN users e ON tl.employee_id = e.id
                WHERE {$where_sql}
                GROUP BY e.id, e.name
                ORDER BY total_minutes DESC";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get employee summary report error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get summary report by client
 * 
 * @param array $filters Optional filters (date range, employee, etc.)
 * @return array Client summary report
 */
function get_client_summary_report($filters = []) {
    global $pdo;
    
    try {
        $where_clauses = ["tl.end_time IS NOT NULL", "tl.client_id IS NOT NULL"];
        $params = [];
        
        // Add employee filter
        if (!empty($filters['employee_id'])) {
            $where_clauses[] = "tl.employee_id = :employee_id";
            $params[':employee_id'] = $filters['employee_id'];
        }
        
        // Add date range filters
        if (!empty($filters['start_date'])) {
            $where_clauses[] = "DATE(tl.start_time) >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_clauses[] = "DATE(tl.start_time) <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "SELECT 
                  c.id as client_id,
                  c.name as client_name,
                  COUNT(tl.id) as entries_count,
                  SUM(
                    COALESCE(
                      tl.duration_minutes, 
                      TIMESTAMPDIFF(MINUTE, tl.start_time, tl.end_time)
                    )
                  ) as total_minutes
                FROM employee_time_logs tl
                JOIN clients c ON tl.client_id = c.id
                WHERE {$where_sql}
                GROUP BY c.id, c.name
                ORDER BY total_minutes DESC";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get client summary report error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Format minutes as hours and minutes
 * 
 * @param int $minutes Total minutes
 * @return string Formatted time (e.g., "2h 30m")
 */
function format_time_duration($minutes) {
    $hours = floor($minutes / 60);
    $remaining_minutes = $minutes % 60;
    
    return $hours . 'h ' . str_pad($remaining_minutes, 2, '0', STR_PAD_LEFT) . 'm';
}

/**
 * Generate a CSV export of time logs
 * 
 * @param array $logs Time logs
 * @return string CSV content
 */
function generate_time_logs_csv($logs) {
    // Define CSV headers
    $headers = [
        'ID',
        'Employee',
        'Client',
        'Task',
        'Start Time',
        'End Time',
        'Duration',
        'Description',
        'Manual Entry'
    ];
    
    // Create output buffer
    $output = fopen('php://temp', 'r+');
    
    // Add headers
    fputcsv($output, $headers);
    
    // Add data rows
    foreach ($logs as $log) {
        $minutes = isset($log['minutes_logged']) ? $log['minutes_logged'] : 
                   (isset($log['duration_minutes']) ? $log['duration_minutes'] : 
                   (isset($log['end_time']) ? floor((strtotime($log['end_time']) - strtotime($log['start_time'])) / 60) : 0));
        
        $duration = format_time_duration($minutes);
        
        $row = [
            $log['id'],
            $log['employee_name'] ?? '',
            $log['client_name'] ?? '',
            $log['task_title'] ?? '',
            date('Y-m-d H:i:s', strtotime($log['start_time'])),
            $log['end_time'] ? date('Y-m-d H:i:s', strtotime($log['end_time'])) : '',
            $duration,
            $log['description'] ?? '',
            $log['is_manual_entry'] ? 'Yes' : 'No'
        ];
        
        fputcsv($output, $row);
    }
    
    // Get the content
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}