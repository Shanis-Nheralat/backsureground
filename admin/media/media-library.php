<?php
/**
 * Media Library
 * 
 * Manage uploaded files and images
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';
require_once '../../shared/csrf/csrf-functions.php';
require_once '../../shared/utils/file-upload-handler.php';

// Ensure user is authenticated and has admin role
require_role('admin');

// Initialize variables
$success_message = '';
$error_message = '';
$media_items = [];
$pagination = [];
$active_tab = 'list';

// Process file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid form submission. Please try again.';
    } else {
        if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $result = handle_file_upload($_FILES['file']);
            
            if ($result['success']) {
                // Log activity
                log_action('media_upload', "Uploaded file: {$result['file_name']}", 'media', $result['media_id']);
                
                $success_message = 'File uploaded successfully.';
            } else {
                $error_message = $result['error'];
            }
        } else {
            $error_message = 'No file selected.';
        }
    }
    
    // Regenerate CSRF token
    csrf_regenerate();
}

// Process media deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid form submission. Please try again.';
    } else {
        $media_id = isset($_POST['media_id']) ? (int) $_POST['media_id'] : 0;
        
        if ($media_id > 0) {
            $media = get_media_item($media_id);
            
            if ($media && delete_media_item($media_id)) {
                // Log activity
                log_action('media_delete', "Deleted file: {$media['file_name']}", 'media', $media_id);
                
                $success_message = 'File deleted successfully.';
            } else {
                $error_message = 'Failed to delete file.';
            }
        } else {
            $error_message = 'Invalid media ID.';
        }
    }
    
    // Regenerate CSRF token
    csrf_regenerate();
}

// Handle AJAX request for media selection
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    header('Content-Type: application/json');
    
    // Get media items
    $type_filter = isset($_GET['type']) ? $_GET['type'] : null;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    
    $filters = [];
    if ($type_filter) {
        $filters['type'] = $type_filter;
    }
    if ($search) {
        $filters['search'] = $search;
    }
    
    $result = get_media_items($filters, $page);
    
    echo json_encode($result);
    exit;
}

// Get query parameters
$type_filter = isset($_GET['type']) ? $_GET['type'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Get media items
$filters = [];
if ($type_filter) {
    $filters['type'] = $type_filter;
}
if ($search) {
    $filters['search'] = $search;
}

$media_result = get_media_items($filters, $page);
$media_items = $media_result['items'];
$pagination = $media_result['pagination'];

// Set page variables
$page_title = 'Media Library';
$active_page = 'media';

// Include header template
include_once '../../shared/templates/admin-header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
    <h1 class="h2">Media Library</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" id="uploadButton">
            <i class="bi bi-cloud-upload"></i> Upload New File
        </button>
    </div>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search files..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit">Search</button>
                </div>
            </div>
            
            <div class="col-md-4">
                <select class="form-select" name="type" onchange="this.form.submit()">
                    <option value="" <?php if (!$type_filter) echo 'selected'; ?>>All Files</option>
                    <option value="image" <?php if ($type_filter === 'image') echo 'selected'; ?>>Images Only</option>
                    <option value="document" <?php if ($type_filter === 'document') echo 'selected'; ?>>Documents Only</option>
                </select>
            </div>
            
            <div class="col-md-4 text-end">
                <span class="text-muted">Total: <?php echo $pagination['total_items']; ?> files</span>
            </div>
        </form>
    </div>
</div>

<!-- Media Grid -->
<?php if (empty($media_items)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-images display-1 text-muted"></i>
            <p class="mt-3 mb-0">No media files found.</p>
            <?php if (!empty($search) || !empty($type_filter)): ?>
                <p>Try removing filters or uploading new files.</p>
                <a href="media-library.php" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
            <?php else: ?>
                <p>Upload your first file to get started.</p>
                <button type="button" class="btn btn-primary" id="emptyUploadButton">
                    <i class="bi bi-cloud-upload"></i> Upload New File
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-4 g-4 mb-4">
        <?php foreach ($media_items as $item): ?>
            <div class="col">
                <div class="card h-100 media-card">
                    <div class="card-preview text-center bg-light">
                        <?php if ($item['is_image']): ?>
                            <img src="<?php echo htmlspecialchars($item['file_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['file_name']); ?>">
                        <?php else: ?>
                            <div class="file-icon">
                                <i class="bi bi-file-earmark-<?php echo file_type_icon($item['file_type']); ?> display-1"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h6 class="card-title text-truncate" title="<?php echo htmlspecialchars($item['file_name']); ?>">
                            <?php echo htmlspecialchars($item['file_name']); ?>
                        </h6>
                        <p class="card-text">
                            <small class="text-muted">
                                <?php echo htmlspecialchars($item['formatted_size']); ?> • 
                                <?php echo htmlspecialchars($item['formatted_date']); ?>
                            </small>
                        </p>
                    </div>
                    <div class="card-footer bg-white border-top-0">
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-sm btn-outline-primary select-media" 
                                    data-id="<?php echo $item['id']; ?>"
                                    data-path="<?php echo htmlspecialchars($item['file_path']); ?>"
                                    data-name="<?php echo htmlspecialchars($item['file_name']); ?>"
                                    data-is-image="<?php echo $item['is_image'] ? 'true' : 'false'; ?>">
                                <i class="bi bi-check2"></i> Select
                            </button>
                            
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars($item['file_url']); ?>" target="_blank"><i class="bi bi-eye"></i> View</a></li>
                                    <li>
                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="media_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Delete</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
        <nav aria-label="Media pagination">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php if ($pagination['current_page'] <= 1) echo 'disabled'; ?>">
                    <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?>&type=<?php echo htmlspecialchars($type_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">Previous</a>
                </li>
                
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <li class="page-item <?php if ($i === $pagination['current_page']) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo htmlspecialchars($type_filter); ?>&search=<?php echo htmlspecialchars($search); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?php if ($pagination['current_page'] >= $pagination['total_pages']) echo 'disabled'; ?>">
                    <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?>&type=<?php echo htmlspecialchars($type_filter); ?>&search=<?php echo htmlspecialchars($search); ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="mb-3">
                        <label for="fileUpload" class="form-label">Select File</label>
                        <input type="file" class="form-control" id="fileUpload" name="file" required>
                        <div class="form-text">
                            Max file size: <?php echo format_file_size(MAX_FILE_SIZE); ?><br>
                            Allowed file types: <?php echo implode(', ', array_merge(array_values(ALLOWED_IMAGE_TYPES), array_values(ALLOWED_FILE_TYPES))); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Media Browser Modal -->
<div class="modal fade" id="mediaBrowserModal" tabindex="-1" aria-labelledby="mediaBrowserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mediaBrowserModalLabel">Select Media</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Media browser content will be loaded here -->
                <div id="mediaBrowserContent">
                    <div class="text-center py-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading media...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="selectMediaButton" disabled>Select</button>
            </div>
        </div>
    </div>
</div>

<style>
    .card-preview {
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    
    .card-preview img {
        max-height: 100%;
        object-fit: contain;
    }
    
    .file-icon {
        padding: 20px;
        color: #6c757d;
    }
    
    .media-card {
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .media-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    
    .media-card.selected {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
</style>

<script>
    // Initialize modals
    document.addEventListener('DOMContentLoaded', function() {
        // Upload modal
        const uploadModal = new bootstrap.Modal(document.getElementById('uploadModal'));
        document.getElementById('uploadButton').addEventListener('click', function() {
            uploadModal.show();
        });
        
        if (document.getElementById('emptyUploadButton')) {
            document.getElementById('emptyUploadButton').addEventListener('click', function() {
                uploadModal.show();
            });
        }
        
        // Media browser modal
        const mediaBrowserModal = new bootstrap.Modal(document.getElementById('mediaBrowserModal'));
        
        // Media selection in browser modal
        document.addEventListener('click', function(e) {
            if (e.target.matches('.media-card, .media-card *')) {
                const card = e.target.closest('.media-card');
                if (card) {
                    document.querySelectorAll('.media-card').forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    
                    // Enable the select button
                    document.getElementById('selectMediaButton').disabled = false;
                    
                    // Store selected file info
                    const selectButton = card.querySelector('.select-media');
                    if (selectButton) {
                        document.getElementById('selectMediaButton').dataset.id = selectButton.dataset.id;
                        document.getElementById('selectMediaButton').dataset.path = selectButton.dataset.path;
                        document.getElementById('selectMediaButton').dataset.isImage = selectButton.dataset.isImage;
                    }
                }
            }
        });
        
        // Load media browser content
        // This will be implemented when we build the integration
    });
    
    // Helper function for file type icons
    function file_type_icon(type) {
        switch (type) {
            case 'pdf': return 'pdf';
            case 'doc':
            case 'docx': return 'word';
            case 'xls':
            case 'xlsx': return 'excel';
            case 'zip': return 'zip';
            case 'txt': return 'text';
            case 'csv': return 'csv';
            default: return 'earmark';
        }
    }
</script>

<?php 
/**
 * Get appropriate icon for file type
 * 
 * @param string $type File type
 * @return string Icon name
 */
function file_type_icon($type) {
    switch ($type) {
        case 'pdf': return 'pdf';
        case 'doc':
        case 'docx': return 'word';
        case 'xls':
        case 'xlsx': return 'excel';
        case 'zip': return 'zip';
        case 'txt': return 'text';
        case 'csv': return 'csv';
        default: return 'earmark';
    }
}

// Include footer template
include_once '../../shared/templates/admin-footer.php';
?>