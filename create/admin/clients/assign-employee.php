<?php
/**
 * Admin: Assign Employee to Client
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
$page_title = 'Assign Employee to Client';
$current_page = 'assign_employee';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/admin/dashboard.php'],
    ['title' => 'Clients', 'url' => '/admin/clients/manage-clients.php'],
    ['title' => 'Assign Employee', 'url' => '#']
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
    } else {
        // Get form data
        $post_client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $employee_ids = isset($_POST['employee_ids']) ? $_POST['employee_ids'] : [];
        $notes = $_POST['notes'] ?? '';
        
        if ($post_client_id <= 0) {
            set_notification('error', 'Client ID is required.');
        } elseif (empty($employee_ids)) {
            set_notification('error', 'Please select at least one employee.');
        } else {
            // Process each employee assignment
            $success_count = 0;
            foreach ($employee_ids as $employee_id) {
                $result = assign_employee_to_client(
                    intval($employee_id),
                    $post_client_id,
                    $notes
                );
                
                if ($result) {
                    $success_count++;
                }
            }
            
            if ($success_count > 0) {
                set_notification('success', "Successfully assigned {$success_count} employee(s) to client.");
                header('Location: /admin/clients/view-assignments.php?client_id=' . $post_client_id);
                exit;
            } else {
                set_notification('error', 'Failed to assign employees. Please try again.');
            }
        }
    }
}

// Get all employees
$sql = "SELECT id, name, username, email FROM users WHERE role = 'employee' ORDER BY name";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$employees = $stmt->fetchAll();

// Get all clients for dropdown if no client ID is provided
$clients = [];
if ($client_id <= 0) {
    $sql = "SELECT id, name FROM clients ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $clients = $stmt->fetchAll();
}

// Get current assignments for this client
$current_assignments = [];
if ($client_id > 0) {
    $sql = "SELECT employee_id FROM employee_client_assignments 
            WHERE client_id = :client_id AND is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_id' => $client_id]);
    $current_assignments = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

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
            
            <a href="/admin/clients/view-assignments.php<?php echo $client_id > 0 ? '?client_id=' . $client_id : ''; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back to Assignments
            </a>
        </div>
        
        <?php display_notifications(); ?>
        
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">
                            <?php echo $client ? 'Assign Employees to ' . htmlspecialchars($client['name']) : 'Assign Employees to Client'; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <!-- CSRF protection -->
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <?php if ($client_id <= 0): ?>
                            <!-- Client selection dropdown if no client ID is provided -->
                            <div class="mb-4">
                                <label for="client_id" class="form-label">Select Client</label>
                                <select class="form-select" id="client_id" name="client_id" required>
                                    <option value="">-- Select Client --</option>
                                    <?php foreach ($clients as $c): ?>
                                    <option value="<?php echo $c['id']; ?>">
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                            <!-- Hidden client ID if already provided -->
                            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-4">
                                <label class="form-label">Select Employees to Assign</label>
                                
                                <?php if (empty($employees)): ?>
                                <div class="alert alert-info">
                                    No employees available for assignment. Please create employee accounts first.
                                </div>
                                <?php else: ?>
                                
                                <div class="employee-list border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($employees as $employee): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="employee_ids[]" 
                                               value="<?php echo $employee['id']; ?>" id="employee_<?php echo $employee['id']; ?>"
                                               <?php echo in_array($employee['id'], $current_assignments) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="employee_<?php echo $employee['id']; ?>">
                                            <strong><?php echo htmlspecialchars($employee['name']); ?></strong>
                                            <small class="text-muted d-block">
                                                <?php echo htmlspecialchars($employee['email']); ?>
                                            </small>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="form-text mt-2">
                                    <div class="d-flex align-items-center">
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="selectAll">Select All</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAll">Deselect All</button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label for="notes" class="form-label">Assignment Notes (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                <div class="form-text">
                                    Add any relevant notes about this assignment.
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <a href="/admin/clients/view-assignments.php<?php echo $client_id > 0 ? '?client_id=' . $client_id : ''; ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i> Assign Employees
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select/deselect all functionality
    document.getElementById('selectAll')?.addEventListener('click', function() {
        document.querySelectorAll('input[name="employee_ids[]"]').forEach(function(checkbox) {
            checkbox.checked = true;
        });
    });
    
    document.getElementById('deselectAll')?.addEventListener('click', function() {
        document.querySelectorAll('input[name="employee_ids[]"]').forEach(function(checkbox) {
            checkbox.checked = false;
        });
    });
});
</script>

<?php
// Include footer
include '../../shared/templates/admin-footer.php';
?>