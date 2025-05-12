<?php
/**
 * Employee: Assigned Clients
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';

// Authentication check - require employee role
require_admin_auth();
require_admin_role(['employee']);

// Include other required components
require_once '../../shared/admin-notifications.php';
require_once '../../shared/employee/employee-functions.php';

// Page variables
$page_title = 'My Assigned Clients';
$current_page = 'assigned_clients';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/employee/dashboard.php'],
    ['title' => 'Assigned Clients', 'url' => '#']
];

// Get employee ID from session
$employee_id = $_SESSION['user_id'];

// Get assigned clients
$clients = get_employee_assigned_clients($employee_id);

// Include template parts
include '../../shared/templates/employee-head.php';
include '../../shared/templates/employee-sidebar.php';
include '../../shared/templates/employee-header.php';
?>

<main class="employee-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
        </div>
        
        <?php display_notifications(); ?>
        
        <?php if (empty($clients)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i> You are not assigned to any clients yet. Please contact your administrator.
        </div>
        <?php else: ?>
        
        <div class="row">
            <?php foreach ($clients as $client): ?>
            <div class="col-md-4 mb-4">
                <div class="card shadow h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($client['name']); ?></h5>
                        
                        <?php if (!empty($client['description'])): ?>
                        <p class="card-text text-muted mb-3"><?php echo htmlspecialchars($client['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="client-info">
                            <?php if (!empty($client['email'])): ?>
                            <div class="mb-2">
                                <i class="bi bi-envelope me-2 text-secondary"></i> <?php echo htmlspecialchars($client['email']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($client['phone'])): ?>
                            <div class="mb-2">
                                <i class="bi bi-telephone me-2 text-secondary"></i> <?php echo htmlspecialchars($client['phone']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($client['address'])): ?>
                            <div class="mb-3">
                                <i class="bi bi-geo-alt me-2 text-secondary"></i> <?php echo nl2br(htmlspecialchars($client['address'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-grid gap-2">
                            <a href="/employee/clients/client-details.php?client_id=<?php echo $client['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-1"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php endif; ?>
    </div>
</main>

<?php
// Include footer
include '../../shared/templates/employee-footer.php';
?>