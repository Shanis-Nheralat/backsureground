<?php
/**
 * Client Dashboard
 * 
 * Main dashboard for clients showing tasks, plans, and support status.
 */

// Include required files
require_once '../shared/db.php';
require_once '../shared/auth/admin-auth.php';
require_once '../shared/csrf/csrf-functions.php';
require_once '../shared/tasks/task-functions.php'; // <- New line added

// Ensure user is authenticated and has client role
require_role('client');

// Set page variables
$page_title = 'Client Dashboard';
$active_page = 'dashboard';

// Get user's data
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// This is placeholder data for Phase 1
$recent_tasks = [];
$plan_details = [];
$support_tickets = [];

// Get task summary for widget (new addition)
$task_summary = get_dashboard_task_summary('client');

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
                <p class="card-text">This is your client dashboard where you can manage tasks, view your plan details, and get support.</p>
                <p class="text-muted">Last login: <?php echo (isset($_SESSION['last_login'])) ? date('M d, Y H:i', strtotime($_SESSION['last_login'])) : 'First login'; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- New Task Summary and Recent Activity Section -->
<div class="row">
    <!-- Task Summary Widget -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">My Tasks</h6>
                <a href="/client/tasks/my-tasks.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $task_summary['pending']['submitted']; ?></h1>
                                <p class="text-muted mb-0">Submitted</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h1 class="display-4"><?php echo $task_summary['pending']['in_progress']; ?></h1>
                                <p class="text-muted mb-0">In Progress</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($task_summary['pending']['total'] > 0): ?>
                    <div class="alert alert-info mb-0">
                        <p class="mb-1">Task Summary:</p>
                        <ul class="mb-0">
                            <?php if ($task_summary['pending']['high_priority'] > 0): ?>
                            <li><?php echo $task_summary['pending']['high_priority']; ?> high priority task(s)</li>
                            <?php endif; ?>
                            <?php if ($task_summary['pending']['overdue'] > 0): ?>
                            <li><?php echo $task_summary['pending']['overdue']; ?> overdue task(s)</li>
                            <?php endif; ?>
                            <?php if ($task_summary['pending']['due_soon'] > 0): ?>
                            <li><?php echo $task_summary['pending']['due_soon']; ?> task(s) due soon</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success mb-0">
                        <p class="mb-0">You have no pending tasks.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent">
                <a href="/client/tasks/create-task.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-circle me-1"></i> Submit New Task
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Activity Widget -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-success">
                        <i class="bi bi-check text-white"></i>
                    </div>
                    <div class="ms-3">
                        <div>
                            <span class="font-weight-bold"><?php echo $task_summary['completed']; ?> tasks completed</span> in the last 7 days
                        </div>
                        <div class="small text-muted">View your task history for more details</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rest of original dashboard remains unchanged -->
<div class="row">
    <!-- Quick Actions -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow dashboard-card h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/client/tasks/submit-task.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i> Submit New Task
                    </a>
                    <a href="/client/support/new-ticket.php" class="btn btn-info text-white">
                        <i class="bi bi-headset me-2"></i> Open Support Ticket
                    </a>
                    <a href="/client/uploads/" class="btn btn-success">
                        <i class="bi bi-cloud-upload me-2"></i> Upload Files
                    </a>
                    <a href="/client/plans/" class="btn btn-secondary">
                        <i class="bi bi-file-earmark-text me-2"></i> View My Plan
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tasks -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow dashboard-card h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Recent Tasks</h6>
                <a href="/client/tasks/" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_tasks)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">
                                        <div class="py-5">
                                            <i class="bi bi-info-circle display-4 d-block mb-3 text-muted"></i>
                                            <p class="mb-0">You don't have any tasks yet.</p>
                                            <p class="text-muted">Click "Submit New Task" to get started.</p>
                                            <div class="mt-3">
                                                <a href="/client/tasks/submit-task.php" class="btn btn-primary">
                                                    <i class="bi bi-plus-circle me-2"></i> Submit New Task
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_tasks as $task): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($task['status'] === 'completed') ? 'success' : (($task['status'] === 'in_progress') ? 'warning' : 'info'); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($task['created_at'])); ?></td>
                                        <td>
                                            <a href="/client/tasks/view.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info text-white">
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
    <!-- Plan Summary -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow dashboard-card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Plan Summary</h6>
                <a href="/client/plans/" class="btn btn-sm btn-primary">Details</a>
            </div>
            <div class="card-body">
                <?php if (empty($plan_details)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-clipboard-check display-4 d-block mb-3 text-muted"></i>
                        <p class="text-muted">Your plan details will appear here.</p>
                        <p class="small text-muted">This feature will be enabled in a future update.</p>
                    </div>
                <?php else: ?>
                    <!-- Plan details will go here in Phase 6 -->
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Support Tickets -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow dashboard-card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Support Tickets</h6>
                <a href="/client/support/" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($support_tickets)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-headset display-4 d-block mb-3 text-muted"></i>
                        <p class="text-muted">You don't have any support tickets yet.</p>
                        <div class="mt-3">
                            <a href="/client/support/new-ticket.php" class="btn btn-info text-white">
                                <i class="bi bi-plus-circle me-2"></i> Open Support Ticket
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Support tickets will go here in Phase 9 -->
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once '../shared/templates/admin-footer.php'; ?>
