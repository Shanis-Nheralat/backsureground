<?php
/**
 * Centralized Menu Configuration
 * 
 * Defines all menu items for the Backsure Global Support platform
 * Organized by user role and phase
 */

// Helper function for creating menu items
function create_menu_item($id, $title, $icon, $url, $submenu = null) {
    return [
        'id' => $id,
        'title' => $title,
        'icon' => $icon,
        'url' => $url,
        'submenu' => $submenu
    ];
}

// Define base URL
$base_url = '/groundd';

/**
 * PHASE 9: Support Desk Menu Items
 */
function get_support_desk_menu_items($role, $base_url) {
    switch ($role) {
        case 'client':
            return create_menu_item(
                'support',
                'Support',
                'fas fa-headset',
                '#',
                [
                    [
                        'id' => 'my_tickets',
                        'title' => 'My Tickets',
                        'url' => $base_url . '/client/support/my-tickets.php'
                    ],
                    [
                        'id' => 'new_ticket',
                        'title' => 'Submit Ticket',
                        'url' => $base_url . '/client/support/new-ticket.php'
                    ],
                    [
                        'id' => 'view_ticket',
                        'title' => 'View Ticket',
                        'url' => $base_url . '/client/support/view-ticket.php',
                        'hidden' => true // Hidden from menu but used for active state
                    ]
                ]
            );
            
        case 'employee':
            return create_menu_item(
                'support',
                'Support',
                'fas fa-headset',
                '#',
                [
                    [
                        'id' => 'assigned_tickets',
                        'title' => 'Assigned Tickets',
                        'url' => $base_url . '/employee/support/assigned-tickets.php'
                    ],
                    [
                        'id' => 'view_ticket',
                        'title' => 'View Ticket',
                        'url' => $base_url . '/employee/support/view-ticket.php',
                        'hidden' => true // Hidden from menu but used for active state
                    ]
                ]
            );
            
        case 'admin':
            return create_menu_item(
                'support',
                'Support Desk',
                'fas fa-headset',
                '#',
                [
                    [
                        'id' => 'all_tickets',
                        'title' => 'All Tickets',
                        'url' => $base_url . '/admin/support/all-tickets.php'
                    ],
                    [
                        'id' => 'ticket_details',
                        'title' => 'Ticket Details',
                        'url' => $base_url . '/admin/support/ticket-details.php',
                        'hidden' => true // Hidden from menu but used for active state
                    ],
                    [
                        'id' => 'batch_actions',
                        'title' => 'Batch Actions',
                        'url' => $base_url . '/admin/support/batch-actions.php',
                        'hidden' => true // Hidden from menu but used for active state
                    ]
                ]
            );
            
        default:
            return null;
    }
}

/**
 * Get All Menu Items for a Role
 */
function get_all_menu_items($role, $base_url) {
    $menu_items = [];
    
    // Add items from each phase...
    
    // PHASE 9: Support Desk
    $support_menu = get_support_desk_menu_items($role, $base_url);
    if ($support_menu) {
        $menu_items[] = $support_menu;
    }
    
    return $menu_items;
}