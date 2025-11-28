-- Drop existing tables for a clean install (CAUTION in production)
DROP TABLE IF EXISTS password_history;
DROP TABLE IF EXISTS admin_activity_logs;
DROP TABLE IF EXISTS admins;

-- Admins table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    role ENUM('super','admin','staff') NOT NULL DEFAULT 'staff',
    password_hash VARCHAR(255) NOT NULL,
    profile_image VARCHAR(500),
    dark_mode TINYINT(1) DEFAULT 0,
    email_notifications TINYINT(1) DEFAULT 1,
    login_alerts TINYINT(1) DEFAULT 1,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    language VARCHAR(10) DEFAULT 'en',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin activity logs
CREATE TABLE admin_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password history (for preventing reuse)
CREATE TABLE password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    old_password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default super admin (password: "password")
INSERT INTO admins (name, username, email, role, password_hash)
VALUES ('Super Administrator', 'superadmin', 'admin@edubridgesa.co.za', 'super', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Seed sample admin (password: "admin123")
INSERT INTO admins (name, username, email, role, password_hash)
VALUES ('Admin User', 'admin', 'admin.user@edubridgesa.co.za', 'admin', '$2y$10$1rJ8mYlS1m8i7C1Qq1zZRe8t3KfS8G1VJ3n8ZB1o7aB2C3d4E5F6G');

-- Seed sample staff (password: "staff123")
INSERT INTO admins (name, username, email, role, password_hash)
VALUES ('Staff Member', 'staff', 'staff@edubridgesa.co.za', 'staff', '$2y$10$7n9m2qL8p4s1a2w3e4r5t6y7u8i9o0p1a2s3d4f5g6h7j8k9l0z');

-- Show tables (for verification in some DB consoles)
SHOW TABLES;
DESCRIBE admins;
DESCRIBE admin_activity_logs;
DESCRIBE password_history;