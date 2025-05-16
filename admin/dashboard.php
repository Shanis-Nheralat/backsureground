<?php
/**
 * Admin Dashboard
 * 
 * Main dashboard for administrators showing system overview.
 */

// Include required files
require_once '../shared/db.php';
require_once '../shared/auth/admin-auth.php';
require_once '../shared/csrf/csrf-functions.php';
// Include task functions
require_once '../shared/tasks/task-functions.php';
// Include new dashboard widgets functionality
require_once '../shared/utils/dashboard-widgets.php';
// Include activity logger for extended logging
require_once '../shared/utils/activity-logger.php';

// Ensure user is authenticated and has admin role
require_role('admin');

// Set page variables
$page_title = 'Admin Dashboard';
$active_page = 'dashboard';

// Get dashboard data
// Total users by role
$total_users = db_query_value("SELECT COUNT(*) FROM users WHERE status = 'active'");
$total_clients = db_query_value("SELECT COUNT(*) FROM users WHERE role = 'client' AND status = 'active'");
$total_employees = db_query_value("SELECT COUNT(*) FROM users WHERE role = 'employee' AND status = 'active'");

// Get task summary for dashboard
$task_summary = get_dashboard_task_summary('admin');

// Recent logins (last 5)
$recent_logins = db_query(
    "SELECT u.username, u.name, u.role, u.last_login 
     FROM users u 
     WHERE u.last_login IS NOT NULL 
     ORDER BY u.last_login DESC 
     LIMIT 5"
);

// Latest activity logs (last 10)
$activity_logs = db_query(
    "SELECT * FROM admin_activity_log 
     ORDER BY created_at DESC 
     LIMIT 10"
);

// Get admin's widgets
$widgets = get_user_widgets($_SESSION['user_id'], $_SESSION['role']);

// If no widgets exist, create default ones
if (empty($widgets)) {
    create_widget($_SESSION['user_id'], $_SESSION['role'], 'ticket_summary', ['show_closed' => true, 'days_range' => 30], 1);
    create_widget($_SESSION['user_id'], $_SESSION['role'], 'task_status', ['show_completed' => true, 'days_range' => 30], 2);
    create_widget($_SESSION['user_id'], $_SESSION['role'], 'recent_activity', ['limit' => 10, 'include_logins' => false], 3);
    
    // Refresh the widgets list
    $widgets = get_user_widgets($_SESSION['user_id'], $_SESSION['role']);
}

// Process dashboard settings form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_dashboard_settings') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Invalid form submission. Please try again.');
    } else {
        // Process widget updates
        $enabled_widgets = $_POST['widgets'] ?? [];
        
        // Update widget settings
        if (isset($_POST['ticket_summary'])) {
            $widget_id = array_search('ticket_summary', array_column($widgets, 'widget_type'));
            if ($widget_id !== false) {
                update_widget_settings($widgets[$widget_id]['id'], $_POST['ticket_summary']);
            }
        }
        
        if (isset($_POST['task_status'])) {
            $widget_id = array_search('task_status', array_column($widgets, 'widget_type'));
            if ($widget_id !== false) {
                update_widget_settings($widgets[$widget_id]['id'], $_POST['task_status']);
            }
        }
        
        if (isset($_POST['recent_activity'])) {
            $widget_id = array_search('recent_activity', array_column($widgets, 'widget_type'));
            if ($widget_id !== false) {
                update_widget_settings($widgets[$widget_id]['id'], $_POST['recent_activity']);
            }
        }
        
        // Enable/disable widgets
        foreach ($widgets as $widget) {
            $is_active = in_array($widget['widget_type'], $enabled_widgets) ? 1 : 0;
            toggle_widget_active($widget['id'], $is_active);
        }
        
        // Log the action
        log_admin_extended_action(
            $_SESSION['user_id'], 
            'settings_change', 
            'Dashboard', 
            null, 
            'widgets', 
            null, 
            null, 
            'Updated dashboard widget settings'
        );
        
        set_flash_message('success', 'Dashboard settings updated successfully.');
        
        // Redirect to refresh the page and prevent form resubmission
        header('Location: dashboard.php');
        exit;
    }
}

// Include header template
include_once '../shared/templates/admin-header.php';
?>

