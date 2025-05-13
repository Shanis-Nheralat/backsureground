<?php
/**
 * Admin Services Management
 * 
 * Manages services for plans and client subscriptions
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/plans/plan-functions.php';
require_once '../../shared/utils/notifications.php';

// Authentication check
require_admin_auth();
require_admin_role(['admin']);

// Get plan ID from query string
$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;

// Check if plan exists
$plan = null;
$client_plan = false;
$client_id = 0;

if ($plan_id) {
    // Check if this is a client plan subscription or a service plan
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM client_plan_subscriptions WHERE id = ?
        ");
        $stmt->execute([$plan_id]);
        $client_plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client_plan) {
            // This is a client plan subscription
            $plan = get_service_plan($client_plan['plan_id']);
            $client_id = $client_plan['client_id'];
            
            // Get client name
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            $client_name = $client ? $client['name'] : 'Unknown Client';
        } else {
            // This is a service plan
            $plan = get_service_plan($plan_id);
        }
    } catch (PDOException $e) {
        error_log('Check Plan Type Error: ' . $e->getMessage());
    }
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
    } else {
        // Determine which form was submitted
        $form_action = $_POST['form_action'] ?? '';
        
        switch ($form_action) {
            case 'add_service':
                $service_plan_id = (int)($_POST['service_plan_id'] ?? 0);
                $service_name = trim($_POST['service_name'] ?? '');
                $service_description = trim($_POST['service_description'] ?? '');
                $service_icon = trim($_POST['service_icon'] ?? 'fas fa-chart-line');
                $is_active = isset($_POST['is_active']);
                
                if (!$service_plan_id || empty($service_name)) {
                    set_notification('error', 'Plan ID and service name are required.');
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO services 
                            (plan_id, name, description, icon, is_active)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([$service_plan_id, $service_name, $service_description, $service_icon, $is_active]);
                        set_notification('success', 'Service added successfully.');
                        
                        // Refresh page to show new service
                        header('Location: services.php?plan_id=' . $plan_id);
                        exit;
                    } catch (PDOException $e) {
                        error_log('Add Service Error: ' . $e->getMessage());
                        set_notification('error', 'Failed to add service.');
                    }
                }
                break;
                
            case 'update_service':
                $service_id = (int)($_POST['service_id'] ?? 0);
                $service_name = trim($_POST['service_name'] ?? '');
                $service_description = trim($_POST['service_description'] ?? '');
                $service_icon = trim($_POST['service_icon'] ?? 'fas fa-chart-line');
                $is_active = isset($_POST['is_active']);
                
                if (!$service_id || empty($service_name)) {
                    set_notification('error', 'Service ID and name are required.');
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE services 
                            SET name = ?, description = ?, icon = ?, is_active = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        
                        $stmt->execute([$service_name, $service_description, $service_icon, $is_active, $service_id]);
                        set_notification('success', 'Service updated successfully.');
                        
                        // Refresh page
                        header('Location: services.php?plan_id=' . $plan_id);
                        exit;
                    } catch (PDOException $e) {
                        error_log('Update Service Error: ' . $e->getMessage());
                        set_notification('error', 'Failed to update service.');
                    }
                }
                break;
                
            case 'add_client_service':
                $client_plan_id = (int)($_POST['client_plan_id'] ?? 0);
                $service_id = (int)($_POST['service_id'] ?? 0);
                $additional_details = trim($_POST['additional_details'] ?? '');
                
                if (!$client_plan_id || !$service_id) {
                    set_notification('error', 'Client plan ID and service ID are required.');
                } else {
                    // Check if service is already assigned
                    try {
                        $stmt = $pdo->prepare("
                            SELECT id FROM client_service_subscriptions
                            WHERE client_plan_id = ? AND service_id = ?
                        ");
                        
                        $stmt->execute([$client_plan_id, $service_id]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existing) {
                            set_notification('error', 'This service is already assigned to the client.');
                        } else {
                            $result = add_client_service_subscription($client_plan_id, $service_id, $additional_details);
                            
                            if ($result) {
                                set_notification('success', 'Service assigned to client successfully.');
                                
                                // Refresh page
                                header('Location: services.php?plan_id=' . $plan_id);
                                exit;
                            } else {
                                set_notification('error', 'Failed to assign service to client.');
                            }
                        }
                    } catch (PDOException $e) {
                        error_log('Add Client Service Error: ' . $e->getMessage());
                        set_notification('error', 'Failed to assign service to client.');
                    }
                }
                break;
                
            case 'remove_client_service':
                $subscription_id = (int)($_POST['subscription_id'] ?? 0);
                
                if (!$subscription_id) {
                    set_notification('error', 'Subscription ID is required.');
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            DELETE FROM client_service_subscriptions
                            WHERE id = ?
                        ");
                        
                        $stmt->execute([$subscription_id]);
                        set_notification('success', 'Service removed from client successfully.');
                        
                        // Refresh page
                        header('Location: services.php?plan_id=' . $plan_id);
                        exit;
                    } catch (PDOException $e) {
                        error_log('Remove Client Service Error: ' . $e->getMessage());
                        set_notification('error', 'Failed to remove service from client.');
                    }
                }
                break;
        }
    }
}

// Get services for this plan
$services = [];
if ($plan) {
    $services = get_services_by_plan($client_plan ? $client_plan['plan_id'] : $plan_id, false);
}

// Get client service subscriptions
$client_services = [];
if ($client_plan) {
    $client_services = get_client_service_subscriptions($client_plan['id']);
    
    // Get service IDs for assigned services
    $assigned_service_ids = array_column($client_services, 'service_id');
    
    // Filter services to only show unassigned ones
    $available_services = array_filter($services, function($service) use ($assigned_service_ids) {
        return !in_array($service['id'], $assigned_service_ids);
    });
}

// Page variables
if ($client_plan) {
    $page_title = 'Client Services: ' . $client_name;
    $current_page = 'client_services';
} else {
    $page_title = $plan ? 'Services for Plan: ' . $plan['name'] : 'Services';
    $current_page = 'plan_services';
}

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Business Care Plans', 'url' => 'manage.php'],
    ['title' => $page_title, 'url' => '#']
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
            <div>
                <a href="manage.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Plans
                </a>
                
                <?php if (!$client_plan && $plan): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                        <i class="fas fa-plus me-1"></i> Add Service
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <?php if (!$plan): ?>
            <div class="alert alert-warning">
                <p>Please select a valid plan to manage services.</p>
            </div>
        <?php else: ?>
            <?php if ($client_plan): ?>
                <!-- Client Plan Information -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Client Plan Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Client:</strong> <?php echo htmlspecialchars($client_name); ?></p>
                                <p><strong>Plan:</strong> <?php echo htmlspecialchars($plan['name']); ?></p>
                                <p><strong>Status:</strong> <?php echo ucfirst($client_plan['status']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Start Date:</strong> <?php echo date('F j, Y', strtotime($client_plan['start_date'])); ?></p>
                                <p><strong>End Date:</strong> <?php echo $client_plan['end_date'] ? date('F j, Y', strtotime($client_plan['end_date'])) : 'Ongoing'; ?></p>
                                <p><strong>Created By:</strong> Admin</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Assigned Services -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Assigned Services</h6>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addClientServiceModal">
                            <i class="fas fa-plus me-1"></i> Add Service
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($client_services)): ?>
                            <div class="alert alert-info">No services assigned to this client yet.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Service</th>
                                            <th>Description</th>
                                            <th>Additional Details</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($client_services as $service): ?>
                                            <tr>
                                                <td>
                                                    <i class="<?php echo htmlspecialchars($service['icon']); ?> me-2"></i>
                                                    <?php echo htmlspecialchars($service['service_name']); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    // Get service description from main services list
                                                    $description = '';
                                                    foreach ($services as $s) {
                                                        if ($s['id'] == $service['service_id']) {
                                                            $description = $s['description'] ?? '';
                                                            break;
                                                        }
                                                    }
                                                    echo htmlspecialchars($description);
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($service['additional_details'] ?? ''); ?></td>
                                                <td>
                                                    <form method="post" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to remove this service?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="form_action" value="remove_client_service">
                                                        <input type="hidden" name="subscription_id" value="<?php echo $service['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </form>
                                                    
                                                    <a href="document-categories.php?service_id=<?php echo $service['service_id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-folder"></i> Categories
                                                    </a>
                                                    
                                                    <a href="insights.php?client_id=<?php echo $client_id; ?>&service_id=<?php echo $service['service_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-chart-line"></i> Insights
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
                <!-- Plan Services -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Plan Services</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($services)): ?>
                            <div class="alert alert-info">No services defined for this plan yet.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Service</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($services as $service): ?>
                                            <tr>
                                                <td>
                                                    <i class="<?php echo htmlspecialchars($service['icon']); ?> me-2"></i>
                                                    <?php echo htmlspecialchars($service['name']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($service['description'] ?? ''); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $service['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info edit-service-btn" 
                                                            data-bs-toggle="modal" data-bs-target="#editServiceModal"
                                                            data-service-id="<?php echo $service['id']; ?>"
                                                            data-service-name="<?php echo htmlspecialchars($service['name']); ?>"
                                                            data-service-description="<?php echo htmlspecialchars($service['description'] ?? ''); ?>"
                                                            data-service-icon="<?php echo htmlspecialchars($service['icon'] ?? 'fas fa-chart-line'); ?>"
                                                            data-service-active="<?php echo $service['is_active'] ? '1' : '0'; ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    
                                                    <a href="document-categories.php?service_id=<?php echo $service['id']; ?>" class="btn btn-sm btn-secondary">
                                                        <i class="fas fa-folder"></i> Categories
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
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Add Service Modal -->
<?php if (!$client_plan && $plan): ?>
<div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addServiceModalLabel">Add Service to Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="form_action" value="add_service">
                    <input type="hidden" name="service_plan_id" value="<?php echo $plan_id; ?>">
                    
                    <div class="mb-3">
                        <label for="service_name" class="form-label">Service Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="service_name" name="service_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="service_description" class="form-label">Description</label>
                        <textarea class="form-control" id="service_description" name="service_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="service_icon" class="form-label">Icon</label>
                        <input type="text" class="form-control" id="service_icon" name="service_icon" value="fas fa-chart-line">
                        <small class="text-muted">Use Font Awesome class names (e.g., fas fa-chart-line)</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">
                            Active
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Service Modal -->
<div class="modal fade" id="editServiceModal" tabindex="-1" aria-labelledby="editServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editServiceModalLabel">Edit Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="form_action" value="update_service">
                    <input type="hidden" id="edit_service_id" name="service_id">
                    
                    <div class="mb-3">
                        <label for="edit_service_name" class="form-label">Service Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_service_name" name="service_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_service_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_service_description" name="service_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_service_icon" class="form-label">Icon</label>
                        <input type="text" class="form-control" id="edit_service_icon" name="service_icon">
                        <small class="text-muted">Use Font Awesome class names (e.g., fas fa-chart-line)</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">
                            Active
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Service</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Client Service Modal -->
<?php if ($client_plan): ?>
<div class="modal fade" id="addClientServiceModal" tabindex="-1" aria-labelledby="addClientServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addClientServiceModalLabel">Add Service to Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="form_action" value="add_client_service">
                    <input type="hidden" name="client_plan_id" value="<?php echo $client_plan['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="service_id" class="form-label">Service <span class="text-danger">*</span></label>
                        <select class="form-select" id="service_id" name="service_id" required>
                            <option value="">Select Service</option>
                            <?php foreach ($available_services as $service): ?>
                                <?php if ($service['is_active']): ?>
                                    <option value="<?php echo $service['id']; ?>">
                                        <?php echo htmlspecialchars($service['name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($available_services)): ?>
                            <small class="text-danger">No more available services for this plan.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="additional_details" class="form-label">Additional Details</label>
                        <textarea class="form-control" id="additional_details" name="additional_details" rows="3"></textarea>
                        <small class="text-muted">Optional notes about this service for the client.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" <?php echo empty($available_services) ? 'disabled' : ''; ?>>
                        Add Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Initialize modals
    document.addEventListener('DOMContentLoaded', function() {
        // Edit Service Modal
        const editServiceButtons = document.querySelectorAll('.edit-service-btn');
        editServiceButtons.forEach(button => {
            button.addEventListener('click', function() {
                const serviceId = this.getAttribute('data-service-id');
                const serviceName = this.getAttribute('data-service-name');
                const serviceDescription = this.getAttribute('data-service-description');
                const serviceIcon = this.getAttribute('data-service-icon');
                const serviceActive = this.getAttribute('data-service-active') === '1';
                
                document.getElementById('edit_service_id').value = serviceId;
                document.getElementById('edit_service_name').value = serviceName;
                document.getElementById('edit_service_description').value = serviceDescription;
                document.getElementById('edit_service_icon').value = serviceIcon;
                document.getElementById('edit_is_active').checked = serviceActive;
            });
        });
    });
</script>

<?php include '../../shared/templates/admin-footer.php'; ?>