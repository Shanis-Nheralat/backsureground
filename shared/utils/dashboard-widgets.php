<?php
// /shared/utils/dashboard-widgets.php

/**
 * Dashboard Widget Management Functions
 */

/**
 * Get all widgets for a user
 * 
 * @param int $user_id User ID
 * @param string $user_role User role (admin, client, employee)
 * @return array List of widgets
 */
function get_user_widgets($user_id, $user_role) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM dashboard_widgets 
            WHERE user_id = ? AND user_role = ? AND is_active = 1
            ORDER BY position
        ");
        $stmt->execute([$user_id, $user_role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error retrieving dashboard widgets: ' . $e->getMessage());
        return [];
    }
}

/**
 * Create a new widget for a user
 * 
 * @param int $user_id User ID
 * @param string $user_role User role
 * @param string $widget_type Widget type
 * @param array $settings Widget settings
 * @param int $position Widget position
 * @return int|bool The new widget ID or false on failure
 */
function create_widget($user_id, $user_role, $widget_type, $settings = [], $position = 0) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO dashboard_widgets 
            (user_id, user_role, widget_type, settings, position) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $settings_json = json_encode($settings);
        $stmt->execute([$user_id, $user_role, $widget_type, $settings_json, $position]);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log('Error creating dashboard widget: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update widget settings
 * 
 * @param int $widget_id Widget ID
 * @param array $settings New settings
 * @return bool Success status
 */
function update_widget_settings($widget_id, $settings) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE dashboard_widgets 
            SET settings = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $settings_json = json_encode($settings);
        return $stmt->execute([$settings_json, $widget_id]);
    } catch (Exception $e) {
        error_log('Error updating widget settings: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update widget position
 * 
 * @param int $widget_id Widget ID
 * @param int $position New position
 * @return bool Success status
 */
function update_widget_position($widget_id, $position) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE dashboard_widgets 
            SET position = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$position, $widget_id]);
    } catch (Exception $e) {
        error_log('Error updating widget position: ' . $e->getMessage());
        return false;
    }
}

/**
 * Toggle widget active status
 * 
 * @param int $widget_id Widget ID
 * @param bool $is_active New active status
 * @return bool Success status
 */