<!-- Dashboard Content -->
<div class="row">
    <!-- Stats Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 dashboard-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-people fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 dashboard-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Active Clients</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_clients; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-person-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2 dashboard-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Active Employees</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_employees; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-person-badge fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2 dashboard-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Pending Tasks</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $task_summary['pending']['total']; ?></div>
                        <?php if ($task_summary['pending']['total'] > 0): ?>
                        <div class="mt-2">
                            <?php if ($task_summary['pending']['high_priority'] > 0): ?>
                            <span class="badge bg-danger me-2">
                                <?php echo $task_summary['pending']['high_priority']; ?> High
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($task_summary['pending']['overdue'] > 0): ?>
                            <span class="badge bg-warning me-2">
                                <?php echo $task_summary['pending']['overdue']; ?> Overdue
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-list-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Controls -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow dashboard-card">
            <div class="card-body d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Dashboard</h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#dashboardSettingsModal">
                        <i class="bi bi-gear me-1"></i> Dashboard Settings
                    </button>
                    <a href="#" class="btn btn-sm btn-primary" id="refreshDashboard">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow dashboard-card">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="/admin/clients/" class="btn btn-primary btn-block d-flex align-items-center justify-content-center">
                            <i class="bi bi-person-plus me-2"></i> Add New Client
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="/admin/employees/" class="btn btn-success btn-block d-flex align-items-center justify-content-center">
                            <i class="bi bi-person-plus-fill me-2"></i> Add New Employee
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="/admin/tasks/" class="btn btn-info btn-block d-flex align-items-center justify-content-center text-white">
                            <i class="bi bi-plus-square me-2"></i> Create New Task
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="/admin/support/" class="btn btn-warning btn-block d-flex align-items-center justify-content-center">
                            <i class="bi bi-headset me-2"></i> View Support Tickets
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Widgets -->
<div class="row">
    <?php 
    foreach ($widgets as $widget): 
        if ($widget['is_active']):
    ?>
    <div class="col-lg-6 mb-4">
        <?php echo render_widget($widget['widget_type'], json_decode($widget['settings'], true) ?? []); ?>
    </div>
    <?php 
        endif;
    endforeach; 
    ?>
</div>

