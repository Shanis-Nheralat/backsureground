<?php
// /admin/system/go-live-checklist.php
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';

// Require admin authentication
require_admin_auth();
require_admin_role(['admin']);

// Page variables
$page_title = 'Go-Live Checklist';
$current_page = 'go_live_checklist';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/admin/dashboard.php'],
    ['title' => 'System', 'url' => '#'],
    ['title' => 'Go-Live Checklist', 'url' => '#']
];

// Checklist items (add more as needed)
$checklist_items = [
    [
        'id' => 'backup',
        'category' => 'Backup',
        'title' => 'Create a full database backup',
        'description' => 'Ensure you have a recent database backup before going live.',
        'link' => '/admin/system/backup-manager.php',
        'link_text' => 'Backup Manager'
    ],
    [
        'id' => 'system_test',
        'category' => 'Testing',
        'title' => 'Run system tests',
        'description' => 'Ensure all system tests pass successfully.',
        'link' => '/admin/system/system-test.php?run_tests=1',
        'link_text' => 'Run Tests'
    ],
    [
        'id' => 'admin_user',
        'category' => 'Security',
        'title' => 'Change default admin password',
        'description' => 'Ensure the default admin password has been changed.',
        'link' => '/admin/users/edit-user.php?id=1',
        'link_text' => 'Edit Admin User'
    ],
    [
        'id' => 'ssl',
        'category' => 'Security',
        'title' => 'Enable SSL',
        'description' => 'Ensure SSL is enabled and HTTPS is enforced.',
        'link' => '/admin/system/system-test.php?run_tests=1&test=ssl',
        'link_text' => 'Check SSL'
    ],
    [
        'id' => 'htaccess',
        'category' => 'Security',
        'title' => 'Verify .htaccess files',
        'description' => 'Ensure all required .htaccess files are in place.',
        'link' => '/admin/system/system-test.php?run_tests=1&test=htaccess',
        'link_text' => 'Check .htaccess'
    ],
    [
        'id' => 'permissions',
        'category' => 'Security',
        'title' => 'Verify directory permissions',
        'description' => 'Ensure all directories have the correct permissions.',
        'link' => '/admin/system/system-test.php?run_tests=1&test=file_permissions',
        'link_text' => 'Check Permissions'
    ],
    [
        'id' => 'cron',
        'category' => 'Setup',
        'title' => 'Configure CRON jobs',
        'description' => 'Ensure all required CRON jobs are configured.',
        'link' => '#',
        'link_text' => 'CRON Instructions'
    ],
    [
        'id' => 'settings',
        'category' => 'Setup',
        'title' => 'Configure system settings',
        'description' => 'Ensure all system settings are properly configured.',
        'link' => '/admin/settings/admin-settings.php',
        'link_text' => 'System Settings'
    ],
    [
        'id' => 'email',
        'category' => 'Setup',
        'title' => 'Configure email settings',
        'description' => 'Ensure email settings are properly configured and tested.',
        'link' => '/admin/settings/admin-email-settings.php',
        'link_text' => 'Email Settings'
    ],
    [
        'id' => 'test_auth',
        'category' => 'Testing',
        'title' => 'Test user authentication',
        'description' => 'Test login, logout, and password reset functionality.',
        'link' => '/login.php',
        'link_text' => 'Login Page'
    ],
    [
        'id' => 'test_client',
        'category' => 'Testing',
        'title' => 'Test client functionality',
        'description' => 'Test all client features (tasks, tickets, documents).',
        'link' => '/admin/users.php?role=client',
        'link_text' => 'Client Users'
    ],
    [
        'id' => 'test_employee',
        'category' => 'Testing',
        'title' => 'Test employee functionality',
        'description' => 'Test all employee features (assigned tasks, time tracking).',
        'link' => '/admin/users.php?role=employee',
        'link_text' => 'Employee Users'
    ],
    [
        'id' => 'logs',
        'category' => 'Monitoring',
        'title' => 'Check error logs',
        'description' => 'Ensure there are no errors in the logs.',
        'link' => '/admin/logs/error-logs.php',
        'link_text' => 'Error Logs'
    ]
];

