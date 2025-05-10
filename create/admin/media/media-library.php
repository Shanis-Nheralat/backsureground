<?php
/**
 * Media Library - Admin Interface
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';

// Authentication check - will redirect to login if not authenticated
require_admin_auth();

// Include other required components
require_once '../../shared/admin-notifications.php';
require_once '../../shared/media/media-functions.php';

// Check for picker mode
$is_picker = isset($_GET['picker']) && $_GET['picker'] == 1;
$picker_filter = $_GET['filter'] ?? '';
$picker_client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : null;
$picker_multiple = isset($_GET['multiple']) && $_GET['multiple'] == 1;

// Page variables
$page_title = 'Media Library';
$current_page = 'media_library';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/admin/dashboard.php'],
    ['title' => 'Media Library', 'url' => '#']
];

// Get current folder
$current_folder = isset($_GET['folder']) ? $_GET['folder'] : '/';

// Get search filters
$search = $_GET['search'] ?? '';
$filetype = $_GET['filetype'] ?? '';
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : null;

// Get page number
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;

// Process uploads
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
    } else {
        $folder = $_POST['folder'] ?? '/';
        $tags = $_POST['tags'] ?? '';
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : null;
        
        $uploaded_count = 0;
        $errors = [];
        
        // Process each uploaded file
        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['files']['name'][$key],
                    'type' => $_FILES['files']['type'][$key],
                    'tmp_name' => $tmp_name,
                    'error' => $_FILES['files']['error'][$key],
                    'size' => $_FILES['files']['size'][$key]
                ];
                
                $result = upload_media_file($file, [
                    'folder' => $folder,
                    'client_id' => $client_id,
                    'tags' => $tags
                ]);
                
                if ($result) {
                    $uploaded_count++;
                } else {
                    $errors[] = "Failed to upload file: " . $_FILES['files']['name'][$key];
                }
            } else {
                $errors[] = "Error with file: " . $_FILES['files']['name'][$key];
            }
        }
        
        if ($uploaded_count > 0) {
            set_notification('success', "Successfully uploaded {$uploaded_count} file(s).");
        }
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                set_notification('error', $error);
            }
        }
    }
}

// Search media with filters
$filters = [
    'folder' => $current_folder,
    'search' => $search,
    'filetype' => $filetype,
    'client_id' => $client_id
];

// In picker mode with filter, apply the filter
if ($is_picker && !empty($picker_filter)) {
    if ($picker_filter == 'image') {
        $filters['filetype'] = ['jpg', 'jpeg', 'png', 'gif'];
    } elseif ($picker_filter == 'document') {
        $filters['filetype'] = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
    }
}

// In picker mode with client_id, apply the filter
if ($is_picker && $picker_client_id) {
    $filters['client_id'] = $picker_client_id;
}

$media_results = search_media($filters, $page, $per_page);
$media_items = $media_results['items'];
$total_items = $media_results['total'];

// Calculate pagination
$total_pages = ceil($total_items / $per_page);

// Get folder structure
$folders = get_media_folders();

// Get client list for filter (admin only)
$clients = [];
if (has_admin_role(['admin'])) {
    $client_sql = "SELECT id, name FROM clients ORDER BY name";
    $client_stmt = $pdo->prepare($client_sql);
    $client_stmt->execute();
    $clients = $client_stmt->fetchAll();
}

// Get file type list for filter
$filetype_sql = "SELECT DISTINCT filetype FROM media_library WHERE is_deleted = 0 ORDER BY filetype";
$filetype_stmt = $pdo->prepare($filetype_sql);
$filetype_stmt->execute();
$filetypes = $filetype_stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Slim view for picker mode
if ($is_picker) {
    include '../../shared/templates/picker-head.php';
} else {
    // Include template parts
    include '../../shared/templates/admin-head.php';
    include '../../shared/templates/admin-sidebar.php';
    include '../../shared/templates/admin-header.php';
}
?>

<?php if (!$is_picker): ?>
<main class="admin-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            
            <!-- Upload button -->
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="bi bi-upload me-2"></i> Upload Files
                </button>
            </div>
        </div>
        
        <?php display_notifications(); ?>
<?php else: ?>
<div class="media-picker-container p-3">
    <h5>Select File</h5>
<?php endif; ?>
        
        <!-- Media library content -->
        <div class="row">
            <!-- Sidebar with filters and folders -->
            <div class="col-md-3">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Filters</h6>
                    </div>
                    <div class="card-body">
                        <form method="get" class="mb-3">
                            <?php if ($is_picker): ?>
                            <input type="hidden" name="picker" value="1">
                            <?php if (!empty($picker_filter)): ?>
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($picker_filter); ?>">
                            <?php endif; ?>
                            <?php if ($picker_client_id): ?>
                            <input type="hidden" name="client_id" value="<?php echo $picker_client_id; ?>">
                            <?php endif; ?>
                            <?php if ($picker_multiple): ?>
                            <input type="hidden" name="multiple" value="1">
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="filetype" class="form-label">File Type</label>
                                <select class="form-select" id="filetype" name="filetype">
                                    <option value="">All Types</option>
                                    <?php foreach ($filetypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filetype == $type ? 'selected' : ''; ?>>
                                        <?php echo strtoupper(htmlspecialchars($type)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if (has_admin_role(['admin']) && !empty($clients)): ?>
                            <div class="mb-3">
                                <label for="client_id" class="form-label">Client</label>
                                <select class="form-select" id="client_id" name="client_id">
                                    <option value="">All Clients</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>" <?php echo $client_id == $client['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        </form>
                        
                        <hr>
                        
                        <h6 class="font-weight-bold mb-3">Folders</h6>
                        
                        <?php echo render_folder_tree($folders, $current_folder); ?>
                    </div>
                </div>
            </div>
            
            <!-- Main content with media grid -->
            <div class="col-md-9">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">
                            <?php
                            if ($current_folder == '/') {
                                echo 'Root Folder';
                            } else {
                                echo 'Folder: ' . htmlspecialchars($current_folder);
                            }
                            ?>
                            <span class="text-muted ms-2">(<?php echo $total_items; ?> files)</span>
                        </h6>
                        
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary view-toggle view-grid active">
                                <i class="bi bi-grid"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary view-toggle view-list">
                                <i class="bi bi-list"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($media_items)): ?>
                        <div class="alert alert-info">
                            No files found in this folder. Use the upload button to add files.
                        </div>
                        <?php else: ?>
                        
                        <!-- Batch actions -->
                        <?php if (!$is_picker): ?>
                        <div class="batch-actions mb-3" style="display: none;">
                            <div class="d-flex align-items-center">
                                <div class="selection-count me-3">
                                    <span class="selected-count">0</span> files selected
                                </div>
                                <div class="actions">
                                    <button type="button" class="btn btn-sm btn-danger batch-delete">
                                        <i class="bi bi-trash"></i> Delete Selected
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary batch-move" data-bs-toggle="modal" data-bs-target="#moveFolderModal">
                                        <i class="bi bi-folder"></i> Move Selected
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary batch-tag" data-bs-toggle="modal" data-bs-target="#batchTagModal">
                                        <i class="bi bi-tag"></i> Tag Selected
                                    </button>
                                </div>
                            </div>
                            <hr>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Grid view (default) -->
                        <div class="media-view media-grid-view">
                            <div class="row">
                                <?php foreach ($media_items as $item): ?>
                                <div class="col-md-3 mb-4">
                                    <div class="media-card card h-100" data-id="<?php echo $item['id']; ?>">
                                        <?php if (!$is_picker): ?>
                                        <div class="media-select">
                                            <input type="checkbox" class="media-checkbox" data-id="<?php echo $item['id']; ?>">
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="media-preview">
                                            <?php if (in_array($item['filetype'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                            <img src="<?php echo $item['url']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['original_filename']); ?>">
                                            <?php else: ?>
                                            <div class="file-icon">
                                                <i class="bi bi-file-earmark-<?php echo get_file_icon_class($item['filetype']); ?> display-4"></i>
                                                <span class="file-ext"><?php echo strtoupper($item['filetype']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="card-body">
                                            <h6 class="card-title text-truncate" title="<?php echo htmlspecialchars($item['original_filename']); ?>">
                                                <?php echo htmlspecialchars($item['original_filename']); ?>
                                            </h6>
                                            <p class="card-text small text-muted mb-2">
                                                <?php echo format_filesize($item['filesize']); ?> &middot; <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                                            </p>
                                            
                                            <?php if (!empty($item['tags'])): ?>
                                            <div class="media-tags mb-2">
                                                <?php foreach (explode(',', $item['tags']) as $tag): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="media-actions mt-2">
                                                <?php if ($is_picker): ?>
                                                <button type="button" class="btn btn-sm btn-primary select-media" data-id="<?php echo $item['id']; ?>" data-filename="<?php echo htmlspecialchars($item['original_filename']); ?>" data-url="<?php echo $item['url']; ?>">
                                                    Select
                                                </button>
                                                <?php else: ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="<?php echo $item['url']; ?>" target="_blank">View</a></li>
                                                        <li><a class="dropdown-item media-info" href="#" data-bs-toggle="modal" data-bs-target="#mediaInfoModal" data-id="<?php echo $item['id']; ?>">Info</a></li>
                                                        <li><a class="dropdown-item media-move" href="#" data-bs-toggle="modal" data-bs-target="#moveFolderModal" data-id="<?php echo $item['id']; ?>">Move</a></li>
                                                        <li><a class="dropdown-item media-tag" href="#" data-bs-toggle="modal" data-bs-target="#tagModal" data-id="<?php echo $item['id']; ?>">Edit Tags</a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger media-delete" href="#" data-id="<?php echo $item['id']; ?>">Delete</a></li>
                                                    </ul>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- List view (alternate) -->
                        <div class="media-view media-list-view" style="display: none;">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <?php if (!$is_picker): ?>
                                        <th width="40">
                                            <input type="checkbox" class="select-all-checkbox">
                                        </th>
                                        <?php endif; ?>
                                        <th width="60">Type</th>
                                        <th>Filename</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($media_items as $item): ?>
                                    <tr data-id="<?php echo $item['id']; ?>">
                                        <?php if (!$is_picker): ?>
                                        <td>
                                            <input type="checkbox" class="media-checkbox" data-id="<?php echo $item['id']; ?>">
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <i class="bi bi-file-earmark-<?php echo get_file_icon_class($item['filetype']); ?>"></i>
                                            <span class="small"><?php echo strtoupper($item['filetype']); ?></span>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($item['original_filename']); ?>">
                                                <?php echo htmlspecialchars($item['original_filename']); ?>
                                            </div>
                                            <?php if (!empty($item['tags'])): ?>
                                            <div class="small mt-1">
                                                <?php foreach (explode(',', $item['tags']) as $tag): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo format_filesize($item['filesize']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($item['created_at'])); ?></td>
                                        <td>
                                            <?php if ($is_picker): ?>
                                            <button type="button" class="btn btn-sm btn-primary select-media" data-id="<?php echo $item['id']; ?>" data-filename="<?php echo htmlspecialchars($item['original_filename']); ?>" data-url="<?php echo $item['url']; ?>">
                                                Select
                                            </button>
                                            <?php else: ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="<?php echo $item['url']; ?>" target="_blank">View</a></li>
                                                    <li><a class="dropdown-item media-info" href="#" data-bs-toggle="modal" data-bs-target="#mediaInfoModal" data-id="<?php echo $item['id']; ?>">Info</a></li>
                                                    <li><a class="dropdown-item media-move" href="#" data-bs-toggle="modal" data-bs-target="#moveFolderModal" data-id="<?php echo $item['id']; ?>">Move</a></li>
                                                    <li><a class="dropdown-item media-tag" href="#" data-bs-toggle="modal" data-bs-target="#tagModal" data-id="<?php echo $item['id']; ?>">Edit Tags</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger media-delete" href="#" data-id="<?php echo $item['id']; ?>">Delete</a></li>
                                                </ul>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                                </li>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a></li>';
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
<?php if (!$is_picker): ?>
    </div>
</main>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <!-- CSRF protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label for="folder" class="form-label">Folder</label>
                        <input type="text" class="form-control" id="folder" name="folder" value="<?php echo htmlspecialchars($current_folder); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="tags" class="form-label">Tags (comma separated)</label>
                        <input type="text" class="form-control" id="tags" name="tags" placeholder="e.g. logo, header, document">
                    </div>
                    
                    <?php if (has_admin_role(['admin']) && !empty($clients)): ?>
                    <div class="mb-3">
                        <label for="upload_client_id" class="form-label">Client (optional)</label>
                        <select class="form-select" id="upload_client_id" name="client_id">
                            <option value="">None</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>">
                                <?php echo htmlspecialchars($client['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="files" class="form-label">Files</label>
                        <div class="file-drop-area">
                            <span class="file-message">Drag & drop files here or click to browse</span>
                            <input type="file" class="file-input" id="files" name="files[]" multiple>
                        </div>
                        <div class="file-list mt-3"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="uploadSubmit">Upload</button>
            </div>
        </div>
    </div>
</div>

<!-- Move Folder Modal -->
<div class="modal fade" id="moveFolderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Move to Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="moveFolderForm">
                    <input type="hidden" name="media_ids" id="moveMediaIds">
                    
                    <div class="mb-3">
                        <label for="destination_folder" class="form-label">Destination Folder</label>
                        <input type="text" class="form-control" id="destination_folder" name="destination_folder" value="/">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="moveSubmit">Move</button>
            </div>
        </div>
    </div>
</div>

<!-- Tag Modal -->
<div class="modal fade" id="tagModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Tags</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="tagForm">
                    <input type="hidden" name="media_id" id="tagMediaId">
                    
                    <div class="mb-3">
                        <label for="media_tags" class="form-label">Tags (comma separated)</label>
                        <input type="text" class="form-control" id="media_tags" name="media_tags" placeholder="e.g. logo, header, document">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="tagSubmit">Save Tags</button>
            </div>
        </div>
    </div>
</div>

<!-- Batch Tag Modal -->
<div class="modal fade" id="batchTagModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Tags for Selected Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="batchTagForm">
                    <input type="hidden" name="media_ids" id="batchTagMediaIds">
                    
                    <div class="mb-3">
                        <label for="batch_media_tags" class="form-label">Tags (comma separated)</label>
                        <input type="text" class="form-control" id="batch_media_tags" name="batch_media_tags" placeholder="e.g. logo, header, document">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tag_action" id="tag_action_replace" value="replace" checked>
                            <label class="form-check-label" for="tag_action_replace">
                                Replace existing tags
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tag_action" id="tag_action_add" value="add">
                            <label class="form-check-label" for="tag_action_add">
                                Add to existing tags
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="batchTagSubmit">Save Tags</button>
            </div>
        </div>
    </div>
</div>

<!-- Media Info Modal -->
<div class="modal fade" id="mediaInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">File Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5" id="infoPreview">
                        <!-- Preview will be inserted here -->
                    </div>
                    <div class="col-md-7">
                        <table class="table">
                            <tbody id="infoDetails">
                                <!-- Details will be inserted here -->
                            </tbody>
                        </table>
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
// Media library JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Toggle between grid and list view
    document.querySelectorAll('.view-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            document.querySelectorAll('.view-toggle').forEach(e => e.classList.remove('active'));
            this.classList.add('active');
            
            if (this.classList.contains('view-grid')) {
                document.querySelector('.media-grid-view').style.display = 'block';
                document.querySelector('.media-list-view').style.display = 'none';
            } else {
                document.querySelector('.media-grid-view').style.display = 'none';
                document.querySelector('.media-list-view').style.display = 'block';
            }
        });
    });
    
    // File upload handling
    if (document.querySelector('.file-drop-area')) {
        const dropArea = document.querySelector('.file-drop-area');
        const fileInput = dropArea.querySelector('.file-input');
        const fileMessage = dropArea.querySelector('.file-message');
        
        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add('highlight');
        }
        
        function unhighlight() {
            dropArea.classList.remove('highlight');
        }
        
        // Handle dropped files
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            fileInput.files = files;
            updateFileList();
            
            e.preventDefault();
        }
        
        // File input change
        fileInput.addEventListener('change', function() {
            updateFileList();
        });
        
        function updateFileList() {
            const fileList = document.querySelector('.file-list');
            fileList.innerHTML = '';
            
            if (fileInput.files.length > 0) {
                for (let i = 0; i < fileInput.files.length; i++) {
                    const file = fileInput.files[i];
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    fileItem.innerHTML = `
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-file-earmark me-2"></i>
                            <span class="filename">${file.name}</span>
                            <span class="filesize ms-2 text-muted">(${formatFileSize(file.size)})</span>
                        </div>
                    `;
                    fileList.appendChild(fileItem);
                }
                
                fileMessage.textContent = fileInput.files.length + ' file(s) selected';
            } else {
                fileMessage.textContent = 'Drag & drop files here or click to browse';
            }
        }
        
        // Format file size
        function formatFileSize(bytes) {
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            if (bytes === 0) return '0 B';
            const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
            return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
        }
        
        // Handle upload form submission
        document.getElementById('uploadSubmit').addEventListener('click', function() {
            if (fileInput.files.length === 0) {
                alert('Please select at least one file to upload.');
                return;
            }
            
            document.getElementById('uploadForm').submit();
        });
    }
    
    // Batch selection handling
    const checkboxes = document.querySelectorAll('.media-checkbox');
    const selectAllCheckbox = document.querySelector('.select-all-checkbox');
    const batchActions = document.querySelector('.batch-actions');
    const selectedCountElement = document.querySelector('.selected-count');
    
    if (checkboxes.length > 0) {
        // Update batch actions visibility
        function updateBatchActions() {
            const selectedCount = document.querySelectorAll('.media-checkbox:checked').length;
            selectedCountElement.textContent = selectedCount;
            
            if (selectedCount > 0) {
                batchActions.style.display = 'block';
            } else {
                batchActions.style.display = 'none';
            }
        }
        
        // Individual checkbox change
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                updateBatchActions();
                
                // Update select all state
                selectAllCheckbox.checked = document.querySelectorAll('.media-checkbox:checked').length === checkboxes.length;
            });
        });
        
        // Select all checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                
                updateBatchActions();
            });
        }
        
        // Batch delete
        document.querySelector('.batch-delete').addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('.media-checkbox:checked')).map(cb => cb.dataset.id);
            
            if (selectedIds.length === 0) return;
            
            if (confirm('Are you sure you want to delete ' + selectedIds.length + ' selected file(s)?')) {
                deleteMedia(selectedIds);
            }
        });
        
        // Batch move
        document.querySelector('.batch-move').addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('.media-checkbox:checked')).map(cb => cb.dataset.id);
            document.getElementById('moveMediaIds').value = selectedIds.join(',');
        });
        
        // Batch tag
        document.querySelector('.batch-tag').addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('.media-checkbox:checked')).map(cb => cb.dataset.id);
            document.getElementById('batchTagMediaIds').value = selectedIds.join(',');
        });
    }
    
    // Individual media actions
    
    // Move media
    document.querySelectorAll('.media-move').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const mediaId = this.dataset.id;
            document.getElementById('moveMediaIds').value = mediaId;
        });
    });
    
    // Tag media
    document.querySelectorAll('.media-tag').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const mediaId = this.dataset.id;
            document.getElementById('tagMediaId').value = mediaId;
            
            // Get current tags
            fetch('/admin/media/media-metadata.php?id=' + mediaId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('media_tags').value = data.media.tags || '';
                    }
                });
        });
    });
    
    // Delete media
    document.querySelectorAll('.media-delete').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const mediaId = this.dataset.id;
            
            if (confirm('Are you sure you want to delete this file?')) {
                deleteMedia([mediaId]);
            }
        });
    });
    
    // View media info
    document.querySelectorAll('.media-info').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const mediaId = this.dataset.id;
            
            fetch('/admin/media/media-metadata.php?id=' + mediaId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const media = data.media;
                        const previewEl = document.getElementById('infoPreview');
                        const detailsEl = document.getElementById('infoDetails');
                        
                        // Preview
                        if (['jpg', 'jpeg', 'png', 'gif'].includes(media.filetype)) {
                            previewEl.innerHTML = `<img src="${media.url}" class="img-fluid" alt="${media.original_filename}">`;
                        } else {
                            previewEl.innerHTML = `
                                <div class="file-icon text-center p-4">
                                    <i class="bi bi-file-earmark-${getFileIconClass(media.filetype)} display-1"></i>
                                    <p class="mt-3">${media.original_filename}</p>
                                    <a href="${media.url}" class="btn btn-primary mt-2" target="_blank">Download</a>
                                </div>
                            `;
                        }
                        
                        // Details
                        let details = `
                            <tr><th>Filename</th><td>${media.original_filename}</td></tr>
                            <tr><th>Type</th><td>${media.filetype.toUpperCase()}</td></tr>
                            <tr><th>Size</th><td>${formatFileSize(media.filesize)}</td></tr>
                            <tr><th>Uploaded</th><td>${new Date(media.created_at).toLocaleString()}</td></tr>
                            <tr><th>Folder</th><td>${media.folder}</td></tr>
                        `;
                        
                        if (media.tags) {
                            details += `<tr><th>Tags</th><td>${media.tags}</td></tr>`;
                        }
                        
                        // Add metadata
                        if (media.metadata) {
                            const metadata = JSON.parse(media.metadata);
                            
                            if (metadata.width && metadata.height) {
                                details += `<tr><th>Dimensions</th><td>${metadata.width} x ${metadata.height}px</td></tr>`;
                            }
                            
                            if (metadata.pages) {
                                details += `<tr><th>Pages</th><td>${metadata.pages}</td></tr>`;
                            }
                        }
                        
                        detailsEl.innerHTML = details;
                    }
                });
        });
    });
    
    // Submit move form
    document.getElementById('moveSubmit').addEventListener('click', function() {
        const mediaIds = document.getElementById('moveMediaIds').value.split(',');
        const destFolder = document.getElementById('destination_folder').value;
        
        const promises = mediaIds.map(id => {
            return fetch('/admin/media/media-move.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'media_id=' + id + '&destination=' + encodeURIComponent(destFolder) + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
            })
            .then(response => response.json());
        });
        
        Promise.all(promises)
            .then(results => {
                const successCount = results.filter(r => r.success).length;
                
                if (successCount === mediaIds.length) {
                    alert('Successfully moved ' + successCount + ' file(s).');
                    location.reload();
                } else {
                    alert('Moved ' + successCount + ' of ' + mediaIds.length + ' file(s). Some errors occurred.');
                    location.reload();
                }
                
                document.getElementById('moveFolderModal').querySelector('.btn-close').click();
            });
    });
    
    // Submit tag form
    document.getElementById('tagSubmit').addEventListener('click', function() {
        const mediaId = document.getElementById('tagMediaId').value;
        const tags = document.getElementById('media_tags').value;
        
        fetch('/admin/media/media-metadata.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'media_id=' + mediaId + '&tags=' + encodeURIComponent(tags) + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Tags updated successfully.');
                location.reload();
            } else {
                alert('Error updating tags: ' + data.message);
            }
            
            document.getElementById('tagModal').querySelector('.btn-close').click();
        });
    });
    
    // Submit batch tag form
    document.getElementById('batchTagSubmit').addEventListener('click', function() {
        const mediaIds = document.getElementById('batchTagMediaIds').value.split(',');
        const tags = document.getElementById('batch_media_tags').value;
        const action = document.querySelector('input[name="tag_action"]:checked').value;
        
        const promises = mediaIds.map(id => {
            return fetch('/admin/media/media-metadata.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'media_id=' + id + '&tags=' + encodeURIComponent(tags) + '&action=' + action + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
            })
            .then(response => response.json());
        });
        
        Promise.all(promises)
            .then(results => {
                const successCount = results.filter(r => r.success).length;
                
                if (successCount === mediaIds.length) {
                    alert('Successfully updated tags for ' + successCount + ' file(s).');
                    location.reload();
                } else {
                    alert('Updated tags for ' + successCount + ' of ' + mediaIds.length + ' file(s). Some errors occurred.');
                    location.reload();
                }
                
                document.getElementById('batchTagModal').querySelector('.btn-close').click();
            });
    });
    
    // Helper function to delete media files
    function deleteMedia(ids) {
        const promises = ids.map(id => {
            return fetch('/admin/media/media-delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'media_id=' + id + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
            })
            .then(response => response.json());
        });
        
        Promise.all(promises)
            .then(results => {
                const successCount = results.filter(r => r.success).length;
                
                if (successCount === ids.length) {
                    alert('Successfully deleted ' + successCount + ' file(s).');
                    location.reload();
                } else {
                    alert('Deleted ' + successCount + ' of ' + ids.length + ' file(s). Some errors occurred.');
                    location.reload();
                }
            });
    }
    
    // Helper function to get file icon class
    function getFileIconClass(filetype) {
        switch (filetype) {
            case 'pdf': return 'pdf';
            case 'doc':
            case 'docx': return 'word';
            case 'xls':
            case 'xlsx': return 'excel';
            case 'ppt':
            case 'pptx': return 'slides';
            case 'zip':
            case 'rar': return 'zip';
            case 'txt': return 'text';
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif': return 'image';
            default: return 'file';
        }
    }
    
    // Helper function to format file size
    function formatFileSize(bytes) {
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        if (bytes === 0) return '0 B';
        const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
        return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
    }
});
</script>
<?php else: ?>
<!-- Picker mode JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Media selection in picker mode
    document.querySelectorAll('.select-media').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const mediaId = this.dataset.id;
            const filename = this.dataset.filename;
            const url = this.dataset.url;
            
            // Send selected media info to parent window
            window.parent.postMessage({
                type: 'media-selected',
                media: {
                    id: mediaId,
                    filename: filename,
                    url: url
                }
            }, '*');
        });
    });
    
    // Helper function to get file icon class
    function getFileIconClass(filetype) {
        switch (filetype) {
            case 'pdf': return 'pdf';
            case 'doc':
            case 'docx': return 'word';
            case 'xls':
            case 'xlsx': return 'excel';
            case 'ppt':
            case 'pptx': return 'slides';
            case 'zip':
            case 'rar': return 'zip';
            case 'txt': return 'text';
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif': return 'image';
            default: return 'file';
        }
    }
});
</script>
<?php endif; ?>

<?php
// Helper function to get file icon class
function get_file_icon_class($filetype) {
    switch ($filetype) {
        case 'pdf': return 'pdf';
        case 'doc':
        case 'docx': return 'word';
        case 'xls':
        case 'xlsx': return 'excel';
        case 'ppt':
        case 'pptx': return 'slides';
        case 'zip':
        case 'rar': return 'zip';
        case 'txt': return 'text';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif': return 'image';
        default: return 'file';
    }
}

// Include footer in admin mode
if (!$is_picker) {
    include '../../shared/templates/admin-footer.php';
} else {
    // Include picker footer
    include '../../shared/templates/picker-footer.php';
}
?>