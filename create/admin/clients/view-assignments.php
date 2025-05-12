<?php
/**
 * Admin: View Employee Assignments
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';

// Authentication check - require admin role
require_admin_auth();
require_admin_role(['admin']);

// Include other required components
require_once '../../shared/admin-notifications.php';
require_once '../../shared/employee/employee-functions.php';

// Page variables
$page_title = 'Employee Assignments';
$current_page = 'view_assignments';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/admin/dashboard.php'],
    ['title' => 'Clients', 'url' => '/admin/clients/manage-clients.php'],
    ['title' => 'Employee Assignments', 'url' => '#']
];

// Get client ID from query string
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// If client ID is provided, get client details
$client = null;
if ($client_id > 0) {
    $client = get_client_by_id($client_id);
    
    if (!$client) {
        set_notification('error', 'Client not found.');
        header('Location: /admin/clients/manage-clients.php');
        exit;
    }
}

// Process unassignment if requested
if (isset($_GET['unassign']) && isset($_GET['employee_id']) && $client_id > 0) {
    $employee_id = intval($_GET['employee_id']);
    
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        // Unassign the employee
        $result = unassign_employee_from_client($employee_id, $client_id);
        
        if ($result) {
            set_notification('success', 'Employee has been unassigned from client.');
        } else {
            set_notification('error', 'Failed to unassign employee. Please try again.');
        }
        
        // Redirect to remove query parameters
        header('Location: /admin/clients/view-assignments.php?client_id=' . $client_id);
        exit;
    }
}

// Get all clients for dropdown if no client ID is provided
$clients = [];
if ($client_id <= 0) {
    $sql = "SELECT id, name FROM clients ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $clients = $stmt->fetchAll();
}

// Get assignments
$assignments = [];
if ($client_id > 0) {
    // Get assignments for specific client
    $assignments = get_client_assigned_employees($client_id, false);
} else {
    // Get all assignments
    $sql = "SELECT eca.*, c.name as client_name, u.name as employee_name, u.email as employee_email 
            FROM employee_client_assignments eca
            JOIN clients c ON eca.client_id = c.id
            JOIN users u ON eca.employee_id = u.id
            ORDER BY c.name, u.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $assignments = $stmt->fetchAll();
}

// Include template parts
include '../../shared/templates/admin-head.php';
include '../../shared/templates/admin-sidebar.php';
include '../../shared/templates/admin-header.php';
?>

<main class="admin-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <?php echo $client ? 'Employees Assigned to ' . htmlspecialchars($client['name']) : 'All Employee Assignments'; ?>
            </h1>
            
            <div>
                <?php if ($client_id > 0): ?>
                <a href="/admin/clients/assign-employee.php?client_id=<?php echo $client_id; ?>" class="btn btn-primary">
                    <i class="bi bi-person-plus me-2"></i> Assign Employees
                </a>
                <a href="/admin/clients/manage-clients.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-left me-2"></i> Back to Clients
                </a>
                <?php else: ?>
                <a href="/admin/clients/assign-employee.php" class="btn btn-primary">
                    <i class="bi bi-person-plus me-2"></i> New Assignment
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <?php if ($client_id <= 0 && !empty($clients)): ?>
        <!-- Client filter dropdown -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <form method="get" id="clientFilterForm">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label for="client_id" class="form-label">Filter by Client</label>
                            <select class="form-select" id="client_id" name="client_id" onchange="this.form.submit()">
                                <option value="">-- All Clients --</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Assignments list -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">
                    <?php echo $client ? 'Assigned Employees' : 'Employee-Client Assignments'; ?>
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($assignments)): ?>
                <div class="alert alert-info">
                    <?php if ($client_id > 0): ?>
                    No employees are currently assigned to this client.
                    <a href="/admin/clients/assign-employee.php?client_id=<?php echo $client_id; ?>">Assign employees now.</a>
                    <?php else: ?>
                    No employee-client assignments have been created yet.
                    <a href="/admin/clients/assign-employee.php">Create your first assignment.</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <?php if ($client_id <= 0): ?>
                                <th>Client</th>
                                <?php endif; ?>
                                <th>Employee</th>
                                <th>Assigned On</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <?php if ($client_id <= 0): ?>
                                <td>
                                    <a href="/admin/clients/view-assignments.php?client_id=<?php echo $assignment['client_id']; ?>">
                                        <?php echo htmlspecialchars($assignment['client_name']); ?>
                                    </a>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <div>
                                        <?php if (isset($assignment['employee_name'])): ?>
                                        <strong><?php echo htmlspecialchars($assignment['employee_name']); ?></strong>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($assignment['employee_email']); ?></small>
                                        <?php else: ?>
                                        <strong><?php echo htmlspecialchars($assignment['name']); ?></strong>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($assignment['email']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($assignment['assigned_at'])); ?></td>
                                <td>
                                    <?php if ($assignment['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo !empty($assignment['notes']) ? nl2br(htmlspecialchars($assignment['notes'])) : '<em class="text-muted">No notes</em>'; ?></td>
                                <td>
                                    <?php if ($assignment['is_active']): ?>
                                    <?php if (isset($_GET['unassign']) && isset($_GET['employee_id']) && $_GET['employee_id'] == $assignment['id']): ?>
                                    <div class="confirm-unassign">
                                        <span class="text-danger">Confirm unassign?</span>
                                        <div class="mt-1">
                                            <a href="?client_id=<?php echo $client_id; ?>&unassign=1&employee_id=<?php echo $assignment['id']; ?>&confirm=yes" class="btn btn-sm btn-danger">Yes</a>
                                            <a href="?client_id=<?php echo $client_id; ?>" class="btn btn-sm btn-secondary">No</a>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <a href="?client_id=<?php echo $client_id; ?>&unassign=1&employee_id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-person-dash"></i> Unassign
                                    </a>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <a href="/admin/clients/assign-employee.php?client_id=<?php echo $client_id; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-person-plus"></i> Reassign
                                    </a>
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

<?php
// Include footer
include '../../shared/templates/admin-footer.php';
?>