/**
 * AKABBO SOCIAL FUND - Branding Settings Migration
 * Adds logo and favicon configuration to settings table
 * 
 * Run: mysql -u root -p akabbo_fund < database/migration_branding.sql
 */

-- Add branding settings if not exists
INSERT IGNORE INTO `settings` (`key`, `description`, `value`, `type`, `group`) VALUES
('org_logo', 'Organization Logo (stored in storage/uploads/logos/)', '', 'file', 'general'),
('org_favicon', 'Organization Favicon/Icon (stored in storage/uploads/logos/)', '', 'file', 'general');

-- Ensure logos directory exists (applications should create at runtime)
-- ALTER TABLE settings ADD COLUMN logo_updated_at TIMESTAMP DEFAULT NULL AFTER org_logo;

-- Add a reference table for logo versions (optional, for future versioning)
CREATE TABLE IF NOT EXISTS `organization_logos` (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('logo', 'favicon', 'print-header') NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    mime_type VARCHAR(50),
    file_size INT,
    dimensions VARCHAR(50),
    is_active BOOLEAN DEFAULT true,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_type_active (type, is_active),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Version control for audit trail
CREATE TABLE IF NOT EXISTS `logo_audit` (
    id INT PRIMARY KEY AUTO_INCREMENT,
    logo_id INT,
    action VARCHAR(50),
    old_value TEXT,
    new_value TEXT,
    changed_by INT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (logo_id) REFERENCES organization_logos(id),
    FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
