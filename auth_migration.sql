-- Authentication System Database Migration
-- Simple Car Rental ERP - Phase 1: Database Schema Implementation
-- Created: 2025-07-24

USE car_rental_erp;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    must_change_password BOOLEAN DEFAULT FALSE,
    password_reset_token VARCHAR(255) NULL,
    password_reset_expires DATETIME NULL,
    last_login DATETIME NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_email (email),
    INDEX idx_active (is_active),
    INDEX idx_reset_token (password_reset_token)
);

-- Create roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_name (name),
    INDEX idx_active (is_active)
);

-- Create user_roles junction table
CREATE TABLE IF NOT EXISTS user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT NULL,
    UNIQUE KEY unique_user_role (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_role_id (role_id)
);

-- Create screens table
CREATE TABLE IF NOT EXISTS screens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    url_pattern VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active),
    INDEX idx_sort_order (sort_order)
);

-- Create role_permissions junction table
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    screen_id INT NOT NULL,
    can_view BOOLEAN DEFAULT TRUE,
    can_create BOOLEAN DEFAULT FALSE,
    can_edit BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    UNIQUE KEY unique_role_screen (role_id, screen_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (screen_id) REFERENCES screens(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_role_id (role_id),
    INDEX idx_screen_id (screen_id)
);

-- Create user_sessions table
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_user_expires (user_id, expires_at),
    INDEX idx_active (is_active)
);

-- Insert initial roles
INSERT IGNORE INTO roles (name, description) VALUES 
('Super Admin', 'Full system access with all permissions'),
('Manager', 'Operational access to all business functions'),
('Staff', 'Limited access to daily operations'),
('Viewer', 'Read-only access to system data');

-- Insert initial screens
INSERT IGNORE INTO screens (name, display_name, description, url_pattern, sort_order) VALUES 
('dashboard', 'Dashboard', 'System overview and statistics', '/?page=dashboard', 1),
('vehicles', 'Vehicles', 'Vehicle management and inventory', '/?page=vehicles', 2),
('customers', 'Customers', 'Customer database management', '/?page=customers', 3),
('reservations', 'Reservations', 'Rental booking management', '/?page=reservations', 4),
('maintenance', 'Maintenance', 'Vehicle maintenance scheduling', '/?page=maintenance', 5),
('users', 'Users', 'User account management', '/?page=users', 6),
('roles', 'Roles', 'Role and permission management', '/?page=roles', 7);

-- Create Super Admin user (password will be set separately)
-- Note: This will be updated with actual password hash in the next step
INSERT IGNORE INTO users (email, password_hash, first_name, last_name, is_active) 
VALUES ('david@infiniteautomanagement.com', 'TEMP_HASH_TO_BE_REPLACED', 'David', 'Administrator', TRUE);

-- Assign Super Admin role to the admin user
INSERT IGNORE INTO user_roles (user_id, role_id) 
SELECT u.id, r.id 
FROM users u, roles r 
WHERE u.email = 'david@infiniteautomanagement.com' 
AND r.name = 'Super Admin';

-- Grant all permissions to Super Admin role
INSERT IGNORE INTO role_permissions (role_id, screen_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, s.id, TRUE, TRUE, TRUE, TRUE
FROM roles r, screens s
WHERE r.name = 'Super Admin';

-- Grant operational permissions to Manager role
INSERT IGNORE INTO role_permissions (role_id, screen_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, s.id, TRUE, TRUE, TRUE, FALSE
FROM roles r, screens s
WHERE r.name = 'Manager' 
AND s.name IN ('dashboard', 'vehicles', 'customers', 'reservations', 'maintenance');

-- Grant limited permissions to Staff role
INSERT IGNORE INTO role_permissions (role_id, screen_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, s.id, TRUE, TRUE, FALSE, FALSE
FROM roles r, screens s
WHERE r.name = 'Staff' 
AND s.name IN ('dashboard', 'vehicles', 'customers', 'reservations');

-- Grant read-only permissions to Viewer role
INSERT IGNORE INTO role_permissions (role_id, screen_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, s.id, TRUE, FALSE, FALSE, FALSE
FROM roles r, screens s
WHERE r.name = 'Viewer' 
AND s.name IN ('dashboard', 'vehicles', 'customers', 'reservations', 'maintenance');

-- Display migration results
SELECT 'Migration completed successfully' as status;
SELECT COUNT(*) as users_count FROM users;
SELECT COUNT(*) as roles_count FROM roles;
SELECT COUNT(*) as screens_count FROM screens;
SELECT COUNT(*) as permissions_count FROM role_permissions;

