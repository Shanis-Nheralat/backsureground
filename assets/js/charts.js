/**
 * Backsure Global Support Platform
 * Charts and visualizations
 */
(function() {
    'use strict';
    
    // Initialize charts when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Detect and initialize all charts on the page
        initializeCharts();
    });
    
    /**
     * Initialize all charts on the page
     */
    function initializeCharts() {
        initializeTicketStatusChart();
        initializeTaskStatusChart();
        initializeTimeTrackingChart();
        initializeClientMetricsChart();
    }
    
    /**
     * Initialize Ticket Status Chart if present
     */
    function initializeTicketStatusChart() {
        const ctx = document.getElementById('ticketStatusChart');
        
        if (!ctx) return;
        
        // Get chart data from the element's data attributes
        const labels = JSON.parse(ctx.dataset.labels || '[]');
        const values = JSON.parse(ctx.dataset.values || '[]');
        const colors = JSON.parse(ctx.dataset.colors || '[]');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors.length ? colors : [
                        '#e74a3b', // Open
                        '#f6c23e', // In Progress
                        '#1cc88a', // Closed
                        '#858796'  // Cancelled
                    ],
                    hoverBackgroundColor: [
                        '#be3c30',
                        '#daa520',
                        '#169a6e',
                        '#717384'
                    ],
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
    }
    
    /**
     * Initialize Task Status Chart if present
     */
    function initializeTaskStatusChart() {
        const ctx = document.getElementById('taskStatusChart');
        
        if (!ctx) return;
        
        // Get chart data from the element's data attributes
        const labels = JSON.parse(ctx.dataset.labels || '[]');
        const values = JSON.parse(ctx.dataset.values || '[]');
        const colors = JSON.parse(ctx.dataset.colors || '[]');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors.length ? colors : [
                        '#36b9cc', // Submitted
                        '#f6c23e', // In Progress
                        '#1cc88a', // Completed
                        '#858796'  // Cancelled
                    ],
                    hoverBackgroundColor: [
                        '#2c9faf',
                        '#daa520',
                        '#169a6e',
                        '#717384'
                    ],
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
    }
    
    /**
     * Initialize Time Tracking Chart if present
     */
    function initializeTimeTrackingChart() {
        const ctx = document.getElementById('timeTrackingChart');
        
        if (!ctx) return;
        
        // Get chart data from the element's data attributes
        const labels = JSON.parse(ctx.dataset.labels || '[]');
        const values = JSON.parse(ctx.dataset.values || '[]');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Hours',
                    data: values,
                    backgroundColor: 'rgba(0, 97, 213, 0.5)',
                    borderColor: 'rgba(0, 97, 213, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Initialize Client Metrics Chart if present
     */
    function initializeClientMetricsChart() {
        const ctx = document.getElementById('clientMetricsChart');
        
        if (!ctx) return;
        
        // Get chart data from the element's data attributes
        const labels = JSON.parse(ctx.dataset.labels || '[]');
        const datasets = JSON.parse(ctx.dataset.datasets || '[]');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    /**
     * Create and render a new chart
     * 
     * @param {string} selector - CSS selector for the chart container
     * @param {string} type - Chart type (bar, line, doughnut, etc.)
     * @param {object} data - Chart data
     * @param {object} options - Chart options
     * @returns {Chart} The created chart instance
     */
    window.createChart = function(selector, type, data, options) {
        const ctx = document.querySelector(selector);
        
        if (!ctx) {
            console.error(`Chart container not found: ${selector}`);
            return null;
        }
        
        return new Chart(ctx, {
            type: type,
            data: data,
            options: options
        });
    };
    
    /**
     * Update an existing chart
     * 
     * @param {Chart} chart - Chart instance to update
     * @param {object} data - New chart data
     * @param {boolean} animate - Whether to animate the update
     */
    window.updateChart = function(chart, data, animate = true) {
        if (!chart) {
            console.error('Invalid chart instance');
            return;
        }
        
        chart.data = data;
        chart.update(animate ? undefined : 0);
    };
})();