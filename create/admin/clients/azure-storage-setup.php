<?php
/**
 * Admin: Azure Storage Setup for Client
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
require_once '../../shared/azure/azure-access.php';

// Page variables
$page_title = 'Azure Storage Setup';
$current_page = 'azure_storage_setup';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/admin/dashboard.php'],
    ['title' => 'Clients', 'url' => '/admin/clients/manage-clients.php'],
    ['title' => 'Azure Storage Setup', 'url' => '#']
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
} else {
    set_notification('error', 'Client ID is required.');
    header('Location: /admin/clients/manage-clients.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
    } else {
        // Get form data
        $container_name = isset($_POST['container_name']) ? trim($_POST['container_name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
        if (empty($container_name)) {
            set_notification('error', 'Container name is required.');
        } else {
            // Validate container name (lowercase letters, numbers, and hyphens only)
            if (!preg_match('/^[a-z0-9-]+$/', $container_name)) {
                set_notification('error', 'Container name must contain only lowercase letters, numbers, and hyphens.');
            } else {
                // Create Azure container
                $result = create_azure_container(
                    $container_name,
                    $client_id,
                    $description
                );
                
                if ($result) {
                    set_notification('success', "Successfully created Azure container '{$container_name}' for client.");
                    header('Location: /admin/clients/azure-storage-setup.php?client_id=' . $client_id);
                    exit;
                } else {
                    set_notification('error', 'Failed to create Azure container. Please check your Azure credentials.');
                }
            }
        }
    }
}

// Get client's Azure containers
$containers = get_client_azure_containers($client_id);

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
            <h1 class="h3 mb-0">
                <?php echo 'Azure Storage for ' . htmlspecialchars($client['name']); ?>
            </h1>
            
            <a href="/admin/clients/client-details.php?id=<?php echo $client_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back to Client
            </a>
        </div>
        
        <?php display_notifications(); ?>
        
        <!-- Azure settings check -->
        <?php 
        $azure_account = get_setting('azure_storage_account', '');
        $azure_key = get_setting('azure_storage_key', '');
        
        if (empty($azure_account) || empty($azure_key)):
        ?>
        <div class="alert alert-warning mb-4">
            <h5><i class="bi bi-exclamation-triangle me-2"></i> Azure Storage Not Configured</h5>
            <p>
                Azure Storage settings are not fully configured. Please set up the following settings:
            </p>
            <ul>
                <li>Azure Storage Account Name</li>
                <li>Azure Storage Account Key</li>
            </ul>
            <a href="/admin/settings/admin-integrations.php" class="btn btn-primary mt-2">
                Configure Azure Settings
            </a>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Create new container -->
            <div class="col-md-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Create New Container</h6>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <!-- CSRF protection -->
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-3">
                                <label for="container_name" class="form-label">Container Name</label>
                                <input type="text" class="form-control" id="container_name" name="container_name" 
                                       pattern="[a-z0-9-]+" required placeholder="client-documents">
                                <div class="form-text">
                                    Lowercase letters, numbers, and hyphens only. Container names must be unique across your Azure Storage account.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description (Optional)</label>
                                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" <?php echo (empty($azure_account) || empty($azure_key)) ? 'disabled' : ''; ?>>
                                <i class="bi bi-plus-circle me-2"></i> Create Container
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Existing containers -->
            <div class="col-md-7">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Client Containers</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($containers)): ?>
                        <div class="alert alert-info">
                            No Azure containers have been created for this client yet.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Container Name</th>
                                        <th>Description</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($containers as $container): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($container['container_name']); ?></td>
                                        <td><?php echo !empty($container['description']) ? htmlspecialchars($container['description']) : '<em class="text-muted">No description</em>'; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($container['created_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary generate-sas" 
                                                    data-bs-toggle="modal" data-bs-target="#sasModal" 
                                                    data-container-id="<?php echo $container['id']; ?>"
                                                    data-container-name="<?php echo htmlspecialchars($container['container_name']); ?>">
                                                <i class="bi bi-key"></i> Generate SAS
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
    </div>
</main>

<!-- SAS Token Modal -->
<div class="modal fade" id="sasModal" tabindex="-1" aria-labelledby="sasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sasModalLabel">Generate SAS Token</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="sasForm">
                    <input type="hidden" id="container_id" name="container_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Container Name</label>
                        <div class="form-control-plaintext" id="modal_container_name"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="perm_read" name="read" value="1" checked>
                            <label class="form-check-label" for="perm_read">Read</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="perm_write" name="write" value="1">
                            <label class="form-check-label" for="perm_write">Write</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="perm_delete" name="delete" value="1">
                            <label class="form-check-label" for="perm_delete">Delete</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="perm_list" name="list" value="1" checked>
                            <label class="form-check-label" for="perm_list">List</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="expiry" class="form-label">Expiry Time (hours)</label>
                        <input type="number" class="form-control" id="expiry" name="expiry" min="1" max="24" value="1">
                        <div class="form-text">
                            How long the SAS token should be valid for (1-24 hours).
                        </div>
                    </div>
                </form>
                
                <div id="sasResult" style="display: none;">
                    <div class="alert alert-success">
                        <p>SAS token generated successfully! The URL below can be used to access the container:</p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Container URL with SAS token</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="sasUrl" readonly>
                            <button class="btn btn-outline-secondary" type="button" id="copySasUrl">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        <div class="form-text">
                            This URL will expire after <span id="expiryTime">1</span> hour(s).
                        </div>
                    </div>
                </div>
                
                <div id="sasError" class="alert alert-danger" style="display: none;">
                    Failed to generate SAS token. Please check your Azure settings and try again.
                </div>
                
                <div id="sasLoading" style="display: none;">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <p class="text-center mt-2">Generating SAS token...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="generateSas">Generate Token</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set container ID and name in modal when button is clicked
    document.querySelectorAll('.generate-sas').forEach(function(button) {
        button.addEventListener('click', function() {
            const containerId = this.getAttribute('data-container-id');
            const containerName = this.getAttribute('data-container-name');
            
            document.getElementById('container_id').value = containerId;
            document.getElementById('modal_container_name').textContent = containerName;
            
            // Reset modal state
            document.getElementById('sasResult').style.display = 'none';
            document.getElementById('sasError').style.display = 'none';
            document.getElementById('sasLoading').style.display = 'none';
            document.getElementById('sasForm').style.display = 'block';
            document.getElementById('generateSas').style.display = 'block';
        });
    });
    
    // Generate SAS token when button is clicked
    document.getElementById('generateSas').addEventListener('click', function() {
        const containerId = document.getElementById('container_id').value;
        
        if (!containerId) {
            return;
        }
        
        // Show loading state
        document.getElementById('sasForm').style.display = 'none';
        document.getElementById('sasResult').style.display = 'none';
        document.getElementById('sasError').style.display = 'none';
        document.getElementById('sasLoading').style.display = 'block';
        document.getElementById('generateSas').style.display = 'none';
        
        // Build query parameters
        const params = new URLSearchParams();
        params.append('container_id', containerId);
        
        if (document.getElementById('perm_read').checked) {
            params.append('read', '1');
        }
        
        if (document.getElementById('perm_write').checked) {
            params.append('write', '1');
        }
        
        if (document.getElementById('perm_delete').checked) {
            params.append('delete', '1');
        }
        
        if (document.getElementById('perm_list').checked) {
            params.append('list', '1');
        }
        
        const expiry = document.getElementById('expiry').value;
        if (expiry) {
            params.append('expiry', expiry);
        }
        
        // Call Azure SAS generator
        fetch('/shared/azure/azure-sas-generator.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                document.getElementById('sasLoading').style.display = 'none';
                
                if (data.success) {
                    document.getElementById('sasUrl').value = data.url;
                    document.getElementById('expiryTime').textContent = data.expiry_hours;
                    document.getElementById('sasResult').style.display = 'block';
                } else {
                    document.getElementById('sasError').style.display = 'block';
                    document.getElementById('generateSas').style.display = 'block';
                    document.getElementById('sasForm').style.display = 'block';
                }
            })
            .catch(error => {
                document.getElementById('sasLoading').style.display = 'none';
                document.getElementById('sasError').style.display = 'block';
                document.getElementById('generateSas').style.display = 'block';
                document.getElementById('sasForm').style.display = 'block';
                console.error('Error generating SAS token:', error);
            });
    });
    
    // Copy SAS URL to clipboard
    document.getElementById('copySasUrl').addEventListener('click', function() {
        const sasUrl = document.getElementById('sasUrl');
        sasUrl.select();
        document.execCommand('copy');
        
        // Show copy confirmation
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="bi bi-check"></i> Copied!';
        
        setTimeout(() => {
            this.innerHTML = originalText;
        }, 2000);
    });
});
</script>

<?php
// Include footer
include '../../shared/templates/admin-footer.php';
?>