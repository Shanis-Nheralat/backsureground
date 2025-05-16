/**
 * Backsure Global Support Platform
 * Main JavaScript file
 */
(function() {
    'use strict';
    
    // Initialize Bootstrap components
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Initialize popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
        
        // Initialize sidebar toggle for mobile
        initializeSidebar();
        
        // Initialize file uploads if available
        initializeFileUploads();
        
        // Initialize notification system
        initializeNotifications();
        
        // Set up AJAX CSRF token
        setupCSRFToken();
    });
    
    /**
     * Initialize sidebar functionality
     */
    function initializeSidebar() {
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('show');
                
                // Add backdrop on mobile
                if (sidebar.classList.contains('show')) {
                    const backdrop = document.createElement('div');
                    backdrop.classList.add('sidebar-backdrop');
                    backdrop.style.position = 'fixed';
                    backdrop.style.top = '0';
                    backdrop.style.left = '0';
                    backdrop.style.width = '100vw';
                    backdrop.style.height = '100vh';
                    backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.3)';
                    backdrop.style.zIndex = '99';
                    document.body.appendChild(backdrop);
                    
                    backdrop.addEventListener('click', function() {
                        sidebar.classList.remove('show');
                        backdrop.remove();
                    });
                } else {
                    const backdrop = document.querySelector('.sidebar-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                }
            });
        }
    }
    
    /**
     * Initialize file uploads
     */
    function initializeFileUploads() {
        const fileUploads = document.querySelectorAll('.file-upload-input');
        
        fileUploads.forEach(function(fileUpload) {
            const container = fileUpload.closest('.file-upload-container');
            const preview = container.querySelector('.file-preview');
            
            // Handle file selection
            fileUpload.addEventListener('change', function(e) {
                handleFiles(this.files);
            });
            
            // Handle drag and drop
            if (container) {
                container.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.add('dragover');
                });
                
                container.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.remove('dragover');
                });
                
                container.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.remove('dragover');
                    
                    if (e.dataTransfer.files.length) {
                        fileUpload.files = e.dataTransfer.files;
                        handleFiles(e.dataTransfer.files);
                    }
                });
                
                container.addEventListener('click', function(e) {
                    if (e.target === this || e.target.classList.contains('file-upload-prompt')) {
                        fileUpload.click();
                    }
                });
            }
            
            // Handle the selected files
            function handleFiles(files) {
                if (!preview) return;
                
                // Clear preview if not multiple
                if (!fileUpload.multiple) {
                    preview.innerHTML = '';
                }
                
                Array.from(files).forEach(file => {
                    const reader = new FileReader();
                    const fileSize = formatFileSize(file.size);
                    const fileType = file.type;
                    const fileName = file.name;
                    
                    const filePreview = document.createElement('div');
                    filePreview.className = 'file-preview-item';
                    
                    reader.onload = function(e) {
                        let fileContent = '';
                        
                        // Check if it's an image
                        if (fileType.startsWith('image/')) {
                            fileContent = `<img src="${e.target.result}" alt="${fileName}">`;
                        } else {
                            // Show icon based on file type
                            let iconClass = 'bi-file-earmark';
                            
                            if (fileType.includes('pdf')) {
                                iconClass = 'bi-file-earmark-pdf';
                            } else if (fileType.includes('word') || fileName.endsWith('.doc') || fileName.endsWith('.docx')) {
                                iconClass = 'bi-file-earmark-word';
                            } else if (fileType.includes('excel') || fileName.endsWith('.xls') || fileName.endsWith('.xlsx')) {
                                iconClass = 'bi-file-earmark-excel';
                            } else if (fileType.includes('zip') || fileType.includes('rar') || fileType.includes('7z')) {
                                iconClass = 'bi-file-earmark-zip';
                            } else if (fileType.includes('text')) {
                                iconClass = 'bi-file-earmark-text';
                            }
                            
                            fileContent = `<div class="file-icon"><i class="bi ${iconClass}"></i></div>`;
                        }
                        
                        filePreview.innerHTML = `
                            ${fileContent}
                            <div class="file-info">
                                <div class="file-name" title="${fileName}">${fileName}</div>
                                <div class="file-size">${fileSize}</div>
                            </div>
                            <div class="file-remove" data-filename="${fileName}">
                                <i class="bi bi-x"></i>
                            </div>
                        `;
                        
                        preview.appendChild(filePreview);
                        
                        // Handle remove button
                        const removeButton = filePreview.querySelector('.file-remove');
                        if (removeButton) {
                            removeButton.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                filePreview.remove();
                                
                                // TODO: Handle removal from FileList
                                // This is tricky as FileList is immutable
                                // For a complete solution, you might need to use a custom file upload solution
                            });
                        }
                    };
                    
                    reader.readAsDataURL(file);
                });
            }
            
            // Format file size
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
        });
    }
    
    /**
     * Initialize notifications
     */
    function initializeNotifications() {
        const notificationDropdown = document.querySelector('.notification-dropdown');
        const markAllRead = document.querySelector('.mark-all-read');
        
        if (markAllRead) {
            markAllRead.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Send AJAX request to mark all as read
                fetch('/api/notifications/mark-all-read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        const unreadItems = notificationDropdown.querySelectorAll('.notification-item.unread');
                        unreadItems.forEach(item => {
                            item.classList.remove('unread');
                        });
                        
                        // Update counter
                        const counter = document.querySelector('.notification-badge .badge');
                        if (counter) {
                            counter.textContent = '0';
                            counter.classList.add('d-none');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error marking notifications as read:', error);
                });
            });
        }
        
        // Handle individual notification read status
        if (notificationDropdown) {
            notificationDropdown.addEventListener('click', function(e) {
                const notificationItem = e.target.closest('.notification-item');
                
                if (notificationItem && notificationItem.classList.contains('unread')) {
                    const notificationId = notificationItem.dataset.id;
                    
                    // Send AJAX request to mark as read
                    fetch(`/api/notifications/mark-read/${notificationId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update UI
                            notificationItem.classList.remove('unread');
                            
                            // Update counter
                            const counter = document.querySelector('.notification-badge .badge');
                            if (counter) {
                                const count = parseInt(counter.textContent) - 1;
                                counter.textContent = count.toString();
                                
                                if (count <= 0) {
                                    counter.classList.add('d-none');
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error marking notification as read:', error);
                    });
                }
            });
        }
    }
    
    /**
     * Set up CSRF token for AJAX requests
     */
    function setupCSRFToken() {
        // Check if we have a CSRF token meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        
        if (csrfToken) {
            // Set up AJAX requests to include CSRF token
            const token = csrfToken.getAttribute('content');
            
            // For fetch API
            const originalFetch = window.fetch;
            window.fetch = function(url, options = {}) {
                if (options.method && options.method.toUpperCase() !== 'GET') {
                    if (!options.headers) {
                        options.headers = {};
                    }
                    
                    if (options.headers instanceof Headers) {
                        options.headers.append('X-CSRF-Token', token);
                    } else {
                        options.headers['X-CSRF-Token'] = token;
                    }
                }
                
                return originalFetch.call(this, url, options);
            };
            
            // For jQuery AJAX if used
            if (window.jQuery) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-Token': token
                    }
                });
            }
        }
    }
    
    /**
     * Time Tracking Functionality
     */
    window.TimeTracker = {
        timerInterval: null,
        startTime: null,
        elapsedTime: 0,
        isRunning: false,
        taskId: null,
        
        // Initialize the timer
        init: function(taskId) {
            this.taskId = taskId;
            this.displayElement = document.querySelector('.timer-display');
            this.startButton = document.querySelector('.timer-start');
            this.stopButton = document.querySelector('.timer-stop');
            
            if (this.startButton) {
                this.startButton.addEventListener('click', this.start.bind(this));
            }
            
            if (this.stopButton) {
                this.stopButton.addEventListener('click', this.stop.bind(this));
            }
            
            // Check if there's a session storage timer running
            const savedState = this.loadState();
            if (savedState && savedState.isRunning) {
                this.elapsedTime = (Date.now() - savedState.startTime) + savedState.elapsedTime;
                this.start();
            } else if (savedState) {
                this.elapsedTime = savedState.elapsedTime;
                this.updateDisplay();
            }
        },
        
        // Start the timer
        start: function() {
            if (this.isRunning) return;
            
            this.isRunning = true;
            this.startTime = Date.now();
            
            if (this.startButton) this.startButton.disabled = true;
            if (this.stopButton) this.stopButton.disabled = false;
            
            this.timerInterval = setInterval(() => {
                this.elapsedTime = (Date.now() - this.startTime) + this.elapsedTime;
                this.updateDisplay();
                this.saveState();
            }, 1000);
            
            // Send AJAX request to start tracking on server
            fetch('/api/time-tracking/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    task_id: this.taskId
                })
            })
            .catch(error => {
                console.error('Error starting time tracking:', error);
            });
        },
        
        // Stop the timer
        stop: function() {
            if (!this.isRunning) return;
            
            clearInterval(this.timerInterval);
            this.isRunning = false;
            this.saveState();
            
            if (this.startButton) this.startButton.disabled = false;
            if (this.stopButton) this.stopButton.disabled = true;
            
            // Send AJAX request to stop tracking on server
            fetch('/api/time-tracking/stop', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    task_id: this.taskId,
                    duration: this.elapsedTime
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reset timer
                    this.elapsedTime = 0;
                    this.updateDisplay();
                    this.saveState();
                    
                    // Reload time logs if available
                    const timeLogList = document.querySelector('.time-log-list');
                    if (timeLogList) {
                        location.reload();
                    }
                }
            })
            .catch(error => {
                console.error('Error stopping time tracking:', error);
            });
        },
        
        // Update the timer display
        updateDisplay: function() {
            if (!this.displayElement) return;
            
            const totalSeconds = Math.floor(this.elapsedTime / 1000);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            
            this.displayElement.textContent = 
                hours.toString().padStart(2, '0') + ':' + 
                minutes.toString().padStart(2, '0') + ':' + 
                seconds.toString().padStart(2, '0');
        },
        
        // Save timer state to session storage
        saveState: function() {
            const state = {
                taskId: this.taskId,
                isRunning: this.isRunning,
                startTime: this.startTime,
                elapsedTime: this.elapsedTime
            };
            
            sessionStorage.setItem('timeTracker', JSON.stringify(state));
        },
        
        // Load timer state from session storage
        loadState: function() {
            const stateJson = sessionStorage.getItem('timeTracker');
            if (!stateJson) return null;
            
            try {
                const state = JSON.parse(stateJson);
                
                // Only load state if it's for the same task
                if (state.taskId === this.taskId) {
                    return state;
                }
                
                return null;
            } catch (e) {
                return null;
            }
        }
    };
    
    /**
     * Global AJAX loading indicator
     */
    window.showLoading = function() {
        // Check if loading indicator already exists
        let loadingIndicator = document.querySelector('.ajax-loading-indicator');
        
        if (!loadingIndicator) {
            loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'ajax-loading-indicator';
            loadingIndicator.innerHTML = `
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            `;
            
            // Style the loading indicator
            loadingIndicator.style.position = 'fixed';
            loadingIndicator.style.top = '50%';
            loadingIndicator.style.left = '50%';
            loadingIndicator.style.transform = 'translate(-50%, -50%)';
            loadingIndicator.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
            loadingIndicator.style.padding = '2rem';
            loadingIndicator.style.borderRadius = '0.5rem';
            loadingIndicator.style.zIndex = '9999';
            
            document.body.appendChild(loadingIndicator);
        } else {
            loadingIndicator.style.display = 'block';
        }
    };
    
    window.hideLoading = function() {
        const loadingIndicator = document.querySelector('.ajax-loading-indicator');
        
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }
    };
    
    // Set up global AJAX loading indicator
    if (window.jQuery) {
        $(document).ajaxStart(function() {
            window.showLoading();
        });
        
        $(document).ajaxStop(function() {
            window.hideLoading();
        });
    }
    
    // Expose global utilities
    window.Backsure = {
        // Format date helper
        formatDate: function(dateString, format) {
            const date = new Date(dateString);
            
            if (isNaN(date.getTime())) {
                return dateString;
            }
            
            if (format === 'short') {
                return date.toLocaleDateString();
            } else if (format === 'long') {
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
            } else {
                return date.toLocaleDateString();
            }
        },
        
        // Format file size helper
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        // Show notification helper
        showNotification: function(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast bg-${type} text-white`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="toast-header bg-${type} text-white">
                    <strong class="me-auto">Notification</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;
            
            const toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                const container = document.createElement('div');
                container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                document.body.appendChild(container);
                container.appendChild(toast);
            } else {
                toastContainer.appendChild(toast);
            }
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        }
    };
})();