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

// Ensure user is authenticated and has client role
require_role('client');

// Set page variables
$page_title = 'Client Dashboard';
$active_page = 'dashboard';

// Get user's data
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// This is placeholder data for Phase 1
// In future phases, this will pull real data from database
$recent_tasks = []; // Will be populated in Phase 4
$plan_details = []; // Will be populated in Phase 6
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
                <p class="card-text">This is your client dashboard where you can manage tasks, view your plan details, and get support.</p>
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
                                            <span class="badge bg-<?php 
                                                echo ($task['status'] === 'completed') ? 'success' : 
                                                     (($task['status'] === 'in_progress') ? 'warning' : 'info'); 
                                            ?>">
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

<?php 
// Include footer template
include_once '../shared/templates/admin-footer.php';
?>