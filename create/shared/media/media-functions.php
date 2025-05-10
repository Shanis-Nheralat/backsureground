<?php
/**
 * Core Media Library Functions
 */

// Prevent direct access
if (!defined('BACKSURE_SYSTEM')) {
    die('Direct access not permitted');
}

/**
 * Get a media item by ID
 * 
 * @param int $media_id Media ID
 * @param bool $check_access Whether to check user access
 * @return array|false Media data or false if not found/accessible
 */
function get_media($media_id, $check_access = true) {
    global $pdo;
    
    $sql = "SELECT * FROM media_library WHERE id = :id AND is_deleted = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $media_id]);
    $media = $stmt->fetch();
    
    if (!$media) {
        return false;
    }
    
    if ($check_access && !can_access_media($media)) {
        return false;
    }
    
    // Add full URL path for convenience
    $media['url'] = get_media_url($media['id']);
    
    return $media;
}

/**
 * Check if current user can access a media item
 * 
 * @param array $media Media data
 * @return bool Whether user can access the media
 */
function can_access_media($media) {
    // Admin can access all media
    if (has_admin_role(['admin'])) {
        return true;
    }
    
    // Get current user ID and role
    $user_id = $_SESSION['user_id'] ?? 0;
    $user_role = $_SESSION['user_role'] ?? '';
    
    // User can access their own uploads
    if ($media['uploaded_by'] == $user_id && $media['uploader_role'] == $user_role) {
        return true;
    }
    
    // Client can only access their own files
    if ($user_role == 'client') {
        return $media['client_id'] == get_client_id_from_user($user_id);
    }
    
    // Employee can access assigned client files
    if ($user_role == 'employee') {
        return is_employee_assigned_to_client($user_id, $media['client_id']);
    }
    
    return false;
}

/**
 * Get media URL for secure access
 * 
 * @param int $media_id Media ID
 * @return string Secure media URL
 */
function get_media_url($media_id) {
    $token = generate_media_access_token($media_id);
    return "/admin/media/media-serve.php?id={$media_id}&token={$token}";
}

/**
 * Generate secure token for media access
 * 
 * @param int $media_id Media ID
 * @return string Access token
 */
function generate_media_access_token($media_id) {
    $secret = get_setting('media_access_secret', 'backsure_media_key');
    $user_id = $_SESSION['user_id'] ?? 0;
    $user_role = $_SESSION['user_role'] ?? '';
    
    // Token valid for 1 hour
    $timestamp = floor(time() / 3600);
    
    return hash('sha256', $secret . $media_id . $user_id . $user_role . $timestamp);
}

/**
 * Search media library with filters
 * 
 * @param array $filters Associative array of filters
 * @param int $page Page number (1-based)
 * @param int $per_page Items per page
 * @return array [total_count, media_items]
 */
