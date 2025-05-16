<?php
// /admin/system/backup-manager.php
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/settings-functions.php';

// Require admin authentication
require_admin_auth();
require_admin_role(['admin']);

// Page variables
$page_title = 'Backup Manager';
$current_page = 'backup_manager';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/admin/dashboard.php'],
    ['title' => 'System', 'url' => '#'],
    ['title' => 'Backup Manager', 'url' => '#']
];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
    } else {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_backup':
                    // Run the backup script
                    include_once '../../cron/daily-backup.php';
                    $backup_result = generate_database_backup();
                    
                    if ($backup_result['success']) {
                        set_notification('success', 'Backup created successfully: ' . $backup_result['file']);
                    } else {
                        set_notification('error', 'Backup failed: ' . $backup_result['error']);
                    }
                    break;
                    
                case 'update_settings':
                    // Update backup settings
                    $retention_days = intval($_POST['retention_days']);
                    if ($retention_days >= 1 && $retention_days <= 365) {
                        set_setting('backup_retention_days', $retention_days);
                        set_notification('success', 'Backup settings updated successfully.');
                    } else {
                        set_notification('error', 'Invalid retention days. Please enter a value between 1 and 365.');
                    }
                    break;
                    
                case 'delete_backup':
                    // Delete a backup file
                    if (isset($_POST['backup_file'])) {
                        $file = $_POST['backup_file'];
                        
                        // Security check - ensure the filename is a valid backup file
                        if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.zip$/', $file)) {
                            $backup_dir = dirname(dirname(__DIR__)) . '/backups';
                            $backup_file = $backup_dir . '/' . $file;
                            
                            if (file_exists($backup_file) && is_file($backup_file)) {
                                if (unlink($backup_file)) {
                                    set_notification('success', 'Backup file deleted successfully.');
                                } else {
                                    set_notification('error', 'Failed to delete backup file.');
                                }
                            } else {
                                set_notification('error', 'Backup file not found.');
                            }
                        } else {
                            set_notification('error', 'Invalid backup filename.');
                        }
                    }
                    break;
            }
        }
    }
    
    // Redirect to avoid form resubmission
    header('Location: backup-manager.php');
    exit;
}

// Get backup settings
$retention_days = intval(get_setting('backup_retention_days', 30));

// Get list of backup files
$backup_dir = dirname(dirname(__DIR__)) . '/backups';
$backup_files = [];

if (is_dir($backup_dir)) {
    $files = glob($backup_dir . '/backup_*.zip');
    
    foreach ($files as $file) {
        $backup_files[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file)
        ];
    }
    
    // Sort by date (newest first)
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Include template parts
include '../../shared/templates/admin-head.php';
include '../../shared/templates/admin-sidebar.php';
include '../../shared/templates/admin-header.php';
?>

<main class="admin-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            
            <div>
                <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-database me-1"></i> Create Backup Now
                    </button>
                </form>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <!-- Backup Settings -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Backup Settings</h6>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="retention_days" class="form-label">Backup Retention (Days)</label>
                                <input type="number" class="form-control" id="retention_days" name="retention_days" 
                                    value="<?php echo $retention_days; ?>" min="1" max="365" required>
                                <div class="form-text">Backups older than this will be automatically deleted.</div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
        
        <!-- Backup Files -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Backup Files</h6>
                <span class="badge bg-primary"><?php echo count($backup_files); ?> Files</span>
            </div>
            <div class="card-body">
                <?php if (empty($backup_files)): ?>
                <div class="alert alert-info">No backup files found. Use the "Create Backup Now" button to create your first backup.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Date</th>
                                <th>Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backup_files as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['name']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', $file['date']); ?></td>
                                <td><?php echo format_file_size($file['size']); ?></td>
                                <td>
                                    <a href="/admin/system/download-backup.php?file=<?php echo urlencode($file['name']); ?>&token=<?php echo generate_download_token($file['name']); ?>" 
                                       class="btn btn-sm btn-primary me-2">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    
                                    <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this backup file?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="delete_backup">
                                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($file['name']); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../../shared/templates/admin-footer.php'; ?>

<?php
/**
 * Format file size in human-readable format
 */
function format_file_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Generate a secure download token
 */
function generate_download_token($filename) {
    $secret = get_setting('download_token_key', '');
    if (empty($secret)) {
        $secret = bin2hex(random_bytes(32));
        set_setting('download_token_key', $secret);
    }
    
    $timestamp = time();
    return $timestamp . '|' . hash_hmac('sha256', $filename . '|' . $timestamp, $secret);
}
?>