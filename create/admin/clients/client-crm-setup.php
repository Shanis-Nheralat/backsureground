<?php
/**
 * Admin: Client CRM Setup
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
require_once '../../shared/crm/crm-functions.php';

// Page variables
$page_title = 'Client CRM Setup';
$current_page = 'client_crm_setup';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/admin/dashboard.php'],
    ['title' => 'Clients', 'url' => '/admin/clients/manage-clients.php'],
    ['title' => 'CRM Setup', 'url' => '#']
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

// Get current CRM settings if they exist
$crm = get_client_crm($client_id);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
    } else {
        // Get form data
        $crm_type = $_POST['crm_type'] ?? '';
        $crm_identifier = $_POST['crm_identifier'] ?? '';
        $crm_url = $_POST['crm_url'] ?? '';
        $api_key = $_POST['api_key'] ?? '';
        
        if (empty($crm_type) || empty($crm_identifier) || empty($crm_url)) {
            set_notification('error', 'CRM Type, Identifier, and URL are required.');
        } else {
            // Set up CRM integration
            $result = setup_client_crm(
                $client_id,
                $crm_type,
                $crm_identifier,
                $crm_url,
                $api_key
            );
            
            if ($result) {
                set_notification('success', 'CRM integration has been set up successfully.');
                header('Location: /admin/clients/client-crm-setup.php?client_id=' . $client_id);
                exit;
            } else {
                set_notification('error', 'Failed to set up CRM integration. Please try again.');
            }
        }
    }
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
            <h1 class="h3 mb-0">
                <?php echo 'CRM Setup for ' . htmlspecialchars($client['name']); ?>
            </h1>
            
            <a href="/admin/clients/client-details.php?id=<?php echo $client_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back to Client
            </a>
        </div>
        
        <?php display_notifications(); ?>
        
        <div class="row">
            <div class="col-md-7 mx-auto">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">
                            <?php echo $crm ? 'Edit CRM Integration' : 'Set Up CRM Integration'; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <!-- CSRF protection -->
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-3">
                                <label for="crm_type" class="form-label">CRM Type</label>
                                <select class="form-select" id="crm_type" name="crm_type" required>
                                    <option value="">-- Select CRM Type --</option>
                                    <option value="zoho" <?php echo $crm && $crm['crm_type'] == 'zoho' ? 'selected' : ''; ?>>Zoho CRM</option>
                                    <option value="dynamics" <?php echo $crm && $crm['crm_type'] == 'dynamics' ? 'selected' : ''; ?>>Microsoft Dynamics 365</option>
                                    <option value="salesforce" <?php echo $crm && $crm['crm_type'] == 'salesforce' ? 'selected' : ''; ?>>Salesforce</option>
                                    <option value="custom" <?php echo $crm && $crm['crm_type'] == 'custom' ? 'selected' : ''; ?>>Custom CRM</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="crm_identifier" class="form-label">CRM Identifier</label>
                                <input type="text" class="form-control" id="crm_identifier" name="crm_identifier" 
                                       value="<?php echo $crm ? htmlspecialchars($crm['crm_identifier']) : ''; ?>" required>
                                <div class="form-text">
                                    Client's unique identifier in the CRM system (e.g., Account ID, Organization ID).
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="crm_url" class="form-label">CRM URL</label>
                                <input type="url" class="form-control" id="crm_url" name="crm_url" 
                                       value="<?php echo $crm ? htmlspecialchars($crm['crm_url']) : ''; ?>" required>
                                <div class="form-text">
                                    URL to embed the CRM view or access the CRM API (e.g., https://crm.zoho.com/client/123).
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="api_key" class="form-label">API Key / Authentication Token (Optional)</label>
                                <input type="text" class="form-control" id="api_key" name="api_key" 
                                       value="<?php echo $crm ? htmlspecialchars($crm['api_key']) : ''; ?>">
                                <div class="form-text">
                                    API key or token for accessing the CRM API or embedding the CRM view.
                                </div>
                            </div>
                            
                            <div class="form-text mb-4">
                                <div class="alert alert-info">
                                    <h6><i class="bi bi-info-circle me-2"></i> Important Notes</h6>
                                    <ul class="mb-0">
                                        <li>This integration will enable employees to view client data from the CRM system.</li>
                                        <li>Make sure the CRM URL and API key provide appropriate access levels.</li>
                                        <li>For iframe embedding, the CRM system must allow embedding in external sites.</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <a href="/admin/clients/client-details.php?id=<?php echo $client_id; ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i> Save CRM Settings
                                </button>
                            </div>
                        </form>
                        
                        <?php if ($crm): ?>
                        <hr class="my-4">
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Test CRM Integration</h6>
                            
                            <button type="button" class="btn btn-sm btn-outline-primary" id="testCrmButton">
                                <i class="bi bi-play-circle me-2"></i> Test Connection
                            </button>
                        </div>
                        
                        <div id="crmTestResult" class="mt-3" style="display: none;"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php if ($crm): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const testButton = document.getElementById('testCrmButton');
    const resultDiv = document.getElementById('crmTestResult');
    
    testButton.addEventListener('click', function() {
        // Show loading state
        testButton.disabled = true;
        testButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Testing...';
        resultDiv.style.display = 'none';
        
        // Call CRM proxy to test connection
        fetch('/shared/crm/crm-proxy.php?client_id=<?php echo $client_id; ?>&action=iframe_url')
            .then(response => response.json())
            .then(data => {
                // Reset button state
                testButton.disabled = false;
                testButton.innerHTML = '<i class="bi bi-play-circle me-2"></i> Test Connection';
                
                // Display result
                resultDiv.style.display = 'block';
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h6><i class="bi bi-check-circle me-2"></i> Connection Successful</h6>
                            <p class="mb-2">CRM iframe URL is available:</p>
                            <code class="d-block p-2 bg-light">${data.url}</code>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <h6><i class="bi bi-x-circle me-2"></i> Connection Failed</h6>
                            <p class="mb-0">${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                // Reset button state
                testButton.disabled = false;
                testButton.innerHTML = '<i class="bi bi-play-circle me-2"></i> Test Connection';
                
                // Display error
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-x-circle me-2"></i> Connection Failed</h6>
                        <p class="mb-0">An error occurred while testing the connection. Please check your CRM settings.</p>
                    </div>
                `;
                console.error('Error testing CRM connection:', error);
            });
    });
});
</script>
<?php endif; ?>

<?php
// Include footer
include '../../shared/templates/admin-footer.php';
?>