function search_media($filters = [], $page = 1, $per_page = 20) {
    global $pdo;
    
    $where = ['is_deleted = 0'];
    $params = [];
    
    // Apply role-based filtering
    if (!has_admin_role(['admin'])) {
        $user_id = $_SESSION['user_id'] ?? 0;
        $user_role = $_SESSION['user_role'] ?? '';
        
        if ($user_role == 'client') {
            $client_id = get_client_id_from_user($user_id);
            $where[] = "(uploaded_by = :user_id AND uploader_role = :user_role) OR client_id = :client_id";
            $params['user_id'] = $user_id;
            $params['user_role'] = $user_role;
            $params['client_id'] = $client_id;
        } elseif ($user_role == 'employee') {
            $assigned_clients = get_employee_assigned_clients($user_id);
            if (empty($assigned_clients)) {
                $where[] = "uploaded_by = :user_id AND uploader_role = :user_role";
                $params['user_id'] = $user_id;
                $params['user_role'] = $user_role;
            } else {
                $client_list = implode(',', $assigned_clients);
                $where[] = "(uploaded_by = :user_id AND uploader_role = :user_role) OR client_id IN ({$client_list})";
                $params['user_id'] = $user_id;
                $params['user_role'] = $user_role;
            }
        }
    }
    
    // Apply other filters
    if (!empty($filters['folder'])) {
        $where[] = "folder = :folder";
        $params['folder'] = $filters['folder'];
    }
    
    if (!empty($filters['filetype'])) {
        $where[] = "filetype = :filetype";
        $params['filetype'] = $filters['filetype'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(filename LIKE :search OR original_filename LIKE :search OR tags LIKE :search)";
        $params['search'] = "%{$filters['search']}%";
    }
    
    if (!empty($filters['client_id'])) {
        $where[] = "client_id = :filter_client_id";
        $params['filter_client_id'] = $filters['client_id'];
    }
    
    // Build query
    $where_clause = implode(' AND ', $where);
    
    // Count total results
    $count_sql = "SELECT COUNT(*) FROM media_library WHERE {$where_clause}";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
    
    // Get paginated results
    $offset = ($page - 1) * $per_page;
    $sql = "SELECT * FROM media_library WHERE {$where_clause} ORDER BY created_at DESC LIMIT :offset, :limit";
    $stmt = $pdo->prepare($sql);
    
    // PDO requires explicit binding for LIMIT parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    // Add URLs to items
    foreach ($items as &$item) {
        $item['url'] = get_media_url($item['id']);
        // Parse metadata for display
        if (!empty($item['metadata'])) {
            $item['metadata'] = json_decode($item['metadata'], true);
        }
    }
    
    return [
        'total' => $total_count,
        'items' => $items
    ];
}

/**
 * Upload a file to media library
 * 
 * @param array $file $_FILES array item
 * @param array $options Upload options
 * @return array|false Media data or false on failure
 */
function upload_media_file($file, $options = []) {
    global $pdo;
    
    // Default options
    $defaults = [
        'folder' => '/',
        'client_id' => null,
        'tags' => '',
    ];
    $options = array_merge($defaults, $options);
    
    // Validate file
    if (!is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    
    // Get file info
    $original_filename = $file['name'];
    $filetype = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $filesize = $file['size'];
    
    // Validate file type
    $allowed_types = get_allowed_file_types();
    if (!in_array($filetype, $allowed_types)) {
        return false;
    }
    
    // Generate unique filename
    $unique_name = generate_unique_filename($original_filename);
    
    // Determine storage path
    $storage_path = get_storage_path($options['client_id'], $filetype);
    
    // Create directory if it doesn't exist
    if (!is_dir($storage_path)) {
        mkdir($storage_path, 0755, true);
    }
    
    $filepath = $storage_path . '/' . $unique_name;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return false;
    }
    
    // Extract metadata
    $metadata = extract_file_metadata($filepath, $filetype);
    
    // Store in database
    $user_id = $_SESSION['user_id'] ?? 0;
    $user_role = $_SESSION['user_role'] ?? '';
    
    $sql = "INSERT INTO media_library (
        filename, original_filename, filepath, filetype, filesize, 
        uploaded_by, uploader_role, client_id, tags, folder, metadata
    ) VALUES (
        :filename, :original_filename, :filepath, :filetype, :filesize,
        :uploaded_by, :uploader_role, :client_id, :tags, :folder, :metadata
    )";
    
    $stmt = $pdo->prepare($sql);
    $params = [
        'filename' => $unique_name,
        'original_filename' => $original_filename,
        'filepath' => $filepath,
        'filetype' => $filetype,
        'filesize' => $filesize,
        'uploaded_by' => $user_id,
        'uploader_role' => $user_role,
        'client_id' => $options['client_id'],
        'tags' => $options['tags'],
        'folder' => $options['folder'],
        'metadata' => json_encode($metadata)
    ];
    
    $stmt->execute($params);
    $media_id = $pdo->lastInsertId();
    
    // Return the newly created media
    return get_media($media_id, false);
}

/**
 * Get allowed file types
 * 
 * @return array List of allowed extensions
 */
function get_allowed_file_types() {
    $default_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'];
    
    // Allow admin to configure allowed types through settings
    $custom_types = get_setting('allowed_file_types', '');
    if (!empty($custom_types)) {
        $types_array = explode(',', $custom_types);
        $types_array = array_map('trim', $types_array);
        $types_array = array_filter($types_array);
        if (!empty($types_array)) {
            return $types_array;
        }
    }
    
    return $default_types;
}

/**
 * Generate unique filename for storage
 * 
 * @param string $original_filename Original filename
 * @return string Unique filename
 */
