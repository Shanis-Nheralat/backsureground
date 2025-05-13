<?php
/**
 * Admin Task Management
 * 
 * Allows admin to view, filter, update, and complete tasks
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/tasks/task-functions.php';
require_once '../../shared/utils/notifications.php';

// Authentication check - will redirect to login if not authenticated
require_admin_auth();

// For pages with specific role requirements
require_admin_role(['admin']);

// Apply filters if provided
$filters = [];

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
    $filters['client_id'] = (int)$_GET['client_id'];
}

if (isset($_GET['priority']) && !empty($_GET['priority'])) {
    $filters['priority'] = $_GET['priority'];
}

// Get all tasks with applied filters
$tasks = get_all_tasks($filters);

// Get all clients for filter dropdown
try {
    $stmt = $pdo->prepare("
        SELECT id, username, name 
        FROM users 
        WHERE role = 'client'
        ORDER BY name
    ");
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Get Clients Error: ' . $e->getMessage());
    $clients = [];
}

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

// Page variables
$page_title = 'Manage Tasks';
$current_page = 'manage_tasks';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Manage Tasks', 'url' => '#']
];

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
        </div>
        
        <?php display_notifications(); ?>
        
        <!-- Filters -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Filters</h6>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="submitted" <?php echo (isset($filters['status']) && $filters['status'] === 'submitted') ? 'selected' : ''; ?>>Submitted</option>
                            <option value="in_progress" <?php echo (isset($filters['status']) && $filters['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo (isset($filters['status']) && $filters['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo (isset($filters['status']) && $filters['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="client_id" class="form-label">Client</label>
                        <select class="form-select" id="client_id" name="client_id">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo (isset($filters['client_id']) && $filters['client_id'] === $client['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <option value="">All Priorities</option>
                            <option value="low" <?php echo (isset($filters['priority']) && $filters['priority'] === 'low') ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo (isset($filters['priority']) && $filters['priority'] === 'medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo (isset($filters['priority']) && $filters['priority'] === 'high') ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                        <a href="manage-tasks.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tasks Table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Task List</h6>
            </div>
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                    <div class="alert alert-info">
                        No tasks found. Try adjusting your filters.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
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
                                        <td><?php echo htmlspecialchars($task['client_name']); ?></td>
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
                                                <i class="fas fa-eye"></i> View/Edit
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
    <div class="modal-dialog modal-xl">
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
                    <form id="task-form" method="post" action="update-task.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="task_id" id="task-id">
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Client Name</label>
                                    <p id="client-name" class="form-control-static"></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Task Title</label>
                                    <p id="task-title" class="form-control-static"></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <div id="task-description" class="bg-light p-3 rounded"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="task-status" class="form-label">Status</label>
                                    <select class="form-select" id="task-status" name="status">
                                        <option value="submitted">Submitted</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <p id="task-priority" class="form-control-static"></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Submitted On</label>
                                    <p id="task-submitted" class="form-control-static"></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Deadline</label>
                                    <p id="task-deadline" class="form-control-static"></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Client Files</label>
                                    <div id="client-files" class="bg-light p-3 rounded">
                                        <p class="text-muted">No files attached</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Admin Files</label>
                                    <div id="admin-files" class="bg-light p-3 rounded">
                                        <p class="text-muted">No files uploaded</p>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <label for="task-upload" class="form-label">Upload Files</label>
                                        <input type="file" class="form-control" id="task-upload" name="task_files[]" multiple>
                                        <small class="text-muted">
                                            Upload completed work or reference files for the client.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin-notes" class="form-label">Admin Notes (Internal Only)</label>
                            <textarea class="form-control" id="admin-notes" name="admin_notes" rows="3"></textarea>
                            <small class="text-muted">These notes are only visible to admins, not to the client.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status-remarks" class="form-label">Status Update Remarks</label>
                            <textarea class="form-control" id="status-remarks" name="remarks" rows="2"></textarea>
                            <small class="text-muted">Internal record of why the status was changed.</small>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="task-form" class="btn btn-primary">Save Changes</button>
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
            fetch('get-admin-task-details.php?task_id=' + taskId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const task = data.task;
                        
                        // Set form task ID
                        document.getElementById('task-id').value = task.id;
                        
                        // Populate task details
                        document.getElementById('client-name').textContent = data.client_name;
                        document.getElementById('task-title').textContent = task.title;
                        document.getElementById('task-description').textContent = task.description;
                        
                        // Set status dropdown
                        document.getElementById('task-status').value = task.status;
                        
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
                        
                        // Admin notes
                        document.getElementById('admin-notes').value = task.admin_notes || '';
                        
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
                                        <div>
                                            <a href="../../shared/download-file.php?file_id=${file.id}" class="btn btn-sm btn-primary me-1">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger delete-file-btn" data-file-id="${file.id}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </li>
                                `;
                            });
                            filesHtml += '</ul>';
                            adminFilesElement.innerHTML = filesHtml;
                            
                            // Add event listeners for delete buttons
                            document.querySelectorAll('.delete-file-btn').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    if (confirm('Are you sure you want to delete this file?')) {
                                        const fileId = this.getAttribute('data-file-id');
                                        fetch('delete-file.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded',
                                            },
                                            body: `file_id=${fileId}&csrf_token=${document.querySelector('input[name="csrf_token"]').value}`
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                // Remove file from list
                                                this.closest('li').remove();
                                                if (document.querySelectorAll('#admin-files li').length === 0) {
                                                    adminFilesElement.innerHTML = '<p class="text-muted">No files uploaded</p>';
                                                }
                                            } else {
                                                alert('Error: ' + data.message);
                                            }
                                        });
                                    }
                                });
                            });
                        } else {
                            adminFilesElement.innerHTML = '<p class="text-muted">No files uploaded</p>';
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

<?php include '../../shared/templates/admin-footer.php'; ?>