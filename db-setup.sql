-- Create database if not exists
CREATE DATABASE IF NOT EXISTS backsure_admin;
USE backsure_admin;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'client', 'employee') NOT NULL,
    avatar VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    remember_token VARCHAR(255),
    token_expiry DATETIME,
    login_attempts INT DEFAULT 0,
    last_attempt_time DATETIME,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);

-- Roles Table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);

-- Permissions Table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Role Permissions Mapping
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Password Resets
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (email, token)
);

-- Activity Logging
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(50),
    action_type VARCHAR(30) NOT NULL,
    resource VARCHAR(50),
    resource_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id, action_type)
);

-- Insert default roles
INSERT INTO roles (name, description) VALUES 
('admin', 'Full access to all dashboards and features'),
('client', 'Task uploads, plan view, insights, support'),
('employee', 'Assigned task access, time logs, uploads');

-- Insert basic permissions
INSERT INTO permissions (name, description) VALUES
('manage_users', 'Can create, edit and delete users'),
('view_dashboard', 'Can view dashboard'),
('manage_content', 'Can manage content'),
('manage_settings', 'Can manage system settings'),
('view_reports', 'Can view reports'),
('manage_tasks', 'Can manage tasks'),
('manage_support', 'Can manage support tickets'),
('manage_plans', 'Can manage business plans'),
('manage_time', 'Can manage time tracking');

-- Assign all permissions to admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin';

-- Assign client permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'client' AND p.name IN ('view_dashboard', 'manage_tasks', 'manage_support');

-- Assign employee permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'employee' AND p.name IN ('view_dashboard', 'manage_tasks', 'manage_time', 'manage_support');

-- Create default admin user (password: admin123)
INSERT INTO users (username, password, email, name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@backsureglobalsupport.com', 'Admin User', 'admin');

-- Settings Table for Centralized Configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value LONGTEXT,
    setting_type ENUM('text', 'textarea', 'boolean', 'image', 'file', 'json') NOT NULL DEFAULT 'text',
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
    setting_label VARCHAR(100) NOT NULL,
    setting_description TEXT,
    autoload BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);

-- Create Media Table for File/Image Uploads
CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, setting_label, setting_description, autoload) VALUES
-- General Settings
('site_name', 'Backsure Global Support', 'text', 'general', 'Site Name', 'Name of the website', TRUE),
('site_description', 'Your trusted global support partner', 'textarea', 'general', 'Site Description', 'Brief description of the site', TRUE),
('logo', '', 'image', 'general', 'Site Logo', 'Main logo (recommended size: 200x50px)', TRUE),
('logo_white', '', 'image', 'general', 'White Logo', 'White version for dark backgrounds', TRUE),
('favicon', '', 'image', 'general', 'Favicon', 'Small icon shown in browser tabs (recommended: 32x32px)', TRUE),
('timezone', 'UTC', 'text', 'general', 'Default Timezone', 'Default timezone for the application', TRUE),
('date_format', 'Y-m-d', 'text', 'general', 'Date Format', 'PHP date format for displaying dates', TRUE),
('time_format', 'H:i', 'text', 'general', 'Time Format', 'PHP time format for displaying times', TRUE),

-- SEO Settings
('meta_title', 'Backsure Global Support | Your Trusted Partner', 'text', 'seo', 'Meta Title', 'Title shown in search results', TRUE),
('meta_description', 'Backsure Global Support provides dedicated employee, on-demand services, and business care plans for global businesses', 'textarea', 'seo', 'Meta Description', 'Description shown in search results', TRUE),
('meta_keywords', 'global support, outsourcing, business services', 'text', 'seo', 'Meta Keywords', 'Keywords for search engines', TRUE),
('google_analytics', '', 'text', 'seo', 'Google Analytics ID', 'Google Analytics tracking ID (e.g., UA-XXXXX-Y)', FALSE),
('social_image', '', 'image', 'seo', 'Social Share Image', 'Image used when sharing on social media', FALSE),

-- Email Settings
('email_sender_name', 'Backsure Global Support', 'text', 'email', 'Sender Name', 'Name that appears in the From field', TRUE),
('email_sender_email', 'noreply@backsureglobalsupport.com', 'text', 'email', 'Sender Email', 'Email address that appears in the From field', TRUE),
('smtp_host', 'smtp.zoho.com', 'text', 'email', 'SMTP Host', 'SMTP server hostname', FALSE),
('smtp_port', '465', 'text', 'email', 'SMTP Port', 'SMTP server port', FALSE),
('smtp_username', '', 'text', 'email', 'SMTP Username', 'SMTP authentication username', FALSE),
('smtp_password', '', 'text', 'email', 'SMTP Password', 'SMTP authentication password', FALSE),
('smtp_encryption', 'ssl', 'text', 'email', 'SMTP Encryption', 'SMTP encryption method (ssl, tls)', FALSE),
('use_smtp', 'false', 'boolean', 'email', 'Use SMTP', 'Enable SMTP for sending emails', FALSE),

