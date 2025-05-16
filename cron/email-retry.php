<?php
// /cron/email-retry.php
require_once '../shared/db.php';
require_once '../shared/mailer/mailer.php';

/**
 * Retry failed email deliveries
 */
function retry_failed_emails() {
    global $pdo;
    
    try {
        // Get failed emails that are less than 24 hours old
        // We don't want to keep trying very old emails
        $stmt = $pdo->prepare("
            SELECT id, event_key, recipient, subject, body, metadata
            FROM email_log
            WHERE status = 'failed'
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY id
            LIMIT 20
        ");
        $stmt->execute();
        $failed_emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $success_count = 0;
        $fail_count = 0;
        
        foreach ($failed_emails as $email) {
            try {
                // Parse metadata
                $metadata = json_decode($email['metadata'], true) ?? [];
                
                // Try to resend the email
                $result = resend_email($email['recipient'], $email['subject'], $email['body'], $metadata);
                
                if ($result['success']) {
                    // Update email status to sent
                    $update_stmt = $pdo->prepare("
                        UPDATE email_log
                        SET status = 'sent', error_message = NULL, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$email['id']]);
                    
                    $success_count++;
                } else {
                    // Update error message
                    $update_stmt = $pdo->prepare("
                        UPDATE email_log
                        SET error_message = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$result['error'], $email['id']]);
                    
                    $fail_count++;
                }
            } catch (Exception $e) {
                // Log individual email errors but continue with the next one
                error_log("Error retrying email ID {$email['id']}: " . $e->getMessage());
                $fail_count++;
            }
        }
        
        return [
            'success' => true,
            'processed' => count($failed_emails),
            'success_count' => $success_count,
            'fail_count' => $fail_count
        ];
    } catch (Exception $e) {
        error_log("Error in retry_failed_emails: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Auto-close old support tickets
 */
function auto_close_old_tickets() {
    global $pdo;
    
    try {
        // Get auto-close days from settings
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'support_ticket_auto_close_days'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $auto_close_days = isset($result['setting_value']) ? intval($result['setting_value']) : 7;
        
        // Only proceed if the setting is enabled (greater than 0)
        if ($auto_close_days <= 0) {
            return [
                'success' => true,
                'message' => 'Auto-close tickets feature is disabled',
                'closed_count' => 0
            ];
        }
        
        // Find tickets that haven't been updated in the specified number of days
        $stmt = $pdo->prepare("
            SELECT id, subject
            FROM support_tickets
            WHERE status IN ('open', 'in_progress')
            AND (
                last_reply_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                OR (last_reply_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY))
            )
        ");
        $stmt->execute([$auto_close_days, $auto_close_days]);
        $old_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $closed_count = 0;
        
        foreach ($old_tickets as $ticket) {
            // Update ticket status to closed
            $update_stmt = $pdo->prepare("
                UPDATE support_tickets
                SET status = 'closed', updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$ticket['id']]);
            
            // Add auto-close reply
            $reply_stmt = $pdo->prepare("
                INSERT INTO support_replies
                (ticket_id, sender_id, sender_role, message, is_internal_note)
                VALUES (?, 0, 'admin', ?, 1)
            ");
            $message = "This ticket has been automatically closed due to {$auto_close_days} days of inactivity. Please open a new ticket if you still need assistance.";
            $reply_stmt->execute([$ticket['id'], $message]);
            
            // Log the action
            $log_stmt = $pdo->prepare("
                INSERT INTO admin_extended_log
                (admin_id, action_type, module, item_id, item_type, details, ip_address, user_agent)
                VALUES (0, 'update', 'Support', ?, 'ticket', ?, 'cron', 'system')
            ");
            $details = "Ticket automatically closed after {$auto_close_days} days of inactivity: " . $ticket['subject'];
            $log_stmt->execute([$ticket['id'], $details]);
            
            $closed_count++;
        }
        
        return [
            'success' => true,
            'closed_count' => $closed_count
        ];
    } catch (Exception $e) {
        error_log("Error in auto_close_old_tickets: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Execute email retry
$retry_result = retry_failed_emails();
$ticket_result = auto_close_old_tickets();

// Output result for CRON log
echo "Email retry processed: " . $retry_result['processed'] . " emails\n";
echo "Successful retries: " . $retry_result['success_count'] . "\n";
echo "Failed retries: " . $retry_result['fail_count'] . "\n";
echo "\n";
echo "Auto-closed tickets: " . $ticket_result['closed_count'] . "\n";