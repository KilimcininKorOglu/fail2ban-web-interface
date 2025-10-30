-- Centralized Fail2Ban Multi-Server Database Schema
-- This allows multiple independent fail2ban servers to share data via MySQL

CREATE DATABASE IF NOT EXISTS fail2ban_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fail2ban_central;

-- Servers table: Track all fail2ban servers
CREATE TABLE IF NOT EXISTS servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_name VARCHAR(100) NOT NULL UNIQUE,
    server_ip VARCHAR(45) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    last_sync TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_server_name (server_name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jails table: Track jails across all servers
CREATE TABLE IF NOT EXISTS jails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    jail_name VARCHAR(100) NOT NULL,
    findtime INT DEFAULT 600,
    bantime INT DEFAULT 3600,
    maxretry INT DEFAULT 5,
    is_active TINYINT(1) DEFAULT 1,
    last_sync TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_jail (server_id, jail_name),
    INDEX idx_server_jail (server_id, jail_name),
    INDEX idx_jail_name (jail_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Banned IPs table: Central repository of all banned IPs
CREATE TABLE IF NOT EXISTS banned_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    jail_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    hostname VARCHAR(255) NULL,
    country VARCHAR(100) NULL,
    ban_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unban_time TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    ban_reason VARCHAR(255) NULL,
    ban_count INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    FOREIGN KEY (jail_id) REFERENCES jails(id) ON DELETE CASCADE,
    INDEX idx_ip_address (ip_address),
    INDEX idx_server_id (server_id),
    INDEX idx_jail_id (jail_id),
    INDEX idx_is_active (is_active),
    INDEX idx_ban_time (ban_time),
    INDEX idx_ip_active (ip_address, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Global ban list: IPs that should be banned across ALL servers
CREATE TABLE IF NOT EXISTS global_bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    hostname VARCHAR(255) NULL,
    country VARCHAR(100) NULL,
    reason TEXT NOT NULL,
    banned_by VARCHAR(100) NOT NULL,
    ban_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    permanent TINYINT(1) DEFAULT 0,
    expires_at TIMESTAMP NULL,
    INDEX idx_ip_address (ip_address),
    INDEX idx_is_active (is_active),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log: Track all ban/unban actions
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NULL,
    action_type ENUM('ban', 'unban', 'global_ban', 'global_unban') NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    jail_name VARCHAR(100) NULL,
    performed_by VARCHAR(100) NOT NULL,
    action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    details TEXT NULL,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE SET NULL,
    INDEX idx_action_time (action_time),
    INDEX idx_ip_address (ip_address),
    INDEX idx_action_type (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Statistics table: Aggregated statistics per server
CREATE TABLE IF NOT EXISTS statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    date DATE NOT NULL,
    total_bans INT DEFAULT 0,
    total_unbans INT DEFAULT 0,
    unique_ips INT DEFAULT 0,
    active_jails INT DEFAULT 0,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_stat (server_id, date),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User preferences (for web interface)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    role ENUM('admin', 'viewer') DEFAULT 'viewer',
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data
INSERT INTO servers (server_name, server_ip, description) VALUES
('web-server-1', '192.168.1.10', 'Main web server'),
('mail-server-1', '192.168.1.11', 'Mail server'),
('db-server-1', '192.168.1.12', 'Database server')
ON DUPLICATE KEY UPDATE server_ip=VALUES(server_ip);
