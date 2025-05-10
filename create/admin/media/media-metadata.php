<?php
/**
 * Media Metadata Handler
 */

// Include required files
require_once '../../shared/db.php';
require_once '../../shared/auth/admin-auth.php';

// Authentication check
require_admin_auth();

// Include media functions
require_once '../../shared/media/media-functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Handle GET request (get metadata)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Media ID is required'
        ]);
        exit;
    }
    
    $media_id = intval($_GET['id']);
    $media = get_media($media_id);
    
    if ($media) {
        echo json_encode([
            'success' => true,
            'media' => $media
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Media not found or insufficient permissions'
        ]);
    }
    
    exit;
}

// Handle POST request (update metadata)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token'
        ]);
        exit;
    }
    
    // Check for required parameters
    if (!isset($_POST['media_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Media ID is required'
        ]);
        exit;
    }
    
    $media_id = intval($_POST['media_id']);
    $updates = [];
    
    // Tag updates
    if (isset($_POST['tags'])) {
        $tags = $_POST['tags'];
        $action = $_POST['action'] ?? 'replace';
        
        if ($action === 'add') {
            // Get current tags
            $media = get_media($media_id);
            if ($media) {
                $current_tags = array_map('trim', explode(',', $media['tags'] ?? ''));
                $new_tags = array_map('trim', explode(',', $tags));
                
                // Merge tags and remove duplicates
                $merged_tags = array_unique(array_merge($current_tags, $new_tags));
                
                // Filter out empty tags
                $merged_tags = array_filter($merged_tags, function($tag) {
                    return !empty($tag);
                });
                
                $tags = implode(', ', $merged_tags);
            }
        }
        
        $updates['tags'] = $tags;
    }
    
    // Folder updates
    if (isset($_POST['folder'])) {
        $updates['folder'] = $_POST['folder'];
    }
    
    // Update metadata
    if (!empty($updates)) {
        $result = update_media($media_id, $updates);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Media metadata updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update media metadata or insufficient permissions'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No updates provided'
        ]);
    }
    
    exit;
}

// Invalid request method
echo json_encode([
    'success' => false,
    'message' => 'Invalid request method'
]);