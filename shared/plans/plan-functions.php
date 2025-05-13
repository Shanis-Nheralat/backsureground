<?php
/**
 * Business Care Plans - Core Functions
 * 
 * Handles plan management, subscriptions, and document processing
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth/admin-auth.php';
require_once __DIR__ . '/../utils/notifications.php';

/**
 * Get all service plans
 */
function get_service_plans($active_only = true) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM service_plans";
        if ($active_only) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY name";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Service Plans Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get plan by ID
 */
function get_service_plan($plan_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM service_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Service Plan Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get all plan tiers
 */
function get_plan_tiers($active_only = true) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM plan_tiers";
        if ($active_only) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY display_order";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Plan Tiers Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get tier by ID
 */
function get_plan_tier($tier_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM plan_tiers WHERE id = ?");
        $stmt->execute([$tier_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Plan Tier Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get services by plan
 */
function get_services_by_plan($plan_id, $active_only = true) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM services WHERE plan_id = ?";
        if ($active_only) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$plan_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Services By Plan Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get service by ID
 */
function get_service($service_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
        $stmt->execute([$service_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Service Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get client plan subscriptions
 */
function get_client_plan_subscriptions($client_id) {
    global $pdo;
    
    try {
        $sql = "SELECT cps.*, sp.name as plan_name, pt.name as tier_name
                FROM client_plan_subscriptions cps
                JOIN service_plans sp ON cps.plan_id = sp.id
                JOIN plan_tiers pt ON cps.tier_id = pt.id
                WHERE cps.client_id = ?
                ORDER BY cps.start_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$client_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Client Plan Subscriptions Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get active client plan subscription
 */
function get_active_client_plan($client_id) {
    global $pdo;
    
    try {
        $sql = "SELECT cps.*, sp.name as plan_name, pt.name as tier_name
                FROM client_plan_subscriptions cps
                JOIN service_plans sp ON cps.plan_id = sp.id
                JOIN plan_tiers pt ON cps.tier_id = pt.id
                WHERE cps.client_id = ? AND cps.status = 'active'
                ORDER BY cps.start_date DESC
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$client_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Active Client Plan Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get client service subscriptions
 */
function get_client_service_subscriptions($client_plan_id) {
    global $pdo;
    
    try {
        $sql = "SELECT css.*, s.name as service_name, s.icon
                FROM client_service_subscriptions css
                JOIN services s ON css.service_id = s.id
                WHERE css.client_plan_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$client_plan_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Client Service Subscriptions Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Create client plan subscription
 */
function create_client_plan_subscription($client_id, $plan_id, $tier_id, $start_date, $end_date, $created_by) {
    global $pdo;
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Check for existing active subscriptions
        $stmt = $pdo->prepare("
            UPDATE client_plan_subscriptions 
            SET status = 'expired', updated_at = NOW() 
            WHERE client_id = ? AND status = 'active'
        ");
        $stmt->execute([$client_id]);
        
        // Create new subscription
        $stmt = $pdo->prepare("
            INSERT INTO client_plan_subscriptions 
            (client_id, plan_id, tier_id, start_date, end_date, status, created_by)
            VALUES (?, ?, ?, ?, ?, 'active', ?)
        ");
        
        $stmt->execute([$client_id, $plan_id, $tier_id, $start_date, $end_date, $created_by]);
        $client_plan_id = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        
        // Notify admin
        set_admin_notification('info', 'New client plan subscription created for client ID: ' . $client_id, 'admin-plans.php');
        
        return $client_plan_id;
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        error_log('Create Client Plan Subscription Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Add service to client subscription
 */
function add_client_service_subscription($client_plan_id, $service_id, $additional_details = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO client_service_subscriptions 
            (client_plan_id, service_id, additional_details)
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([$client_plan_id, $service_id, $additional_details]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Add Client Service Subscription Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get document categories by service
 */
function get_document_categories_by_service($service_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM plan_document_categories
            WHERE service_id = ?
            ORDER BY display_order
        ");
        
        $stmt->execute([$service_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Document Categories Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get client documents by category
 */
function get_client_documents($client_id, $category_id = null) {
    global $pdo;
    
    try {
        $sql = "SELECT cpd.*, pdc.name as category_name
                FROM client_plan_documents cpd
                JOIN plan_document_categories pdc ON cpd.category_id = pdc.id
                WHERE cpd.client_id = ?";
        
        $params = [$client_id];
        
        if ($category_id) {
            $sql .= " AND cpd.category_id = ?";
            $params[] = $category_id;
        }
        
        $sql .= " ORDER BY cpd.uploaded_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Client Documents Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Upload client document
 */
function upload_client_document($client_id, $category_id, $file) {
    // Create document directory if it doesn't exist
    $upload_dir = __DIR__ . '/../../uploads/plans/documents/' . $client_id . '/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate a unique filename
    $filename = time() . '_' . basename($file['name']);
    $filepath = $upload_dir . $filename;
    
    // Check file type
    $allowed_types = ['pdf', 'docx', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Check file size (10MB limit)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File size exceeds limit (10MB)'];
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Save file info to database
        $db_path = 'uploads/plans/documents/' . $client_id . '/' . $filename;
        $document_id = save_document_to_db($client_id, $category_id, $file['name'], $db_path, $file['size'], $file_ext);
        
        if ($document_id) {
            return [
                'success' => true, 
                'document_id' => $document_id,
                'file_path' => $db_path
            ];
        } else {
            // Delete file if database insert failed
            unlink($filepath);
            return ['success' => false, 'message' => 'Failed to save document to database'];
        }
    } else {
        return ['success' => false, 'message' => 'Failed to upload file'];
    }
}

/**
 * Save document to database
 */
function save_document_to_db($client_id, $category_id, $original_file_name, $file_path, $file_size, $file_type) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO client_plan_documents 
            (client_id, category_id, file_name, file_path, original_file_name, file_size, file_type)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $file_name = basename($file_path);
        
        $stmt->execute([
            $client_id,
            $category_id,
            $file_name,
            $file_path,
            $original_file_name,
            $file_size,
            $file_type
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Save Document DB Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Review client document
 */
function review_client_document($document_id, $status, $review_notes, $reviewed_by) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE client_plan_documents 
            SET status = ?, review_notes = ?, reviewed_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $review_notes, $reviewed_by, $document_id]);
        return true;
    } catch (PDOException $e) {
        error_log('Review Document Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get document by ID
 */
function get_document($document_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT cpd.*, pdc.name as category_name
            FROM client_plan_documents cpd
            JOIN plan_document_categories pdc ON cpd.category_id = pdc.id
            WHERE cpd.id = ?
        ");
        
        $stmt->execute([$document_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Document Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Check if user owns document
 */
function user_owns_document($document_id, $client_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM client_plan_documents 
            WHERE id = ? AND client_id = ?
        ");
        
        $stmt->execute([$document_id, $client_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('User Owns Document Check Error: ' . $e->getMessage());
        return false;
    }
}