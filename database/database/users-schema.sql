-- Create users table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(100),
  role ENUM('superadmin', 'admin', 'hr', 'marketing', 'support', 'author', 'user') NOT NULL DEFAULT 'user',
  status ENUM('active', 'inactive') DEFAULT 'active',
  avatar VARCHAR(255) DEFAULT NULL,
  remember_token VARCHAR(255) DEFAULT NULL,
  token_expiry DATETIME DEFAULT NULL,
  login_attempts INT DEFAULT 0,
  last_attempt_time DATETIME DEFAULT NULL,
  last_login DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
