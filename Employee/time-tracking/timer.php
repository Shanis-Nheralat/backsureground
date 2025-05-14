<?php
/**
 * Employee Time Tracking
 * Allows employees to track time with start/stop timer or manual entries
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/time-tracking/time-functions.php';
require_once '../../shared/utils/notifications.php';
require_once '../../shared/user-functions.php';

// Authentication check
require_admin_auth();
require_admin_role(['employee']);

// Get employee ID from session
$employee_id = $_SESSION['user_id'];

// Get assigned clients
$assigned_clients = get_assigned_clients($employee_id);

// Get active tasks for this employee
$active_tasks = get_employee_active_tasks($employee_id);

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_notification('error', 'Invalid form submission. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'start_timer') {
            $client_id = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
            $task_id = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
            $description = $_POST['description'] ?? '';
            
            $time_log_id = start_time_tracking($employee_id, $client_id, $task_id, null, $description);
            
            if ($time_log_id) {
                set_flash_notification('success', 'Timer started successfully.');
            } else {
                set_flash_notification('error', 'Failed to start timer. You may have another active timer.');
            }
        } elseif ($action === 'stop_timer') {
            $time_log_id = (int)$_POST['time_log_id'];
            $description = $_POST['description'] ?? null;
            
            if (stop_time_tracking($time_log_id, $employee_id, $description)) {
                set_flash_notification('success', 'Timer stopped successfully.');
            } else {
                set_flash_notification('error', 'Failed to stop timer.');
            }
        } elseif ($action === 'add_manual') {
            $client_id = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
            $task_id = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
            $start_date = $_POST['start_date'] ?? '';
            $start_time = $_POST['start_time'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $end_time = $_POST['end_time'] ?? '';
            $description = $_POST['description'] ?? '';
            
            $start_datetime = $start_date . ' ' . $start_time . ':00';
            $end_datetime = $end_date . ' ' . $end_time . ':00';
            
            $time_log_id = add_manual_time_entry(
                $employee_id,
                $start_datetime,
                $end_datetime,
                $client_id,
                $task_id,
                null,
                $description
            );
            
            if ($time_log_id) {
                set_flash_notification('success', 'Manual time entry added successfully.');
            } else {
                set_flash_notification('error', 'Failed to add manual time entry. Please check the times.');
            }
        } elseif ($action === 'update_log') {
            $time_log_id = (int)$_POST['time_log_id'];
            
            $update_data = [
                'client_id' => !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null,
                'task_id' => !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null,
                'description' => $_POST['description'] ?? ''
            ];
            
            // For manual entries, allow updating times
            if (isset($_POST['is_manual']) && $_POST['is_manual']) {
                $start_date = $_POST['start_date'] ?? '';
                $start_time = $_POST['start_time'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                $end_time = $_POST['end_time'] ?? '';
                
                $update_data['start_time'] = $start_date . ' ' . $start_time . ':00';
                $update_data['end_time'] = $end_date . ' ' . $end_time . ':00';
            }
            
            if (update_time_log($time_log_id, $employee_id, $update_data)) {
                set_flash_notification('success', 'Time log updated successfully.');
            } else {
                set_flash_notification('error', 'Failed to update time log. Please check the times.');
            }
        } elseif ($action === 'delete_log') {
            $time_log_id = (int)$_POST['time_log_id'];
            
            if (delete_time_log($time_log_id, $employee_id)) {
                set_flash_notification('success', 'Time log deleted successfully.');
            } else {
                set_flash_notification('error', 'Failed to delete time log.');
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: timer.php');
    exit;
}

// Get active time session
$active_session = get_active_time_session($employee_id);

// Get time logs for today
$today = date('Y-m-d');
$filters = [
    'start_date' => $today,
    'end_date' => $today
];
$today_logs = get_employee_time_logs($employee_id, $filters, 50);
$total_today_minutes = get_employee_total_time($employee_id, $filters);

// Get time logs for this week
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$filters = [
    'start_date' => $week_start,
    'end_date' => $week_end
];
$total_week_minutes = get_employee_total_time($employee_id, $filters);

// Page variables
$page_title = 'Time Tracking';
$current_page = 'time-tracking';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Time Tracking', 'url' => '#']
];

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include template parts
include '../../shared/templates/employee-head.php';
include '../../shared/templates/employee-sidebar.php';
include '../../shared/templates/employee-header.php';
?>

<main class="employee-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#manualEntryModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Manual Entry
                </button>
            </div>
        </div>
        
        <?php echo display_flash_notifications(); ?>
        
        <!-- Time Summary -->
        <div class="row mb-4">
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Today</h5>
                        <h2 class="display-4"><?php echo format_time_duration($total_today_minutes); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">This Week</h5>
                        <h2 class="display-4"><?php echo format_time_duration($total_week_minutes); ?></h2>
                        <p class="text-muted"><?php echo $week_start; ?> to <?php echo $week_end; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Current Status</h5>
                        
                        <?php if ($active_session): ?>
                            <div class="alert alert-success">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Timer Running</strong><br>
                                        Started: <?php echo date('g:i A', strtotime($active_session['start_time'])); ?><br>
                                        Duration: <span id="timer"></span><br>
                                        <?php if ($active_session['client_name']): ?>
                                            Client: <?php echo htmlspecialchars($active_session['client_name']); ?><br>
                                        <?php endif; ?>
                                        <?php if ($active_session['task_title']): ?>
                                            Task: <?php echo htmlspecialchars($active_session['task_title']); ?><br>
                                        <?php endif; ?>
                                        <?php if ($active_session['description']): ?>
                                            Note: <?php echo htmlspecialchars($active_session['description']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="stop_timer">
                                            <input type="hidden" name="time_log_id" value="<?php echo $active_session['id']; ?>">
                                            <button type="button" class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#stopTimerModal">
                                                <i class="bi bi-stop-circle me-1"></i> Stop
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>No Active Timer</strong><br>
                                        Start a new timer to track your work.
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#startTimerModal">
                                            <i class="bi bi-play-circle me-1"></i> Start Timer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Today's Time Logs -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h5 class="m-0 font-weight-bold">Today's Time Logs</h5>
            </div>
            <div class="card-body">
                <?php if (empty($today_logs)): ?>
                    <p class="text-center py-4">No time logs for today.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Task</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Duration</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($today_logs as $log): ?>
                                    <?php 
                                    $minutes = isset($log['duration_minutes']) ? $log['duration_minutes'] : 
                                              (isset($log['end_time']) ? floor((strtotime($log['end_time']) - strtotime($log['start_time'])) / 60) : 0);
                                    
                                    $duration = format_time_duration($minutes);
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['client_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($log['task_title'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('g:i A', strtotime($log['start_time'])); ?></td>
                                        <td>
                                            <?php if ($log['end_time']): ?>
                                                <?php echo date('g:i A', strtotime($log['end_time'])); ?>
                                            <?php else: ?>
                                                <span class="badge bg-success">Running</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $duration; ?></td>
                                        <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                                        <td>
                                            <?php if ($log['end_time']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-log" 
                                                        data-log-id="<?php echo $log['id']; ?>"
                                                        data-client-id="<?php echo $log['client_id'] ?? ''; ?>"
                                                        data-task-id="<?php echo $log['task_id'] ?? ''; ?>"
                                                        data-description="<?php echo htmlspecialchars($log['description'] ?? ''); ?>"
                                                        data-start-time="<?php echo date('Y-m-d\TH:i', strtotime($log['start_time'])); ?>"
                                                        data-end-time="<?php echo date('Y-m-d\TH:i', strtotime($log['end_time'])); ?>"
                                                        data-is-manual="<?php echo $log['is_manual_entry'] ? '1' : '0'; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-log" 
                                                        data-log-id="<?php echo $log['id']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
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

<!-- Start Timer Modal -->
<div class="modal fade" id="startTimerModal" tabindex="-1" aria-labelledby="startTimerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="startTimerModalLabel">Start Timer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="start_timer">
                    
                    <div class="mb-3">
                        <label for="client_id" class="form-label">Client</label>
                        <select class="form-select" id="client_id" name="client_id">
                            <option value="">-- Select Client (Optional) --</option>
                            <?php foreach ($assigned_clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="task_id" class="form-label">Task</label>
                        <select class="form-select" id="task_id" name="task_id">
                            <option value="">-- Select Task (Optional) --</option>
                            <?php foreach ($active_tasks as $task): ?>
                                <option value="<?php echo $task['id']; ?>" data-client="<?php echo $task['client_id']; ?>">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="What are you working on?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Start Timer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stop Timer Modal -->
<div class="modal fade" id="stopTimerModal" tabindex="-1" aria-labelledby="stopTimerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stopTimerModalLabel">Stop Timer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="stop_timer">
                    <input type="hidden" name="time_log_id" value="<?php echo $active_session ? $active_session['id'] : ''; ?>">
                    
                    <p>Current timer duration: <strong><span id="stop-timer-duration"></span></strong></p>
                    
                    <div class="mb-3">
                        <label for="stop_description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="stop_description" name="description" rows="3" placeholder="Enter or update description"><?php echo $active_session ? htmlspecialchars($active_session['description'] ?? '') : ''; ?></textarea>
                        <div class="form-text">Leave a note about what you accomplished</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Stop Timer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manual Entry Modal -->
<div class="modal fade" id="manualEntryModal" tabindex="-1" aria-labelledby="manualEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manualEntryModalLabel">Add Manual Time Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="add_manual">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="manual_client_id" class="form-label">Client</label>
                        <select class="form-select" id="manual_client_id" name="client_id">
                            <option value="">-- Select Client (Optional) --</option>
                            <?php foreach ($assigned_clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="manual_task_id" class="form-label">Task</label>
                        <select class="form-select" id="manual_task_id" name="task_id">
                            <option value="">-- Select Task (Optional) --</option>
                            <?php foreach ($active_tasks as $task): ?>
                                <option value="<?php echo $task['id']; ?>" data-client="<?php echo $task['client_id']; ?>">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="manual_description" class="form-label">Description</label>
                        <textarea class="form-control" id="manual_description" name="description" rows="3" placeholder="What did you work on?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Time Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Time Log Modal -->
<div class="modal fade" id="editLogModal" tabindex="-1" aria-labelledby="editLogModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLogModalLabel">Edit Time Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_log">
                    <input type="hidden" name="time_log_id" id="edit_time_log_id" value="">
                    <input type="hidden" name="is_manual" id="edit_is_manual" value="0">
                    
                    <div id="edit_time_section" class="d-none">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="edit_start_time" name="start_time">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="edit_end_date" name="end_date">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="edit_end_time" name="end_time">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_client_id" class="form-label">Client</label>
                        <select class="form-select" id="edit_client_id" name="client_id">
                            <option value="">-- Select Client (Optional) --</option>
                            <?php foreach ($assigned_clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_task_id" class="form-label">Task</label>
                        <select class="form-select" id="edit_task_id" name="task_id">
                            <option value="">-- Select Task (Optional) --</option>
                            <?php foreach ($active_tasks as $task): ?>
                                <option value="<?php echo $task['id']; ?>" data-client="<?php echo $task['client_id']; ?>">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Time Log</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Time Log Modal -->
<div class="modal fade" id="deleteLogModal" tabindex="-1" aria-labelledby="deleteLogModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteLogModalLabel">Delete Time Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="delete_log">
                    <input type="hidden" name="time_log_id" id="delete_time_log_id" value="">
                    
                    <p>Are you sure you want to delete this time log? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Timer functionality
    <?php if ($active_session): ?>
    let startTime = new Date('<?php echo $active_session['start_time']; ?>').getTime();
    let timerInterval = setInterval(function() {
        let now = new Date().getTime();
        let elapsed = now - startTime;
        
        let hours = Math.floor(elapsed / (1000 * 60 * 60));
        let minutes = Math.floor((elapsed % (1000 * 60 * 60)) / (1000 * 60));
        let seconds = Math.floor((elapsed % (1000 * 60)) / 1000);
        
        // Format as HH:MM:SS
        let displayHours = hours.toString().padStart(2, '0');
        let displayMinutes = minutes.toString().padStart(2, '0');
        let displaySeconds = seconds.toString().padStart(2, '0');
        
        document.getElementById('timer').textContent = `${displayHours}:${displayMinutes}:${displaySeconds}`;
        document.getElementById('stop-timer-duration').textContent = `${displayHours}:${displayMinutes}:${displaySeconds}`;
    }, 1000);
    <?php endif; ?>
    
    // Task dropdown filtering based on client selection
    document.getElementById('client_id').addEventListener('change', function() {
        const clientId = this.value;
        const taskSelect = document.getElementById('task_id');
        
        // Reset task selection
        taskSelect.value = '';
        
        // Show/hide tasks based on client
        Array.from(taskSelect.options).forEach(option => {
            if (option.value === '') return; // Skip placeholder option
            
            const taskClientId = option.getAttribute('data-client');
            
            if (!clientId || clientId === taskClientId) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
    });
    
    // Same for manual entry form
    document.getElementById('manual_client_id').addEventListener('change', function() {
        const clientId = this.value;
        const taskSelect = document.getElementById('manual_task_id');
        
        // Reset task selection
        taskSelect.value = '';
        
        // Show/hide tasks based on client
        Array.from(taskSelect.options).forEach(option => {
            if (option.value === '') return; // Skip placeholder option
            
            const taskClientId = option.getAttribute('data-client');
            
            if (!clientId || clientId === taskClientId) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
    });
    
    // Task selection updates client
    document.getElementById('task_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value !== '') {
            const clientId = selectedOption.getAttribute('data-client');
            if (clientId) {
                document.getElementById('client_id').value = clientId;
            }
        }
    });
    
    // Same for manual entry form
    document.getElementById('manual_task_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value !== '') {
            const clientId = selectedOption.getAttribute('data-client');
            if (clientId) {
                document.getElementById('manual_client_id').value = clientId;
            }
        }
    });
    
    // Set default times for manual entry
    const now = new Date();
    const currentHour = now.getHours().toString().padStart(2, '0');
    const currentMinute = now.getMinutes().toString().padStart(2, '0');
    
    document.getElementById('start_time').value = `${currentHour}:${currentMinute}`;
    document.getElementById('end_time').value = `${currentHour}:${currentMinute}`;
    
    // Edit log modal
    document.querySelectorAll('.edit-log').forEach(button => {
        button.addEventListener('click', function() {
            const logId = this.getAttribute('data-log-id');
            const clientId = this.getAttribute('data-client-id');
            const taskId = this.getAttribute('data-task-id');
            const description = this.getAttribute('data-description');
            const isManual = this.getAttribute('data-is-manual') === '1';
            
            document.getElementById('edit_time_log_id').value = logId;
            document.getElementById('edit_client_id').value = clientId;
            document.getElementById('edit_task_id').value = taskId;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_is_manual').value = isManual ? '1' : '0';
            
            // For manual entries, show time editing fields
            if (isManual) {
                document.getElementById('edit_time_section').classList.remove('d-none');
                
                // Parse start time
                const startTime = new Date(this.getAttribute('data-start-time'));
                document.getElementById('edit_start_date').value = startTime.toISOString().split('T')[0];
                document.getElementById('edit_start_time').value = startTime.toTimeString().substring(0, 5);
                
                // Parse end time
                const endTime = new Date(this.getAttribute('data-end-time'));
                document.getElementById('edit_end_date').value = endTime.toISOString().split('T')[0];
                document.getElementById('edit_end_time').value = endTime.toTimeString().substring(0, 5);
            } else {
                document.getElementById('edit_time_section').classList.add('d-none');
            }
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('editLogModal'));
            modal.show();
        });
    });
    
    // Delete log modal
    document.querySelectorAll('.delete-log').forEach(button => {
        button.addEventListener('click', function() {
            const logId = this.getAttribute('data-log-id');
            document.getElementById('delete_time_log_id').value = logId;
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('deleteLogModal'));
            modal.show();
        });
    });
});
</script>

<?php include '../../shared/templates/employee-footer.php'; ?>