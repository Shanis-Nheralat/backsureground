<?php
/**
 * Employee Dashboard
 * 
 * Main dashboard for employees showing assigned tasks and time tracking.
 */

// Include required files
require_once '../shared/db.php';
require_once '../shared/auth/admin-auth.php';
require_once '../shared/csrf/csrf-functions.php';

// Ensure user is authenticated and has employee role
require_role('employee');

// Set page variables
$page_title = 'Employee Dashboard';
$active_page = 'dashboard';

// Get user's data
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// This is placeholder data for Phase 1
// In future phases, this will pull real data from database
$assigned_tasks = []; // Will be populated in Phase 4
$time_logs = []; // Will be populated in Phase 8
$support_tickets = []; // Will be populated in Phase 9

// Include header template
include_once '../shared/templates/admin-header.php';
?>

<!-- Dashboard Content -->
<div class="row">
    <!-- Welcome Card -->
    <div class="col-12 mb-4">
        <div class="card shadow dashboard-card">
            <div class="card-body">
                <h5 class="card-title">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h5>
                <p class="card-text">This is your employee dashboard where you can manage assigned tasks, track time, and get support.</p>
                <p class="text-muted">Last login: <?php echo (isset($_SESSION['last_login'])) ? date('M d, Y H:i', strtotime($_SESSION['last_login'])) : 'First login'; ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quick Actions -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow dashboard-card h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/employee/assigned-tasks/" class="btn btn-primary">
                        <i class="bi bi-list-task me-2"></i> View My Tasks
                    </a>
                    <a href="/employee/time-tracking/" class="btn btn-success">
                        <i class="bi bi-clock me-2"></i> Time Tracking
                    </a>
                    <a href="/employee/support/new-ticket.php" class="btn btn-info text-white">
                        <i class="bi bi-headset me-2"></i> Request Support
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Assigned Tasks -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow dashboard-card h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Assigned Tasks</h6>
                <a href="/employee/assigned-tasks/" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Client</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assigned_tasks)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <div class="py-5">
                                            <i class="bi bi-info-circle display-4 d-block mb-3 text-muted"></i>
                                            <p class="mb-0">You don't have any assigned tasks yet.</p>
                                            <p class="text-muted">Tasks assigned to you will appear here.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assigned_tasks as $task): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                                        <td><?php echo htmlspecialchars($task['client_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($task['due_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($task['status'] === 'completed') ? 'success' : 
                                                     (($task['status'] === 'in_progress') ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="/employee/assigned-tasks/view.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info text-white">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
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

<div class="row">
    <!-- Time Tracking Summary -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow dashboard-card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Time Tracking</h6>
                <a href="/employee/time-tracking/" class="btn btn-sm btn-primary">Details</a>
            </div>
            <div class="card-body">
                <div class="text-center py-5">
                    <i class="bi bi-clock-history display-4 d-block mb-3 text-muted"></i>
                    <p class="text-muted">Your time tracking summary will appear here.</p>
                    <p class="small text-muted">This feature will be enabled in a future update.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Support Tickets -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow dashboard-card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Support Tickets</h6>
                <a href="/employee/support/" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="text-center py-5">
                    <i class="bi bi-headset display-4 d-block mb-3 text-muted"></i>
                    <p class="text-muted">You don't have any support tickets yet.</p>
                    <div class="mt-3">
                        <a href="/employee/support/new-ticket.php" class="btn btn-info text-white">
                            <i class="bi bi-plus-circle me-2"></i> Request Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Include footer template
include_once '../shared/templates/admin-footer.php';
?>
