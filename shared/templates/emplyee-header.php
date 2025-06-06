<?php
/**
 * Employee Header Template
 * 
 * Displays the header for employee pages
 */

// Check if user is logged in
if (!function_exists('is_admin_logged_in') || !is_admin_logged_in() || $_SESSION['admin_role'] !== 'employee') {
    // Redirect to login page if not logged in as employee
    header('Location: /login.php');
    exit;
}

// Get employee info
$employee_id = $_SESSION['admin_user_id'];
$employee_name = $_SESSION['admin_name'] ?? 'Employee';

// Get notifications if function exists
$notifications = function_exists('get_employee_notifications') ? get_employee_notifications($employee_id) : [];
$unread_count = 0;
if (!empty($notifications)) {
    foreach ($notifications as $notification) {
        if (!$notification['is_read']) {
            $unread_count++;
        }
    }
}

// Get current client context if available
$current_client = isset($_SESSION['current_client']) ? $_SESSION['current_client'] : null;

// Get assigned clients if function exists
$assigned_clients = function_exists('get_assigned_clients') ? get_assigned_clients($employee_id) : [];

// Page title
$page_title = isset($page_title) ? $page_title . ' | Backsure Global Support' : 'Employee Dashboard | Backsure Global Support';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="/assets/img/favicon.ico" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="/assets/css/employee.css" rel="stylesheet">
    
    <!-- Additional CSS if needed -->
    <?php if (isset($extra_css)): ?>
        <?php echo $extra_css; ?>
    <?php endif; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="employee-layout">
    <div class="wrapper">
        <!-- Include Sidebar -->
        <?php include __DIR__ . '/employee-sidebar.php'; ?>
        
        <div class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle-btn">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <!-- Breadcrumbs -->
                    <?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                    <?php if ($index === count($breadcrumbs) - 1): ?>
                                        <li class="breadcrumb-item active" aria-current="page"><?php echo $crumb['title']; ?></li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item"><a href="<?php echo $crumb['url']; ?>"><?php echo $crumb['title']; ?></a></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    <?php endif; ?>
                </div>
                
                <div class="header-right">
                    <!-- Client Selector (if applicable) -->
                    <?php if (!empty($assigned_clients)): ?>
                        <div class="client-selector dropdown me-3">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-tie me-1"></i>
                                <?php echo $current_client ? 'Client: ' . $current_client['name'] : 'Select Client'; ?>
                            </button>
                            <ul class="dropdown-menu">
                                <?php foreach ($assigned_clients as $client): ?>
                                    <li>
                                        <a class="dropdown-item <?php echo ($current_client && $current_client['id'] == $client['id']) ? 'active' : ''; ?>" 
                                           href="/employee/set-client.php?client_id=<?php echo $client['id']; ?>">
                                            <?php echo $client['name']; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Notifications -->
                    <div class="dropdown notifications-dropdown">
                        <button class="btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="dropdown-header">
                                <span>Notifications</span>
                                <?php if (!empty($notifications)): ?>
                                    <a href="/employee/notifications.php" class="text-decoration-none">View All</a>
                                <?php endif; ?>
                            </div>
                            <div class="notifications-list">
                                <?php if (empty($notifications)): ?>
                                    <div class="dropdown-item">
                                        <p class="text-muted mb-0">No notifications</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
                                        <a href="<?php echo $notification['url']; ?>" class="dropdown-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                            <div class="notification-icon">
                                                <i class="<?php echo $notification['icon'] ?? 'fas fa-bell'; ?>"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?php echo $notification['message']; ?></p>
                                                <small class="text-muted"><?php echo format_time_ago($notification['created_at']); ?></small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="dropdown user-dropdown">
                        <button class="btn d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                <span><?php echo get_initials($employee_name); ?></span>
                            </div>
                            <div class="user-info d-none d-md-block">
                                <span class="user-name"><?php echo $employee_name; ?></span>
                                <span class="user-role">Employee</span>
                            </div>
                            <i class="fas fa-chevron-down ms-1"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/employee/profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="/employee/settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>
            
            <!-- Notifications Display -->
            <?php if (function_exists('display_notifications')): ?>
                <div class="notifications-container">
                    <?php display_notifications(); ?>
                </div>
            <?php endif; ?>
            
            <!-- Page Content Container -->
            <div class="content-container">

<?php
/**
 * Helper function to format time ago
 */
function format_time_ago($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    
    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    }
    if ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    }
    if ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    }
    if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    if ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    
    return 'Just now';
}

/**
 * Helper function to get initials from name
 */
function get_initials($name) {
    $words = explode(' ', $name);
    $initials = '';
    
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
        
        if (strlen($initials) >= 2) {
            break;
        }
    }
    
    return $initials;
}
?>