// Process completion updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
    } else {
        if (isset($_POST['item_id']) && isset($_POST['completed'])) {
            $item_id = $_POST['item_id'];
            $completed = $_POST['completed'] === '1';
            
            // Update the completion status in user preferences
            $user_id = $_SESSION['user_id'];
            $key = "go_live_checklist_{$item_id}";
            $value = $completed ? 'completed' : 'pending';
            
            // Use settings function if available, or store in session
            if (function_exists('set_user_preference')) {
                set_user_preference($user_id, $key, $value);
            } else {
                if (!isset($_SESSION['user_preferences'])) {
                    $_SESSION['user_preferences'] = [];
                }
                
                $_SESSION['user_preferences'][$key] = $value;
            }
            
            // Return JSON response for AJAX
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    // If we reached here, something went wrong
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Get completion status for each item
foreach ($checklist_items as &$item) {
    $key = "go_live_checklist_{$item['id']}";
    
    if (function_exists('get_user_preference')) {
        $item['completed'] = get_user_preference($_SESSION['user_id'], $key) === 'completed';
    } else {
        $item['completed'] = isset($_SESSION['user_preferences'][$key]) && $_SESSION['user_preferences'][$key] === 'completed';
    }
}

// Group items by category
$grouped_items = [];
foreach ($checklist_items as $item) {
    if (!isset($grouped_items[$item['category']])) {
        $grouped_items[$item['category']] = [];
    }
    
    $grouped_items[$item['category']][] = $item;
}

// Calculate progress
$total_items = count($checklist_items);
$completed_items = count(array_filter($checklist_items, function($item) { return $item['completed']; }));
$progress_percentage = ($total_items > 0) ? round(($completed_items / $total_items) * 100) : 0;

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
                <button id="resetChecklist" class="btn btn-outline-danger">
                    <i class="fas fa-redo me-1"></i> Reset Checklist
                </button>
                <a href="system-test.php?run_tests=1" class="btn btn-primary ms-2">
                    <i class="fas fa-vial me-1"></i> Run All Tests
                </a>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <!-- Progress Bar -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <h5 class="card-title">Launch Readiness: <?php echo $progress_percentage; ?>% Complete</h5>
                <div class="progress mb-2">
                    <div class="progress-bar bg-<?php echo ($progress_percentage < 50) ? 'danger' : (($progress_percentage < 100) ? 'warning' : 'success'); ?>" 
                         role="progressbar" style="width: <?php echo $progress_percentage; ?>%" 
                         aria-valuenow="<?php echo $progress_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php echo $progress_percentage; ?>%
                    </div>
                </div>
                <div class="text-muted small">
                    <?php echo $completed_items; ?> of <?php echo $total_items; ?> items completed
                </div>
            </div>
        </div>
        
        <!-- Checklist Items -->
        <?php foreach ($grouped_items as $category => $items): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold"><?php echo htmlspecialchars($category); ?></h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 50px;"></th>
                                <th>Task</th>
                                <th>Description</th>
                                <th style="width: 150px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input checklist-item" type="checkbox" 
                                            data-item-id="<?php echo $item['id']; ?>" 
                                            id="check_<?php echo $item['id']; ?>" 
                                            <?php echo $item['completed'] ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                                <td><label for="check_<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['title']); ?></label></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td>
                                    <a href="<?php echo $item['link']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-external-link-alt me-1"></i> <?php echo htmlspecialchars($item['link_text']); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Deployment Notes -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">CRON Job Instructions</h6>
            </div>
            <div class="card-body">
                <p>Set up the following CRON jobs in your cPanel:</p>
                
                <pre class="bg-light p-3 rounded">
# Daily database backup - Runs at 2:00 AM every day
0 2 * * * php <?php echo dirname(dirname(dirname(__FILE__))); ?>/cron/daily-backup.php >> <?php echo dirname(dirname(dirname(__FILE__))); ?>/logs/cron.log 2>&1

# Email retry and ticket auto-close - Runs every 6 hours
0 */6 * * * php <?php echo dirname(dirname(dirname(__FILE__))); ?>/cron/email-retry.php >> <?php echo dirname(dirname(dirname(__FILE__))); ?>/logs/cron.log 2>&1
                </pre>
                
                <p class="mt-3">To set up CRON jobs in cPanel:</p>
                <ol>
                    <li>Log in to your cPanel account</li>
                    <li>Navigate to the "Advanced" section and click on "Cron Jobs"</li>
                    <li>Scroll down to "Add New Cron Job"</li>
                    <li>Set the time settings for each job as specified above</li>
                    <li>Copy and paste each command into the "Command" field</li>
                    <li>Click "Add New Cron Job" to save each job</li>
                </ol>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> 
                    Remember to adjust the file paths if your installation is in a different directory.
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle checklist item completion
    document.querySelectorAll('.checklist-item').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const itemId = this.getAttribute('data-item-id');
            const completed = this.checked ? '1' : '0';
            
            // Send AJAX request to update status
            fetch('go-live-checklist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=update_status&item_id=' + encodeURIComponent(itemId) + 
                      '&completed=' + encodeURIComponent(completed) + 
                      '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to update checklist item status');
                    // Revert the checkbox state
                    this.checked = !this.checked;
                }
                
                // Update progress bar
                updateProgressBar();
            })
            .catch(error => {
                console.error('Error:', error);
                // Revert the checkbox state
                this.checked = !this.checked;
            });
        });
    });
    
    // Reset checklist button
    document.getElementById('resetChecklist').addEventListener('click', function() {
        if (confirm('Are you sure you want to reset the entire checklist? This will mark all items as incomplete.')) {
            document.querySelectorAll('.checklist-item').forEach(function(checkbox) {
                checkbox.checked = false;
                
                // Trigger the change event to update the server
                const event = new Event('change');
                checkbox.dispatchEvent(event);
            });
        }
    });
    
    // Function to update progress bar
    function updateProgressBar() {
        const totalItems = <?php echo $total_items; ?>;
        const completedItems = document.querySelectorAll('.checklist-item:checked').length;
        const progressPercentage = Math.round((completedItems / totalItems) * 100);
        
        // Update the progress bar
        const progressBar = document.querySelector('.progress-bar');
        progressBar.style.width = progressPercentage + '%';
        progressBar.setAttribute('aria-valuenow', progressPercentage);
        progressBar.textContent = progressPercentage + '%';
        
        // Update the text
        document.querySelector('.text-muted.small').textContent = completedItems + ' of ' + totalItems + ' items completed';
        
        // Update the header
        document.querySelector('.card-title').textContent = 'Launch Readiness: ' + progressPercentage + '% Complete';
        
        // Update progress bar color
        if (progressPercentage < 50) {
            progressBar.className = 'progress-bar bg-danger';
        } else if (progressPercentage < 100) {
            progressBar.className = 'progress-bar bg-warning';
        } else {
            progressBar.className = 'progress-bar bg-success';
        }
    }
});
</script>

<?php include '../../shared/templates/admin-footer.php'; ?>