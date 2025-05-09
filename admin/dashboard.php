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
     ORDER BY timestamp DESC 
     LIMIT 10"
);

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

<?php 
// Include footer template
include_once '../shared/templates/admin-footer.php';
?>