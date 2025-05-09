<?php
/**
 * Admin Sidebar Template
 * 
 * Sidebar navigation for different user roles.
 */

// Get current user role
$user_role = $_SESSION['user_role'] ?? '';

// Define menu items based on role
$menu_items = [];

switch ($user_role) {
    case 'admin':
        $menu_items = [
            ['id' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'bi-speedometer2', 'url' => '/admin/dashboard.php'],
            ['id' => 'tasks', 'title' => 'Tasks', 'icon' => 'bi-check2-square', 'url' => '/admin/tasks/'],
            ['id' => 'clients', 'title' => 'Clients', 'icon' => 'bi-people', 'url' => '/admin/clients/'],
            ['id' => 'employees', 'title' => 'Employees', 'icon' => 'bi-person-badge', 'url' => '/admin/employees/'],
            ['id' => 'plans', 'title' => 'Business Plans', 'icon' => 'bi-clipboard-check', 'url' => '/admin/plans/'],
            ['id' => 'support', 'title' => 'Support Desk', 'icon' => 'bi-headset', 'url' => '/admin/support/'],
            ['id' => 'settings', 'title' => 'Settings', 'icon' => 'bi-gear', 'url' => '/admin/settings/'],
        ];
        break;
        
    case 'client':
        $menu_items = [
            ['id' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'bi-speedometer2', 'url' => '/client/dashboard.php'],
            ['id' => 'tasks', 'title' => 'Tasks', 'icon' => 'bi-check2-square', 'url' => '/client/tasks/'],
            ['id' => 'uploads', 'title' => 'Uploads', 'icon' => 'bi-cloud-upload', 'url' => '/client/uploads/'],
            ['id' => 'plans', 'title' => 'My Plan', 'icon' => 'bi-clipboard-check', 'url' => '/client/plans/'],
            ['id' => 'support', 'title' => 'Support', 'icon' => 'bi-headset', 'url' => '/client/support/'],
        ];
        break;
        
    case 'employee':
        $menu_items = [
            ['id' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'bi-speedometer2', 'url' => '/employee/dashboard.php'],
            ['id' => 'assigned-tasks', 'title' => 'Assigned Tasks', 'icon' => 'bi-check2-square', 'url' => '/employee/assigned-tasks/'],
            ['id' => 'time-tracking', 'title' => 'Time Tracking', 'icon' => 'bi-clock', 'url' => '/employee/time-tracking/'],
            ['id' => 'support', 'title' => 'Support', 'icon' => 'bi-headset', 'url' => '/employee/support/'],
        ];
        break;
        
    default:
        // Should not reach here, but just in case
        $menu_items = [
            ['id' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'bi-speedometer2', 'url' => '/dashboard.php'],
        ];
}
?>

<div class="position-sticky pt-3 sidebar-sticky">
    <ul class="nav flex-column">
        <?php foreach ($menu_items as $item): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($active_page === $item['id']) ? 'active' : ''; ?>" href="<?php echo $item['url']; ?>">
                    <i class="bi <?php echo $item['icon']; ?>"></i>
                    <?php echo htmlspecialchars($item['title']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>