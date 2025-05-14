<?php
/**
 * Admin Time Reports
 * Generate reports of employee time logs
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/time-tracking/time-functions.php';
require_once '../../shared/utils/notifications.php';
require_once '../../shared/user-functions.php';

// Authentication check
require_admin_auth();
require_admin_role(['admin']);

// Get all employees
$employees = get_all_employees();

// Get all clients
$clients = get_all_clients();

// Process export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Apply filters
    $filters = [];
    
    if (!empty($_GET['employee_id'])) {
        $filters['employee_id'] = (int)$_GET['employee_id'];
    }
    
    if (!empty($_GET['start_date'])) {
        $filters['start_date'] = $_GET['start_date'];
    }
    
    if (!empty($_GET['end_date'])) {
        $filters['end_date'] = $_GET['end_date'];
    }
    
    if (!empty($_GET['client_id'])) {
        $filters['client_id'] = (int)$_GET['client_id'];
    }
    
    // Get all logs for export (no pagination)
    $logs = get_admin_time_logs($filters, 1000, 0);
    
    // Generate CSV
    $csv = generate_time_logs_csv($logs);
    
    // Output CSV file
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="time_report_' . date('Y-m-d') . '.csv"');
    echo $csv;
    exit;
}

// Set default filter values
$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$report_type = $_GET['report_type'] ?? 'detailed';

// Apply filters
$filters = [
    'start_date' => $start_date,
    'end_date' => $end_date
];

if ($employee_id) {
    $filters['employee_id'] = $employee_id;
}

if ($client_id) {
    $filters['client_id'] = $client_id;
}

// Pagination for detailed report
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get time logs for detailed report
$time_logs = [];
if ($report_type === 'detailed') {
    $time_logs = get_admin_time_logs($filters, $per_page, $offset);
    
    // Get total for pagination
    global $pdo;
    $count_where = [];
    $count_params = [];
    
    // Add employee filter to count
    if (!empty($filters['employee_id'])) {
        $count_where[] = "employee_id = :employee_id";
        $count_params[':employee_id'] = $filters['employee_id'];
    }
    
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
    
    $where_sql = implode(' AND ', $count_where);
    $count_sql = "SELECT COUNT(*) FROM employee_time_logs WHERE end_time IS NOT NULL";
    if (!empty($where_sql)) {
        $count_sql .= " AND " . $where_sql;
    }
    
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($count_params);
    $total_logs = $stmt->fetchColumn();
    $total_pages = ceil($total_logs / $per_page);
}

// Get summary report by employee
$employee_summary = [];
if ($report_type === 'by_employee' || $report_type === 'summary') {
    $employee_summary = get_employee_summary_report($filters);
}

// Get summary report by client
$client_summary = [];
if ($report_type === 'by_client' || $report_type === 'summary') {
    $client_summary = get_client_summary_report($filters);
}

// Page variables
$page_title = 'Time Reports';
$current_page = 'time-report';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Reports', 'url' => '#'],
    ['title' => 'Time Reports', 'url' => '#']
];

// Include template parts
include '../../shared/templates/admin-head.php';
include '../../shared/templates/admin-sidebar.php';
include '../../shared/templates/admin-header.php';
?>

<main class="admin-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <div>
                <a href="?export=csv&employee_id=<?php echo $employee_id ? $employee_id : ''; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&client_id=<?php echo $client_id ? $client_id : ''; ?>" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
                </a>
            </div>
        </div>
        
        <!-- Filter Form -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h5 class="m-0 font-weight-bold">Report Filters</h5>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-2">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select class="form-select" id="report_type" name="report_type">
                            <option value="detailed" <?php echo $report_type === 'detailed' ? 'selected' : ''; ?>>Detailed Logs</option>
                            <option value="by_employee" <?php echo $report_type === 'by_employee' ? 'selected' : ''; ?>>By Employee</option>
                            <option value="by_client" <?php echo $report_type === 'by_client' ? 'selected' : ''; ?>>By Client</option>
                            <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Summary</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="employee_id" class="form-label">Employee</label>
                        <select class="form-select" id="employee_id" name="employee_id">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php echo $employee_id == $employee['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="client_id" class="form-label">Client</label>
                        <select class="form-select" id="client_id" name="client_id">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $client_id == $client['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($report_type === 'summary'): ?>
            <!-- Summary Report -->
            <div class="row mb-4">
                <!-- By Employee -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header py-3">
                            <h5 class="m-0 font-weight-bold">Summary by Employee</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($employee_summary)): ?>
                                <p class="text-center py-4">No data for the selected filters.</p>
                            <?php else: ?>
                                <div class="table-responsive">