function generate_unique_filename($original_filename) {
    $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $base = bin2hex(random_bytes(8)); // 16 character random hex
    $timestamp = date('Ymd_His');
    
    return "{$base}_{$timestamp}.{$extension}";
}

/**
 * Get storage path for uploaded file
 * 
 * @param int|null $client_id Client ID
 * @param string $filetype File extension
 * @return string Storage path
 */
function get_storage_path($client_id = null, $filetype = null) {
    $year = date('Y');
    $month = date('m');
    
    $base_path = __DIR__ . '/../../uploads';
    
    $role_path = 'admin';
    if ($_SESSION['user_role'] == 'client') {
        $role_path = 'client';
    } elseif ($_SESSION['user_role'] == 'employee') {
        $role_path = 'employee';
    }
    
    // For client uploads, store in client-specific folder
    if ($client_id) {
        return "{$base_path}/{$role_path}/{$client_id}/{$year}/{$month}";
    }
    
    return "{$base_path}/{$role_path}/{$year}/{$month}";
}

/**
 * Extract metadata from file
 * 
 * @param string $filepath Path to file
 * @param string $filetype File extension
 * @return array Metadata
 */
function extract_file_metadata($filepath, $filetype) {
    $metadata = ['type' => $filetype];
    
    // Image metadata
    if (in_array($filetype, ['jpg', 'jpeg', 'png', 'gif'])) {
        $info = getimagesize($filepath);
        if ($info) {
            $metadata['width'] = $info[0];
            $metadata['height'] = $info[1];
            $metadata['mime'] = $info['mime'];
        }
    }
    
    // PDF metadata
    if ($filetype == 'pdf' && extension_loaded('imagick')) {
        try {
            $im = new Imagick($filepath . '[0]'); // First page only
            $metadata['width'] = $im->getImageWidth();
            $metadata['height'] = $im->getImageHeight();
            $metadata['pages'] = $im->getNumberImages();
        } catch (Exception $e) {
            // Silently fail - metadata is optional
        }
    }
    
    return $metadata;
}

/**
 * Delete media file
 * 
 * @param int $media_id Media ID
 * @return bool Success
 */
function delete_media($media_id) {
    global $pdo;
    
    // Get media data
    $media = get_media($media_id);
    if (!$media) {
        return false;
    }
    
    // Check permission
    if (!can_modify_media($media)) {
        return false;
    }
    
    // Soft delete - mark as deleted in database
    $sql = "UPDATE media_library SET is_deleted = 1 WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute(['id' => $media_id]);
    
    // Log the deletion
    if ($result) {
        log_admin_action('media_delete', 'media', $media_id, "Deleted file: {$media['original_filename']}");
    }
    
    return $result;
}

/**
 * Check if user can modify a media item
 * 
 * @param array $media Media data
 * @return bool Whether user can modify the media
 */
function can_modify_media($media) {
    // Admin can modify all media
    if (has_admin_role(['admin'])) {
        return true;
    }
    
    // Users can only modify their own uploads
    $user_id = $_SESSION['user_id'] ?? 0;
    $user_role = $_SESSION['user_role'] ?? '';
    
    return ($media['uploaded_by'] == $user_id && $media['uploader_role'] == $user_role);
}

/**
 * Move media to different folder
 * 
 * @param int $media_id Media ID
 * @param string $new_folder New folder path
 * @return bool Success
 */
function move_media($media_id, $new_folder) {
    global $pdo;
    
    // Get media data
    $media = get_media($media_id);
    if (!$media) {
        return false;
    }
    
    // Check permission
    if (!can_modify_media($media)) {
        return false;
    }
    
    // Normalize folder path
    $new_folder = '/' . ltrim($new_folder, '/');
    
    // Update database
    $sql = "UPDATE media_library SET folder = :folder WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'folder' => $new_folder,
        'id' => $media_id
    ]);
    
    // Log the move
    if ($result) {
        log_admin_action('media_move', 'media', $media_id, "Moved file from {$media['folder']} to {$new_folder}");
    }
    
    return $result;
}

/**
 * Update media metadata
 * 
 * @param int $media_id Media ID
 * @param array $updates Fields to update
 * @return bool Success
 */
