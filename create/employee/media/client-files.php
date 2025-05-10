<?php
/**
 * Employee View: Client Files
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';

// Authentication check - require employee role
require_admin_auth();
require_admin_role(['employee']);

// Include other required components
require_once '../../shared/admin-notifications.php';
require_once '../../shared/media/media-functions.php';

// Page variables
$page_title = 'Client Files';
$current_page = 'employee_client_files';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/employee/dashboard.php'],
    ['title' => 'Client Files', 'url' => '#']
];

// Get client ID from query string if provided
$active_client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : null;

// Get employee's assigned clients
$employee_id = $_SESSION['user_id'];
$assigned_clients = get_employee_assigned_clients($employee_id);

if (empty($assigned_clients)) {
    set_notification('info', 'You have no assigned clients yet.');
}

// If client ID is provided, check if employee is assigned to this client
if ($active_client_id && !in_array($active_client_id, $assigned_clients)) {
    set_notification('error', 'You are not assigned to this client.');
    header('Location: /employee/media/client-files.php');
    exit;
}

// Get current folder
$current_folder = isset($_GET['folder']) ? $_GET['folder'] : '/';

// Get search filters
$search = $_GET['search'] ?? '';
$filetype = $_GET['filetype'] ?? '';

// Get page number
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;

// Get client list for dropdown
$client_sql = "SELECT id, name FROM clients WHERE id IN (" . implode(',', $assigned_clients) . ") ORDER BY name";
$client_stmt = $pdo->prepare($client_sql);
$client_stmt->execute();
$clients = $client_stmt->fetchAll();

// If we have clients but no active client selected, use the first one
if (empty($active_client_id) && !empty($clients)) {
    $active_client_id = $clients[0]['id'];
}

// Search media with filters
$filters = [
    'folder' => $current_folder,
    'search' => $search,
    'filetype' => $filetype,
    'client_id' => $active_client_id
];

$media_results = search_media($filters, $page, $per_page);
$media_items = $media_results['items'];
$total_items = $media_results['total'];

// Calculate pagination
$total_pages = ceil($total_items / $per_page);

// Get folder structure
$folders = get_media_folders();

// Get file type list for filter
$filetype_sql = "SELECT DISTINCT filetype FROM media_library WHERE is_deleted = 0 AND client_id = :client_id ORDER BY filetype";
$filetype_stmt = $pdo->prepare($filetype_sql);
$filetype_stmt->execute(['client_id' => $active_client_id]);
$filetypes = $filetype_stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include template parts
include '../../shared/templates/employee-head.php';
include '../../shared/templates/employee-sidebar.php';
include '../../shared/templates/employee-header.php';
?>

<main class="employee-main">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            
            <?php if (!empty($clients)): ?>
            <!-- Client selection dropdown -->
            <div class="client-selector">
                <form method="get" id="clientForm">
                    <div class="input-group">
                        <label class="input-group-text" for="client_id">Client:</label>
                        <select class="form-select" id="client_id" name="client_id" onchange="this.form.submit()">
                            <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" <?php echo $active_client_id == $client['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
        
        <?php display_notifications(); ?>
        
        <?php if (empty($clients)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i> You have no assigned clients. Please contact your administrator.
        </div>
        <?php elseif ($active_client_id): ?>
        
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
                            <input type="hidden" name="client_id" value="<?php echo $active_client_id; ?>">
                            
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
                                echo 'All Client Files';
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
                            No files found for this client.
                        </div>
                        <?php else: ?>
                        
                        <!-- Grid view (default) -->
                        <div class="media-view media-grid-view">
                            <div class="row">
                                <?php foreach ($media_items as $item): ?>
                                <div class="col-md-3 mb-4">
                                    <div class="media-card card h-100">
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
                                                <a href="<?php echo $item['url']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
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
                                        <th width="60">Type</th>
                                        <th>Filename</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($media_items as $item): ?>
                                    <tr>
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
                                            <a href="<?php echo $item['url']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                <i class="bi bi-download"></i> Download
                                            </a>
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
        
        <?php endif; ?>
    </div>
</main>

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
});
</script>

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

// Helper function to get assigned clients
function get_employee_assigned_clients($employee_id) {
    global $pdo;
    
    $sql = "SELECT client_id FROM employee_client_assignments WHERE employee_id = :employee_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['employee_id' => $employee_id]);
    
    $clients = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $clients ?: [];
}

// Include footer
include '../../shared/templates/employee-footer.php';
?>