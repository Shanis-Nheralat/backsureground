<?php
/**
 * Admin Sidebar Template
 * 
 * Complete sidebar navigation for admin users including all phases (1-6)
 */

// Initialize the menu items array
$admin_menu_items = [];

// PHASE 1: System Foundation
// Dashboard
$admin_menu_items[] = [
    'id' => 'dashboard',
    'title' => 'Dashboard',
    'icon' => 'fas fa-tachometer-alt',
    'url' => '/groundd/admin/dashboard.php'
];

// User Management
$admin_menu_items[] = [
    'id' => 'users',
    'title' => 'User Management',
    'icon' => 'fas fa-users',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'all_users',
            'title' => 'All Users',
            'url' => '/groundd/admin/users/all-users.php'
        ],
        [
            'id' => 'add_user',
            'title' => 'Add New User',
            'url' => '/groundd/admin/users/add-user.php'
        ],
        [
            'id' => 'roles',
            'title' => 'Roles & Permissions',
            'url' => '/groundd/admin/users/roles.php'
        ]
    ]
];

// PHASE 2: Settings Management System
$admin_menu_items[] = [
    'id' => 'settings',
    'title' => 'Settings',
    'icon' => 'fas fa-cog',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'general_settings',
            'title' => 'General Settings',
            'url' => '/groundd/admin/settings/admin-settings.php'
        ],
        [
            'id' => 'seo_settings',
            'title' => 'SEO Settings',
            'url' => '/groundd/admin/settings/admin-seo.php'
        ],
        [
            'id' => 'email_settings',
            'title' => 'Email Settings',
            'url' => '/groundd/admin/settings/admin-email-settings.php'
        ],
        [
            'id' => 'email_templates',
            'title' => 'Email Templates',
            'url' => '/groundd/admin/settings/admin-email-templates.php'
        ],
        [
            'id' => 'notification_settings',
            'title' => 'Notification Settings',
            'url' => '/groundd/admin/settings/admin-notification-settings.php'
        ],
        [
            'id' => 'chat_settings',
            'title' => 'Chat Settings',
            'url' => '/groundd/admin/settings/admin-chat-settings.php'
        ]
    ]
];

// PHASE 3: Media Library
$admin_menu_items[] = [
    'id' => 'media',
    'title' => 'Media Library',
    'icon' => 'fas fa-images',
    'url' => '/groundd/admin/media/media-library.php'
];

// PHASE 4: Dedicated Employee Model
$admin_menu_items[] = [
    'id' => 'dedicated_employee',
    'title' => 'Employee Model',
    'icon' => 'fas fa-user-tie',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'assign_employee',
            'title' => 'Assign Employees',
            'url' => '/groundd/admin/assign-employee.php'
        ],
        [
            'id' => 'client_crm',
            'title' => 'CRM Settings',
            'url' => '/groundd/admin/client-crm.php'
        ],
        [
            'id' => 'azure_storage',
            'title' => 'Azure Storage',
            'url' => '/groundd/admin/azure-storage.php'
        ],
        [
            'id' => 'employee_reports',
            'title' => 'Employee Reports',
            'url' => '/groundd/admin/employee-reports.php'
        ]
    ]
];

// PHASE 5: On-Demand Service Support
$admin_menu_items[] = [
    'id' => 'tasks',
    'title' => 'On-Demand Tasks',
    'icon' => 'fas fa-tasks',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'manage_tasks',
            'title' => 'Manage Tasks',
            'url' => '/groundd/admin/tasks/manage-tasks.php'
        ],
        [
            'id' => 'task_reports',
            'title' => 'Task Reports',
            'url' => '/groundd/admin/tasks/reports.php'
        ]
    ]
];

// PHASE 6: Business Care Plans
$admin_menu_items[] = [
    'id' => 'plans',
    'title' => 'Business Care Plans',
    'icon' => 'fas fa-cubes',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'manage_plans',
            'title' => 'Manage Plans',
            'url' => '/groundd/admin/plans/manage.php'
        ],
        [
            'id' => 'plan_services',
            'title' => 'Services',
            'url' => '/groundd/admin/plans/services.php'
        ],
        [
            'id' => 'plan_documents',
            'title' => 'Documents',
            'url' => '/groundd/admin/plans/documents.php'
        ],
        [
            'id' => 'plan_insights',
            'title' => 'Insights',
            'url' => '/groundd/admin/plans/insights.php'
        ]
    ]
];

