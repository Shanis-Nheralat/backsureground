<?php
// /cron/daily-backup.php
require_once '../shared/db.php';
require_once '../shared/settings-functions.php';

// Set execution time limit for large databases
set_time_limit(300);

/**
 * Generate and store a database backup
 * For cPanel environments with limited privileges
 */
function generate_database_backup() {
    global $pdo;
    
    try {
        // Get database credentials from db.php
        $db_name = $GLOBALS['db_name'] ?? 'backsure_admin';
        $db_user = $GLOBALS['db_user'] ?? '';
        $db_pass = $GLOBALS['db_pass'] ?? '';
        $db_host = $GLOBALS['db_host'] ?? 'localhost';
        
        // Build backup file path
        $backup_dir = dirname(__DIR__) . '/backups';
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $date_suffix = date('Y-m-d_His');
        $backup_file = $backup_dir . "/backup_{$date_suffix}.sql";
        
        // Create the mysqldump command
        // This approach uses PHP's ability to execute shell commands
        // It should work on most cPanel environments
        $command = "mysqldump --opt --host={$db_host} --user={$db_user} --password={$db_pass} {$db_name} > {$backup_file}";
        
        // Execute the command
        exec($command, $output, $return_var);
        
        if ($return_var !== 0) {
            throw new Exception("mysqldump returned error code: $return_var");
        }
        
        // Compress the backup file
        $zip_file = $backup_file . ".zip";
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($backup_file, basename($backup_file));
            $zip->close();
            
            // Remove the uncompressed SQL file
            unlink($backup_file);
            
            // Clean up old backups
            cleanup_old_backups();
            
            // Log success
            log_backup_event('success', "Database backup created: " . basename($zip_file));
            
            return [
                'success' => true,
                'file' => basename($zip_file),
                'size' => filesize($zip_file),
                'path' => $zip_file
            ];
        } else {
            throw new Exception("Failed to create zip archive");
        }
    } catch (Exception $e) {
        // Log error
        log_backup_event('error', "Backup failed: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Clean up old backup files
 */
function cleanup_old_backups() {
    // Get retention days from settings
    $retention_days = intval(get_setting('backup_retention_days', 30));
    
    $backup_dir = dirname(__DIR__) . '/backups';
    $files = glob($backup_dir . "/backup_*.zip");
    
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file)) {
            // Check file age
            $file_time = filemtime($file);
            $age_in_days = ($now - $file_time) / (60 * 60 * 24);
            
            // Delete if older than retention period
            if ($age_in_days > $retention_days) {
                unlink($file);
                log_backup_event('info', "Removed old backup: " . basename($file));
            }
        }
    }
}

/**
 * Log backup events
 */
function log_backup_event($type, $message) {
    $log_file = dirname(__DIR__) . '/logs/backup.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    // Log to admin_extended_log as well
    if ($type === 'error') {
        try {
            global $pdo;
            $stmt = $pdo->prepare("
                INSERT INTO admin_extended_log 
                (admin_id, action_type, module, details, ip_address, user_agent) 
                VALUES (0, 'system', 'Backup', ?, 'cron', 'system')
            ");
            $stmt->execute([$message]);
        } catch (Exception $e) {
            // If logging to database fails, we already have file logging
        }
    }
}

// Execute the backup
$backup_result = generate_database_backup();

// Output result for CRON log
if ($backup_result['success']) {
    echo "Backup successful: " . $backup_result['file'] . " (" . round($backup_result['size'] / 1024 / 1024, 2) . " MB)\n";
} else {
    echo "Backup failed: " . $backup_result['error'] . "\n";
}