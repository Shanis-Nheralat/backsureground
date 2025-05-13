<?php
/**
 * Document Categories Management
 * 
 * Manages document categories for services
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/plans/plan-functions.php';
require_once '../../shared/utils/notifications.php';

// Authentication check
require_admin_auth();
require_admin_role(['admin']);

// Get service ID from query string
$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

// Check if service exists
$service = null;
if ($service_id) {
    $service = get_service($service_id);
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
            case 'add_category':
                $category_name = trim($_POST['category_name'] ?? '');
                $category_description = trim($_POST['category_description'] ?? '');
                $category_service_id = (int)($_POST['category_service_id'] ?? 0);
                $display_order = (int)($_POST['display_order'] ?? 0);
                $required_tiers = $_POST['required_tiers'] ?? [];
                
                if (empty($category_name) || !$category_service_id) {
                    set_notification('error', 'Category name and service ID are required.');
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO plan_document_categories 
                            (name, description, service_id, required_for_tier, display_order)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $category_name,
                            $category_description,
                            $category_service_id,
                            json_encode($required_tiers),
                            $display_order
                        ]);
                        
                        set_notification('success', 'Document category added successfully.');
                        
                        // Refresh page to show new category
                        header('Location: document-categories.php?service_id=' . $service_id);
                        exit;
                    } catch (PDOException $e) {
                        error_log('Add Category Error: ' . $e->getMessage());
                        set_notification('error', 'Failed to add document category.');
                    }
                }
                break;
                
            case 'update_category':
                $category_id = (int)($_POST['category_id'] ?? 0);
                $category_name = trim($_POST['category_name'] ?? '');
                $category_description = trim($_POST['category_description'] ?? '');
                $display_order = (int)($_POST['display_order'] ?? 0);
                $required_tiers = $_POST['required_tiers'] ?? [];
                
                if (!$category_id || empty($category_name)) {
                    set_notification('error', 'Category ID and name are required.');
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE plan_document_categories 
                            SET name = ?, description = ?, required_for_tier = ?, display_order = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        
                        $stmt->execute([
                            $category_name,
                            $category_description,
                            json_encode($required_tiers),
                            $display_order,
                            $category_id
                        ]);
                        
                        set_notification('success', 'Document category updated successfully.');
                        
                        // Refresh page
                        header('Location: document-categories.php?service_id=' . $service_id);
                        exit;
                    } catch (PDOException $e) {
                        error_log('Update Category Error: ' . $e->getMessage());
                        set_notification('error', 'Failed to update document category.');
                    }
                }
                break;
                
            case 'delete_category':
                $category_id = (int)($_POST['category_id'] ?? 0);
                
                if (!$category_id) {
                    set_notification('error', 'Category ID is required.');
                } else {
                    try {
                        // Check if category has documents
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as count FROM client_plan_documents
                            WHERE category_id = ?
                        ");
                        
                        $stmt->execute([$category_id]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($result && $result['count'] > 0) {
                            set_notification('error', 'Cannot delete category. There are documents associated with it.');
                        } else {
                            $stmt = $pdo->prepare("
                                DELETE FROM plan_document_categories
                                WHERE id = ?
                            ");
                            
                            $stmt->execute([$category_id]);
                            set_notification('success', 'Document category deleted successfully.');
                        }
                        
                        // Refresh page
                        header('Location: document-categories.php?service_id=' . $service_id);
                        exit;
                    } catch (PDOException $e) {
                        error_log('Delete Category Error: ' . $e->getMessage());
                        set_notification('error', 'Failed to delete document category.');
                    }
                }
                break;
        }
    }
}

// Get document categories for this service
$categories = [];
if ($service) {
    $categories = get_document_categories_by_service($service_id);
}

// Get all plan tiers for dropdowns
$plan_tiers = get_plan_tiers();

// Page variables
$page_title = $service ? 'Document Categories for: ' . $service['name'] : 'Document Categories';
$current_page = 'document_categories';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '../dashboard.php'],
    ['title' => 'Business Care Plans', 'url' => 'manage.php'],
    ['title' => 'Services', 'url' => 'services.php?plan_id=' . ($service ? $service['plan_id'] : '')],
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
                <a href="services.php?plan_id=<?php echo $service ? $service['plan_id'] : ''; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Services
                </a>
                
                <?php if ($service): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-plus me-1"></i> Add Category
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php display_notifications(); ?>
        
        <?php if (!$service): ?>
            <div class="alert alert-warning">
                <p>Please select a valid service to manage document categories.</p>
            </div>
        <?php else: ?>
            <!-- Service Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Service Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Service Name:</strong> <?php echo htmlspecialchars($service['name']); ?></p>
                            <p><strong>Description:</strong> <?php echo htmlspecialchars($service['description'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?></p>
                            <p>
                                <strong>Icon:</strong> 
                                <i class="<?php echo htmlspecialchars($service['icon'] ?? 'fas fa-chart-line'); ?> me-2"></i>
                                <?php echo htmlspecialchars($service['icon'] ?? 'fas fa-chart-line'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Document Categories -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Document Categories</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($categories)): ?>
                        <div class="alert alert-info">
                            <p>No document categories defined for this service yet. Add categories to help organize client documents.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Required For</th>
                                        <th>Order</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td><?php echo htmlspecialchars($category['description'] ?? ''); ?></td>
                                            <td>
                                                <?php 
                                                $required_tiers = [];
                                                $required_tier_ids = json_decode($category['required_for_tier'] ?? '[]', true);
                                                
                                                if (!empty($required_tier_ids)) {
                                                    foreach ($plan_tiers as $tier) {
                                                        if (in_array($tier['id'], $required_tier_ids)) {
                                                            $required_tiers[] = $tier['name'];
                                                        }
                                                    }
                                                }
                                                
                                                echo !empty($required_tiers) 
                                                    ? implode(', ', $required_tiers) 
                                                    : '<span class="text-muted">Optional</span>';
                                                ?>
                                            </td>
                                            <td><?php echo $category['display_order']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info edit-category-btn" 
                                                        data-bs-toggle="modal" data-bs-target="#editCategoryModal"
                                                        data-category-id="<?php echo $category['id']; ?>"
                                                        data-category-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                        data-category-description="<?php echo htmlspecialchars($category['description'] ?? ''); ?>"
                                                        data-category-order="<?php echo $category['display_order']; ?>"
                                                        data-category-required-tiers="<?php echo htmlspecialchars($category['required_for_tier'] ?? '[]'); ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                
                                                <button type="button" class="btn btn-sm btn-danger delete-category-btn"
                                                        data-bs-toggle="modal" data-bs-target="#deleteCategoryModal"
                                                        data-category-id="<?php echo $category['id']; ?>"
                                                        data-category-name="<?php echo htmlspecialchars($category['name']); ?>">
                                                    <i class="fas fa-trash"></i> Delete
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
        <?php endif; ?>
    </div>
</main>

<!-- Add Category Modal -->
<?php if ($service): ?>
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Add Document Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="form_action" value="add_category">
                    <input type="hidden" name="category_service_id" value="<?php echo $service_id; ?>">
                    
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_description" class="form-label">Description</label>
                        <textarea class="form-control" id="category_description" name="category_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="display_order" name="display_order" value="0" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Required For Tiers</label>
                        <div class="form-text mb-2">Select tiers where this document category is required.</div>
                        
                        <?php foreach ($plan_tiers as $tier): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="required_tiers[]" 
                                       value="<?php echo $tier['id']; ?>" id="tier_<?php echo $tier['id']; ?>">
                                <label class="form-check-label" for="tier_<?php echo $tier['id']; ?>">
                                    <?php echo htmlspecialchars($tier['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Document Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="form_action" value="update_category">
                    <input type="hidden" id="edit_category_id" name="category_id">
                    
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_category_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_category_description" name="category_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="edit_display_order" name="display_order" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Required For Tiers</label>
                        <div class="form-text mb-2">Select tiers where this document category is required.</div>
                        
                        <?php foreach ($plan_tiers as $tier): ?>
                            <div class="form-check">
                                <input class="form-check-input edit-tier-checkbox" type="checkbox" name="required_tiers[]" 
                                       value="<?php echo $tier['id']; ?>" id="edit_tier_<?php echo $tier['id']; ?>">
                                <label class="form-check-label" for="edit_tier_<?php echo $tier['id']; ?>">
                                    <?php echo htmlspecialchars($tier['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the category <strong id="delete_category_name"></strong>?</p>
                <p class="text-danger">This action cannot be undone. All associated documents will lose their category.</p>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="form_action" value="delete_category">
                <input type="hidden" id="delete_category_id" name="category_id">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Initialize modals
    document.addEventListener('DOMContentLoaded', function() {
        // Edit Category Modal
        const editCategoryButtons = document.querySelectorAll('.edit-category-btn');
        editCategoryButtons.forEach(button => {
            button.addEventListener('click', function() {
                const categoryId = this.getAttribute('data-category-id');
                const categoryName = this.getAttribute('data-category-name');
                const categoryDescription = this.getAttribute('data-category-description');
                const categoryOrder = this.getAttribute('data-category-order');
                const requiredTiers = JSON.parse(this.getAttribute('data-category-required-tiers') || '[]');
                
                document.getElementById('edit_category_id').value = categoryId;
                document.getElementById('edit_category_name').value = categoryName;
                document.getElementById('edit_category_description').value = categoryDescription;
                document.getElementById('edit_display_order').value = categoryOrder;
                
                // Reset checkboxes
                document.querySelectorAll('.edit-tier-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                // Set checkboxes for required tiers
                requiredTiers.forEach(tierId => {
                    const checkbox = document.getElementById('edit_tier_' + tierId);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            });
        });
        
        // Delete Category Modal
        const deleteCategoryButtons = document.querySelectorAll('.delete-category-btn');
        deleteCategoryButtons.forEach(button => {
            button.addEventListener('click', function() {
                const categoryId = this.getAttribute('data-category-id');
                const categoryName = this.getAttribute('data-category-name');
                
                document.getElementById('delete_category_id').value = categoryId;
                document.getElementById('delete_category_name').textContent = categoryName;
            });
        });
    });
</script>

<?php include '../../shared/templates/admin-footer.php'; ?>