function update_media($media_id, $updates) {
    global $pdo;
    
    // Get media data
    $media = get_media($media_id);
    if (!$media) {
        return false;
    }
    
    // Check permission
    if (!can_modify_media($media)) {
        return false;
    }
    
    // Allowed fields to update
    $allowed_fields = ['tags', 'folder'];
    $set_parts = [];
    $params = ['id' => $media_id];
    
    foreach ($updates as $field => $value) {
        if (in_array($field, $allowed_fields)) {
            $set_parts[] = "{$field} = :{$field}";
            $params[$field] = $value;
        }
    }
    
    if (empty($set_parts)) {
        return false;
    }
    
    // Update database
    $set_clause = implode(', ', $set_parts);
    $sql = "UPDATE media_library SET {$set_clause} WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    // Log the update
    if ($result) {
        log_admin_action('media_update', 'media', $media_id, "Updated media metadata");
    }
    
    return $result;
}

/**
 * Get folder structure
 * 
 * @return array Folder structure
 */
function get_media_folders() {
    global $pdo;
    
    $sql = "SELECT DISTINCT folder FROM media_library WHERE is_deleted = 0";
    
    // Apply role-based filtering
    if (!has_admin_role(['admin'])) {
        $user_id = $_SESSION['user_id'] ?? 0;
        $user_role = $_SESSION['user_role'] ?? '';
        
        if ($user_role == 'client') {
            $client_id = get_client_id_from_user($user_id);
            $sql .= " AND ((uploaded_by = :user_id AND uploader_role = :user_role) OR client_id = :client_id)";
        } elseif ($user_role == 'employee') {
            $assigned_clients = get_employee_assigned_clients($user_id);
            if (empty($assigned_clients)) {
                $sql .= " AND (uploaded_by = :user_id AND uploader_role = :user_role)";
            } else {
                $client_list = implode(',', $assigned_clients);
                $sql .= " AND ((uploaded_by = :user_id AND uploader_role = :user_role) OR client_id IN ({$client_list}))";
            }
        }
    }
    
    $sql .= " ORDER BY folder";
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters if needed
    if (!has_admin_role(['admin'])) {
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':user_role', $user_role);
        
        if ($user_role == 'client') {
            $stmt->bindValue(':client_id', $client_id);
        }
    }
    
    $stmt->execute();
    $folders = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Always include root folder
    if (!in_array('/', $folders)) {
        array_unshift($folders, '/');
    }
    
    // Build folder tree
    $tree = [];
    foreach ($folders as $folder) {
        $parts = explode('/', trim($folder, '/'));
        $current = &$tree;
        
        $path = '';
        foreach ($parts as $part) {
            if (empty($part)) continue;
            
            $path .= '/' . $part;
            if (!isset($current[$part])) {
                $current[$part] = [
                    'path' => $path,
                    'children' => []
                ];
            }
            $current = &$current[$part]['children'];
        }
    }
    
    return $tree;
}

/**
 * Render folder tree as HTML
 * 
 * @param array $tree Folder tree from get_media_folders()
 * @param string $current_folder Currently selected folder
 * @return string HTML output
 */
function render_folder_tree($tree, $current_folder = '/') {
    $html = '<ul class="folder-tree">';
    $html .= '<li class="' . ($current_folder == '/' ? 'active' : '') . '">';
    $html .= '<a href="?folder=/" data-folder="/"><i class="bi bi-folder"></i> Root</a>';
    
    // Render children
    if (!empty($tree)) {
        $html .= render_folder_tree_children($tree, $current_folder);
    }
    
    $html .= '</li>';
    $html .= '</ul>';
    
    return $html;
}

/**
 * Helper for rendering folder tree children
 * 
 * @param array $children Children folders
 * @param string $current_folder Currently selected folder
 * @return string HTML output
 */
