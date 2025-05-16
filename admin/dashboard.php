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

// Include task functions (make sure the path is correct)
require_once '../shared/tasks/task-functions.php';

// Get task summary for dashboard
$task_summary = get_dashboard_task_summary('admin');

// Include task functions (make sure the path is correct)
require_once '../shared/tasks/task-functions.php';

// Ensure user is authenticated and has admin role
require_role('admin');

// Set page variables
$page_title = 'Admin Dashboard';
$active_page = 'dashboard';

// Get dashboard data (placeholder for Phase 1)
// In future phases, this will pull real data from database

// Total users by role
$total_users = db_query_value("SELECT COUNT(*) FROM users WHERE status = 'active'");
$total_clients = db_query_value("SELECT COUNT(*) FROM users WHERE role = 'client' AND status = 'active'");
$total_employees = db_query_value("SELECT COUNT(*) FROM users WHERE role = 'employee' AND status = 'active'");

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

// Get task summary for dashboard
$task_summary = get_dashboard_task_summary('admin');

// Include header template
include_once '../shared/templates/admin-header.php';
?>

<!-- Dashboard Content -->

<!-- Task Summary Section -->
<div class="row">
    <!-- Task Summary Widget -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Pending Tasks</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo \$task_summary['pending']['total']; ?>
                        </div>
                        <div class="mt-2">
                            <?php if (\$task_summary['pending']['high_priority'] > 0): ?>
                            <span class="badge bg-danger me-2">
                                <?php echo \$task_summary['pending']['high_priority']; ?> High Priority
                            </span>
                            <?php endif; ?>
                            <?php if (\$task_summary['pending']['overdue'] > 0): ?>
                            <span class="badge bg-warning me-2">
                                <?php echo \$task_summary['pending']['overdue']; ?> Overdue
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-list-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="/admin/tasks/manage-tasks.php" class="text-primary">View All Tasks</a>
            </div>
        </div>
    </div>

    <!-- Task Status Distribution -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                    Task Status</div>
                <div class="row mt-3">
                    <div class="col-6 text-center">
                        <h5><?php echo \$task_summary['pending']['submitted']; ?></h5>
                        <small class="text-muted">Submitted</small>
                    </div>
                    <div class="col-6 text-center">
                        <h5><?php echo \$task_summary['pending']['in_progress']; ?></h5>
                        <small class="text-muted">In Progress</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Completed Tasks -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Completed Tasks (Last 7 Days)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo \$task_summary['completed']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="/admin/tasks/manage-tasks.php?status=completed" class="text-info">View Completed Tasks</a>
            </div>
        </div>
    </div>
</div>
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
                        <div class="h5 mb-0 font-weight-bold text-gray-800">0</div>
                        <div class="small text-muted">Coming in Phase 4</div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-list-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Task Summary Widgets -->
<div class="row">
    <!-- Task Summary Widget -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Pending Tasks</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $task_summary['pending']['total']; ?>
                        </div>
                        <div class="mt-2">
                            <?php if ($task_summary['pending']['high_priority'] > 0): ?>
                            <span class="badge bg-danger me-2">
                                <?php echo $task_summary['pending']['high_priority']; ?> High Priority
                            </span>
                            <?php endif; ?>
                            <?php if ($task_summary['pending']['overdue'] > 0): ?>
                            <span class="badge bg-warning me-2">
                                <?php echo $task_summary['pending']['overdue']; ?> Overdue
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-list-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="/admin/tasks/manage-tasks.php" class="text-primary">View All Tasks</a>
            </div>
        </div>
    </div>

    <!-- Task Status Distribution -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                    Task Status</div>
                <div class="row mt-3">
                    <div class="col-6 text-center">
                        <h5><?php echo $task_summary['pending']['submitted']; ?></h5>
                        <small class="text-muted">Submitted</small>
                    </div>
                    <div class="col-6 text-center">
                        <h5><?php echo $task_summary['pending']['in_progress']; ?></h5>
                        <small class="text-muted">In Progress</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Completed Tasks -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Completed Tasks (Last 7 Days)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $task_summary['completed']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="/admin/tasks/manage-tasks.php?status=completed" class="text-info">View Completed Tasks</a>
            </div>
        </div>
    </div>
</div>
