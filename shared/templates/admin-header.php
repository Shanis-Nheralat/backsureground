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
    <!-- Phase 11: Add meta tags and manifest reference -->
    <meta name="description" content="Backsure Global Support Platform - Administrative Interface">
    <meta name="theme-color" content="#343a40">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/assets/img/logo-192.png">
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
        
        /* Phase 11: Global Search styles */
        .search-container {
            position: relative;
        }
        
        .search-container .form-control {
            border-radius: 20px;
        }
        
        #search-results {
            max-height: 400px;
            overflow-y: auto;
            z-index: 1050;
        }
        
        #search-results .dropdown-item {
            white-space: normal;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f1f1;
        }
        
        #search-results .dropdown-item:last-child {
            border-bottom: none;
        }
        
        /* Phase 11: Mobile optimization */
        @media (max-width: 767.98px) {
            /* Make the navbar more mobile-friendly */
            .navbar-brand {
                width: auto;
            }
            
            /* Search bar styling for mobile */
            .search-container-mobile {
                width: 100%;
                padding: 10px 15px;
                background-color: #343a40;
                position: sticky;
                top: 0;
                z-index: 99;
            }
            
            /* Mobile search results */
            .search-results-mobile {
                position: absolute;
                width: 100%;
                left: 0;
                top: 100%;
                z-index: 1000;
            }
            
            /* Optimize spacing for mobile */
            .container-fluid, .row, main, .px-md-4 {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
            
            /* Table card view for mobile */
            .table-responsive-card thead {
                display: none;
            }
            
            .table-responsive-card tr {
                display: block;
                border: 1px solid #dee2e6;
                border-radius: 0.25rem;
                margin-bottom: 1rem;
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
                background-color: #fff;
            }
            
            .table-responsive-card td {
                display: flex;
                justify-content: space-between;
                border: none;
                padding: 0.75rem;
                border-bottom: 1px solid #dee2e6;
            }
            
            .table-responsive-card td:last-child {
                border-bottom: none;
            }
            
            .table-responsive-card td:before {
                content: attr(data-label);
                font-weight: 600;
                margin-right: 1rem;
            }
            
            /* Floating action button */
            .btn-float {
                position: fixed;
                bottom: 1.5rem;
                right: 1.5rem;
                width: 3.5rem;
                height: 3.5rem;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.15);
                z-index: 1000;
            }
            
            /* Touch-friendly form controls */
            .form-control, .custom-select, .btn {
                min-height: 2.75rem;
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
            }
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
        
        <!-- Phase 11: Add Global Search bar -->
        <div class="search-container d-none d-md-block mx-3 flex-grow-1" style="max-width: 500px;">
            <form action="/admin/search.php" method="get" class="search-form position-relative">
                <div class="input-group">
                    <input 
                        type="text" 
                        id="global-search" 
                        name="q" 
                        class="form-control bg-dark text-light border-secondary" 
                        placeholder="Search or enter code (TKT-1234)" 
                        autocomplete="off"
                    >
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
            <div id="search-results" class="dropdown-menu position-absolute w-100" style="display: none;"></div>
        </div>
        
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
    
    <!-- Phase 11: Add mobile search component -->
    <div class="search-container-mobile d-md-none">
        <form action="/admin/search.php" method="get" class="search-form">
            <div class="input-group">
                <input 
                    type="text" 
                    id="mobile-global-search" 
                    name="q" 
                    class="form-control" 
                    placeholder="Search or enter code" 
                    autocomplete="off"
                >
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
        <div id="mobile-search-results" class="dropdown-menu search-results-mobile w-100" style="display: none;"></div>
    </div>
    
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
                
<?php
// Note: The closing tags for main, div.container-fluid, div.row, body, and html
// should be in the footer file that's included at the end of each page
?>

<!-- Phase 11: Add JavaScript for search functionality and PWA support -->
<script>
// Global Search JavaScript (Phase 11)
$(document).ready(function() {
    const searchInput = $('#global-search');
    const mobileSearchInput = $('#mobile-global-search');
    const resultsDropdown = $('#search-results');
    const mobileResultsDropdown = $('#mobile-search-results');
    
    // Function to debounce requests
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
    
    // Handler for both desktop and mobile search
    function handleSearch(input, resultsContainer) {
        input.on('input', function() {
            const query = $(this).val().trim();
            
            if (query.length < 2) {
                resultsContainer.hide();
                return;
            }
            
            // Check for entity code
            const codePattern = /^(TKT|EMP|CLT|TSK|PLN)-\d{1,4}$/i;
            if (codePattern.test(query)) {
                $.ajax({
                    url: '/shared/search/code-search.php',
                    data: { code: query.toUpperCase() },
                    success: function(response) {
                        if (response.found) {
                            window.location.href = response.deep_link_url;
                        }
                    }
                });
                return;
            }
            
            // Regular search - debounced
            debouncedSearch(query, resultsContainer);
        });
    }
    
    // Set up search handlers
    handleSearch(searchInput, resultsDropdown);
    handleSearch(mobileSearchInput, mobileResultsDropdown);
    
    // Hide dropdowns when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.search-container, .search-container-mobile').length) {
            resultsDropdown.hide();
            mobileResultsDropdown.hide();
        }
    });
    
    // Debounced search function
    const debouncedSearch = debounce(function(query, resultsContainer) {
        $.ajax({
            url: '/shared/search/quick-search.php',
            data: { q: query },
            success: function(results) {
                resultsContainer.empty();
                
                if (results.length === 0) {
                    resultsContainer.append('<div class="p-2 text-muted">No results found</div>');
                } else {
                    results.forEach(function(result) {
                        const metadata = JSON.parse(result.metadata);
                        const code = metadata.code || '';
                        const title = getResultTitle(result);
                        
                        resultsContainer.append(`
                            <a href="${result.deep_link_url}" class="dropdown-item">
                                <span class="badge bg-secondary">${code}</span>
                                <strong>${title}</strong>
                                <div class="small text-muted">${getResultPreview(result)}</div>
                            </a>
                        `);
                    });
                    
                    resultsContainer.append(`
                        <div class="dropdown-divider"></div>
                        <a href="/admin/search.php?q=${encodeURIComponent(query)}" class="dropdown-item text-primary">
                            <i class="bi bi-search me-2"></i>See all results
                        </a>
                    `);
                }
                
                resultsContainer.show();
            }
        });
    }, 300);
    
    // Helper functions for result formatting
    function getResultTitle(result) {
        const metadata = JSON.parse(result.metadata);
        switch(result.item_type) {
            case 'client': return metadata.name || 'Client';
            case 'employee': return metadata.name || 'Employee';
            case 'ticket': return metadata.subject || 'Support Ticket';
            case 'task': return metadata.title || 'Task';
            case 'plan': return metadata.name || 'Plan';
            default: return 'Item';
        }
    }
    
    function getResultPreview(result) {
        // Create a preview based on the item type
        const metadata = JSON.parse(result.metadata);
        switch(result.item_type) {
            case 'client': 
                return `${metadata.company || ''} • ${metadata.status || ''}`;
            case 'ticket': 
                return `${metadata.status || ''} • ${metadata.priority || ''}`;
            case 'task': 
                return `${metadata.status || ''} • Due: ${metadata.deadline || 'N/A'}`;
            default:
                return result.item_type.charAt(0).toUpperCase() + result.item_type.slice(1);
        }
    }
    
    // Set up mobile table data labels
    function setupMobileTableLabels() {
        if (window.innerWidth <= 767) {
            $('.table-responsive-card').each(function() {
                const headerTexts = [];
                $(this).find('thead th').each(function() {
                    headerTexts.push($(this).text().trim());
                });
                
                $(this).find('tbody tr').each(function() {
                    $(this).find('td').each(function(i) {
                        if (headerTexts[i]) {
                            $(this).attr('data-label', headerTexts[i]);
                        }
                    });
                });
            });
        }
    }
    
    // Initialize mobile table labels
    setupMobileTableLabels();
    $(window).on('resize', setupMobileTableLabels);
});

// PWA Service Worker Registration (Phase 11)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then(registration => {
                console.log('ServiceWorker registration successful');
            }).catch(error => {
                console.log('ServiceWorker registration failed: ', error);
            });
    });
}
</script>