function render_folder_tree_children($children, $current_folder) {
    $html = '<ul>';
    
    foreach ($children as $name => $folder) {
        $is_active = ($folder['path'] == $current_folder);
        $html .= '<li class="' . ($is_active ? 'active' : '') . '">';
        $html .= '<a href="?folder=' . urlencode($folder['path']) . '" data-folder="' . htmlspecialchars($folder['path']) . '">';
        $html .= '<i class="bi bi-folder"></i> ' . htmlspecialchars($name) . '</a>';
        
        if (!empty($folder['children'])) {
            $html .= render_folder_tree_children($folder['children'], $current_folder);
        }
        
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    return $html;
}

/**
 * Render media picker for forms
 * 
 * @param string $field_name Form field name
 * @param string $current_value Current media ID
 * @param array $options Picker options
 * @return string HTML output
 */
function render_media_picker($field_name, $current_value = '', $options = []) {
    // Default options
    $defaults = [
        'label' => 'Select File',
        'preview' => true,
        'multiple' => false,
        'filter' => '', // e.g. 'image' to only show images
        'client_id' => null // For client-specific filtering
    ];
    $options = array_merge($defaults, $options);
    
    // Get current media if set
    $current_media = null;
    if (!empty($current_value)) {
        $current_media = get_media($current_value, true);
    }
    
    $preview_html = '';
    if ($options['preview'] && $current_media) {
        // For images, show thumbnail
        if (in_array($current_media['filetype'], ['jpg', 'jpeg', 'png', 'gif'])) {
            $preview_html = '<div class="media-preview mb-2">';
            $preview_html .= '<img src="' . $current_media['url'] . '" class="img-thumbnail" style="max-height: 100px;">';
            $preview_html .= '</div>';
        } else {
            // For other files, show icon
            $preview_html = '<div class="media-preview mb-2">';
            $preview_html .= '<i class="bi bi-file-earmark"></i> ' . htmlspecialchars($current_media['original_filename']);
            $preview_html .= '</div>';
        }
    }
    
    // Generate unique ID for modal
    $modal_id = 'media_picker_' . uniqid();
    
    // Start HTML output
    $html = '<div class="media-picker-container">';
    $html .= '<div class="media-picker-field mb-3">';
    $html .= '<label class="form-label">' . htmlspecialchars($options['label']) . '</label>';
    
    // Preview area
    $html .= '<div class="media-preview-container">' . $preview_html . '</div>';
    
    // Hidden input for value
    $html .= '<input type="hidden" name="' . $field_name . '" value="' . htmlspecialchars($current_value) . '" class="media-picker-input">';
    
    // Button to open picker
    $html .= '<button type="button" class="btn btn-outline-primary media-picker-button" data-bs-toggle="modal" data-bs-target="#' . $modal_id . '">';
    $html .= '<i class="bi bi-images"></i> Browse Files</button>';
    
    // Button to clear selection
    if ($current_value) {
        $html .= ' <button type="button" class="btn btn-outline-danger media-picker-clear"><i class="bi bi-x"></i> Clear</button>';
    }
    
    $html .= '</div>'; // End media-picker-field
    
    // Modal for file selection
    $html .= '<div class="modal fade" id="' . $modal_id . '" tabindex="-1" aria-hidden="true">';
    $html .= '<div class="modal-dialog modal-lg modal-dialog-scrollable">';
    $html .= '<div class="modal-content">';
    $html .= '<div class="modal-header">';
    $html .= '<h5 class="modal-title">Select File</h5>';
    $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
    $html .= '</div>';
    $html .= '<div class="modal-body">';
    
    // File browser iframe
    $iframe_src = '/admin/media/media-library.php?picker=1';
    if ($options['filter']) {
        $iframe_src .= '&filter=' . urlencode($options['filter']);
    }
    if ($options['client_id']) {
        $iframe_src .= '&client_id=' . urlencode($options['client_id']);
    }
    if ($options['multiple']) {
        $iframe_src .= '&multiple=1';
    }
    
    $html .= '<iframe src="' . $iframe_src . '" style="width: 100%; height: 400px; border: none;"></iframe>';
    
    $html .= '</div>'; // End modal-body
    $html .= '<div class="modal-footer">';
    $html .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
    $html .= '<button type="button" class="btn btn-primary media-picker-select" data-bs-dismiss="modal">Select</button>';
    $html .= '</div>'; // End modal-footer
    $html .= '</div>'; // End modal-content
    $html .= '</div>'; // End modal-dialog
    $html .= '</div>'; // End modal
    
    $html .= '</div>'; // End media-picker-container
    
    return $html;
}

/**
 * Helper function to format filesize
 * 
 * @param int $bytes Filesize in bytes
 * @return string Formatted filesize
 */
function format_filesize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}