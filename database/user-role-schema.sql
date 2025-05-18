-- User and Role Management Schema

-- Create users table if it doesn't exist
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('bsg', 'client') NOT NULL DEFAULT 'bsg',
    role_id INT DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    job_title VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    status ENUM('active', 'inactive', 'pending', 'suspended') DEFAULT 'pending',
    password_reset_required BOOLEAN DEFAULT TRUE,
    avatar VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    login_attempts INT DEFAULT 0,
    last_attempt_time DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    INDEX (role_id),
    INDEX (user_type),
    INDEX (status),
    INDEX (created_at)
);

-- Create roles table if it doesn't exist
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    type ENUM('bsg', 'client') NOT NULL DEFAULT 'bsg',
    permissions JSON DEFAULT NULL,
    permissionLevel ENUM('Limited', 'Standard', 'Admin', 'Super Admin') DEFAULT 'Limited',
    isDefault BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    INDEX (type),
    INDEX (status),
    INDEX (isDefault)
);

-- Create permissions table for future granular permission management
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(50) DEFAULT 'general',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    INDEX (category)
);

-- Add foreign key constraint to users table if not already exists
ALTER TABLE users
ADD CONSTRAINT fk_user_role
FOREIGN KEY (role_id) REFERENCES roles(id)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- Predefined roles
INSERT INTO roles (name, description, type, permissionLevel, isDefault, status)
VALUES 
('Administrator', 'Full system access with all permissions', 'bsg', 'Super Admin', false, 'active'),
('HR Admin', 'Can manage users and roles, but not system settings', 'bsg', 'Admin', false, 'active'),
('Standard User', 'Regular BSG employee with standard access', 'bsg', 'Standard', true, 'active'),
('Limited User', 'Limited access BSG user', 'bsg', 'Limited', false, 'active'),
('Client Admin', 'Client with administrative access', 'client', 'Admin', false, 'active'),
('Client User', 'Standard client user', 'client', 'Standard', true, 'active'),
('Guest', 'Limited access guest account', 'client', 'Limited', false, 'active');

-- Set default permissions for roles (as JSON)
UPDATE roles SET permissions = '{"users": {"view": true, "create": true, "edit": true, "delete": true}, "roles": {"view": true, "create": true, "edit": true, "delete": true}, "settings": {"view": true, "edit": true}}' WHERE name = 'Administrator';
UPDATE roles SET permissions = '{"users": {"view": true, "create": true, "edit": true, "delete": false}, "roles": {"view": true, "create": false, "edit": false, "delete": false}}' WHERE name = 'HR Admin';
UPDATE roles SET permissions = '{"users": {"view": true, "create": false, "edit": false, "delete": false}, "roles": {"view": true, "create": false, "edit": false, "delete": false}}' WHERE name = 'Standard User';