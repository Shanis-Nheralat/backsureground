-- Use existing database instead of creating a new one
USE backzvsg_crm;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'client', 'employee', 'superadmin') NOT NULL,
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
-- Modified to match the actual implementation in admin-auth.php
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action_type VARCHAR(30) NOT NULL,
    action_details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Settings Table for Phase 2
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

-- Media Table for File/Image Uploads (Phase 2)
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

-- Insert default roles
INSERT INTO roles (name, description) VALUES 
('admin', 'Full access to all dashboards and features'),
('client', 'Task uploads, plan view, insights, support'),
('employee', 'Assigned task access, time logs, uploads'),
('superadmin', 'Super administrator with access to all features');

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

-- Assign all permissions to admin and superadmin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name IN ('admin', 'superadmin');

-- Assign client permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'client' AND p.name IN ('view_dashboard', 'manage_tasks', 'manage_support');

-- Assign employee permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'employee' AND p.name IN ('view_dashboard', 'manage_tasks', 'manage_time', 'manage_support');

-- Insert the existing admin users
INSERT INTO users (username, email, password, name, role, status, created_at) VALUES
(
  'shanisbsg',
  'shani@backsureglobalsupport.com',
  '$2y$10$mhMnb9OEd/gclyBE3s1jQuZC4Fdb5NxiM0Ee8DwR8nkZz7uzKWU.C',  -- Admin@123
  'Shanis BSG',
  'admin',
  'active',
  NOW()
),
(
  'superadmin',
  'super@backsureglobalsupport.com',
  '$2y$10$V9dp94iCrxkULYT6YlxiHeMKttFbZVXwRxU8vlZTKJY/hkT78DNrm',  -- Super@123
  'Super Admin',
  'superadmin',
  'active',
  NOW()
);

-- Insert Default Settings (from Phase 2)
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
('meta_keywords', 'global support, outsourcing, business services', 'text', 'seo', 'Meta Keywords', 'Keywords for search engines', TRUE);