-- Email Templates
('password_reset_template', '<h1>Password Reset</h1><p>Hello {{name}},</p><p>You have requested to reset your password. Click the link below to reset it:</p><p><a href="{{reset_link}}">Reset Password</a></p><p>This link will expire in 1 hour.</p><p>If you did not request a password reset, please ignore this email.</p><p>Regards,<br>{{site_name}}</p>', 'textarea', 'email_templates', 'Password Reset Template', 'Email template for password reset', FALSE),
('welcome_template', '<h1>Welcome to {{site_name}}</h1><p>Hello {{name}},</p><p>Your account has been created successfully. Here are your login details:</p><p>Username: {{username}}<br>Password: {{password}}</p><p>Please login and change your password.</p><p>Regards,<br>{{site_name}}</p>', 'textarea', 'email_templates', 'Welcome Email Template', 'Email template for new user welcome', FALSE),
('inquiry_template', '<h1>Thank You for Your Inquiry</h1><p>Hello {{name}},</p><p>We have received your inquiry and will get back to you shortly.</p><p>Inquiry details:</p><p>Subject: {{subject}}<br>Message: {{message}}</p><p>Regards,<br>{{site_name}}</p>', 'textarea', 'email_templates', 'Inquiry Confirmation Template', 'Email template for inquiry confirmation', FALSE),
('task_submission_template', '<h1>Task Submitted Successfully</h1><p>Hello {{name}},</p><p>Your task "{{task_title}}" has been submitted successfully and is being processed.</p><p>Task ID: {{task_id}}</p><p>You will be notified when the task is completed.</p><p>Regards,<br>{{site_name}}</p>', 'textarea', 'email_templates', 'Task Submission Template', 'Email template for task submission confirmation', FALSE),
('task_completion_template', '<h1>Task Completed</h1><p>Hello {{name}},</p><p>Your task "{{task_title}}" has been completed.</p><p>Task ID: {{task_id}}</p><p>Please login to your dashboard to view the results.</p><p>Regards,<br>{{site_name}}</p>', 'textarea', 'email_templates', 'Task Completion Template', 'Email template for task completion notification', FALSE),

-- Notification Settings
('enable_email_notifications', 'true', 'boolean', 'notifications', 'Enable Email Notifications', 'Send email notifications for system events', TRUE),
('enable_task_notifications', 'true', 'boolean', 'notifications', 'Task Notifications', 'Send notifications for task status changes', TRUE),
('enable_support_notifications', 'true', 'boolean', 'notifications', 'Support Ticket Notifications', 'Send notifications for support ticket updates', TRUE),
('notification_popup_duration', '5000', 'text', 'notifications', 'Popup Duration', 'Duration in milliseconds to show popup notifications', TRUE),

-- Integration Settings
('zoho_api_key', '', 'text', 'integrations', 'Zoho API Key', 'API key for Zoho integration', FALSE),
('tally_api_key', '', 'text', 'integrations', 'Tally API Key', 'API key for Tally integration', FALSE),
('azure_connection_string', '', 'text', 'integrations', 'Azure Connection String', 'Connection string for Azure Blob Storage', FALSE),
('azure_container_name', '', 'text', 'integrations', 'Azure Container Name', 'Container name for Azure Blob Storage', FALSE),

-- Chat Settings
('chat_enabled', 'false', 'boolean', 'chat', 'Enable Chat', 'Enable chat functionality on the website', TRUE),
('chat_provider', 'custom', 'text', 'chat', 'Chat Provider', 'Provider for chat functionality (custom, intercom, crisp)', TRUE),
('chat_welcome_message', 'Welcome to Backsure Global Support. How can we help you today?', 'textarea', 'chat', 'Welcome Message', 'Welcome message shown to users', TRUE),
('chat_bot_name', 'Backsure Assistant', 'text', 'chat', 'Bot Name', 'Name of the chat bot', TRUE),
('intercom_app_id', '', 'text', 'chat', 'Intercom App ID', 'App ID for Intercom chat integration', FALSE),
('crisp_website_id', '', 'text', 'chat', 'Crisp Website ID', 'Website ID for Crisp chat integration', FALSE);
