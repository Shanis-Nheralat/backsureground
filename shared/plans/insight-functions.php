<?php
/**
 * Business Care Plans - Insight Functions
 * 
 * Handles service insights and analytics
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth/admin-auth.php';
require_once __DIR__ . '/plan-functions.php';

/**
 * Get client insights by service
 */
function get_client_insights($client_id, $service_id = null) {
    global $pdo;
    
    try {
        $sql = "SELECT si.*, s.name as service_name
                FROM service_insights si
                JOIN services s ON si.service_id = s.id
                WHERE si.client_id = ?";
        
        $params = [$client_id];
        
        if ($service_id) {
            $sql .= " AND si.service_id = ?";
            $params[] = $service_id;
        }
        
        $sql .= " ORDER BY si.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Client Insights Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get insight by ID
 */
function get_insight($insight_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT si.*, s.name as service_name
            FROM service_insights si
            JOIN services s ON si.service_id = s.id
            WHERE si.id = ?
        ");
        
        $stmt->execute([$insight_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Get Insight Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Create service insight
 */
function create_service_insight($service_id, $client_id, $insight_type, $title, $description, $data_json, $period_start, $period_end, $created_by) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO service_insights 
            (service_id, client_id, insight_type, title, description, data_json, period_start, period_end, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $service_id,
            $client_id,
            $insight_type,
            $title,
            $description,
            $data_json,
            $period_start,
            $period_end,
            $created_by
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('Create Service Insight Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update service insight
 */
function update_service_insight($insight_id, $title, $description, $data_json, $period_start, $period_end) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE service_insights 
            SET title = ?, description = ?, data_json = ?, period_start = ?, period_end = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $title,
            $description,
            $data_json,
            $period_start,
            $period_end,
            $insight_id
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log('Update Service Insight Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete service insight
 */
function delete_service_insight($insight_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM service_insights WHERE id = ?");
        $stmt->execute([$insight_id]);
        return true;
    } catch (PDOException $e) {
        error_log('Delete Service Insight Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generate turnover insights
 */
function generate_turnover_insight($client_id, $service_id, $period_start, $period_end, $created_by) {
    // This would typically fetch data from Tally, Zoho, or other integrated systems
    // For demonstration, we'll create sample data
    
    $months = [];
    $revenue = [];
    $expenses = [];
    
    // Generate 6 months of sample data
    $start_date = new DateTime($period_start);
    $end_date = new DateTime($period_end);
    
    $current_date = clone $start_date;
    while ($current_date <= $end_date) {
        $month = $current_date->format('M Y');
        $months[] = $month;
        
        // Random revenue between 50,000 and 150,000
        $revenue[] = rand(50000, 150000);
        
        // Random expenses between 30,000 and 100,000
        $expenses[] = rand(30000, 100000);
        
        $current_date->modify('+1 month');
    }
    
    // Calculate profit
    $profit = [];
    for ($i = 0; $i < count($revenue); $i++) {
        $profit[] = $revenue[$i] - $expenses[$i];
    }
    
    // Create insight data
    $data = [
        'labels' => $months,
        'datasets' => [
            [
                'label' => 'Revenue',
                'data' => $revenue,
                'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                'borderColor' => 'rgba(54, 162, 235, 1)'
            ],
            [
                'label' => 'Expenses',
                'data' => $expenses,
                'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                'borderColor' => 'rgba(255, 99, 132, 1)'
            ],
            [
                'label' => 'Profit',
                'data' => $profit,
                'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                'borderColor' => 'rgba(75, 192, 192, 1)'
            ]
        ]
    ];
    
    // Create insight
    $title = 'Turnover Analysis: ' . $start_date->format('M Y') . ' to ' . $end_date->format('M Y');
    $description = 'Monthly breakdown of revenue, expenses, and profit.';
    
    return create_service_insight(
        $service_id,
        $client_id,
        'turnover',
        $title,
        $description,
        json_encode($data),
        $period_start,
        $period_end,
        $created_by
    );
}

/**
 * Generate payroll insights
 */
function generate_payroll_insight($client_id, $service_id, $period_start, $period_end, $created_by) {
    // This would typically fetch data from Zoho, or other integrated systems
    // For demonstration, we'll create sample data
    
    $departments = ['Management', 'Finance', 'HR', 'IT', 'Marketing', 'Operations'];
    $department_costs = [];
    
    // Generate random costs for each department
    foreach ($departments as $department) {
        $department_costs[] = rand(10000, 50000);
    }
    
    // Create insight data
    $data = [
        'type' => 'pie',
        'labels' => $departments,
        'datasets' => [
            [
                'data' => $department_costs,
                'backgroundColor' => [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF',
                    '#FF9F40'
                ]
            ]
        ]
    ];
    
    // Create insight
    $title = 'Payroll Distribution: ' . date('M Y', strtotime($period_start)) . ' to ' . date('M Y', strtotime($period_end));
    $description = 'Breakdown of payroll costs by department.';
    
    return create_service_insight(
        $service_id,
        $client_id,
        'payroll',
        $title,
        $description,
        json_encode($data),
        $period_start,
        $period_end,
        $created_by
    );
}

/**
 * Generate compliance insights
 */
function generate_compliance_insight($client_id, $service_id, $period_start, $period_end, $created_by) {
    // This would typically fetch data from compliance monitoring systems
    // For demonstration, we'll create sample data
    
    $compliance_areas = ['Tax Filings', 'Regulatory Reports', 'HR Compliance', 'Data Protection', 'Industry Standards'];
    $status = [];
    $due_dates = [];
    $completion_dates = [];
    
    // Generate random status for each area
    foreach ($compliance_areas as $area) {
        $status[] = rand(0, 1) ? 'Completed' : 'Pending';
        
        // Random due date
        $due_date = new DateTime($period_start);
        $due_date->modify('+' . rand(1, 30) . ' days');
        $due_dates[] = $due_date->format('Y-m-d');
        
        // Random completion date (if completed)
        if ($status[count($status) - 1] === 'Completed') {
            $completion_date = clone $due_date;
            $completion_date->modify('-' . rand(1, 10) . ' days');
            $completion_dates[] = $completion_date->format('Y-m-d');
        } else {
            $completion_dates[] = null;
        }
    }
    
    // Calculate compliance percentage
    $completed_count = count(array_filter($status, function($s) { return $s === 'Completed'; }));
    $compliance_percentage = ($completed_count / count($compliance_areas)) * 100;
    
    // Create insight data
    $data = [
        'compliancePercentage' => $compliance_percentage,
        'areas' => $compliance_areas,
        'status' => $status,
        'dueDates' => $due_dates,
        'completionDates' => $completion_dates,
        'gaugeData' => [
            'value' => $compliance_percentage,
            'min' => 0,
            'max' => 100,
            'threshold' => 80
        ]
    ];
    
    // Create insight
    $title = 'Compliance Status: ' . date('M Y', strtotime($period_start));
    $description = 'Overview of compliance requirements and completion status.';
    
    return create_service_insight(
        $service_id,
        $client_id,
        'compliance',
        $title,
        $description,
        json_encode($data),
        $period_start,
        $period_end,
        $created_by
    );
}

/**
 * Render insight chart
 */
function render_insight_chart($insight) {
    $data = json_decode($insight['data_json'], true);
    $chart_id = 'chart-' . $insight['id'];
    
    $html = '<div class="insight-chart-container">';
    
    if ($insight['insight_type'] === 'turnover') {
        $html .= '<canvas id="' . $chart_id . '" width="400" height="200"></canvas>';
        $html .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const ctx = document.getElementById("' . $chart_id . '").getContext("2d");
                new Chart(ctx, {
                    type: "bar",
                    data: ' . $insight['data_json'] . ',
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });
        </script>';
    } else if ($insight['insight_type'] === 'payroll') {
        $html .= '<canvas id="' . $chart_id . '" width="400" height="400"></canvas>';
        $html .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const ctx = document.getElementById("' . $chart_id . '").getContext("2d");
                new Chart(ctx, {
                    type: "pie",
                    data: ' . $insight['data_json'] . ',
                    options: {
                        responsive: true
                    }
                });
            });
        </script>';
    } else if ($insight['insight_type'] === 'compliance') {
        // Gauge chart for compliance percentage
        $compliance_data = json_decode($insight['data_json'], true);
        $percentage = $compliance_data['compliancePercentage'];
        
        $html .= '<div class="row">';
        $html .= '<div class="col-md-4">';
        $html .= '<div class="compliance-gauge" id="' . $chart_id . '-gauge"></div>';
        $html .= '</div>';
        $html .= '<div class="col-md-8">';
        $html .= '<table class="table table-striped">';
        $html .= '<thead><tr><th>Area</th><th>Status</th><th>Due Date</th><th>Completed On</th></tr></thead>';
        $html .= '<tbody>';
        
        for ($i = 0; $i < count($compliance_data['areas']); $i++) {
            $status_class = $compliance_data['status'][$i] === 'Completed' ? 'text-success' : 'text-warning';
            $html .= '<tr>';
            $html .= '<td>' . $compliance_data['areas'][$i] . '</td>';
            $html .= '<td><span class="' . $status_class . '">' . $compliance_data['status'][$i] . '</span></td>';
            $html .= '<td>' . date('M d, Y', strtotime($compliance_data['dueDates'][$i])) . '</td>';
            $html .= '<td>' . ($compliance_data['completionDates'][$i] ? date('M d, Y', strtotime($compliance_data['completionDates'][$i])) : '-') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>'; // col-md-8
        $html .= '</div>'; // row
        
        // JavaScript for gauge
        $html .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const gauge = new JustGage({
                    id: "' . $chart_id . '-gauge",
                    value: ' . $percentage . ',
                    min: 0,
                    max: 100,
                    title: "Compliance",
                    label: "%",
                    levelColors: ["#FF0000", "#FFFF00", "#00FF00"],
                    gaugeWidthScale: 0.6
                });
            });
        </script>';
    } else {
        $html .= '<div class="alert alert-info">No visualization available for this insight type.</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}