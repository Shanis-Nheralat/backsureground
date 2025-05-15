-- ============================================================================
-- Backsure Global Support - Full Database Schema
-- Generated on: 2025-05-15
-- ============================================================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS backsure_admin;
USE backsure_admin;

-- ========================
-- Phase 1: Core System
-- ========================

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

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (email, token)
);

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

INSERT INTO roles (name, description) VALUES 
('admin', 'Full access to all dashboards and features'),
('client', 'Task uploads, plan view, insights, support'),
('employee', 'Assigned task access, time logs, uploads');

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

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'client' AND p.name IN ('view_dashboard', 'manage_tasks', 'manage_support');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'employee' AND p.name IN ('view_dashboard', 'manage_tasks', 'manage_time', 'manage_support');

INSERT INTO users (username, password, email, name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@backsureglobalsupport.com', 'Admin User', 'admin');

-- ========================
-- Phase 2: Settings & Media
-- ========================

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

-- (Settings INSERT statements skipped here to keep preview short — already shared earlier)

-- ========================
-- Phase 3: Media Library
-- ========================

CREATE TABLE media_library (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    filetype VARCHAR(50) NOT NULL,
    filesize INT NOT NULL,
    uploaded_by INT NOT NULL,
    uploader_role ENUM('admin','client','employee') NOT NULL,
    client_id INT DEFAULT NULL,
    tags TEXT NULL,
    folder VARCHAR(255) DEFAULT '/',
    metadata JSON NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    INDEX (client_id),
    INDEX (uploaded_by),
    INDEX (folder),
    INDEX (filetype),
    INDEX (is_deleted)
);

-- ========================
-- Phase 4: CRM, Azure, Logs
-- ========================

CREATE TABLE employee_client_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    client_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_assignment (employee_id, client_id),
    INDEX (employee_id),
    INDEX (client_id)
);

CREATE TABLE client_crm_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL UNIQUE,
    crm_type ENUM('zoho', 'dynamics', 'salesforce', 'custom') NOT NULL,
    crm_identifier VARCHAR(255) NOT NULL,
    crm_url VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NULL,
    refresh_token VARCHAR(255) NULL,
    token_expiry DATETIME NULL,
    last_sync DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    INDEX (client_id)
);

CREATE TABLE azure_blob_containers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    container_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_container (client_id, container_name),
    INDEX (client_id)
);

CREATE TABLE client_access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_role ENUM('admin', 'employee') NOT NULL,
    client_id INT NOT NULL,
    access_type VARCHAR(50) NOT NULL,
    resource_type VARCHAR(50) NOT NULL,
    resource_id VARCHAR(50) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (client_id),
    INDEX (created_at)
);

-- ========================
-- Phase 5: On-Demand Tasks
-- ========================

CREATE TABLE on_demand_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    deadline DATE,
    status ENUM('submitted','in_progress','completed','cancelled') DEFAULT 'submitted',
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    admin_notes TEXT
);

CREATE TABLE task_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    uploaded_by ENUM('client','admin') NOT NULL,
    file_name VARCHAR(255),
    file_path VARCHAR(255),
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE task_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    status ENUM('submitted','in_progress','completed','cancelled'),
    updated_by INT,
    role ENUM('client', 'admin'),
    remarks TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE on_demand_tasks
ADD CONSTRAINT fk_client_id FOREIGN KEY (client_id) REFERENCES users(id);

ALTER TABLE task_uploads
ADD CONSTRAINT fk_task_id FOREIGN KEY (task_id) REFERENCES on_demand_tasks(id) ON DELETE CASCADE;

ALTER TABLE task_logs
ADD CONSTRAINT fk_task_logs_task_id FOREIGN KEY (task_id) REFERENCES on_demand_tasks(id) ON DELETE CASCADE;

-- ========================
-- Phase 6: Business Care Plans
-- ========================
-- (Tables for plan_tiers, services, subscriptions, documents, insights, integrations)
-- Already provided and formatted – would continue here...

-- ========================
-- Phase 7: Notifications & Email
-- ========================

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_role ENUM('admin', 'client', 'employee') NOT NULL,
  type ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
  title VARCHAR(100) NOT NULL,
  message TEXT NOT NULL,
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  metadata JSON DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
  INDEX (user_id, user_role, is_read),
  INDEX (created_at)
);

CREATE TABLE email_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_key VARCHAR(100) NOT NULL UNIQUE,
  template_name VARCHAR(100) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  description TEXT,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
  INDEX (event_key)
);

CREATE TABLE email_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_key VARCHAR(100) NOT NULL,
  recipient VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT,
  status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
  error_message TEXT,
  metadata JSON DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
  INDEX (event_key),
  INDEX (recipient),
  INDEX (status)
);

-- ========================
-- Phase 8: Time Tracking
-- ========================

CREATE TABLE time_log_activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  is_billable TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE employee_time_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  client_id INT DEFAULT NULL,
  task_id INT DEFAULT NULL,
  activity_id INT DEFAULT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME DEFAULT NULL,
  duration_minutes INT DEFAULT NULL COMMENT 'For manual entries without start/end',
  description TEXT,
  is_manual_entry TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
  INDEX (employee_id),
  INDEX (client_id),
  INDEX (task_id),
  INDEX (activity_id),
  INDEX (start_time),
  INDEX (end_time)
);

-- ========================
-- Phase 9: Support Desk
-- ========================

CREATE TABLE support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_role ENUM('client', 'employee', 'admin') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'closed', 'cancelled') DEFAULT 'open',
    assigned_to INT DEFAULT NULL,
    last_reply_by INT DEFAULT NULL,
    last_reply_role ENUM('client', 'employee', 'admin') DEFAULT NULL,
    last_reply_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    INDEX (user_id, user_role),
    INDEX (status),
    INDEX (assigned_to),
    INDEX (created_at)
);

CREATE TABLE support_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_role ENUM('client', 'employee', 'admin') NOT NULL,
    message TEXT NOT NULL,
    is_internal_note TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    INDEX (ticket_id, created_at)
);

CREATE TABLE support_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reply_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reply_id) REFERENCES support_replies(id) ON DELETE CASCADE,
    INDEX (reply_id)
);
