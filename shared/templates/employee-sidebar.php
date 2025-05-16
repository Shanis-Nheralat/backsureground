<?php
/**
 * Employee Sidebar Template
 * 
 * Complete sidebar navigation for employee users including all relevant phases
 */

// Initialize the menu items array
$employee_menu_items = [];

// PHASE 1: System Foundation
// Dashboard
$employee_menu_items[] = [
    'id' => 'dashboard',
    'title' => 'Dashboard',
    'icon' => 'fas fa-tachometer-alt',
    'url' => '/groundd/employee/dashboard.php'
];

// PHASE 4: Dedicated Employee Model - Client Management
$employee_menu_items[] = [
    'id' => 'clients',
    'title' => 'My Clients',
    'icon' => 'fas fa-users',
    'url' => '/groundd/employee/assigned-clients.php'
];

// PHASE 4: Dedicated Employee Model - Client Data Access
$employee_menu_items[] = [
    'id' => 'client_data',
    'title' => 'Client Data',
    'icon' => 'fas fa-database',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'client_crm',
            'title' => 'CRM Data',
            'url' => '/groundd/employee/client-data/crm.php'
        ],
        [
            'id' => 'client_azure',
            'title' => 'Azure Storage',
            'url' => '/groundd/employee/client-data/azure.php'
        ],
        [
            'id' => 'client_files',
            'title' => 'Client Files',
            'url' => '/groundd/employee/client-data/files.php'
        ]
    ]
];

// PHASE 5: On-Demand Service Support - Assigned Tasks
// Employees can view and work on tasks assigned to their clients
$employee_menu_items[] = [
    'id' => 'assigned_tasks',
    'title' => 'Assigned Tasks',
    'icon' => 'fas fa-clipboard-list',
    'url' => '/groundd/employee/assigned-tasks/tasks.php'
];

// PHASE 6: Business Care Plans - Insights Management
// Employees can create and manage insights for their clients
$employee_menu_items[] = [
    'id' => 'client_insights',
    'title' => 'Client Insights',
    'icon' => 'fas fa-chart-line',
    'url' => '/groundd/employee/client-insights/manage.php'
];

// PHASE 6: Business Care Plans - Document Management
// Employees can review client documents
$employee_menu_items[] = [
    'id' => 'client_documents',
    'title' => 'Client Documents',
    'icon' => 'fas fa-file-alt',
    'url' => '/groundd/employee/client-documents/review.php'
];

// Time Tracking (all phases)
$employee_menu_items[] = [
    'id' => 'time_tracking',
    'title' => 'Time Tracking',
    'icon' => 'fas fa-clock',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'time_log',
            'title' => 'My Time Log',
            'url' => '/groundd/employee/time-tracking/time-log.php'
        ],
        [
            'id' => 'time_report',
            'title' => 'Reports',
            'url' => '/groundd/employee/time-tracking/reports.php'
        ]
    ]
];

// PHASE 8: Add Time Tracking direct links (NEW)
$employee_menu_items[] = [
    'id' => 'time_tracker',
    'title' => 'Time Tracker',
    'icon' => 'fas fa-stopwatch',
    'url' => '/groundd/employee/time-tracking/timer.php'
];
$employee_menu_items[] = [
    'id' => 'time_history',
    'title' => 'Time History',
    'icon' => 'fas fa-history',
    'url' => '/groundd/employee/time-tracking/history.php'
];

// Support Tickets (all phases)
$employee_menu_items[] = [
    'id' => 'support',
    'title' => 'Support',
    'icon' => 'fas fa-headset',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'my_tickets',
            'title' => 'My Tickets',
            'url' => '/groundd/employee/support/my-tickets.php'
        ],
        [
            'id' => 'client_tickets',
            'title' => 'Client Tickets',
            'url' => '/groundd/employee/support/client-tickets.php'
        ],
        [
            'id' => 'new_ticket',
            'title' => 'Create Ticket',
            'url' => '/groundd/employee/support/new-ticket.php'
        ]
    ]
];

// User Profile & Settings (all phases)

// PHASE 9: Support Desk
$employee_menu_items[] = [
    'id' => 'support',
    'title' => 'Support',
    'icon' => 'fas fa-headset',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'assigned_tickets',
            'title' => 'Assigned Tickets',
            'url' => '/groundd/employee/support/assigned-tickets.php'
        ],
        [
            'id' => 'my_tickets',
            'title' => 'My Tickets',
            'url' => '/groundd/employee/support/my-tickets.php'
        ],
        [
            'id' => 'client_tickets',
            'title' => 'Client Tickets',
            'url' => '/groundd/employee/support/client-tickets.php'
        ],
        [
            'id' => 'new_ticket',
            'title' => 'Create Ticket',
            'url' => '/groundd/employee/support/new-ticket.php'
        ],
        [
            'id' => 'view_ticket',
            'title' => 'View Ticket',
            'url' => '/groundd/employee/support/view-ticket.php',
            'hidden' => true // Hidden from menu but used for active state
        ]
    ]
];

// Add hook for additional menu items
if (function_exists('add_employee_menu_items')) {
    add_employee_menu_items($employee_menu_items);
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
            <?php foreach ($employee_menu_items as $menu_item): ?>
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
        <a href="/groundd/logout.php" class="btn btn-logout">
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