function toggle_widget_active($widget_id, $is_active) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE dashboard_widgets 
            SET is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$is_active ? 1 : 0, $widget_id]);
    } catch (Exception $e) {
        error_log('Error toggling widget active status: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete a widget
 * 
 * @param int $widget_id Widget ID
 * @return bool Success status
 */
function delete_widget($widget_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM dashboard_widgets WHERE id = ?");
        return $stmt->execute([$widget_id]);
    } catch (Exception $e) {
        error_log('Error deleting dashboard widget: ' . $e->getMessage());
        return false;
    }
}

/**
 * Render a specific widget type
 * 
 * @param string $widget_type Widget type
 * @param array $settings Widget settings
 * @return string HTML for the widget
 */
function render_widget($widget_type, $settings) {
    switch ($widget_type) {
        case 'ticket_summary':
            return render_ticket_summary_widget($settings);
        
        case 'task_status':
            return render_task_status_widget($settings);
        
        case 'recent_activity':
            return render_recent_activity_widget($settings);
        
        // Add more widget types as needed
        
        default:
            return '<div class="alert alert-warning">Unknown widget type: ' . htmlspecialchars($widget_type) . '</div>';
    }
}

/**
 * Render the ticket summary widget
 * 
 * @param array $settings Widget settings
 * @return string HTML for the widget
 */
function render_ticket_summary_widget($settings) {
    global $pdo;
    
    // Default settings
    $days_range = $settings['days_range'] ?? 30;
    $show_closed = $settings['show_closed'] ?? true;
    
    try {
        // Get ticket counts by status
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days_range} days"));
        
        $status_conditions = ['open', 'in_progress'];
        if ($show_closed) {
            $status_conditions[] = 'closed';
            $status_conditions[] = 'cancelled';
        }
        
        $placeholders = str_repeat('?,', count($status_conditions) - 1) . '?';
        
        $sql = "
            SELECT status, COUNT(*) as count
            FROM support_tickets
            WHERE created_at >= ? AND status IN ({$placeholders})
            GROUP BY status
        ";
        
        $params = [$date_limit];
        $params = array_merge($params, $status_conditions);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare data for chart
        $status_counts = [];
        $total_tickets = 0;
        
        foreach ($results as $row) {
            $status_counts[$row['status']] = $row['count'];
            $total_tickets += $row['count'];
        }
        
        // Default values for statuses
        $statuses = ['open' => 0, 'in_progress' => 0, 'closed' => 0, 'cancelled' => 0];
        
        // Apply actual values
        foreach ($status_counts as $status => $count) {
            $statuses[$status] = $count;
        }
        
        // Render widget
        $output = '
        <div class="card shadow h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Support Tickets Summary</h6>
                <span class="badge bg-primary">' . $total_tickets . ' Tickets</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="ticketStatusChart" height="200"></canvas>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <div class="card border-left-danger shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Open</div>
                                                <div class="h5 mb-0 font-weight-bold">' . $statuses['open'] . '</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-ticket-alt fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6 mb-3">
                                <div class="card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">In Progress</div>
                                                <div class="h5 mb-0 font-weight-bold">' . $statuses['in_progress'] . '</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-spinner fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>';
                            
        if ($show_closed) {
            $output .= '
                            <div class="col-6 mb-3">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Closed</div>
                                                <div class="h5 mb-0 font-weight-bold">' . $statuses['closed'] . '</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6 mb-3">
                                <div class="card border-left-secondary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Cancelled</div>
                                                <div class="h5 mb-0 font-weight-bold">' . $statuses['cancelled'] . '</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>';
        }
                            
        $output .= '
                        </div>
                    </div>
                </div>
                <div class="text-xs text-muted mt-3">
                    Data shown for the last ' . $days_range . ' days
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var ctx = document.getElementById("ticketStatusChart");
            var ticketStatusChart = new Chart(ctx, {
                type: "doughnut",
                data: {
                    labels: ["Open", "In Progress"' . ($show_closed ? ', "Closed", "Cancelled"' : '') . '],
                    datasets: [{
                        data: [' . $statuses['open'] . ', ' . $statuses['in_progress'] . 
                        ($show_closed ? ', ' . $statuses['closed'] . ', ' . $statuses['cancelled'] : '') . '],
                        backgroundColor: ["#e74a3b", "#f6c23e"' . ($show_closed ? ', "#1cc88a", "#858796"' : '') . '],
                        hoverBackgroundColor: ["#be3c30", "#daa520"' . ($show_closed ? ', "#169a6e", "#717384"' : '') . '],
                        hoverBorderColor: "rgba(234, 236, 244, 1)",
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    tooltips: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyFontColor: "#858796",
                        borderColor: "#dddfeb",
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        caretPadding: 10,
                    },
                    legend: {
                        display: false
                    },
                    cutoutPercentage: 80,
                },
            });
        });
        </script>';
        
        return $output;
    } catch (Exception $e) {
        error_log('Error rendering ticket summary widget: ' . $e->getMessage());
        return '<div class="alert alert-danger">Error loading ticket summary data.</div>';
    }
}

/**
 * Render the task status widget
 * 
 * @param array $settings Widget settings
 * @return string HTML for the widget
 */
