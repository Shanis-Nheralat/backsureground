<?php
/**
 * Mark notification as read via AJAX
 */

// Include required files
require_once '../db.php';
require_once '../auth/admin-auth.php';
require_once '../utils/notifications.php';

// Check if user is logged in
if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit;
}

// Get notification ID
$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if ($notification_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit;
}

// Mark notification as read
$success = mark_notification_read($notification_id, $_SESSION['user_id']);

// Return response
header('Content-Type: application/json');
echo json_encode(['success' => $success]);