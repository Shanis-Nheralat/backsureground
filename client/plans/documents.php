<?php
/**
 * Client Plan Documents
 * 
 * Allows clients to view and upload service-related documents
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/plans/plan-functions.php';

// Ensure client is logged in and has the client role
require_admin_auth();
require_admin_role(['client']);

// Current user info
$client_id = $_SESSION['admin_user_id'];

// Get service ID from query parameter
$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

// Get active client plan
$active_plan = get_active_client_plan($client_id);

// Check if service belongs to client's plan
$has_access = false;
$service_name = 'Unknown Service';

if ($active_plan) {
    $subscribed_services = get_client_service_subscriptions($active_plan['id']);
    
    foreach ($subscribed_services as $service) {
        if ($service['service_id'] == $service_id) {
            $has_access = true;
            $service_name = $service['service_name'];
            break;
        }
    }
}

// Get document categories for this service
$categories = [];
if ($has_access) {
    $categories = get_document_categories_by_service($service_id);
}

// Process document upload
$upload_success = false;
$upload_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $has_access) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $upload_error = 'Invalid form submission. Please try again.';
    } else {
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        
        if (!$category_id) {
            $upload_error = 'Please select a document category.';
        } else if (empty($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
            $upload_error = 'Please select a file to upload.';
        } else {
            // Upload document
            $result = upload_client_document($client_id, $category_id, $_FILES['document']);
            
            if ($result['success']) {
                $upload_success = true;
                set_notification('success', 'Document uploaded successfully.');
                
                // Redirect to avoid form resubmission
                header('Location: documents.php?service_id=' . $service_id);
                exit;
            } else {
                $upload_error = $result['message'];
            }
        }
    }
}

// Get all client documents for this service
$documents = [];
if ($has_access) {
    // Get categories for this service
    $service_categories = get_document_categories_by_service($service_id);
    $category_ids = array_column($service_categories, 'id');
    
    // Get documents for these categories
    if (!empty($category_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
            
            $sql = "SELECT cpd.*, pdc.name as category_name
                    FROM client_plan_documents cpd
                    JOIN plan_document_categories pdc ON cpd.category_id = pdc.id
                    WHERE cpd.client_id = ? AND cpd.category_id IN ($placeholders)
                    ORDER BY cpd.uploaded_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $params = array_merge([$client_id], $category_ids);
            $stmt->execute($params);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Get Client Documents Error: ' . $e->getMessage());
            $documents = [];
        }
    }
}

// Status badge classes
$status_badges = [
    'pending' => 'badge bg-warning',
    'approved' => 'badge bg-success',
    'rejected' => 'badge bg-danger'
];

// Page variables
$page_title = $has_access ? $service_name . ' Documents' : 'Service Documents';
$current_page = 'plan_documents';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Business Care Plan', 'url' => 'dashboard.php'],
    ['title' => $page_title, 'url' => '#']
];

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include template parts
include '../../shared/templates/client-head.php';
include '../../shared/templates/client-sidebar.php';
include '../../shared/templates/client-header.php';
?>

<main class="client-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <?php if (!$has_access): ?>
            <div class="alert alert-warning">
                <?php if ($service_id === 0): ?>
                    <p>Please select a service to view its documents.</p>
                <?php else: ?>
                    <p>You don't have access to this service. Please contact support if you believe this is an error.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Document Upload -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold">Upload Document</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($upload_success): ?>
                                <div class="alert alert-success">
                                    Document uploaded successfully.
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($upload_error): ?>
                                <div class="alert alert-danger">
                                    <?php echo $upload_error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Document Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="document" class="form-label">Upload File <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="document" name="document" required>
                                    <small class="text-muted">
                                        Allowed formats: PDF, DOCX, XLSX, JPG, PNG, ZIP (Max 10MB)
                                    </small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-1"></i> Upload Document
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Document List -->
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold">Your Documents</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($documents)): ?>
                                <div class="alert alert-info">
                                    You haven't uploaded any documents for this service yet.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Document</th>
                                                <th>Category</th>
                                                <th>Uploaded</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($documents as $document): ?>
                                                <tr>
                                                    <td>
                                                        <?php 
                                                        $icon_class = 'fas fa-file';
                                                        if (in_array($document['file_type'], ['pdf'])) {
                                                            $icon_class = 'fas fa-file-pdf';
                                                        } else if (in_array($document['file_type'], ['docx', 'doc'])) {
                                                            $icon_class = 'fas fa-file-word';
                                                        } else if (in_array($document['file_type'], ['xlsx', 'xls'])) {
                                                            $icon_class = 'fas fa-file-excel';
                                                        } else if (in_array($document['file_type'], ['jpg', 'jpeg', 'png'])) {
                                                            $icon_class = 'fas fa-file-image';
                                                        } else if (in_array($document['file_type'], ['zip'])) {
                                                            $icon_class = 'fas fa-file-archive';
                                                        }
                                                        ?>
                                                        <i class="<?php echo $icon_class; ?> me-2"></i>
                                                        <?php echo htmlspecialchars($document['original_file_name']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($document['category_name']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($document['uploaded_at'])); ?></td>
                                                    <td>
                                                        <span class="<?php echo $status_badges[$document['status']]; ?>">
                                                            <?php echo ucfirst($document['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="../../shared/download-document.php?id=<?php echo $document['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-download"></i> Download
                                                        </a>
                                                        
                                                        <?php if ($document['status'] === 'rejected' && !empty($document['review_notes'])): ?>
                                                            <button type="button" class="btn btn-sm btn-danger ms-1" 
                                                                    data-bs-toggle="tooltip" 
                                                                    data-bs-placement="top"
                                                                    title="<?php echo htmlspecialchars($document['review_notes']); ?>">
                                                                <i class="fas fa-info-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
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
        <?php endif; ?>
    </div>
</main>

<script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php include '../../shared/templates/client-footer.php'; ?>