function render_task_status_widget($settings) {
    global $pdo;
    
    // Default settings
    $days_range = $settings['days_range'] ?? 30;
    $show_completed = $settings['show_completed'] ?? true;
    
    try {
        // Get task counts by status
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days_range} days"));
        
        $status_conditions = ['submitted', 'in_progress'];
        if ($show_completed) {
            $status_conditions[] = 'completed';
            $status_conditions[] = 'cancelled';
        }
        
        $placeholders = str_repeat('?,', count($status_conditions) - 1) . '?';
        
        $sql = "
            SELECT status, COUNT(*) as count
            FROM on_demand_tasks
            WHERE submitted_at >= ? AND status IN ({$placeholders})
            GROUP BY status
        ";
        
        $params = [$date_limit];
        $params = array_merge($params, $status_conditions);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare data for chart
        $status_counts = [];
        $total_tasks = 0;
        
        foreach ($results as $row) {
            $status_counts[$row['status']] = $row['count'];
            $total_tasks += $row['count'];
        }
        
        // Default values for statuses
        $statuses = ['submitted' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
        
        // Apply actual values
        foreach ($status_counts as $status => $count) {
            $statuses[$status] = $count;
        }
        
        // Render widget
        $output = '
        <div class="card shadow h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">On-Demand Tasks Summary</h6>
                <span class="badge bg-primary">' . $total_tasks . ' Tasks</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="taskStatusChart" height="200"></canvas>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <div class="card border-left-info shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Submitted</div>
                                                <div class="h5 mb-0 font-weight-bold">' . $statuses['submitted'] . '</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6 mb-3">
                                <div class="card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">In Progress</div>
                                                <div class="h5 mb-0 font-weight-bold">' . $statuses['in_progress'] . '</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-spinner fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>';
                            
        if ($show_completed) {
            $output .= '
                            <div class="col-6 mb-3">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                                                <div class="h5 mb-0 font-weight-bold">' . $statuses['completed'] . '</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6 mb-3">
                                <div class="card border-left-secondary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Cancelled</div>
                                                <div class="h5 mb-0 font-weight-bold">' . $statuses['cancelled'] . '</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>';
        }
                            
        $output .= '
                        </div>
                    </div>
                </div>
                <div class="text-xs text-muted mt-3">
                    Data shown for the last ' . $days_range . ' days
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var ctx = document.getElementById("taskStatusChart");
            var taskStatusChart = new Chart(ctx, {
                type: "doughnut",
                data: {
                    labels: ["Submitted", "In Progress"' . ($show_completed ? ', "Completed", "Cancelled"' : '') . '],
                    datasets: [{
                        data: [' . $statuses['submitted'] . ', ' . $statuses['in_progress'] . 
                        ($show_completed ? ', ' . $statuses['completed'] . ', ' . $statuses['cancelled'] : '') . '],
                        backgroundColor: ["#36b9cc", "#f6c23e"' . ($show_completed ? ', "#1cc88a", "#858796"' : '') . '],
                        hoverBackgroundColor: ["#2c9faf", "#daa520"' . ($show_completed ? ', "#169a6e", "#717384"' : '') . '],
                        hoverBorderColor: "rgba(234, 236, 244, 1)",
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    tooltips: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyFontColor: "#858796",
                        borderColor: "#dddfeb",
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        caretPadding: 10,
                    },
                    legend: {
                        display: false
                    },
                    cutoutPercentage: 80,
                },
            });
        });
        </script>';
        
        return $output;
    } catch (Exception $e) {
        error_log('Error rendering task status widget: ' . $e->getMessage());
        return '<div class="alert alert-danger">Error loading task status data.</div>';
    }
}

/**
 * Render the recent activity widget
 * 
 * @param array $settings Widget settings
 * @return string HTML for the widget
 */
