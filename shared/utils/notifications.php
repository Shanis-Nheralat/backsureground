<?php
/**
 * Notifications System
 * Handles both flash and persistent notifications
 */

// Include required files
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth/admin-auth.php';

/**
 * Set a flash notification (temporary, session-based)
 * 
 * @param string $type info|success|warning|error
 * @param string $message The notification message
 * @return void
 */
function set_flash_notification($type, $message) {
    if (!isset($_SESSION['flash_notifications'])) {
        $_SESSION['flash_notifications'] = [];
    }
    
    $_SESSION['flash_notifications'][] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

/**
 * Get and clear flash notifications
 * 
 * @return array Flash notifications
 */
function get_flash_notifications() {
    $notifications = isset($_SESSION['flash_notifications']) ? $_SESSION['flash_notifications'] : [];
    $_SESSION['flash_notifications'] = [];
    return $notifications;
}

/**
 * Add a persistent notification to the database
 * 
 * @param int $user_id User ID
 * @param string $user_role admin|client|employee
 * @param string $type info|success|warning|error
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $link Optional link
 * @param array $metadata Optional metadata as associative array
 * @return int|false The notification ID or false on failure
 */
function add_notification($user_id, $user_role, $type, $title, $message, $link = null, $metadata = null) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO notifications (user_id, user_role, type, title, message, link, metadata) 
                VALUES (:user_id, :user_role, :type, :title, :message, :link, :metadata)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':user_role' => $user_role,
            ':type' => $type,
            ':title' => $title,
            ':message' => $message,
            ':link' => $link,
            ':metadata' => $metadata ? json_encode($metadata) : null
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Notification creation error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get notifications for a user
 * 
 * @param int $user_id User ID
 * @param string $user_role User role
 * @param bool $unread_only Get only unread notifications
 * @param int $limit Max number of notifications
 * @param int $offset Pagination offset
 * @return array Notifications
 */
function get_notifications($user_id, $user_role, $unread_only = false, $limit = 10, $offset = 0) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = :user_id AND user_role = :user_role";
        
        if ($unread_only) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_role', $user_role, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get notifications error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get unread notification count for a user
 * 
 * @param int $user_id User ID
 * @param string $user_role User role
 * @return int Count of unread notifications
 */
function get_unread_count($user_id, $user_role) {
    global $pdo;
    
    try {
        $sql = "SELECT COUNT(*) FROM notifications 
                WHERE user_id = :user_id AND user_role = :user_role AND is_read = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_role', $user_role, PDO::PARAM_STR);
        $stmt->execute();
        
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Get unread count error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Mark notification as read
 * 
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for security)
 * @return bool Success status
 */
function mark_notification_read($notification_id, $user_id) {
    global $pdo;
    
    try {
        $sql = "UPDATE notifications SET is_read = 1, updated_at = NOW() 
                WHERE id = :id AND user_id = :user_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $notification_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('Mark notification read error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $user_id User ID
 * @param string $user_role User role
 * @return bool Success status
 */
function mark_all_notifications_read($user_id, $user_role) {
    global $pdo;
    
    try {
        $sql = "UPDATE notifications SET is_read = 1, updated_at = NOW() 
                WHERE user_id = :user_id AND user_role = :user_role AND is_read = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_role', $user_role, PDO::PARAM_STR);
        $stmt->execute();
        
        return true;
    } catch (PDOException $e) {
        error_log('Mark all notifications read error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete a notification
 * 
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for security)
 * @return bool Success status
 */
function delete_notification($notification_id, $user_id) {
    global $pdo;
    
    try {
        $sql = "DELETE FROM notifications 
                WHERE id = :id AND user_id = :user_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $notification_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('Delete notification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete all notifications for a user
 * 
 * @param int $user_id User ID
 * @param string $user_role User role
 * @return bool Success status
 */
function delete_all_notifications($user_id, $user_role) {
    global $pdo;
    
    try {
        $sql = "DELETE FROM notifications 
                WHERE user_id = :user_id AND user_role = :user_role";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_role', $user_role, PDO::PARAM_STR);
        $stmt->execute();
        
        return true;
    } catch (PDOException $e) {
        error_log('Delete all notifications error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Display flash notifications in HTML
 * 
 * @return string HTML for flash notifications
 */
function display_flash_notifications() {
    $notifications = get_flash_notifications();
    $html = '';
    
    foreach ($notifications as $notification) {
        $icon = '';
        switch ($notification['type']) {
            case 'success':
                $icon = '<i class="bi bi-check-circle"></i>';
                break;
            case 'warning':
                $icon = '<i class="bi bi-exclamation-triangle"></i>';
                break;
            case 'error':
                $icon = '<i class="bi bi-x-circle"></i>';
                break;
            default:
                $icon = '<i class="bi bi-info-circle"></i>';
        }
        
        $html .= '<div class="alert alert-' . $notification['type'] . ' alert-dismissible fade show" role="alert">';
        $html .= $icon . ' ' . htmlspecialchars($notification['message']);
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $html .= '</div>';
    }
    
    return $html;
}
?>