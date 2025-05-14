<?php
/**
 * Client Sidebar Template
 * 
 * Displays the sidebar navigation for client users
 */

// Initialize the menu items array
$client_menu_items = [];

// Dashboard
$client_menu_items[] = [
    'id' => 'dashboard',
    'title' => 'Dashboard',
    'icon' => 'fas fa-tachometer-alt',
    'url' => '/client/dashboard.php'
];

// Phase 5: On-Demand Tasks
$client_menu_items[] = [
    'id' => 'tasks',
    'title' => 'Tasks',
    'icon' => 'fas fa-clipboard-list',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'submit_task',
            'title' => 'Submit New Task',
            'url' => '/client/tasks/submit-task.php'
        ],
        [
            'id' => 'task_history',
            'title' => 'My Tasks',
            'url' => '/client/tasks/history.php'
        ]
    ]
];

// Phase 6: Business Care Plans
$client_menu_items[] = [
    'id' => 'plans',
    'title' => 'Business Care',
    'icon' => 'fas fa-chart-line',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'plan_dashboard',
            'title' => 'Dashboard',
            'url' => '/client/plans/dashboard.php'
        ],
        [
            'id' => 'plan_documents',
            'title' => 'My Documents',
            'url' => '/client/plans/documents.php'
        ]
    ]
];

// Support
$client_menu_items[] = [
    'id' => 'support',
    'title' => 'Support',
    'icon' => 'fas fa-headset',
    'url' => '/client/support/my-tickets.php'
];

// Profile
$client_menu_items[] = [
    'id' => 'profile',
    'title' => 'My Profile',
    'icon' => 'fas fa-user',
    'url' => '/client/profile.php'
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
        <img src="/assets/img/logo-white.png" alt="Backsure Global Support" class="logo">
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
                        <?php endif; ?>
                    </a>
                    
                    <?php if ($has_submenu): ?>
                        <div class="collapse <?php echo $is_active ? 'show' : ''; ?>" id="submenu-<?php echo $menu_item['id']; ?>">
                            <ul class="nav flex-column submenu">
                                <?php foreach ($menu_item['submenu'] as $submenu_item): ?>
                                    <li class="nav-item <?php echo $current_page === $submenu_item['id'] ? 'active' : ''; ?>">
                                        <a class="nav-link" href="<?php echo $submenu_item['url']; ?>">
                                            <span><?php echo $submenu_item['title']; ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="sidebar-footer">
        <a href="/logout.php" class="btn btn-logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<?php
/**
 * Helper function to check if any submenu item is active
 */
function check_submenu_active($submenu, $current_page) {
    foreach ($submenu as $item) {
        if ($item['id'] === $current_page) {
            return true;
        }
    }
    return false;
}
?>