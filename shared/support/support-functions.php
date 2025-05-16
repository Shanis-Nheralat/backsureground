<?php
/**
 * Support desk core functions
 */

// Create a new support ticket
function create_support_ticket($user_id, $user_role, $subject, $description, $priority = 'medium') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO support_tickets 
                (user_id, user_role, subject, description, priority, status, created_at) 
            VALUES 
                (?, ?, ?, ?, ?, 'open', NOW())
        ");
        
        $stmt->execute([$user_id, $user_role, $subject, $description, $priority]);
        $ticket_id = $pdo->lastInsertId();
        
        // Log the action
        log_admin_action($user_id, $user_role, 'create', 'support_tickets', $ticket_id, "Created support ticket: $subject");
        
        // Create ticket directory for attachments
        $upload_dir = __DIR__ . '/../../uploads/support/' . $ticket_id;
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Trigger notification to admins
        $notification_data = [
            'ticket_id' => $ticket_id,
            'subject' => $subject,
            'priority' => $priority
        ];
        
        // Notify admins
        $admin_ids = get_all_admin_ids();
        foreach ($admin_ids as $admin_id) {
            create_notification($admin_id, 'admin', 'New support ticket', 
                "A new support ticket has been created: $subject", 
                '/admin/support/ticket-details.php?id=' . $ticket_id, 
                $notification_data
            );
        }
        
        // Trigger email event
        trigger_email_event('new_support_ticket', [
            'ticket_id' => $ticket_id,
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority,
            'user_id' => $user_id,
            'user_role' => $user_role
        ]);
        
        return $ticket_id;
    } catch (PDOException $e) {
        error_log('Error creating support ticket: ' . $e->getMessage());
        return false;
    }
}

// Add a reply to a ticket
function add_ticket_reply($ticket_id, $sender_id, $sender_role, $message, $is_internal_note = 0, $files = []) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Add the reply
        $stmt = $pdo->prepare("
            INSERT INTO support_replies 
                (ticket_id, sender_id, sender_role, message, is_internal_note, created_at) 
            VALUES 
                (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$ticket_id, $sender_id, $sender_role, $message, $is_internal_note]);
        $reply_id = $pdo->lastInsertId();
        
        // Update the ticket's last reply information
        $stmt = $pdo->prepare("
            UPDATE support_tickets 
            SET last_reply_by = ?, last_reply_role = ?, last_reply_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$sender_id, $sender_role, $ticket_id]);
        
        // If the ticket is in 'open' status and this is an admin reply, update to 'in_progress'
        if ($sender_role == 'admin' && !$is_internal_note) {
            $stmt = $pdo->prepare("
                UPDATE support_tickets 
                SET status = 'in_progress' 
                WHERE id = ? AND status = 'open'
            ");
            
            $stmt->execute([$ticket_id]);
        }
        
        // Process file attachments
        if (!empty($files)) {
            $upload_dir = __DIR__ . '/../../uploads/support/' . $ticket_id . '/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($files['name'] as $key => $name) {
                if ($files['error'][$key] === 0) {
                    $tmp_name = $files['tmp_name'][$key];
                    $size = $files['size'][$key];
                    $type = $files['type'][$key];
                    
                    // Generate a unique filename
                    $file_ext = pathinfo($name, PATHINFO_EXTENSION);
                    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
                    $path = $upload_dir . $new_filename;
                    
                    // Move the file
                    if (move_uploaded_file($tmp_name, $path)) {
                        // Add to database
                        $stmt = $pdo->prepare("
                            INSERT INTO support_attachments 
                                (reply_id, file_name, file_path, file_size, file_type, uploaded_at) 
                            VALUES 
                                (?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $file_path = '/uploads/support/' . $ticket_id . '/' . $new_filename;
                        $stmt->execute([$reply_id, $name, $file_path, $size, $type]);
                    }
                }
            }
        }
        
        // Send notifications if this is not an internal note
        if (!$is_internal_note) {
            // Get ticket details
            $ticket = get_ticket($ticket_id);
            
            if ($sender_role == 'admin' || $sender_role == 'employee') {
                // Notify client
                if ($ticket['user_role'] == 'client') {
                    create_notification(
                        $ticket['user_id'], 
                        $ticket['user_role'], 
                        'Support ticket reply', 
                        "Your support ticket '{$ticket['subject']}' has received a reply", 
                        '/client/support/my-tickets.php?view=' . $ticket_id,
                        ['ticket_id' => $ticket_id]
                    );
                    
                    // Trigger email event
                    trigger_email_event('support_ticket_reply', [
                        'ticket_id' => $ticket_id,
                        'subject' => $ticket['subject'],
                        'message' => $message,
                        'recipient_id' => $ticket['user_id'],
                        'recipient_role' => $ticket['user_role']
                    ]);
                }
            } else {
                // This is a client or employee reply, notify admins and assigned employee
                
                // Notify admins
                $admin_ids = get_all_admin_ids();
                foreach ($admin_ids as $admin_id) {
                    create_notification(
                        $admin_id, 
                        'admin', 
                        'Support ticket reply', 
                        "Support ticket '{$ticket['subject']}' has received a reply", 
                        '/admin/support/ticket-details.php?id=' . $ticket_id,
                        ['ticket_id' => $ticket_id]
                    );
                }
                
                // If ticket is assigned to an employee, notify them too
                if (!empty($ticket['assigned_to'])) {
                    create_notification(
                        $ticket['assigned_to'], 
                        'employee', 
                        'Support ticket reply', 
                        "Support ticket '{$ticket['subject']}' has received a reply", 
                        '/employee/support/assigned-tickets.php?view=' . $ticket_id,
                        ['ticket_id' => $ticket_id]
                    );
                    
                    // Trigger email event for employee
                    trigger_email_event('support_ticket_reply', [
                        'ticket_id' => $ticket_id,
                        'subject' => $ticket['subject'],
                        'message' => $message,
                        'recipient_id' => $ticket['assigned_to'],
                        'recipient_role' => 'employee'
                    ]);
                }
            }
        }
        
        $pdo->commit();
        return $reply_id;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Error adding ticket reply: ' . $e->getMessage());
        return false;
    }
}

