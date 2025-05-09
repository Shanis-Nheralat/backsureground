<?php
/**
 * File Upload Handler
 * 
 * Functions for handling file uploads, validation, and media library management
 */

// Include database connection if not already included
if (!function_exists('db_query')) {
    require_once __DIR__ . '/../../db.php';
}

// Constants
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/uploads/');
define('UPLOAD_URL', '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/svg+xml' => 'svg'
]);
define('ALLOWED_FILE_TYPES', [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/zip' => 'zip',
    'application/x-zip-compressed' => 'zip',
    'text/plain' => 'txt',
    'text/csv' => 'csv'
]);

/**
 * Handle file upload
 * 
 * @param array $file $_FILES array element
 * @param string $type Optional type restriction ('image' or 'document')
 * @return array Result with success/error info and file data
 */
function handle_file_upload($file, $type = null) {
    // Check if file was uploaded properly
    if (!isset($file) || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'error' => get_upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE)
        ];
    }
    
    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return [
            'success' => false,
            'error' => 'File size exceeds the maximum allowed size of ' . format_file_size(MAX_FILE_SIZE)
        ];
    }
    
    // Determine allowed mime types based on type parameter
    $allowed_mime_types = [];
    if ($type === 'image') {
        $allowed_mime_types = ALLOWED_IMAGE_TYPES;
    } elseif ($type === 'document') {
        $allowed_mime_types = ALLOWED_FILE_TYPES;
    } else {
        $allowed_mime_types = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_FILE_TYPES);
    }
    
    // Get mime type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    // Validate mime type
    if (!array_key_exists($mime_type, $allowed_mime_types)) {
        return [
            'success' => false,
            'error' => 'Invalid file type. Allowed types: ' . implode(', ', array_values($allowed_mime_types))
        ];
    }
    
    // Create upload directory if it doesn't exist
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    // Generate unique filename
    $extension = $allowed_mime_types[$mime_type];
    $filename = generate_unique_filename($extension);
    $filepath = UPLOAD_DIR . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => false,
            'error' => 'Failed to move uploaded file'
        ];
    }
    
    // Determine if it's an image
    $is_image = array_key_exists($mime_type, ALLOWED_IMAGE_TYPES);
    
    // Get file dimensions if it's an image
    $width = null;
    $height = null;
    if ($is_image && $mime_type !== 'image/svg+xml') {
        $image_info = getimagesize($filepath);
        if ($image_info) {
            $width = $image_info[0];
            $height = $image_info[1];
        }
    }
    
    // Insert into media table
    $media_id = db_insert('media', [
        'file_name' => $filename,
        'file_path' => UPLOAD_URL . $filename,
        'file_type' => $extension,
        'file_size' => $file['size'],
        'mime_type' => $mime_type,
        'uploaded_by' => $_SESSION['user_id'] ?? null
    ]);
    
    return [
        'success' => true,
        'media_id' => $media_id,
        'file_name' => $filename,
        'file_path' => UPLOAD_URL . $filename,
        'file_url' => UPLOAD_URL . $filename,
        'file_type' => $extension,
        'file_size' => $file['size'],
        'mime_type' => $mime_type,
        'is_image' => $is_image,
        'width' => $width,
        'height' => $height
    ];
}

/**
 * Generate a unique filename for upload
 * 
 * @param string $extension File extension
 * @return string Unique filename
 */
function generate_unique_filename($extension) {
    $base = date('Ymd') . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 10);
    return $base . '.' . $extension;
}

/**
 * Get a user-friendly error message for upload errors
 * 
 * @param int $error_code PHP upload error code
 * @return string User-friendly error message
 */
function get_upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}

/**
 * Format file size for display
 * 
 * @param int $bytes File size in bytes
 * @param int $precision Number of decimal places
 * @return string Formatted file size
 */
function format_file_size($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Get media library items
 * 
 * @param array $filters Filtering options
 * @param int $page Page number
 * @param int $per_page Items per page
 * @return array Media items and pagination info
 */
function get_media_items($filters = [], $page = 1, $per_page = 20) {
    // Base query
    $query = "SELECT * FROM media";
    $params = [];
    
    // Apply filters
    $where_clauses = [];
    
    if (isset($filters['type'])) {
        if ($filters['type'] === 'image') {
            $where_clauses[] = "mime_type LIKE 'image/%'";
        } elseif ($filters['type'] === 'document') {
            $where_clauses[] = "mime_type NOT LIKE 'image/%'";
        }
    }
    
    if (isset($filters['search']) && !empty($filters['search'])) {
        $where_clauses[] = "file_name LIKE ?";
        $params[] = '%' . $filters['search'] . '%';
    }
    
    // Add WHERE clause if we have any conditions
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    // Add ORDER BY
    $query .= " ORDER BY created_at DESC";
    
    // Count total items (for pagination)
    $count_query = str_replace("SELECT *", "SELECT COUNT(*)", $query);
    $total_items = db_query_value($count_query, $params);
    
    // Apply pagination
    $offset = ($page - 1) * $per_page;
    $query .= " LIMIT $per_page OFFSET $offset";
    
    // Get items
    $items = db_query($query, $params);
    
    // Calculate pagination info
    $total_pages = ceil($total_items / $per_page);
    
    // Enhance items with additional info
    foreach ($items as &$item) {
        $item['is_image'] = strpos($item['mime_type'], 'image/') === 0;
        $item['file_url'] = $item['file_path'] . '?v=' . strtotime($item['updated_at']);
        $item['formatted_size'] = format_file_size($item['file_size']);
        $item['formatted_date'] = date('M d, Y H:i', strtotime($item['created_at']));
    }
    
    return [
        'items' => $items,
        'pagination' => [
            'total_items' => $total_items,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page
        ]
    ];
}

/**
 * Delete a media item
 * 
 * @param int $media_id Media ID
 * @return bool Success or failure
 */
function delete_media_item($media_id) {
    // Get media item
    $media = db_query_row("SELECT * FROM media WHERE id = ?", [$media_id]);
    
    if (!$media) {
        return false;
    }
    
    // Check if file exists
    $filepath = $_SERVER['DOCUMENT_ROOT'] . $media['file_path'];
    
    // Delete file if it exists
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    // Delete from database
    return (bool) db_delete('media', 'id = ?', [$media_id]);
}

/**
 * Get media item by ID
 * 
 * @param int $media_id Media ID
 * @return array|false Media item or false if not found
 */
function get_media_item($media_id) {
    return db_query_row("SELECT * FROM media WHERE id = ?", [$media_id]);
}

/**
 * Get media item by path
 * 
 * @param string $path File path
 * @return array|false Media item or false if not found
 */
function get_media_item_by_path($path) {
    return db_query_row("SELECT * FROM media WHERE file_path = ?", [$path]);
}