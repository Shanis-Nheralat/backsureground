<?php
/**
 * Client Task History
 * 
 * Displays list of tasks submitted by client
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/tasks/task-functions.php';

// Ensure client is logged in and has the client role
require_admin_auth();
require_admin_role(['client']);

// Current user info
$client_id = $_SESSION['admin_user_id'];

// Page variables
$page_title = 'My Tasks';
$current_page = 'task_history';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'My Tasks', 'url' => '#']
];

// Get tasks for this client
$tasks = get_client_tasks($client_id);

// Status badge classes
$status_badges = [
    'submitted' => 'badge bg-info',
    'in_progress' => 'badge bg-warning',
    'completed' => 'badge bg-success',
    'cancelled' => 'badge bg-danger'
];

// Priority badge classes
$priority_badges = [
    'low' => 'badge bg-secondary',
    'medium' => 'badge bg-primary',
    'high' => 'badge bg-danger'
];

// Include template parts
include '../../shared/templates/client-head.php';
include '../../shared/templates/client-sidebar.php';
include '../../shared/templates/client-header.php';
?>

<main class="client-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <div>
                <a href="submit-task.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Submit New Task</a>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Task History</h6>
            </div>
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                    <div class="alert alert-info">
                        You haven't submitted any tasks yet. <a href="submit-task.php">Submit your first task</a>.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Task ID</th>
                                    <th>Title</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Deadline</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <tr>
                                        <td>#<?php echo $task['id']; ?></td>
                                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                                        <td>
                                            <span class="<?php echo $priority_badges[$task['priority']]; ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="<?php echo $status_badges[$task['status']]; ?>">
                                                <?php echo ucfirst($task['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($task['submitted_at'])); ?></td>
                                        <td>
                                            <?php 
                                            echo !empty($task['deadline']) 
                                                ? date('M d, Y', strtotime($task['deadline'])) 
                                                : 'N/A'; 
                                            ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-info view-task-btn" 
                                                    data-bs-toggle="modal" data-bs-target="#taskModal" 
                                                    data-task-id="<?php echo $task['id']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
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

<!-- Task Modal -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalLabel">Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="task-loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="task-details" class="d-none">
                    <div class="mb-3">
                        <h5>Title</h5>
                        <p id="task-title"></p>
                    </div>
                    <div class="mb-3">
                        <h5>Description</h5>
                        <p id="task-description"></p>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Status</h5>
                            <p id="task-status"></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Priority</h5>
                            <p id="task-priority"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Submitted On</h5>
                            <p id="task-submitted"></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Deadline</h5>
                            <p id="task-deadline"></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h5>Your Attachments</h5>
                        <div id="client-files">
                            <p class="text-muted">No files attached</p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h5>Completed Files</h5>
                        <div id="admin-files">
                            <p class="text-muted">No files available yet</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- AJAX script to fetch task details -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const taskModal = document.getElementById('taskModal');
    const viewButtons = document.querySelectorAll('.view-task-btn');
    
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const taskId = this.getAttribute('data-task-id');
            
            // Show loading
            document.getElementById('task-loading').classList.remove('d-none');
            document.getElementById('task-details').classList.add('d-none');
            
            // Fetch task details via AJAX
            fetch('get-task-details.php?task_id=' + taskId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const task = data.task;
                        
                        // Populate task details
                        document.getElementById('task-title').textContent = task.title;
                        document.getElementById('task-description').textContent = task.description;
                        
                        // Status with badge
                        const statusElement = document.getElementById('task-status');
                        const statusClass = {
                            'submitted': 'badge bg-info',
                            'in_progress': 'badge bg-warning',
                            'completed': 'badge bg-success',
                            'cancelled': 'badge bg-danger'
                        };
                        statusElement.innerHTML = `<span class="${statusClass[task.status]}">${task.status.charAt(0).toUpperCase() + task.status.slice(1)}</span>`;
                        
                        // Priority with badge
                        const priorityElement = document.getElementById('task-priority');
                        const priorityClass = {
                            'low': 'badge bg-secondary',
                            'medium': 'badge bg-primary',
                            'high': 'badge bg-danger'
                        };
                        priorityElement.innerHTML = `<span class="${priorityClass[task.priority]}">${task.priority.charAt(0).toUpperCase() + task.priority.slice(1)}</span>`;
                        
                        // Dates
                        document.getElementById('task-submitted').textContent = new Date(task.submitted_at).toLocaleDateString();
                        document.getElementById('task-deadline').textContent = task.deadline ? new Date(task.deadline).toLocaleDateString() : 'N/A';
                        
                        // Client files
                        const clientFilesElement = document.getElementById('client-files');
                        if (data.client_files && data.client_files.length > 0) {
                            let filesHtml = '<ul class="list-group">';
                            data.client_files.forEach(file => {
                                filesHtml += `
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-file me-2"></i> ${file.file_name}</span>
                                        <a href="../../shared/download-file.php?file_id=${file.id}" class="btn btn-sm btn-primary">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </li>
                                `;
                            });
                            filesHtml += '</ul>';
                            clientFilesElement.innerHTML = filesHtml;
                        } else {
                            clientFilesElement.innerHTML = '<p class="text-muted">No files attached</p>';
                        }
                        
                        // Admin files
                        const adminFilesElement = document.getElementById('admin-files');
                        if (data.admin_files && data.admin_files.length > 0) {
                            let filesHtml = '<ul class="list-group">';
                            data.admin_files.forEach(file => {
                                filesHtml += `
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-file me-2"></i> ${file.file_name}</span>
                                        <a href="../../shared/download-file.php?file_id=${file.id}" class="btn btn-sm btn-success">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </li>
                                `;
                            });
                            filesHtml += '</ul>';
                            adminFilesElement.innerHTML = filesHtml;
                        } else {
                            adminFilesElement.innerHTML = '<p class="text-muted">No files available yet</p>';
                        }
                        
                        // Hide loading, show details
                        document.getElementById('task-loading').classList.add('d-none');
                        document.getElementById('task-details').classList.remove('d-none');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while fetching task details.');
                });
        });
    });
});
</script>

<?php include '../../shared/templates/client-footer.php'; ?>