// PHASE 6: Integrations
$admin_menu_items[] = [
    'id' => 'integrations',
    'title' => 'Integrations',
    'icon' => 'fas fa-plug',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'api_config',
            'title' => 'API Settings',
            'url' => '/groundd/admin/integrations/api-config.php'
        ],
        [
            'id' => 'zoho_integration',
            'title' => 'Zoho Integration',
            'url' => '/groundd/admin/integrations/zoho.php'
        ],
        [
            'id' => 'tally_integration',
            'title' => 'Tally Integration',
            'url' => '/groundd/admin/integrations/tally.php'
        ],
        [
            'id' => 'custom_integration',
            'title' => 'Custom Integrations',
            'url' => '/groundd/admin/integrations/custom.php'
        ]
    ]
];

// Support Desk (All Phases)
$admin_menu_items[] = [
    'id' => 'support',
    'title' => 'Support Desk',
    'icon' => 'fas fa-headset',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'all_tickets',
            'title' => 'All Tickets',
            'url' => '/groundd/admin/support/all-tickets.php'
        ],
        [
            'id' => 'pending_tickets',
            'title' => 'Pending Tickets',
            'url' => '/groundd/admin/support/pending-tickets.php'
        ],
        [
            'id' => 'ticket_categories',
            'title' => 'Categories',
            'url' => '/groundd/admin/support/ticket-categories.php'
        ]
    ]
];

// Analytics
$admin_menu_items[] = [
    'id' => 'analytics',
    'title' => 'Analytics',
    'icon' => 'fas fa-chart-bar',
    'url' => '/groundd/admin/analytics.php'
];

// PHASE 8: Time Reports (NEW)
$admin_menu_items[] = [
    'id' => 'time_reports',
    'title' => 'Time Reports',
    'icon' => 'fas fa-clock',
    'url' => '/groundd/admin/logs/time-report.php'
];

// System Tools (Admin Utilities)
$admin_menu_items[] = [
    'id' => 'tools',
    'title' => 'System Tools',
    'icon' => 'fas fa-tools',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'system_logs',
            'title' => 'System Logs',
            'url' => '/groundd/admin/tools/system-logs.php'
        ],
        [
            'id' => 'activity_logs',
            'title' => 'Activity Logs',
            'url' => '/groundd/admin/tools/activity-logs.php'
        ],
        [
            'id' => 'system_backup',
            'title' => 'Backup & Restore',
            'url' => '/groundd/admin/tools/backup.php'
        ]
    ]
];

// PHASE 9: Support Desk
$admin_menu_items[] = [
    'id' => 'support',
    'title' => 'Support Desk',
    'icon' => 'fas fa-headset',
    'url' => '#',
    'submenu' => [
        [
            'id' => 'all_tickets',
            'title' => 'All Tickets',
            'url' => '/groundd/admin/support/all-tickets.php'
        ],
        [
            'id' => 'pending_tickets',
            'title' => 'Pending Tickets',
            'url' => '/groundd/admin/support/pending-tickets.php'
        ],
        [
            'id' => 'ticket_categories',
            'title' => 'Categories',
            'url' => '/groundd/admin/support/ticket-categories.php'
        ],
        [
            'id' => 'ticket_details',
            'title' => 'Ticket Details',
            'url' => '/groundd/admin/support/ticket-details.php',
            'hidden' => true // Hidden from menu but used for active state
        ],
        [
            'id' => 'batch_actions',
            'title' => 'Batch Actions',
            'url' => '/groundd/admin/support/batch-actions.php',
            'hidden' => true // Hidden from menu but used for active state
        ]
    ]
];

// Add hook for additional menu items
if (function_exists('add_admin_menu_items')) {
    add_admin_menu_items($admin_menu_items);
}

// Get the current page to highlight the active menu item
$current_script = basename($_SERVER['SCRIPT_NAME']);
$current_page = isset($current_page) ? $current_page : '';
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="/groundd/assets/img/bsg-icon.png" alt="Backsure Global Support" class="logo">
        <div class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <?php foreach ($admin_menu_items as $menu_item): ?>
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
