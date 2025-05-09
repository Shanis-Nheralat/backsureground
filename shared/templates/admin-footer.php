<!-- End of page content -->
            </main>
        </div>
    </div>
    
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <div class="d-flex justify-content-between">
                <span class="text-muted">&copy; <?php echo date('Y'); ?> Backsure Global Support</span>
                <span class="text-muted">Version 1.0</span>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Common JavaScript functions can be added here
        
        // Example: Confirm before performing delete operations
        function confirmDelete(formId, message) {
            if (confirm(message || 'Are you sure you want to delete this item?')) {
                document.getElementById(formId).submit();
                return true;
            }
            return false;
        }
    </script>
</body>
</html>