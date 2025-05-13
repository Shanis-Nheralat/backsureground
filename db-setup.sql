-- Create database if not exists
CREATE DATABASE IF NOT EXISTS backzvsg_crm;
USE backzvsg_crm;

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
