<?php
/**
 * Client Footer Template
 * 
 * Displays the footer for client pages
 */

// Get current year for copyright
$current_year = date('Y');

// Get site name from settings if available
$site_name = function_exists('get_setting') ? get_setting('site_name', 'Backsure Global Support') : 'Backsure Global Support';

// Additional JavaScript if needed
$extra_js = isset($extra_js) ? $extra_js : '';
?>

            </div><!-- /.content-container -->
            
            <footer class="footer">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6">
                            <p>&copy; <?php echo $current_year; ?> <?php echo $site_name; ?>. All rights reserved.</p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p>
                                <a href="/privacy-policy.php" class="text-muted me-3">Privacy Policy</a>
                                <a href="/terms-of-service.php" class="text-muted">Terms of Service</a>
                            </p>
                        </div>
                    </div>
                </div>
            </footer>
        </div><!-- /.main-content -->
    </div><!-- /.wrapper -->
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/client.js"></script>
    
    <!-- Additional JS if needed -->
    <?php if (!empty($extra_js)): ?>
        <?php echo $extra_js; ?>
    <?php endif; ?>
    
    <script>
        // Initialize tooltips and popovers
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
        
        // Sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                    
                    // Save state to localStorage
                    localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
                });
                
                // Check for saved state
                const sidebarState = localStorage.getItem('sidebar-collapsed');
                if (sidebarState === 'true') {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                }
            }
            
            // Mobile sidebar toggle
            const mobileSidebarToggle = document.querySelector('.sidebar-toggle-btn');
            if (mobileSidebarToggle) {
                mobileSidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-show');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (sidebar.classList.contains('mobile-show') && 
                    !sidebar.contains(event.target) && 
                    !mobileSidebarToggle.contains(event.target)) {
                    sidebar.classList.remove('mobile-show');
                }
            });
        });
    </script>
</body>
</html>