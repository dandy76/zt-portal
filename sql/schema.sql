-- Zero Trust Portal Database Schema

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100),
    wireguard_ip VARCHAR(15) NOT NULL COMMENT 'Fixed WG IP e.g. 10.1.40.10',
    totp_secret VARCHAR(64) DEFAULT NULL,
    totp_enabled TINYINT(1) DEFAULT 0,
    role ENUM('user', 'admin') DEFAULT 'user',
    enabled TINYINT(1) DEFAULT 1,
    allowed_source_ips TEXT DEFAULT NULL COMMENT 'JSON array of additional allowed IPs/subnets besides WG IP',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Network resources that can be accessed
CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Human name e.g. "RDP Server"',
    description TEXT,
    address_list_name VARCHAR(50) NOT NULL COMMENT 'MikroTik address-list name e.g. auth_rdp',
    dst_address VARCHAR(50) NOT NULL COMMENT 'Destination IP/subnet e.g. 10.0.50.0/24',
    dst_port VARCHAR(50) NOT NULL COMMENT 'Port(s) e.g. 3389 or 445,139',
    protocol ENUM('tcp', 'udp', 'tcp+udp') DEFAULT 'tcp',
    timeout_minutes INT DEFAULT 10 COMMENT 'Address-list timeout in minutes',
    enabled TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Many-to-many: which user can access which resource
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    resource_id INT NOT NULL,
    granted_by INT COMMENT 'admin user_id who granted this',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
    UNIQUE KEY unique_perm (user_id, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Active sessions tracking
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    resource_id INT NOT NULL,
    source_ip VARCHAR(45) NOT NULL COMMENT 'The IP that was added to address-list',
    mikrotik_list_name VARCHAR(50) NOT NULL,
    granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    last_keepalive DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'expired', 'killed') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (resource_id) REFERENCES resources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL COMMENT 'login, login_fail, 2fa_fail, grant, revoke, keepalive, kill, admin_*',
    details TEXT COMMENT 'JSON with context',
    source_ip VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- App settings (key-value)
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('totp_issuer', 'ZT-Portal'),
('portal_title', 'ZT Portal'),
('session_timeout', '1800'),
('default_timeout_minutes', '10');

-- Rate limiting table
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempt_type VARCHAR(20) NOT NULL DEFAULT 'login',
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_type (ip_address, attempt_type),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
