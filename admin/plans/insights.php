<?php
/**
 * Admin Insights Management
 * 
 * Manages insights for client services
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/plans/plan-functions.php';
require_once '../../shared/plans/insight-functions.php';
require_once '../../shared/utils/notifications.php';

// Authentication check
require_admin_auth();
require_admin_role(['admin']);

// Get client ID and service ID from query string
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

// Check if client exists
$client = null;
if ($client_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'client'");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Client Error: ' . $e->getMessage());
    }
}

// Check if service exists
$service = null;
if ($service_id) {
    $service = get_service($service_id);
}

// Get active client plan
$active_plan = $client ? get_active_client_plan($client_id) : null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
    } else {
        // Determine which form was submitted
        $form_action = $_POST['form_action'] ?? '';
        
        switch ($form_action) {
            case 'generate_insight':
                $insight_client_id = (int)($_POST['insight_client_id'] ?? 0);
                $insight_service_id = (int)($_POST['insight_service_id'] ?? 0);
                $insight_type = $_POST['insight_type'] ?? '';
                $period_start = $_POST['period_start'] ?? '';
                $period_end = $_POST['period_end'] ?? '';
                
                if (!$insight_client_id || !$insight_service_id || empty($insight_type) || empty($period_start) || empty($period_end)) {
                    set_notification('error', 'All fields are required.');
                } else {
                    $created_by = $_SESSION['admin_user_id'];
                    $insight_id = null;
                    
                    // Generate insight based on type
                    switch ($insight_type) {
                        case 'turnover':
                            $insight_id = generate_turnover_insight($insight_client_id, $insight_service_id, $period_start, $period_end, $created_by);
                            break;
                            
                        case 'payroll':
                            $insight_id = generate_payroll_insight($insight_client_id, $insight_service_id, $period_start, $period_end, $created_by);
                            break;
                            
                        case 'compliance':
                            $insight_id = generate_compliance_insight($insight_client_id, $insight_service_id, $period_start, $period_end, $created_by);
                            break;
                    }
                    
                    if ($insight_id) {
                        set_notification('success', 'Insight generated successfully.');
                        
                        // Refresh page
                        header('Location: insights.php?client_id=' . $client_id . '&service_id=' . $service_id);
                        exit;
                    } else {
                        set_notification('error', 'Failed to generate insight.');
                    }
                }
                break;
                
            case 'delete_insight':
                $insight_id = (int)($_POST['insight_id'] ?? 0);
                
                if (!$insight_id) {
                    set_notification('error', 'Insight ID is required.');
                } else {
                    if (delete_service_insight($insight_id)) {
                        set_notification('success', 'Insight deleted successfully.');
                    } else {
                        set_notification('error', 'Failed to delete insight.');
                    }
                    
                    // Refresh page
                    header('Location: insights.php?client_id=' . $client_id . '&service_id=' . $service_id);
                    exit;
                }
                break;
        }
    }
}

// Get insights for this client and service
$insights = [];
if ($client) {
    $insights = get_client_insights($client_id, $service_id);
}

// Get client's subscribed services
$client_services = [];
if ($client && $active_plan) {
    $client_services = get_client_service_subscriptions($active_plan['id']);
}

// Page variables
if ($client && $service) {
    $page_title = 'Insights for ' . $client['name'] . ' - ' . $service['name'];
} elseif ($client) {
    $page_title = 'Insights for ' . $client['name'];
} else {
    $page_title = 'Client Insights';
}

$current_page = 'client_insights';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Business Care Plans', 'url' => 'manage.php'],
    ['title' => $page_title, 'url' => '#']
];

// Get all clients with active plans for dropdown
try {
    $sql = "SELECT u.id, u.name
            FROM users u
            JOIN client_plan_subscriptions cps ON u.id = cps.client_id
            WHERE u.role = 'client' AND cps.status = 'active'
            GROUP BY u.id
            ORDER BY u.name";
    
    $stmt = $pdo->query($sql);
    $all_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Get All Clients Error: ' . $e->getMessage());
    $all_clients = [];
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
            <div>
                <a href="manage.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Plans
                </a>
                
                <?php if ($client && $service): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateInsightModal">
                        <i class="fas fa-chart-line me-1"></i> Generate New Insight
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <?php if (!$client): ?>
            <!-- Client Selection -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Select Client</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($all_clients)): ?>
                        <div class="alert alert-info">No clients with active plans found.</div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="client_selection" class="form-label">Select a client to view insights:</label>
                                    <select class="form-select" id="client_selection">
                                        <option value="">-- Select Client --</option>
                                        <?php foreach ($all_clients as $c): ?>
                                            <option value="<?php echo $c['id']; ?>">
                                                <?php echo htmlspecialchars($c['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" id="view_client_btn" class="btn btn-primary">
                                    View Client Insights
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Client Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Client Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Client:</strong> <?php echo htmlspecialchars($client['name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($active_plan): ?>
                                <p><strong>Plan:</strong> <?php echo htmlspecialchars($active_plan['plan_name']); ?></p>
                                <p><strong>Tier:</strong> <?php echo htmlspecialchars($active_plan['tier_name']); ?></p>
                            <?php else: ?>
                                <p class="text-danger">No active plan found for this client.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($client_services)): ?>
                        <div class="mt-3">
                            <h6>Subscribed Services:</h6>
                            <div class="row">
                                <?php foreach ($client_services as $cs): ?>
                                    <div class="col-md-4 mb-2">
                                        <a href="insights.php?client_id=<?php echo $client_id; ?>&service_id=<?php echo $cs['service_id']; ?>" 
                                           class="btn <?php echo $cs['service_id'] == $service_id ? 'btn-primary' : 'btn-outline-primary'; ?> w-100">
                                            <i class="<?php echo htmlspecialchars($cs['icon'] ?? 'fas fa-chart-line'); ?> me-2"></i>
                                            <?php echo htmlspecialchars($cs['service_name']); ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($service): ?>
                <!-- Service Insights -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Insights for <?php echo htmlspecialchars($service['name']); ?></h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($insights)): ?>
                            <div class="alert alert-info">
                                <p>No insights generated for this service yet. Click the "Generate New Insight" button to create one.</p>
                            </div>
                        <?php else: ?>
                            <div class="accordion" id="insightsAccordion">
                                <?php foreach ($insights as $index => $insight): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?php echo $insight['id']; ?>">
                                            <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" 
                                                    data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $insight['id']; ?>" 
                                                    aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" 
                                                    aria-controls="collapse<?php echo $insight['id']; ?>">
                                                <?php echo htmlspecialchars($insight['title']); ?>
                                                <span class="ms-auto badge bg-secondary">
                                                    <?php echo date('M d, Y', strtotime($insight['created_at'])); ?>
                                                </span>
                                            </button>
                                        </h2>
                                        <div id="collapse<?php echo $insight['id']; ?>" 
                                             class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                                             aria-labelledby="heading<?php echo $insight['id']; ?>"
                                             data-bs-parent="#insightsAccordion">
                                            <div class="accordion-body">
                                                <div class="mb-3">
                                                    <p><?php echo htmlspecialchars($insight['description']); ?></p>
                                                    <p>
                                                        <strong>Period:</strong> 
                                                        <?php echo date('M d, Y', strtotime($insight['period_start'])); ?> - 
                                                        <?php echo date('M d, Y', strtotime($insight['period_end'])); ?>
                                                    </p>
                                                </div>
                                                
                                                <div class="insight-visualization mb-4">
                                                    <?php echo render_insight_chart($insight); ?>
                                                </div>
                                                
                                                <form method="post" class="mt-3" onsubmit="return confirm('Are you sure you want to delete this insight?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="form_action" value="delete_insight">
                                                    <input type="hidden" name="insight_id" value="<?php echo $insight['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i> Delete Insight
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($client): ?>
                <!-- Service Selection Prompt -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="alert alert-info">
                            <p>Please select a service from the list above to view or generate insights.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Generate Insight Modal -->
<?php if ($client && $service): ?>
<div class="modal fade" id="generateInsightModal" tabindex="-1" aria-labelledby="generateInsightModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="generateInsightModalLabel">Generate New Insight</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="form_action" value="generate_insight">
                    <input type="hidden" name="insight_client_id" value="<?php echo $client_id; ?>">
                    <input type="hidden" name="insight_service_id" value="<?php echo $service_id; ?>">
                    
                    <div class="mb-3">
                        <label for="insight_type" class="form-label">Insight Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="insight_type" name="insight_type" required>
                            <option value="">Select Insight Type</option>
                            <option value="turnover">Turnover Analysis</option>
                            <option value="payroll">Payroll Distribution</option>
                            <option value="compliance">Compliance Status</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="period_start" class="form-label">Period Start <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="period_start" name="period_start" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="period_end" class="form-label">Period End <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="period_end" name="period_end" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Insight</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Include Chart.js for insights -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<!-- Include JustGage for compliance gauge -->
<script src="https://cdn.jsdelivr.net/npm/raphael@2.3.0/raphael.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/justgage@1.4.2/justgage.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Client selection button
        const viewClientBtn = document.getElementById('view_client_btn');
        const clientSelection = document.getElementById('client_selection');
        
        if (viewClientBtn && clientSelection) {
            viewClientBtn.addEventListener('click', function() {
                const clientId = clientSelection.value;
                if (clientId) {
                    window.location.href = 'insights.php?client_id=' + clientId;
                } else {
                    alert('Please select a client.');
                }
            });
        }
        
        // Set default dates for period start and end
        const periodStartInput = document.getElementById('period_start');
        const periodEndInput = document.getElementById('period_end');
        
        if (periodStartInput && periodEndInput) {
            // Set period start to first day of previous month
            const today = new Date();
            const firstDayPrevMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            periodStartInput.value = firstDayPrevMonth.toISOString().substr(0, 10);
            
            // Set period end to last day of previous month
            const lastDayPrevMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            periodEndInput.value = lastDayPrevMonth.toISOString().substr(0, 10);
        }
    });
</script>

<?php include '../../shared/templates/admin-footer.php'; ?>