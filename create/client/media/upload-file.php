<?php
/**
 * Client File Upload
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';

// Authentication check - require client role
require_admin_auth();
require_admin_role(['client']);

// Include other required components
require_once '../../shared/admin-notifications.php';
require_once '../../shared/media/media-functions.php';

// Page variables
$page_title = 'Upload Files';
$current_page = 'client_media_upload';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/client/dashboard.php'],
    ['title' => 'My Files', 'url' => '/client/media/my-uploads.php'],
    ['title' => 'Upload Files', 'url' => '#']
];

// Get client ID
$client_id = get_client_id_from_user($_SESSION['user_id']);
if (!$client_id) {
    set_notification('error', 'Client account not found.');
    header('Location: /client/dashboard.php');
    exit;
}

// Process uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_notification('error', 'Invalid form submission. Please try again.');
    } else {
        $folder = $_POST['folder'] ?? '/';
        $tags = $_POST['tags'] ?? '';
        
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
            header('Location: /client/media/my-uploads.php');
            exit;
        }
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                set_notification('error', $error);
            }
        }
    }
}

// Get folder structure
$folders = get_media_folders();

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
            
            <a href="/client/media/my-uploads.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back to Files
            </a>
        </div>
        
        <?php display_notifications(); ?>
        
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold">Upload New Files</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" id="uploadForm">
                            <!-- CSRF protection -->
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-4">
                                <label for="folder" class="form-label">Folder</label>
                                <select class="form-select" id="folder" name="folder">
                                    <?php 
                                    // Render folder options
                                    function render_folder_options($folders, $current_folder = '/', $indent = 0) {
                                        $html = '';
                                        
                                        // Root folder
                                        if ($indent === 0) {
                                            $html .= '<option value="/" ' . ($current_folder === '/' ? 'selected' : '') . '>Root Folder</option>';
                                        }
                                        
                                        // Child folders
                                        foreach ($folders as $name => $folder) {
                                            $indent_str = str_repeat('&nbsp;&nbsp;', $indent);
                                            $html .= '<option value="' . htmlspecialchars($folder['path']) . '" ' . 
                                                     ($current_folder === $folder['path'] ? 'selected' : '') . '>' . 
                                                     $indent_str . '└ ' . htmlspecialchars($name) . '</option>';
                                            
                                            if (!empty($folder['children'])) {
                                                $html .= render_folder_options($folder['children'], $current_folder, $indent + 1);
                                            }
                                        }
                                        
                                        return $html;
                                    }
                                    
                                    echo render_folder_options($folders, '/');
                                    ?>
                                </select>
                                <div class="form-text">
                                    Select an existing folder or enter a new folder path
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="tags" class="form-label">Tags (comma separated)</label>
                                <input type="text" class="form-control" id="tags" name="tags" placeholder="e.g. invoice, report, contract">
                                <div class="form-text">
                                    Optional tags to help organize your files
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="files" class="form-label">Files</label>
                                <div class="file-drop-area p-5 border rounded text-center">
                                    <span class="file-message mb-3 d-block">Drag & drop files here or click to browse</span>
                                    <input type="file" class="file-input d-none" id="files" name="files[]" multiple>
                                    <button type="button" class="btn btn-outline-primary btn-browse">
                                        <i class="bi bi-folder"></i> Browse Files
                                    </button>
                                    <div class="small text-muted mt-2">
                                        Allowed file types: JPG, PNG, PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, ZIP
                                    </div>
                                </div>
                                <div class="file-list mt-3"></div>
                            </div>
                            
                            <div class="text-end">
                                <a href="/client/media/my-uploads.php" class="btn btn-outline-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary" id="uploadSubmit">
                                    <i class="bi bi-upload me-2"></i> Upload Files
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File upload handling
    const dropArea = document.querySelector('.file-drop-area');
    const fileInput = dropArea.querySelector('.file-input');
    const fileMessage = dropArea.querySelector('.file-message');
    const browseBtn = dropArea.querySelector('.btn-browse');
    
    // Click browse button to trigger file input
    browseBtn.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Highlight drop area when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        dropArea.classList.add('border-primary');
    }
    
    function unhighlight() {
        dropArea.classList.remove('border-primary');
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
            fileList.innerHTML = '<h6 class="mt-3 mb-2">Selected Files:</h6>';
            
            for (let i = 0; i < fileInput.files.length; i++) {
                const file = fileInput.files[i];
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <div class="d-flex align-items-center mb-2 p-2 border rounded">
                        <i class="bi bi-file-earmark me-2"></i>
                        <div class="flex-grow-1">
                            <div class="filename">${file.name}</div>
                            <div class="filesize small text-muted">${formatFileSize(file.size)}</div>
                        </div>
                    </div>
                `;
                fileList.appendChild(fileItem);
            }
            
            fileMessage.textContent = fileInput.files.length > 1 ? 
                fileInput.files.length + ' files selected' : '1 file selected';
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
    
    // Form submission validation
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        if (fileInput.files.length === 0) {
            e.preventDefault();
            alert('Please select at least one file to upload.');
        }
    });
});
</script>

<?php
// Include footer
include '../../shared/templates/client-footer.php';
?>