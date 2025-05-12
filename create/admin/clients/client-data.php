<?php
/**
 * Employee: Client CRM Data
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

// Page variables
$page_title = 'Client CRM Data';
$current_page = 'client_crm_data';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/employee/dashboard.php'],
    ['title' => 'Assigned Clients', 'url' => '/employee/clients/assigned-clients.php'],
    ['title' => 'CRM Data', 'url' => '#']
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

// Get client CRM details
$crm = get_client_crm($client_id);

if (!$crm) {
    set_notification('error', 'CRM integration is not set up for this client.');
    header('Location: /employee/clients/client-details.php?client_id=' . $client_id);
    exit;
}

// Log access to client CRM
log_client_access($client_id, 'view', 'crm', $crm['crm_identifier']);

// Get CRM iframe URL
$iframe_url = get_crm_iframe_url($client_id);

// Set page title with client name
$page_title = htmlspecialchars($client['name']) . ' - CRM Data';

// Include template parts
include '../../shared/templates/employee-head.php';
include '../../shared/templates/employee-sidebar.php';
include '../../shared/templates/employee-header.php';
?>

<main class="employee-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            
            <a href="/employee/clients/client-details.php?client_id=<?php echo $client_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back to Client
            </a>
        </div>
        
        <?php display_notifications(); ?>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">
                    <?php echo ucfirst($crm['crm_type']); ?> CRM - <?php echo htmlspecialchars($client['name']); ?>
                </h6>
                
                <span class="badge bg-primary">Read-Only View</span>
            </div>
            <div class="card-body p-0">
                <?php if ($iframe_url): ?>
                <!-- CRM iframe -->
                <div class="crm-iframe-container">
                    <iframe src="<?php echo htmlspecialchars($iframe_url); ?>" 
                            id="crmIframe" 
                            frameborder="0" 
                            style="width: 100%; height: 700px; border: none;"></iframe>
                </div>
                <?php else: ?>
                <div class="alert alert-danger m-3">
                    <h5><i class="bi bi-exclamation-triangle me-2"></i> CRM Access Error</h5>
                    <p class="mb-0">Unable to load CRM data. Please contact your administrator.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- CRM API Data Example (optional based on implementation) -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Client Summary</h6>
            </div>
            <div class="card-body">
                <div id="apiDataLoading" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading client data from CRM...</p>
                </div>
                
                <div id="apiDataError" class="alert alert-danger" style="display: none;">
                    Failed to load client data from CRM API.
                </div>
                
                <div id="apiDataContainer" style="display: none;">
                    <!-- API data will be displayed here -->
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Attempt to fetch CRM data from API
    fetch('/shared/crm/crm-proxy.php?client_id=<?php echo $client_id; ?>&action=get_data&endpoint=summary')
        .then(response => response.json())
        .then(data => {
            document.getElementById('apiDataLoading').style.display = 'none';
            
            if (data.success && data.data) {
                document.getElementById('apiDataContainer').style.display = 'block';
                
                // Format the data based on the CRM system and available fields
                let htmlContent = '';
                
                // Example format - adjust based on actual API response structure
                if (data.data.account) {
                    const account = data.data.account;
                    
                    htmlContent += `
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Account Information</h5>
                                <table class="table table-bordered">
                                    <tbody>
                                        ${account.name ? `<tr><th>Name</th><td>${account.name}</td></tr>` : ''}
                                        ${account.type ? `<tr><th>Type</th><td>${account.type}</td></tr>` : ''}
                                        ${account.industry ? `<tr><th>Industry</th><td>${account.industry}</td></tr>` : ''}
                                        ${account.revenue ? `<tr><th>Annual Revenue</th><td>${account.revenue}</td></tr>` : ''}
                                        ${account.employees ? `<tr><th>Employees</th><td>${account.employees}</td></tr>` : ''}
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Contact Information</h5>
                                <table class="table table-bordered">
                                    <tbody>
                                        ${account.website ? `<tr><th>Website</th><td>${account.website}</td></tr>` : ''}
                                        ${account.phone ? `<tr><th>Phone</th><td>${account.phone}</td></tr>` : ''}
                                        ${account.email ? `<tr><th>Email</th><td>${account.email}</td></tr>` : ''}
                                        ${account.address ? `<tr><th>Address</th><td>${account.address}</td></tr>` : ''}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                    
                    // Add recent activities if available
                    if (data.data.activities && data.data.activities.length > 0) {
                        htmlContent += `
                            <h5 class="mt-4">Recent Activities</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        data.data.activities.forEach(activity => {
                            htmlContent += `
                                <tr>
                                    <td>${activity.date || ''}</td>
                                    <td>${activity.type || ''}</td>
                                    <td>${activity.description || ''}</td>
                                    <td>${activity.status || ''}</td>
                                </tr>
                            `;
                        });
                        
                        htmlContent += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                    }
                } else {
                    // Generic display for unknown data structure
                    htmlContent = `
                        <div class="alert alert-info">
                            <p>Data retrieved from CRM system. View the embedded CRM interface for complete details.</p>
                        </div>
                        <pre class="bg-light p-3 rounded">${JSON.stringify(data.data, null, 2)}</pre>
                    `;
                }
                
                document.getElementById('apiDataContainer').innerHTML = htmlContent;
            } else {
                document.getElementById('apiDataError').style.display = 'block';
                document.getElementById('apiDataError').textContent = data.message || 'Failed to load client data from CRM API.';
            }
        })
        .catch(error => {
            document.getElementById('apiDataLoading').style.display = 'none';
            document.getElementById('apiDataError').style.display = 'block';
            console.error('Error fetching CRM data:', error);
        });
    
    // Handle iframe load events
    const iframe = document.getElementById('crmIframe');
    if (iframe) {
        iframe.addEventListener('load', function() {
            console.log('CRM iframe loaded successfully');
        });
        
        iframe.addEventListener('error', function() {
            console.error('Error loading CRM iframe');
            iframe.style.display = 'none';
            const container = iframe.parentNode;
            container.innerHTML = `
                <div class="alert alert-danger m-3">
                    <h5><i class="bi bi-exclamation-triangle me-2"></i> CRM Access Error</h5>
                    <p class="mb-0">Unable to load CRM interface. Please contact your administrator.</p>
                </div>
            `;
        });
    }
});
</script>

<?php
// Include footer
include '../../shared/templates/employee-footer.php';
?>