// Get a specific ticket
function get_ticket($ticket_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                u.name as user_name, 
                u.username as user_username,
                u.email as user_email,
                a.name as assigned_name
            FROM support_tickets t
            LEFT JOIN users u ON t.user_id = u.id AND t.user_role = u.role
            LEFT JOIN users a ON t.assigned_to = a.id AND a.role = 'employee'
            WHERE t.id = ?
        ");
        
        $stmt->execute([$ticket_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting ticket: ' . $e->getMessage());
        return false;
    }
}

// Get ticket replies
function get_ticket_replies($ticket_id, $include_internal = false) {
    global $pdo;
    
    try {
        $sql = "
            SELECT r.*, 
                u.name as sender_name, 
                u.username as sender_username,
                u.email as sender_email
            FROM support_replies r
            LEFT JOIN users u ON r.sender_id = u.id AND r.sender_role = u.role
            WHERE r.ticket_id = ?
        ";
        
        if (!$include_internal) {
            $sql .= " AND r.is_internal_note = 0";
        }
        
        $sql .= " ORDER BY r.created_at ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticket_id]);
        
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attachments for each reply
        foreach ($replies as &$reply) {
            $reply['attachments'] = get_reply_attachments($reply['id']);
        }
        
        return $replies;
    } catch (PDOException $e) {
        error_log('Error getting ticket replies: ' . $e->getMessage());
        return [];
    }
}

// Get reply attachments
function get_reply_attachments($reply_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM support_attachments
            WHERE reply_id = ?
            ORDER BY uploaded_at ASC
        ");
        
        $stmt->execute([$reply_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting reply attachments: ' . $e->getMessage());
        return [];
    }
}

// Get tickets for a client
function get_client_tickets($client_id, $status = null, $page = 1, $limit = 20) {
    global $pdo;
    
    try {
        $params = [$client_id];
        
        $sql = "
            SELECT t.*, 
                u.name as user_name
            FROM support_tickets t
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.user_id = ? AND t.user_role = 'client'
        ";
        
        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY t.last_reply_at DESC, t.created_at DESC";
        
        // Add pagination
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting client tickets: ' . $e->getMessage());
        return [];
    }
}

// Get tickets for an employee (assigned tickets or created by the employee)
function get_employee_tickets($employee_id, $status = null, $page = 1, $limit = 20) {
    global $pdo;
    
    try {
        $params = [$employee_id, $employee_id];
        
        $sql = "
            SELECT t.*, 
                u.name as user_name
            FROM support_tickets t
            LEFT JOIN users u ON t.user_id = u.id
            WHERE (t.assigned_to = ? OR (t.user_id = ? AND t.user_role = 'employee'))
        ";
        
        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY t.last_reply_at DESC, t.created_at DESC";
        
        // Add pagination
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting employee tickets: ' . $e->getMessage());
        return [];
    }
}

// Get all tickets for admin
function get_all_tickets($status = null, $search = null, $page = 1, $limit = 20) {
    global $pdo;
    
    try {
        $params = [];
        
        $sql = "
            SELECT t.*, 
                u.name as user_name,
                a.name as assigned_name
            FROM support_tickets t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN users a ON t.assigned_to = a.id
            WHERE 1=1
        ";
        
        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        
        if ($search) {
            $sql .= " AND (u.name LIKE ? OR t.id LIKE ? OR t.subject LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $sql .= " ORDER BY t.last_reply_at DESC, t.created_at DESC";
        
        // Add pagination
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting all tickets: ' . $e->getMessage());
        return [];
    }
}

// Update ticket status
function update_ticket_status($ticket_id, $status, $user_id, $user_role) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE support_tickets 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $ticket_id]);
        
        // Log the action
        log_admin_action($user_id, $user_role, 'update', 'support_tickets', $ticket_id, "Updated ticket status to: $status");
        
        // Get ticket details
        $ticket = get_ticket($ticket_id);
        
        // If closing the ticket, notify the client
        if ($status == 'closed' && $ticket['user_role'] == 'client') {
            create_notification(
                $ticket['user_id'], 
                $ticket['user_role'], 
                'Support ticket closed', 
                "Your support ticket '{$ticket['subject']}' has been closed", 
                '/client/support/my-tickets.php?view=' . $ticket_id,
                ['ticket_id' => $ticket_id]
            );
            
            // Trigger email event
            trigger_email_event('support_ticket_closed', [
                'ticket_id' => $ticket_id,
                'subject' => $ticket['subject'],
                'recipient_id' => $ticket['user_id'],
                'recipient_role' => $ticket['user_role']
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log('Error updating ticket status: ' . $e->getMessage());
        return false;
    }
}

// Assign ticket to employee
function assign_ticket_to_employee($ticket_id, $employee_id, $admin_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE support_tickets 
            SET assigned_to = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$employee_id, $ticket_id]);
        
        // Log the action
        log_admin_action($admin_id, 'admin', 'assign', 'support_tickets', $ticket_id, "Assigned ticket to employee ID: $employee_id");
        
        // Get the employee details
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'employee'");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            // Get ticket details
            $ticket = get_ticket($ticket_id);
            
            // Notify the employee
            create_notification(
                $employee_id, 
                'employee', 
                'Support ticket assigned', 
                "You have been assigned to support ticket: '{$ticket['subject']}'", 
                '/employee/support/assigned-tickets.php?view=' . $ticket_id,
                ['ticket_id' => $ticket_id]
            );
            
            // Trigger email event
            trigger_email_event('support_ticket_assigned', [
                'ticket_id' => $ticket_id,
                'subject' => $ticket['subject'],
                'employee_id' => $employee_id,
                'employee_name' => $employee['name'],
                'employee_email' => $employee['email']
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log('Error assigning ticket: ' . $e->getMessage());
        return false;
    }
}

// Get count of tickets by status
function get_ticket_counts_by_status($user_id = null, $user_role = null) {
    global $pdo;
    
    try {
        $params = [];
        
        $sql = "
            SELECT status, COUNT(*) as count
            FROM support_tickets
            WHERE 1=1
        ";
        
        if ($user_id && $user_role) {
            if ($user_role == 'client') {
                $sql .= " AND user_id = ? AND user_role = ?";
                $params[] = $user_id;
                $params[] = $user_role;
            } elseif ($user_role == 'employee') {
                $sql .= " AND (assigned_to = ? OR (user_id = ? AND user_role = ?))";
                $params[] = $user_id;
                $params[] = $user_id;
                $params[] = $user_role;
            }
        }
        
        $sql .= " GROUP BY status";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the results as a simple status => count array
        $counts = [
            'open' => 0,
            'in_progress' => 0,
            'closed' => 0,
            'cancelled' => 0
        ];
        
        foreach ($results as $row) {
            $counts[$row['status']] = (int)$row['count'];
        }
        
        $counts['total'] = array_sum($counts);
        
        return $counts;
    } catch (PDOException $e) {
        error_log('Error getting ticket counts: ' . $e->getMessage());
        return [
            'open' => 0,
            'in_progress' => 0,
            'closed' => 0,
            'cancelled' => 0,
            'total' => 0
        ];
    }
}

// Helper function to check if user can access a ticket
function can_access_ticket($ticket_id, $user_id, $user_role) {
    // Admin can access all tickets
    if ($user_role == 'admin') {
        return true;
    }
    
    $ticket = get_ticket($ticket_id);
    
    if (!$ticket) {
        return false;
    }
    
    // Client can only access their own tickets
    if ($user_role == 'client') {
        return ($ticket['user_id'] == $user_id && $ticket['user_role'] == 'client');
    }
    
    // Employee can access tickets they created or are assigned to
    if ($user_role == 'employee') {
        return ($ticket['user_id'] == $user_id && $ticket['user_role'] == 'employee') || 
               ($ticket['assigned_to'] == $user_id);
    }
    
    return false;
}

// Get all admin IDs (helper function)
function get_all_admin_ids() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE role = 'admin' AND status = 'active'
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('Error getting admin IDs: ' . $e->getMessage());
        return [];
    }
}

// Get available employees for assignment
function get_available_employees() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, name 
            FROM users 
            WHERE role = 'employee' AND status = 'active'
            ORDER BY name ASC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting available employees: ' . $e->getMessage());
        return [];
    }
}
?>