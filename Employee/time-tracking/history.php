<?php
/**
 * Employee Time Log History
 * View and filter past time logs
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

// Process export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Apply filters
    $filters = [];
    
    if (!empty($_GET['start_date'])) {
        $filters['start_date'] = $_GET['start_date'];
    }
    
    if (!empty($_GET['end_date'])) {
        $filters['end_date'] = $_GET['end_date'];
    }
    
    if (!empty($_GET['client_id'])) {
        $filters['client_id'] = (int)$_GET['client_id'];
    }
    
    if (!empty($_GET['task_id'])) {
        $filters['task_id'] = (int)$_GET['task_id'];
    }
    
    // Get all logs for export (no pagination)
    $logs = get_employee_time_logs($employee_id, $filters, 1000, 0);
    
    // Generate CSV
    $csv = generate_time_logs_csv($logs);
    
    // Output CSV file
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="time_logs_' . date('Y-m-d') . '.csv"');
    echo $csv;
    exit;
}

// Set default filter values
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;

// Apply filters
$filters = [
    'start_date' => $start_date,
    'end_date' => $end_date
];

if ($client_id) {
    $filters['client_id'] = $client_id;
}

if ($task_id) {
    $filters['task_id'] = $task_id;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get time logs
$time_logs = get_employee_time_logs($employee_id, $filters, $per_page, $offset);

// Get total for pagination
global $pdo;
$count_where = [];
$count_params = [':employee_id' => $employee_id];

// Add date range filters to count
if (!empty($filters['start_date'])) {
    $count_where[] = "DATE(start_time) >= :start_date";
    $count_params[':start_date'] = $filters['start_date'];
}

if (!empty($filters['end_date'])) {
    $count_where[] = "DATE(start_time) <= :end_date";
    $count_params[':end_date'] = $filters['end_date'];
}

// Add client filter to count
if (!empty($filters['client_id'])) {
    $count_where[] = "client_id = :client_id";
    $count_params[':client_id'] = $filters['client_id'];
}

// Add task filter to count
if (!empty($filters['task_id'])) {
    $count_where[] = "task_id = :task_id";
    $count_params[':task_id'] = $filters['task_id'];
}

$where_sql = implode(' AND ', $count_where);
$count_sql = "SELECT COUNT(*) FROM employee_time_logs WHERE employee_id = :employee_id";
if (!empty($where_sql)) {
    $count_sql .= " AND " . $where_sql;
}

$stmt = $pdo->prepare($count_sql);
$stmt->execute($count_params);
$total_logs = $stmt->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// Get total time for the filtered period
$total_minutes = get_employee_total_time($employee_id, $filters);

// Get client summary
$client_summary = get_client_summary_report(['employee_id' => $employee_id, 'start_date' => $start_date, 'end_date' => $end_date]);

// Page variables
$page_title = 'Time Log History';
$current_page = 'time-history';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Time Tracking', 'url' => 'timer.php'],
    ['title' => 'History', 'url' => '#']
];

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
                <a href="?export=csv&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?><?php echo $task_id ? '&task_id=' . $task_id : ''; ?>" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
                </a>
            </div>
        </div>
        
        <!-- Filter Form -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h5 class="m-0 font-weight-bold">Filter Time Logs</h5>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="client_id" class="form-label">Client</label>
                        <select class="form-select" id="client_id" name="client_id">
                            <option value="">All Clients</option>
                            <?php foreach ($assigned_clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $client_id == $client['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Time Summary -->
        <div class="row mb-4">
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Hours</h5>
                        <h2 class="display-4"><?php echo format_time_duration($total_minutes); ?></h2>
                        <p class="text-muted">
                            <?php echo date('M j, Y', strtotime($start_date)); ?> to 
                            <?php echo date('M j, Y', strtotime($end_date)); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-8 mb-4">
                <div class="card h-100">
                    <div class="card-header py-3">
                        <h5 class="m-0 font-weight-bold">Client Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($client_summary)): ?>
                            <p class="text-center py-4">No client data for the selected period.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Entries</th>
                                            <th>Hours</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($client_summary as $summary): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($summary['client_name']); ?></td>
                                                <td><?php echo $summary['entries_count']; ?></td>
                                                <td><?php echo format_time_duration($summary['total_minutes']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Time Logs -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h5 class="m-0 font-weight-bold">Time Log History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($time_logs)): ?>
                    <p class="text-center py-4">No time logs found for the selected filters.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Task</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Duration</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($time_logs as $log): ?>
                                    <?php 
                                    $minutes = isset($log['duration_minutes']) ? $log['duration_minutes'] : 
                                              (isset($log['end_time']) ? floor((strtotime($log['end_time']) - strtotime($log['start_time'])) / 60) : 0);
                                    
                                    $duration = format_time_duration($minutes);
                                    ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($log['start_time'])); ?></td>
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Time logs pagination">
                            <ul class="pagination justify-content-center mt-4">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?><?php echo $task_id ? '&task_id=' . $task_id : ''; ?>">
                                            Previous
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Previous</span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $start_page + 4);
                                if ($end_page - $start_page < 4 && $total_pages > 4) {
                                    $start_page = max(1, $end_page - 4);
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?><?php echo $task_id ? '&task_id=' . $task_id : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?><?php echo $task_id ? '&task_id=' . $task_id : ''; ?>">
                                            Next
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Next</span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../../shared/templates/employee-footer.php'; ?><table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Entries</th>
                                            <th>Hours</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employee_summary as $summary): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($summary['employee_name']); ?></td>
                                                <td><?php echo $summary['entries_count']; ?></td>
                                                <td><?php echo format_time_duration($summary['total_minutes']); ?></td>
                                                <td>
                                                    <a href="?report_type=detailed&employee_id=<?php echo $summary['employee_id']; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?>" class="btn btn-sm btn-outline-primary">
                                                        View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- By Client -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header py-3">
                            <h5 class="m-0 font-weight-bold">Summary by Client</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($client_summary)): ?>
                                <p class="text-center py-4">No data for the selected filters.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Client</th>
                                                <th>Entries</th>
                                                <th>Hours</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($client_summary as $summary): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($summary['client_name']); ?></td>
                                                    <td><?php echo $summary['entries_count']; ?></td>
                                                    <td><?php echo format_time_duration($summary['total_minutes']); ?></td>
                                                    <td>
                                                        <a href="?report_type=detailed&client_id=<?php echo $summary['client_id']; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?><?php echo $employee_id ? '&employee_id=' . $employee_id : ''; ?>" class="btn btn-sm btn-outline-primary">
                                                            View Details
                                                        </a>
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
            </div>
            
        <?php elseif ($report_type === 'by_employee'): ?>
            <!-- By Employee Report -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold">Time by Employee</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($employee_summary)): ?>
                        <p class="text-center py-4">No data for the selected filters.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Entries</th>
                                        <th>Hours</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employee_summary as $summary): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($summary['employee_name']); ?></td>
                                            <td><?php echo $summary['entries_count']; ?></td>
                                            <td><?php echo format_time_duration($summary['total_minutes']); ?></td>
                                            <td>
                                                <a href="?report_type=detailed&employee_id=<?php echo $summary['employee_id']; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?>" class="btn btn-sm btn-primary">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($report_type === 'by_client'): ?>
            <!-- By Client Report -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold">Time by Client</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($client_summary)): ?>
                        <p class="text-center py-4">No data for the selected filters.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Entries</th>
                                        <th>Hours</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($client_summary as $summary): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($summary['client_name']); ?></td>
                                            <td><?php echo $summary['entries_count']; ?></td>
                                            <td><?php echo format_time_duration($summary['total_minutes']); ?></td>
                                            <td>
                                                <a href="?report_type=detailed&client_id=<?php echo $summary['client_id']; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?><?php echo $employee_id ? '&employee_id=' . $employee_id : ''; ?>" class="btn btn-sm btn-primary">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Detailed Report -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h5 class="m-0 font-weight-bold">Detailed Time Logs</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($time_logs)): ?>
                        <p class="text-center py-4">No time logs found for the selected filters.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Client</th>
                                        <th>Task</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Duration</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($time_logs as $log): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($log['employee_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($log['start_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['client_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($log['task_title'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('g:i A', strtotime($log['start_time'])); ?></td>
                                            <td><?php echo date('g:i A', strtotime($log['end_time'])); ?></td>
                                            <td><?php echo format_time_duration($log['minutes_logged']); ?></td>
                                            <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Time logs pagination">
                                <ul class="pagination justify-content-center mt-4">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&report_type=<?php echo $report_type; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?><?php echo $employee_id ? '&employee_id=' . $employee_id : ''; ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?>">
                                                Previous
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Previous</span>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $start_page + 4);
                                    if ($end_page - $start_page < 4 && $total_pages > 4) {
                                        $start_page = max(1, $end_page - 4);
                                    }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&report_type=<?php echo $report_type; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?><?php echo $employee_id ? '&employee_id=' . $employee_id : ''; ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&report_type=<?php echo $report_type; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?><?php echo $employee_id ? '&employee_id=' . $employee_id : ''; ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?>">
                                                Next
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Next</span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // If the report type is changed, update the form
    document.getElementById('report_type').addEventListener('change', function() {
        const reportType = this.value;
        
        // If detailed report, show employee and client filters
        if (reportType === 'detailed') {
            document.getElementById('employee_id').disabled = false;
            document.getElementById('client_id').disabled = false;
        }
        // If by_employee report, disable employee filter
        else if (reportType === 'by_employee') {
            document.getElementById('employee_id').value = '';
            document.getElementById('employee_id').disabled = true;
            document.getElementById('client_id').disabled = false;
        }
        // If by_client report, disable client filter
        else if (reportType === 'by_client') {
            document.getElementById('client_id').value = '';
            document.getElementById('client_id').disabled = true;
            document.getElementById('employee_id').disabled = false;
        }
        // If summary report, show all filters
        else if (reportType === 'summary') {
            document.getElementById('employee_id').disabled = false;
            document.getElementById('client_id').disabled = false;
        }
    });
    
    // Initialize form based on current report type
    const currentReportType = document.getElementById('report_type').value;
    if (currentReportType === 'by_employee') {
        document.getElementById('employee_id').disabled = true;
    } else if (currentReportType === 'by_client') {
        document.getElementById('client_id').disabled = true;
    }
});
</script>

<?php include '../../shared/templates/admin-footer.php'; ?>