<!-- Task Overview -->
<?php if ($task_summary['pending']['total'] > 0 || $task_summary['completed'] > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow dashboard-card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Task Overview</h6>
                <a href="/admin/tasks/manage-tasks.php" class="btn btn-sm btn-primary">
                    Manage Tasks
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Task Status -->
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Current Task Status</h6>
                        <div class="row mb-3">
                            <div class="col-6 text-center">
                                <div class="px-3 py-2 bg-light rounded">
                                    <h4><?php echo $task_summary['pending']['submitted']; ?></h4>
                                    <div class="small text-muted">Submitted</div>
                                </div>
                            </div>
                            <div class="col-6 text-center">
                                <div class="px-3 py-2 bg-light rounded">
                                    <h4><?php echo $task_summary['pending']['in_progress']; ?></h4>
                                    <div class="small text-muted">In Progress</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Task Completion -->
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Completed Tasks</h6>
                        <div class="d-flex align-items-center">
                            <div class="px-3 py-2 bg-success text-white rounded">
                                <h4 class="mb-0"><?php echo $task_summary['completed']; ?></h4>
                            </div>
                            <div class="ms-3">
                                <span class="d-block">Tasks completed in the last 7 days</span>
                                <a href="/admin/tasks/manage-tasks.php?status=completed" class="small">View completed tasks</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Activity & Logins -->
<div class="row">
    <!-- Recent Logins -->
    <div class="col-lg-6">
        <div class="card shadow mb-4 dashboard-card">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Recent Logins</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_logins)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No login data available yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_logins as $login): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($login['name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($login['role'] === 'admin') ? 'danger' : 
                                                     (($login['role'] === 'client') ? 'primary' : 'success'); 
                                            ?>">
                                                <?php echo ucfirst(htmlspecialchars($login['role'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($login['last_login'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Activity Log -->
    <div class="col-lg-6">
        <div class="card shadow mb-4 dashboard-card">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Recent Activity</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activity_logs)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No activity data available yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activity_logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                                        <td><?php echo htmlspecialchars($log['action_type']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($log['timestamp'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Settings Modal -->
<div class="modal fade" id="dashboardSettingsModal" tabindex="-1" aria-labelledby="dashboardSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dashboardSettingsModalLabel">Dashboard Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="dashboardSettingsForm" method="post" action="dashboard.php">
                    <input type="hidden" name="action" value="update_dashboard_settings">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <h6>Available Widgets</h6>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="widget_ticket_summary" name="widgets[]" value="ticket_summary" 
                                    <?php echo in_array('ticket_summary', array_column($widgets, 'widget_type')) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="widget_ticket_summary">
                                    Support Tickets Summary
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="widget_task_status" name="widgets[]" value="task_status"
                                    <?php echo in_array('task_status', array_column($widgets, 'widget_type')) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="widget_task_status">
                                    Task Status Summary
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="widget_recent_activity" name="widgets[]" value="recent_activity"
                                    <?php echo in_array('recent_activity', array_column($widgets, 'widget_type')) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="widget_recent_activity">
                                    Recent Activity
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <h6>Widget Settings</h6>
                    
                    <!-- Ticket Summary Settings -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Support Tickets Summary</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="ticket_days_range" class="form-label">Days Range</label>
                                        <select class="form-select" id="ticket_days_range" name="ticket_summary[days_range]">
                                            <?php 
                                            $ticket_widget = array_filter($widgets, function($w) { return $w['widget_type'] === 'ticket_summary'; });
                                            $ticket_widget = reset($ticket_widget);
                                            $ticket_settings = $ticket_widget ? json_decode($ticket_widget['settings'], true) : [];
                                            $days_range = $ticket_settings['days_range'] ?? 30;
                                            ?>
                                            <option value="7" <?php echo $days_range == 7 ? 'selected' : ''; ?>>Last 7 days</option>
                                            <option value="30" <?php echo $days_range == 30 ? 'selected' : ''; ?>>Last 30 days</option>
                                            <option value="90" <?php echo $days_range == 90 ? 'selected' : ''; ?>>Last 90 days</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check form-switch mt-4">
                                            <input class="form-check-input" type="checkbox" id="ticket_show_closed" name="ticket_summary[show_closed]" value="1"
                                                <?php echo (isset($ticket_settings['show_closed']) && $ticket_settings['show_closed']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="ticket_show_closed">Show Closed Tickets</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Task Status Settings -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Task Status Summary</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="task_days_range" class="form-label">Days Range</label>
                                        <select class="form-select" id="task_days_range" name="task_status[days_range]">
                                            <?php 
                                            $task_widget = array_filter($widgets, function($w) { return $w['widget_type'] === 'task_status'; });
                                            $task_widget = reset($task_widget);
                                            $task_settings = $task_widget ? json_decode($task_widget['settings'], true) : [];
                                            $days_range = $task_settings['days_range'] ?? 30;
                                            ?>
                                            <option value="7" <?php echo $days_range == 7 ? 'selected' : ''; ?>>Last 7 days</option>
                                            <option value="30" <?php echo $days_range == 30 ? 'selected' : ''; ?>>Last 30 days</option>
                                            <option value="90" <?php echo $days_range == 90 ? 'selected' : ''; ?>>Last 90 days</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check form-switch mt-4">
                                            <input class="form-check-input" type="checkbox" id="task_show_completed" name="task_status[show_completed]" value="1"
                                                <?php echo (isset($task_settings['show_completed']) && $task_settings['show_completed']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="task_show_completed">Show Completed Tasks</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity Settings -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Recent Activity</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="activity_limit" class="form-label">Number of Activities</label>
                                        <select class="form-select" id="activity_limit" name="recent_activity[limit]">
                                            <?php 
                                            $activity_widget = array_filter($widgets, function($w) { return $w['widget_type'] === 'recent_activity'; });
                                            $activity_widget = reset($activity_widget);
                                            $activity_settings = $activity_widget ? json_decode($activity_widget['settings'], true) : [];
                                            $limit = $activity_settings['limit'] ?? 10;
                                            ?>
                                            <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5 items</option>
                                            <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10 items</option>
                                            <option value="15" <?php echo $limit == 15 ? 'selected' : ''; ?>>15 items</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check form-switch mt-4">
                                            <input class="form-check-input" type="checkbox" id="activity_include_logins" name="recent_activity[include_logins]" value="1"
                                                <?php echo (isset($activity_settings['include_logins']) && $activity_settings['include_logins']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="activity_include_logins">Include Login/Logout Events</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="dashboardSettingsForm" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh dashboard button
    document.getElementById('refreshDashboard').addEventListener('click', function(e) {
        e.preventDefault();
        window.location.reload();
    });
    
    // Include Chart.js for widgets if not already included
    if (typeof Chart === 'undefined') {
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js';
        script.async = true;
        document.head.appendChild(script);
    }
});
</script>

<?php 
// Include footer template
include_once '../shared/templates/admin-footer.php';
?>
