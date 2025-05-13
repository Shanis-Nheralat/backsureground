<?php
/**
 * Admin Plan Management
 * 
 * Manages service plans, tiers, and client subscriptions
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/plans/plan-functions.php';
require_once '../../shared/utils/notifications.php';

// Authentication check
require_admin_auth();
require_admin_role(['admin']);

// Get all service plans
$service_plans = get_service_plans(false);

// Get all plan tiers
$plan_tiers = get_plan_tiers(false);

// Get all client plan subscriptions with client and plan info
try {
    $sql = "SELECT cps.*, u.name as client_name, sp.name as plan_name, pt.name as tier_name
            FROM client_plan_subscriptions cps
            JOIN users u ON cps.client_id = u.id
            JOIN service_plans sp ON cps.plan_id = sp.id
            JOIN plan_tiers pt ON cps.tier_id = pt.id
            ORDER BY cps.start_date DESC";
    
    $stmt = $pdo->query($sql);
    $client_subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Get Client Subscriptions Error: ' . $e->getMessage());
    $client_subscriptions = [];
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
            case 'add_plan':
                $plan_name = trim($_POST['plan_name'] ?? '');
                $plan_description = trim($_POST['plan_description'] ?? '');
                $is_active = isset($_POST['is_active']);
                
                if (empty($plan_name)) {
                    set_notification('error', 'Plan name is required.');
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO service_plans (name, description, is_active)
                            VALUES (?, ?, ?)
                        ");
                        
                        $stmt->execute([$plan_name, $plan_description, $is_active]);
                        set_notification('success', 'Service plan added successfully.');
                        
                        // Refresh page to show new plan
                        header('Location: manage.php');
                        exit;
                    } catch (PDOException $e) {
                        error_log('Add Plan Error: ' . $e->getMessage());
                        set_notification('error', 'Failed to add service plan.');
                    }
                }
                break;
                
            case 'add_tier':
                $tier_name = trim($_POST['tier_name'] ?? '');
                $tier_description = trim($_POST['tier_description'] ?? '');
                $display_order = (int)($_POST['display_order'] ?? 0);
                $is_active = isset($_POST['is_active']);
                
                if (empty($tier_name)) {
                    set_notification('error', 'Tier name is required.');
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO plan_tiers (name, description, display_order, is_active)
                            VALUES (?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([$tier_name, $tier_description, $display_order, $is_active]);
                        set_notification('success', 'Plan tier added successfully.');
                        
                        // Refresh page to show new tier
                        header('Location: manage.php');
                        exit;
                    } catch (PDOException $e) {
                        error_log('Add Tier Error: ' . $e->getMessage());
                        set_notification('error', 'Failed to add plan tier.');
                    }
                }
                break;
                
            case 'assign_plan':
                $client_id = (int)($_POST['client_id'] ?? 0);
                $plan_id = (int)($_POST['plan_id'] ?? 0);
                $tier_id = (int)($_POST['tier_id'] ?? 0);
                $start_date = $_POST['start_date'] ?? '';
                $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
                
                if (!$client_id || !$plan_id || !$tier_id || empty($start_date)) {
                    set_notification('error', 'All fields are required.');
                } else {
                    $created_by = $_SESSION['admin_user_id'];
                    
                    $plan_id = create_client_plan_subscription(
                        $client_id,
                        $plan_id,
                        $tier_id,
                        $start_date,
                        $end_date,
                        $created_by
                    );
                    
                    if ($plan_id) {
                        set_notification('success', 'Client plan subscription created successfully.');
                        
                        // Redirect to service assignment page
                        header('Location: services.php?plan_id=' . $plan_id);
                        exit;
                    } else {
                        set_notification('error', 'Failed to create client plan subscription.');
                    }
                }
                break;
                
            case 'update_plan':
                $plan_id = (int)($_POST['plan_id'] ?? 0);
                $plan_name = trim($_POST['plan_name'] ?? '');
                $plan_description = trim($_POST['plan_description'] ?? '');
                $is_active = isset($_POST['is_active']);
                
                if (!$plan_id || empty($plan_name)) {
                    set_notification('error', 'Plan ID and name are required.');
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE service_plans 
                            SET name = ?, description = ?, is_active = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        
                        $stmt->execute([$plan_name, $plan_description, $is_active, $plan_id]);
                        set_notification('success', 'Service plan updated successfully.');
                        
                        // Refresh page
                        header('Location: manage.php');
                        exit;
                    } catch (PDOException $e) {
                        error_log('Update Plan Error: ' . $e->getMessage());
                        set_notification('error', 'Failed to update service plan.');
                    }
                }
                break;
                
            case 'update_tier':
                $tier_id = (int)($_POST['tier_id'] ?? 0);
                $tier_name = trim($_POST['tier_name'] ?? '');
                $tier_description = trim($_POST['tier_description'] ?? '');
                $display_order = (int)($_POST['display_order'] ?? 0);
                $is_active = isset($_POST['is_active']);
                
                if (!$tier_id || empty($tier_name)) {
                    set_notification('error', 'Tier ID and name are required.');
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE plan_tiers 
                            SET name = ?, description = ?, display_order = ?, is_active = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        
                        $stmt->execute([$tier_name, $tier_description, $display_order, $is_active, $tier_id]);
                        set_notification('success', 'Plan tier updated successfully.');
                        
                        // Refresh page
                        header('Location: manage.php');
                        exit;
                    } catch (PDOException $e) {
                        error_log('Update Tier Error: ' . $e->getMessage());
                        set_notification('error', 'Failed to update plan tier.');
                    }
                }
                break;
        }
    }
}

// Get all clients for dropdown
try {
    $stmt = $pdo->prepare("
        SELECT id, name, username 
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
    'active' => 'badge bg-success',
    'pending' => 'badge bg-warning',
    'expired' => 'badge bg-secondary',
    'cancelled' => 'badge bg-danger'
];

// Page variables
$page_title = 'Manage Business Care Plans';
$current_page = 'manage_plans';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Business Care Plans', 'url' => '#']
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
        
        <div class="row">
            <!-- Service Plans -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Service Plans</h6>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                            <i class="fas fa-plus me-1"></i> Add Plan
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($service_plans)): ?>
                            <div class="alert alert-info">No service plans defined yet.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($service_plans as $plan): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($plan['name']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $plan['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info edit-plan-btn" 
                                                            data-bs-toggle="modal" data-bs-target="#editPlanModal"
                                                            data-plan-id="<?php echo $plan['id']; ?>"
                                                            data-plan-name="<?php echo htmlspecialchars($plan['name']); ?>"
                                                            data-plan-description="<?php echo htmlspecialchars($plan['description'] ?? ''); ?>"
                                                            data-plan-active="<?php echo $plan['is_active'] ? '1' : '0'; ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <a href="services.php?plan_id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-cogs"></i> Services
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
            
            <!-- Plan Tiers -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Plan Tiers</h6>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTierModal">
                            <i class="fas fa-plus me-1"></i> Add Tier
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($plan_tiers)): ?>
                            <div class="alert alert-info">No plan tiers defined yet.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Order</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($plan_tiers as $tier): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tier['name']); ?></td>
                                                <td><?php echo $tier['display_order']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $tier['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $tier['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info edit-tier-btn" 
                                                            data-bs-toggle="modal" data-bs-target="#editTierModal"
                                                            data-tier-id="<?php echo $tier['id']; ?>"
                                                            data-tier-name="<?php echo htmlspecialchars($tier['name']); ?>"
                                                            data-tier-description="<?php echo htmlspecialchars($tier['description'] ?? ''); ?>"
                                                            data-tier-order="<?php echo $tier['display_order']; ?>"
                                                            data-tier-active="<?php echo $tier['is_active'] ? '1' : '0'; ?>">
                                                        <i class="fas fa-edit"></i> Edit
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
        </div>
        
        <!-- Client Plan Subscriptions -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Client Plan Subscriptions</h6>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignPlanModal">
                    <i class="fas fa-plus me-1"></i> Assign Plan to Client
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($client_subscriptions)): ?>
                    <div class="alert alert-info">No client plan subscriptions yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Plan</th>
                                    <th>Tier</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($client_subscriptions as $subscription): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subscription['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($subscription['plan_name']); ?></td>
                                        <td><?php echo htmlspecialchars($subscription['tier_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($subscription['start_date'])); ?></td>
                                        <td>
                                            <?php 
                                            echo !empty($subscription['end_date']) 
                                                ? date('M d, Y', strtotime($subscription['end_date'])) 
                                                : 'Ongoing'; 
                                            ?>
                                        </td>
                                        <td>
                                            <span class="<?php echo $status_badges[$subscription['status']]; ?>">
                                                <?php echo ucfirst($subscription['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="services.php?plan_id=<?php echo $subscription['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-cogs"></i> Services
                                            </a>
                                            <a href="insights.php?client_id=<?php echo $subscription['client_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-chart-line"></i> Insights
                                            </a>
                                            <a href="documents.php?client_id=<?php echo $subscription['client_id']; ?>" class="btn btn-sm btn-secondary">
                                                <i class="fas fa-file"></i> Documents
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
</main>

<!-- Add Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1" aria-labelledby="addPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPlanModalLabel">Add Service Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="form_action" value="add_plan">
                    
                    <div class="mb-3">
                        <label for="plan_name" class="form-label">Plan Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="plan_name" name="plan_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="plan_description" class="form-label">Description</label>
                        <textarea class="form-control" id="plan_description" name="plan_description" rows="3"></textarea>
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
                    <button type="submit" class="btn btn-primary">Add Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1" aria-labelledby="editPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPlanModalLabel">Edit Service Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="form_action" value="update_plan">
                    <input type="hidden" id="edit_plan_id" name="plan_id">
                    
                    <div class="mb-3">
                        <label for="edit_plan_name" class="form-label">Plan Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_plan_name" name="plan_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_plan_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_plan_description" name="plan_description" rows="3"></textarea>
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
                    <button type="submit" class="btn btn-primary">Update Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Tier Modal -->
<div class="modal fade" id="addTierModal" tabindex="-1" aria-labelledby="addTierModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTierModalLabel">Add Plan Tier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="form_action" value="add_tier">
                    
                    <div class="mb-3">
                        <label for="tier_name" class="form-label">Tier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tier_name" name="tier_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tier_description" class="form-label">Description</label>
                        <textarea class="form-control" id="tier_description" name="tier_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="display_order" name="display_order" value="0" min="0">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="tier_is_active" name="is_active" checked>
                        <label class="form-check-label" for="tier_is_active">
                            Active
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Tier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Tier Modal -->
<div class="modal fade" id="editTierModal" tabindex="-1" aria-labelledby="editTierModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTierModalLabel">Edit Plan Tier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="form_action" value="update_tier">
                    <input type="hidden" id="edit_tier_id" name="tier_id">
                    
                    <div class="mb-3">
                        <label for="edit_tier_name" class="form-label">Tier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_tier_name" name="tier_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_tier_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_tier_description" name="tier_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="edit_display_order" name="display_order" min="0">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="edit_tier_is_active" name="is_active">
                        <label class="form-check-label" for="edit_tier_is_active">
                            Active
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Tier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Plan Modal -->
<div class="modal fade" id="assignPlanModal" tabindex="-1" aria-labelledby="assignPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignPlanModalLabel">Assign Plan to Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="form_action" value="assign_plan">
                    
                    <div class="mb-3">
                        <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                        <select class="form-select" id="client_id" name="client_id" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>">
                                    <?php echo htmlspecialchars($client['name']); ?> (<?php echo htmlspecialchars($client['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="plan_id" class="form-label">Service Plan <span class="text-danger">*</span></label>
                        <select class="form-select" id="plan_id" name="plan_id" required>
                            <option value="">Select Plan</option>
                            <?php foreach ($service_plans as $plan): ?>
                                <?php if ($plan['is_active']): ?>
                                    <option value="<?php echo $plan['id']; ?>">
                                        <?php echo htmlspecialchars($plan['name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tier_id" class="form-label">Plan Tier <span class="text-danger">*</span></label>
                        <select class="form-select" id="tier_id" name="tier_id" required>
                            <option value="">Select Tier</option>
                            <?php foreach ($plan_tiers as $tier): ?>
                                <?php if ($tier['is_active']): ?>
                                    <option value="<?php echo $tier['id']; ?>">
                                        <?php echo htmlspecialchars($tier['name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="end_date" class="form-label">End Date (Optional)</label>
                        <input type="date" class="form-control" id="end_date" name="end_date">
                        <small class="text-muted">Leave blank for ongoing subscription.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Initialize modals
    document.addEventListener('DOMContentLoaded', function() {
        // Edit Plan Modal
        const editPlanButtons = document.querySelectorAll('.edit-plan-btn');
        editPlanButtons.forEach(button => {
            button.addEventListener('click', function() {
                const planId = this.getAttribute('data-plan-id');
                const planName = this.getAttribute('data-plan-name');
                const planDescription = this.getAttribute('data-plan-description');
                const planActive = this.getAttribute('data-plan-active') === '1';
                
                document.getElementById('edit_plan_id').value = planId;
                document.getElementById('edit_plan_name').value = planName;
                document.getElementById('edit_plan_description').value = planDescription;
                document.getElementById('edit_is_active').checked = planActive;
            });
        });
        
        // Edit Tier Modal
        const editTierButtons = document.querySelectorAll('.edit-tier-btn');
        editTierButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tierId = this.getAttribute('data-tier-id');
                const tierName = this.getAttribute('data-tier-name');
                const tierDescription = this.getAttribute('data-tier-description');
                const tierOrder = this.getAttribute('data-tier-order');
                const tierActive = this.getAttribute('data-tier-active') === '1';
                
                document.getElementById('edit_tier_id').value = tierId;
                document.getElementById('edit_tier_name').value = tierName;
                document.getElementById('edit_tier_description').value = tierDescription;
                document.getElementById('edit_display_order').value = tierOrder;
                document.getElementById('edit_tier_is_active').checked = tierActive;
            });
        });
        
        // Set default start date to today
        const startDateInput = document.getElementById('start_date');
        if (startDateInput) {
            const today = new Date();
            const formattedDate = today.toISOString().substr(0, 10);
            startDateInput.value = formattedDate;
        }
    });
</script>

<?php include '../../shared/templates/admin-footer.php'; ?>