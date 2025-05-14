<?php
/**
 * Client Sidebar Template
 * 
 * Complete sidebar navigation for client users
 */

// Initialize the menu items array
$client_menu_items = [];

// Dashboard
$client_menu_items[] = [
    'id' => 'dashboard',
    'title' => 'Dashboard',
    'icon' => 'fas fa-tachometer-alt',
    'url' => '/groundd/client/dashboard.php'
];

// PHASE 5: On-Demand Tasks
$client_menu_items[] = [
    'id' => 'tasks',
    'title' => 'Tasks',
    'icon' => 'fas fa-tasks',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'submit_task',
            'title' => 'Submit New Task',
            'url' => '/groundd/client/tasks/submit-task.php'
        ],
        [
            'id' => 'task_history',
            'title' => 'Task History',
            'url' => '/groundd/client/tasks/history.php'
        ]
    ]
];

// PHASE 4: Dedicated Employee Model
$client_menu_items[] = [
    'id' => 'dedicated_support',
    'title' => 'Dedicated Support',
    'icon' => 'fas fa-user-tie',
    'url' => '/groundd/client/dedicated-support.php'
];

// PHASE 6: Business Care Plans
$client_menu_items[] = [
    'id' => 'plans',
    'title' => 'Business Plans',
    'icon' => 'fas fa-cubes',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'plan_dashboard',
            'title' => 'Plan Dashboard',
            'url' => '/groundd/client/plans/dashboard.php'
        ],
        [
            'id' => 'plan_documents',
            'title' => 'Documents',
            'url' => '/groundd/client/plans/documents.php'
        ],
        [
            'id' => 'plan_insights',
            'title' => 'Insights',
            'url' => '/groundd/client/plans/insights.php'
        ]
    ]
];

// Documents & Files
$client_menu_items[] = [
    'id' => 'documents',
    'title' => 'Documents',
    'icon' => 'fas fa-file-alt',
    'url' => '/groundd/client/documents.php'
];

// Support Tickets
$client_menu_items[] = [
    'id' => 'support',
    'title' => 'Support',
    'icon' => 'fas fa-headset',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'my_tickets',
            'title' => 'My Tickets',
            'url' => '/groundd/client/support/my-tickets.php'
        ],
        [
            'id' => 'new_ticket',
            'title' => 'Submit Ticket',
            'url' => '/groundd/client/support/new-ticket.php'
        ]
    ]
];

// Profile & Settings
$client_menu_items[] = [
    'id' => 'profile',
    'title' => 'My Profile',
    'icon' => 'fas fa-user',
    'url' => '/groundd/client/profile.php'
];

// Notifications (from Phase 7)
$client_menu_items[] = [
    'id' => 'notifications',
    'title' => 'Notifications',
    'icon' => 'fas fa-bell',
    'url' => '/groundd/client/notifications.php'
];

// Add hook for additional menu items
if (function_exists('add_client_menu_items')) {
    add_client_menu_items($client_menu_items);
}

// Get the current page to highlight the active menu item
$current_script = basename($_SERVER['SCRIPT_NAME']);
$current_page = isset($current_page) ? $current_page : '';
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="/groundd/assets/img/logo-white.png" alt="Backsure Global Support" class="logo">
        <div class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <?php foreach ($client_menu_items as $menu_item): ?>
                <?php 
                $has_submenu = isset($menu_item['submenu']) && !empty($menu_item['submenu']);
                $is_active = ($current_page === $menu_item['id'] || 
                             ($has_submenu && check_submenu_active($menu_item['submenu'], $current_page)));
                ?>
                <li class="nav-item <?php echo $has_submenu ? 'has-submenu' : ''; ?> <?php echo $is_active ? 'active' : ''; ?>">
                    <a class="nav-link" href="<?php echo $has_submenu ? '#' : $menu_item['url']; ?>" 
                       <?php echo $has_submenu ? 'data-bs-toggle="collapse" data-bs-target="#submenu-'.$menu_item['id'].'"' : ''; ?>>
                        <i class="<?php echo $menu_item['icon']; ?> fa-fw"></i>
                        <span><?php echo $menu_item['title']; ?></span>
                        <?php if ($has_submenu): ?>
                            <i class="fas fa-chevron-down submenu-arrow"></i>
