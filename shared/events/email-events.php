<?php
/**
 * Email Event System
 * Maps platform events to email templates and sends emails
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mailer/mailer.php';

/**
 * Initialize default email events
 * 
 * @return void
 */
function initialize_email_events() {
    global $pdo;
    
    $default_events = [
        [
            'event_key' => 'task_submitted',
            'template_name' => 'task_submitted',
            'subject' => 'New Task Submitted',
            'description' => 'Sent when a client submits a new task'
        ],
        [
            'event_key' => 'task_completed',
            'template_name' => 'task_completed',
            'subject' => 'Task Completed',
            'description' => 'Sent when a task is marked as completed'
        ],
        [
            'event_key' => 'document_uploaded',
            'template_name' => 'document_uploaded',
            'subject' => 'New Document Uploaded',
            'description' => 'Sent when a new document is uploaded'
        ],
        [
            'event_key' => 'document_approved',
            'template_name' => 'document_approved',
            'subject' => 'Document Approved',
            'description' => 'Sent when a document is approved'
        ],
        [
            'event_key' => 'ticket_created',
            'template_name' => 'ticket_created',
            'subject' => 'New Support Ticket Created',
            'description' => 'Sent when a new support ticket is created'
        ],
        [
            'event_key' => 'ticket_reply',
            'template_name' => 'ticket_reply',
            'subject' => 'New Reply to Support Ticket',
            'description' => 'Sent when there is a new reply to a support ticket'
        ],
        [
            'event_key' => 'plan_assigned',
            'template_name' => 'plan_assigned',
            'subject' => 'New Business Care Plan Assigned',
            'description' => 'Sent when a business care plan is assigned to a client'
        ],
        [
            'event_key' => 'password_reset',
            'template_name' => 'password_reset',
            'subject' => 'Password Reset Request',
            'description' => 'Sent when a user requests a password reset'
        ]
    ];
    
    try {
        $sql = "INSERT IGNORE INTO email_events 
                (event_key, template_name, subject, description) 
                VALUES (:event_key, :template_name, :subject, :description)";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($default_events as $event) {
            $stmt->execute([
                ':event_key' => $event['event_key'],
                ':template_name' => $event['template_name'],
                ':subject' => $event['subject'],
                ':description' => $event['description']
            ]);
        }
    } catch (PDOException $e) {
        error_log('Initialize email events error: ' . $e->getMessage());
    }
}

/**
 * Get email event details by event key
 * 
 * @param string $event_key The event key
 * @return array|false The event details or false if not found
 */
function get_email_event($event_key) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM email_events WHERE event_key = :event_key AND active = 1 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':event_key' => $event_key]);
        
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        return $event ?: false;
    } catch (PDOException $e) {
        error_log('Get email event error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Triggers an email event and sends the appropriate email
 * 
 * @param string $event_key The event key
 * @param array $context The context data for the email template
 * @param string $recipient_email The recipient email address
 * @param string $recipient_name The recipient name
 * @return bool Success status
 */
function trigger_email_event($event_key, $context, $recipient_email, $recipient_name = '') {
    $event = get_email_event($event_key);
    
    if (!$event) {
        error_log("Email event not found: {$event_key}");
        return false;
    }
    
    // Load the email template
    $template_path = __DIR__ . '/../../email_templates/' . $event['template_name'] . '.html';
    
    if (!file_exists($template_path)) {
        error_log("Email template not found: {$template_path}");
        return false;
    }
    
    $template = file_get_contents($template_path);
    
    // Replace placeholders in the template
    foreach ($context as $key => $value) {
        $template = str_replace('{{' . $key . '}}', $value, $template);
    }
    
    // Add current year for copyright
    $template = str_replace('{{current_year}}', date('Y'), $template);
    
    // Send the email
    $result = send_email($recipient_email, $recipient_name, $event['subject'], $template);
    
    // Log the email
    log_email($event_key, $recipient_email, $event['subject'], $template, $result ? 'sent' : 'failed', $result ? null : 'Email sending failed', $context);
    
    return $result;
}

/**
 * Log an email in the database
 * 
 * @param string $event_key The event key
 * @param string $recipient The recipient email
 * @param string $subject The email subject
 * @param string $body The email body
 * @param string $status The email status (queued, sent, failed)
 * @param string $error_message The error message if any
 * @param array $metadata Additional metadata
 * @return int|false The log ID or false on failure
 */
function log_email($event_key, $recipient, $subject, $body, $status = 'sent', $error_message = null, $metadata = null) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO email_log 
                (event_key, recipient, subject, body, status, error_message, metadata) 
                VALUES (:event_key, :recipient, :subject, :body, :status, :error_message, :metadata)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':event_key' => $event_key,
            ':recipient' => $recipient,
            ':subject' => $subject,
            ':body' => $body,
            ':status' => $status,
            ':error_message' => $error_message,
            ':metadata' => $metadata ? json_encode($metadata) : null
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Log email error: ' . $e->getMessage());
        return false;
    }
}