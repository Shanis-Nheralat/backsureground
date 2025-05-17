<?php
/**
 * Admin Header Template
 * 
 * Common header used across admin pages.
 */

// Ensure the user is authenticated
if (!function_exists('is_logged_in') || !is_logged_in()) {
    header('Location: /login.php');
    exit;
}

// Page-specific variables (set these before including the header)
$page_title = $page_title ?? 'Dashboard';
$active_page = $active_page ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> | Backsure Global Support</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* Sidebar styling */
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #212529;
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: rgba(255, 255, 255, .75);
            padding: .75rem 1rem;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
        }
        
        .sidebar .nav-link i {
            margin-right: .5rem;
        }
        
        /* Main content area */
        .main-content {
            margin-left: 240px;
            padding: 20px;
        }
        
        /* Navbar styling */
        .navbar {
            background-color: #343a40 !important;
            padding: 0.5rem 1rem;
        }
        
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
            width: 240px;
            margin-right: 0;
            text-align: center;
        }
        
        .navbar .navbar-toggler {
            top: .25rem;
            right: 1rem;
        }
        
        .navbar-brand img {
            max-height: 30px;
        }
        
        .user-dropdown {
            margin-left: auto;
        }
        
        /* Dashboard cards */
        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        
        .dashboard-card .card-body {
            padding: 1.5rem;
        }
        
        /* Form styling */
        .form-card {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        
        /* Notification Styles */
        .notification-dropdown {
            width: 350px;
            max-height: 500px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item.unread {
            background-color: #f8f9fa;
        }

        .notification-title {
            font-weight: bold;
            margin-bottom: 3px;
        }

        .notification-message {
            font-size: 0.9rem;
            margin-bottom: 3px;
        }

        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .notification-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        /* Badge position on bell icon */
        .nav-link .badge {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.6rem;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="/admin/dashboard.php">
            <img src="/assets/img/logo-white.png" alt="Backsure Global Support">
        </a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navbar Right Items -->
        <div class="navbar-nav">
            <div class="nav-item text-nowrap d-flex align-items-center">
                <!-- Notifications Dropdown -->
                <?php include_once __DIR__ . '/notifications-dropdown.php'; ?>
                
                <!-- User Dropdown -->
                <div class="dropdown user-dropdown ms-3">
                    <a class="nav-link px-3 dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="/logout.php" method="post" id="logout-form">
                                <?php echo csrf_field(); ?>
                                <a class="dropdown-item" href="#" onclick="document.getElementById('logout-form').submit(); return false;">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse"><?php 
                include_once __DIR__ . '/admin-sidebar.php'; 
            ?></nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
                </div>
                
                <!-- Page content goes here -->
