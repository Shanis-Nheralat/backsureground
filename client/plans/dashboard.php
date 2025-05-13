<?php
/**
 * Client Plan Dashboard
 * 
 * Displays client's active plan, services, and insights
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/plans/plan-functions.php';
require_once '../../shared/plans/insight-functions.php';

// Ensure client is logged in and has the client role
require_admin_auth();
require_admin_role(['client']);

// Current user info
$client_id = $_SESSION['admin_user_id'];

// Get active client plan
$active_plan = get_active_client_plan($client_id);
$subscribed_services = [];

if ($active_plan) {
    // Get subscribed services
    $subscribed_services = get_client_service_subscriptions($active_plan['id']);
}

// Page variables
$page_title = 'Business Care Plan Dashboard';
$current_page = 'plan_dashboard';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Business Care Plan', 'url' => '#']
];

// Include template parts
include '../../shared/templates/client-head.php';
include '../../shared/templates/client-sidebar.php';
include '../../shared/templates/client-header.php';
?>

<main class="client-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
        </div>
        
        <?php display_notifications(); ?>
        
        <?php if (!$active_plan): ?>
            <div class="alert alert-info">
                <h5>No Active Plan</h5>
                <p>You don't have an active Business Care Plan subscription. Please contact our support team to set up your plan.</p>
            </div>
        <?php else: ?>
            <!-- Plan Overview -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Plan Overview</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Current Plan: <span class="text-primary"><?php echo htmlspecialchars($active_plan['plan_name']); ?></span></h5>
                            <p>
                                <strong>Tier:</strong> <?php echo htmlspecialchars($active_plan['tier_name']); ?><br>
                                <strong>Start Date:</strong> <?php echo date('F j, Y', strtotime($active_plan['start_date'])); ?><br>
                                <strong>End Date:</strong> <?php echo $active_plan['end_date'] ? date('F j, Y', strtotime($active_plan['end_date'])) : 'Ongoing'; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h5>Your Services</h5>
                            <?php if (empty($subscribed_services)): ?>
                                <p class="text-muted">No services configured. Please contact support.</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($subscribed_services as $service): ?>
                                        <a href="#service-<?php echo $service['service_id']; ?>" class="list-group-item list-group-item-action">
                                            <i class="<?php echo htmlspecialchars($service['icon'] ?? 'fas fa-chart-line'); ?> me-2"></i>
                                            <?php echo htmlspecialchars($service['service_name']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Service Sections -->
            <?php foreach ($subscribed_services as $service): ?>
                <?php 
                // Get recent insights for this service
                $service_insights = get_client_insights($client_id, $service['service_id']);
                $recent_insight = !empty($service_insights) ? $service_insights[0] : null;
                ?>
                
                <div id="service-<?php echo $service['service_id']; ?>" class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">
                            <i class="<?php echo htmlspecialchars($service['icon'] ?? 'fas fa-chart-line'); ?> me-2"></i>
                            <?php echo htmlspecialchars($service['service_name']); ?>
                        </h6>
                        <div>
                            <a href="documents.php?service_id=<?php echo $service['service_id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-file me-1"></i> Documents
                            </a>
                            <a href="insights.php?service_id=<?php echo $service['service_id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-chart-bar me-1"></i> All Insights
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_insight): ?>
                            <h5 class="mb-3"><?php echo htmlspecialchars($recent_insight['title']); ?></h5>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($recent_insight['description']); ?></p>
                            
                            <?php echo render_insight_chart($recent_insight); ?>
                            
                            <div class="mt-3 text-end">
                                <small class="text-muted">Last updated: <?php echo date('M d, Y', strtotime($recent_insight['created_at'])); ?></small>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <p class="mb-0">No insights available yet for this service. Our team will update your dashboard soon with relevant analytics.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Include Chart.js for insights -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<!-- Include JustGage for compliance gauge -->
<script src="https://cdn.jsdelivr.net/npm/raphael@2.3.0/raphael.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/justgage@1.4.2/justgage.min.js"></script>

<?php include '../../shared/templates/client-footer.php'; ?>