function render_recent_activity_widget($settings) {
    global $pdo;
    
    // Default settings
    $limit = $settings['limit'] ?? 10;
    $include_logins = $settings['include_logins'] ?? false;
    
    try {
        // Build the query
        $where_clause = '';
        if (!$include_logins) {
            $where_clause = "WHERE action_type NOT IN ('login', 'logout')";
        }
        
        $sql = "
            SELECT l.*, u.username, u.name
            FROM admin_extended_log l
            LEFT JOIN users u ON l.admin_id = u.id
            {$where_clause}
            ORDER BY l.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Render widget
        $output = '
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Recent Admin Activity</h6>
            </div>
            <div class="card-body">
                <div class="activity-feed">';
        
        if (empty($activities)) {
            $output .= '<div class="text-center py-4">No recent activity found.</div>';
        } else {
            foreach ($activities as $activity) {
                // Determine icon based on action
                $icon_class = '';
                switch($activity['action_type']) {
                    case 'login': $icon_class = 'fa-sign-in-alt text-success'; break;
                    case 'logout': $icon_class = 'fa-sign-out-alt text-secondary'; break;
                    case 'create': $icon_class = 'fa-plus-circle text-info'; break;
                    case 'update': $icon_class = 'fa-edit text-warning'; break;
                    case 'delete': $icon_class = 'fa-trash-alt text-danger'; break;
                    case 'assign': $icon_class = 'fa-user-check text-primary'; break;
                    case 'settings_change': $icon_class = 'fa-cogs text-dark'; break;
                    case 'access': $icon_class = 'fa-eye text-info'; break;
                    case 'access_denied': $icon_class = 'fa-ban text-danger'; break;
                    default: $icon_class = 'fa-circle text-primary';
                }
                
                $time_ago = time_elapsed_string($activity['created_at']);
                $admin_name = htmlspecialchars($activity['name'] ?? $activity['username'] ?? 'Unknown');
                
                // Format action description
                $action_description = ucfirst($activity['action_type']);
                
                if (!empty($activity['module'])) {
                    $action_description .= ' in ' . htmlspecialchars($activity['module']);
                    
                    if (!empty($activity['item_type']) && !empty($activity['item_id'])) {
                        $action_description .= ' (' . htmlspecialchars($activity['item_type']) . ' #' . $activity['item_id'] . ')';
                    }
                }
                
                $output .= '
                <div class="activity-item d-flex align-items-start mb-3">
                    <div class="activity-icon me-3">
                        <i class="fas ' . $icon_class . ' fa-fw fa-lg"></i>
                    </div>
                    <div class="activity-content flex-grow-1">
                        <div class="d-flex justify-content-between">
                            <strong>' . $admin_name . '</strong>
                            <small class="text-muted">' . $time_ago . '</small>
                        </div>
                        <div>' . $action_description . '</div>';
                
                // Show details if available
                if (!empty($activity['details'])) {
                    $output .= '<small class="text-muted">' . htmlspecialchars($activity['details']) . '</small>';
                }
                
                $output .= '
                    </div>
                </div>';
            }
        }
        
        $output .= '
                </div>
                <div class="text-center mt-3">
                    <a href="/admin/logs/activity-log.php" class="btn btn-sm btn-primary">View All Activity</a>
                </div>
            </div>
        </div>';
        
        return $output;
    } catch (Exception $e) {
        error_log('Error rendering recent activity widget: ' . $e->getMessage());
        return '<div class="alert alert-danger">Error loading recent activity data.</div>';
    }
}

/**
 * Format a timestamp as a human-readable "time ago" string
 * 
 * @param string $datetime MySQL datetime string
 * @return string Time elapsed string
 */
function time_elapsed_string($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    }
    
    $intervals = [
        1                   => ['minute', 'minutes'],
        60                  => ['hour', 'hours'],
        60 * 24            => ['day', 'days'],
        60 * 24 * 7        => ['week', 'weeks'],
        60 * 24 * 30       => ['month', 'months'],
        60 * 24 * 365      => ['year', 'years']
    ];
    
    foreach ($intervals as $seconds => $labels) {
        $divisions = $diff / $seconds;
        
        if ($divisions < 1) {
            $count = floor($divisions);
            $unit = $count == 1 ? $labels[0] : $labels[1];
            return $count . ' ' . $unit . ' ago';
        }
    }
    
    return date('M j, Y', $time);
}