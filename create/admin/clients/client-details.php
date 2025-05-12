<?php
/**
 * Employee: Client Details
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
require_once '../../shared/crm/crm-functions.php';
require_once '../../shared/azure/azure-access.php';

// Page variables
$page_title = 'Client Details';
$current_page = 'client_details';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/employee/dashboard.php'],
    ['title' => 'Assigned Clients', 'url' => '/employee/clients/assigned-clients.php'],
    ['title' => 'Client Details', 'url' => '#']
];

// Get client ID from query string
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// Check if client exists and employee is assigned to it
$employee_id = $_SESSION['user_id'];
$client = null;

if ($client_id > 0) {
    // Check if employee is assigned to client
    if (is_employee_assigned_to_client($employee_id, $client_id)) {
        // Get client details
        $client = get_client_by_id($client_id);
    }
}

if (!$client) {
    set_notification('error', 'Client not found or you are not assigned to this client.');
    header('Location: /employee/clients/assigned-clients.php');
    exit;
}

// Log access to client
log_client_access($client_id, 'view', 'details');

// Get client CRM details
$crm = get_client_crm($client_id);

// Get client Azure containers
$containers = get_client_azure_containers($client_id);

// Include template parts
include '../../shared/templates/employee-head.php';
include '../../shared/templates/employee-sidebar.php';
include '../../shared/templates/employee-header.php';
?>

<main class="employee-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo htmlspecialchars($client['name']); ?></h1>
            
            <a href="/employee/clients/assigned-clients.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back to Clients
            </a>
        </div>
        
        <?php display_notifications(); ?>
        
        <div class="row">
            <!-- Client Information -->
            <div class="col-md-4 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Client Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="client-info">
                            <?php if (!empty($client['email'])): ?>
                            <div class="mb-3">
                                <label class="text-muted mb-1">Email</label>
                                <div><i class="bi bi-envelope me-2 text-secondary"></i> <?php echo htmlspecialchars($client['email']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($client['phone'])): ?>
                            <div class="mb-3">
                                <label class="text-muted mb-1">Phone</label>
                                <div><i class="bi bi-telephone me-2 text-secondary"></i> <?php echo htmlspecialchars($client['phone']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($client['address'])): ?>
                            <div class="mb-3">
                                <label class="text-muted mb-1">Address</label>
                                <div><i class="bi bi-geo-alt me-2 text-secondary"></i> <?php echo nl2br(htmlspecialchars($client['address'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($client['description'])): ?>
                            <div class="mb-3">
                                <label class="text-muted mb-1">Description</label>
                                <div><?php echo nl2br(htmlspecialchars($client['description'])); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-md-8 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- CRM Access -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="bi bi-people me-2 text-primary"></i> CRM Data
                                        </h5>
                                        <p class="card-text">
                                            <?php if ($crm): ?>
                                            Access client information in the CRM system.
                                            <?php else: ?>
                                            CRM integration is not set up for this client.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <a href="<?php echo $crm ? '/employee/clients/client-data.php?client_id=' . $client_id : '#'; ?>" 
                                           class="btn btn-primary btn-sm <?php echo $crm ? '' : 'disabled'; ?>">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> View CRM Data
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Files Access -->
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="bi bi-folder me-2 text-primary"></i> Client Files
                                        </h5>
                                        <p class="card-text">
                                            <?php if (!empty($containers)): ?>
                                            Access files stored in Azure Blob Storage.
                                            <?php else: ?>
                                            No Azure storage containers set up for this client.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <a href="/employee/media/client-files.php?client_id=<?php echo $client_id; ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-folder-symlink me-1"></i> View Files
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Azure Blob Storage Containers -->
        <?php if (!empty($containers)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Azure Storage Containers</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Container Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($containers as $container): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($container['container_name']); ?></td>
                                <td><?php echo !empty($container['description']) ? htmlspecialchars($container['description']) : '<em class="text-muted">No description</em>'; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary access-container" 
                                            data-container-id="<?php echo $container['id']; ?>"
                                            data-container-name="<?php echo htmlspecialchars($container['container_name']); ?>">
                                        <i class="bi bi-cloud"></i> Access Files
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Access Container Modal -->
<div class="modal fade" id="accessContainerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Access Azure Container</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>
                    You are accessing Azure container: <strong id="modalContainerName"></strong>
                </p>
                
                <div id="containerLoading" class="text-center" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Generating secure access link...</p>
                </div>
                
                <div id="containerError" class="alert alert-danger" style="display: none;">
                    Failed to generate access link. Please try again later.
                </div>
                
                <div id="containerSuccess" style="display: none;">
                    <div class="alert alert-success">
                        <p>Access link generated successfully!</p>
                        <p class="mb-0 small">This link will expire after <span id="expiryTime">1</span> hour(s).</p>
                    </div>
                    
                    <div class="mb-3">
                        <a href="#" id="containerLink" target="_blank" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-up-right me-2"></i> Open Azure Storage Explorer
                        </a>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="logAccess" checked>
                        <label class="form-check-label" for="logAccess">
                            Log this access in client activity log
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Azure Container Access
    document.querySelectorAll('.access-container').forEach(function(button) {
        button.addEventListener('click', function() {
            const containerId = this.getAttribute('data-container-id');
            const containerName = this.getAttribute('data-container-name');
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('accessContainerModal'));
            modal.show();
            
            // Set container name in modal
            document.getElementById('modalContainerName').textContent = containerName;
            
            // Show loading state
            document.getElementById('containerLoading').style.display = 'block';
            document.getElementById('containerError').style.display = 'none';
            document.getElementById('containerSuccess').style.display = 'none';
            
            // Generate SAS token
            fetch('/shared/azure/azure-sas-generator.php?container_id=' + containerId + '&read=1&list=1')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('containerLoading').style.display = 'none';
                    
                    if (data.success) {
                        document.getElementById('containerSuccess').style.display = 'block';
                        document.getElementById('containerLink').href = data.url;
                        document.getElementById('expiryTime').textContent = data.expiry_hours;
                    } else {
                        document.getElementById('containerError').style.display = 'block';
                        document.getElementById('containerError').textContent = data.message || 'Failed to generate access link.';
                    }
                })
                .catch(error => {
                    document.getElementById('containerLoading').style.display = 'none';
                    document.getElementById('containerError').style.display = 'block';
                    console.error('Error generating SAS token:', error);
                });
        });
    });
    
    // Log access when container link is clicked
    document.getElementById('containerLink').addEventListener('click', function() {
        if (document.getElementById('logAccess').checked) {
            const clientId = <?php echo $client_id; ?>;
            const data = {
                client_id: clientId,
                access_type: 'access',
                resource_type: 'azure_blob',
                resource_id: document.getElementById('modalContainerName').textContent
            };
            
            // Fire and forget - we don't need to wait for this to complete
            fetch('/api/log-access.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            }).catch(console.error);
        }
    });
});
</script>

<?php
// Include footer
include '../../shared/templates/employee-footer.php';
?>