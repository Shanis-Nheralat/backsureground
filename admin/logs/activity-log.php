<?php
// /admin/logs/activity-log.php
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/utils/activity-logger.php';
require_once '../../shared/utils/pagination.php';

// Require admin authentication
require_admin_auth();
require_admin_role(['admin']);

// Page variables
$page_title = 'Admin Activity Log';
$current_page = 'activity_log';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/admin/dashboard.php'],
    ['title' => 'Activity Log', 'url' => '#']
];

// Get filters from request
$filters = [];
$action_type = isset($_GET['action_type']) ? $_GET['action_type'] : '';
$module = isset($_GET['module']) ? $_GET['module'] : '';
$admin_id = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

if (!empty($action_type)) $filters['action_type'] = $action_type;
if (!empty($module)) $filters['module'] = $module;
if (!empty($admin_id)) $filters['admin_id'] = $admin_id;
if (!empty($date_from)) $filters['date_from'] = $date_from . ' 00:00:00';
if (!empty($date_to)) $filters['date_to'] = $date_to . ' 23:59:59';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;
$total_logs = count_admin_activity_logs($filters);

// Get logs
$logs = get_admin_activity_logs($filters, $per_page, $offset);

// Get all admins for the filter dropdown
$stmt = $pdo->query("SELECT id, username, name FROM users WHERE role = 'admin' ORDER BY username");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <h6 class="m-0 font-weight-bold">Filter Options</h6>
            </div>
            <div class="card-body">
                <form method="get" action="" class="row g-3">
                    <div class="col-md-2">
                        <label for="action_type" class="form-label">Action Type</label>
                        <select class="form-select" id="action_type" name="action_type">
                            <option value="">All Actions</option>
                            <option value="login" <?php echo $action_type === 'login' ? 'selected' : ''; ?>>Login</option>
                            <option value="logout" <?php echo $action_type === 'logout' ? 'selected' : ''; ?>>Logout</option>
                            <option value="create" <?php echo $action_type === 'create' ? 'selected' : ''; ?>>Create</option>
                            <option value="update" <?php echo $action_type === 'update' ? 'selected' : ''; ?>>Update</option>
                            <option value="delete" <?php echo $action_type === 'delete' ? 'selected' : ''; ?>>Delete</option>
                            <option value="assign" <?php echo $action_type === 'assign' ? 'selected' : ''; ?>>Assign</option>
                            <option value="settings_change" <?php echo $action_type === 'settings_change' ? 'selected' : ''; ?>>Settings Change</option>
                            <option value="access" <?php echo $action_type === 'access' ? 'selected' : ''; ?>>Access</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="module" class="form-label">Module</label>
                        <select class="form-select" id="module" name="module">
                            <option value="">All Modules</option>
                            <option value="Authentication" <?php echo $module === 'Authentication' ? 'selected' : ''; ?>>Authentication</option>
                            <option value="Users" <?php echo $module === 'Users' ? 'selected' : ''; ?>>Users</option>
                            <option value="Tasks" <?php echo $module === 'Tasks' ? 'selected' : ''; ?>>Tasks</option>
                            <option value="Support" <?php echo $module === 'Support' ? 'selected' : ''; ?>>Support</option>
                            <option value="Plans" <?php echo $module === 'Plans' ? 'selected' : ''; ?>>Plans</option>
                            <option value="Settings" <?php echo $module === 'Settings' ? 'selected' : ''; ?>>Settings</option>
                            <option value="File" <?php echo $module === 'File' ? 'selected' : ''; ?>>File</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="admin_id" class="form-label">Admin User</label>
                        <select class="form-select" id="admin_id" name="admin_id">
                            <option value="">All Admins</option>
                            <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo $admin['id']; ?>" <?php echo $admin_id === $admin['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                        <a href="activity-log.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Log Table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Activity Log Entries</h6>
                <span class="badge bg-primary"><?php echo $total_logs; ?> Entries Found</span>
            </div>
            <div class="card-body">
                <?php if (empty($logs)): ?>
                <div class="alert alert-info">No log entries found matching your criteria.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Item</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['name'] ?? $log['username'] ?? 'Unknown'); ?></td>
                                <td>
                                    <?php
                                    $action_class = '';
                                    switch($log['action_type']) {
                                        case 'login': $action_class = 'bg-success text-white'; break;
                                        case 'logout': $action_class = 'bg-secondary text-white'; break;
                                        case 'create': $action_class = 'bg-info text-white'; break;
                                        case 'update': $action_class = 'bg-warning'; break;
                                        case 'delete': $action_class = 'bg-danger text-white'; break;
                                        case 'assign': $action_class = 'bg-primary text-white'; break;
                                        case 'settings_change': $action_class = 'bg-dark text-white'; break;
                                        case 'access': $action_class = 'bg-light'; break;
                                        case 'access_denied': $action_class = 'bg-danger text-white'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $action_class; ?>">
                                        <?php echo ucfirst($log['action_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['module']); ?></td>
                                <td>
                                    <?php
                                    if (!empty($log['item_type']) && !empty($log['item_id'])) {
                                        echo htmlspecialchars($log['item_type']) . ' #' . $log['item_id'];
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    // Show details or changes
                                    if (!empty($log['details'])) {
                                        echo htmlspecialchars($log['details']);
                                    } elseif (!empty($log['old_value']) && !empty($log['new_value'])) {
                                        echo 'Changed from: ' . substr(htmlspecialchars($log['old_value']), 0, 30) . '...<br>'
                                            . 'To: ' . substr(htmlspecialchars($log['new_value']), 0, 30) . '...';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php
                $pagination_url = 'activity-log.php?';
                if (!empty($action_type)) $pagination_url .= 'action_type=' . urlencode($action_type) . '&';
                if (!empty($module)) $pagination_url .= 'module=' . urlencode($module) . '&';
                if (!empty($admin_id)) $pagination_url .= 'admin_id=' . $admin_id . '&';
                if (!empty($date_from)) $pagination_url .= 'date_from=' . urlencode($date_from) . '&';
                if (!empty($date_to)) $pagination_url .= 'date_to=' . urlencode($date_to) . '&';
                
                echo generate_pagination($page, $per_page, $total_logs, $pagination_url);
                ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../../shared/templates/admin-footer.php'; ?>