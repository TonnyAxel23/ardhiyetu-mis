-- Create Users Table 
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    id_number VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other', 'prefer_not_to_say'),
    address TEXT,
    county VARCHAR(100),
    security_question VARCHAR(255),
    security_answer VARCHAR(255),
    verification_token VARCHAR(64),
    is_active BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_date TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    failed_attempts INT DEFAULT 0,
    remember_token VARCHAR(64),
    newsletter_subscribed BOOLEAN DEFAULT TRUE,
    marketing_consent BOOLEAN DEFAULT FALSE,
    role ENUM('admin', 'officer', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_id_number (id_number),
    INDEX idx_county (county),
    INDEX idx_role (role),
    INDEX idx_status (is_active, is_verified)
);

-- Create User Profiles Table 
CREATE TABLE IF NOT EXISTS user_profiles (
    profile_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    profile_picture VARCHAR(255),
    occupation VARCHAR(100),
    education_level ENUM('primary', 'secondary', 'diploma', 'degree', 'masters', 'phd', 'other'),
    marital_status ENUM('single', 'married', 'divorced', 'widowed'),
    nationality VARCHAR(50) DEFAULT 'Kenyan',
    postal_address VARCHAR(255),
    next_of_kin_name VARCHAR(100),
    next_of_kin_phone VARCHAR(15),
    next_of_kin_relationship VARCHAR(50),
    emergency_contact VARCHAR(15),
    preferred_language ENUM('en', 'sw') DEFAULT 'en',
    theme_preference ENUM('light', 'dark', 'system') DEFAULT 'light',
    email_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT TRUE,
    two_factor_auth BOOLEAN DEFAULT FALSE,
    last_profile_update TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Create Email Verification Table
CREATE TABLE IF NOT EXISTS email_verifications (
    verification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
);

-- Create Login History Table
CREATE TABLE IF NOT EXISTS login_history (
    login_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    location VARCHAR(100),
    device_type VARCHAR(50),
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    success BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_login (user_id, login_time),
    INDEX idx_login_time (login_time)
);

-- Create Security Log Table
CREATE TABLE IF NOT EXISTS security_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action_type VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details TEXT,
    status ENUM('success', 'failed', 'warning') DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_action (user_id, action_type),
    INDEX idx_created_at (created_at)
);

-- Create Newsletter Subscribers Table
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    subscriber_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(100),
    user_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at TIMESTAMP NULL,
    preferences JSON,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_user_id (user_id),
    INDEX idx_active (is_active)
);

-- Create User Preferences Table
CREATE TABLE IF NOT EXISTS user_preferences (
    preference_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    preference_key VARCHAR(50) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_preference (user_id, preference_key),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_key (user_id, preference_key)
);

-- Create Password Reset Tokens Table
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
);

-- Create User Documents Table (for ID copies, photos, etc.)
CREATE TABLE IF NOT EXISTS user_documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    document_type ENUM('id_front', 'id_back', 'passport', 'photo', 'signature', 'other'),
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255),
    file_size INT,
    mime_type VARCHAR(50),
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by INT NULL,
    verification_date TIMESTAMP NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id),
    INDEX idx_user_docs (user_id, document_type),
    INDEX idx_verified (is_verified)
);

-- ============================================
-- EXISTING TABLES (Updated with Foreign Keys)
-- ============================================

-- Create Land Records Table
CREATE TABLE IF NOT EXISTS land_records (
    record_id INT PRIMARY KEY AUTO_INCREMENT,
    owner_id INT NOT NULL,
    parcel_no VARCHAR(50) UNIQUE NOT NULL,
    title_deed_no VARCHAR(50) UNIQUE,
    location VARCHAR(255) NOT NULL,
    county VARCHAR(100),
    size DECIMAL(10,2) NOT NULL,
    size_unit ENUM('acres', 'hectares', 'square_meters') DEFAULT 'acres',
    land_use ENUM('agricultural', 'residential', 'commercial', 'industrial', 'mixed'),
    land_class VARCHAR(50),
    status ENUM('active', 'pending', 'transferred', 'disputed', 'archived') DEFAULT 'pending',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    registered_by INT,
    notes TEXT,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (registered_by) REFERENCES users(user_id),
    INDEX idx_owner (owner_id),
    INDEX idx_parcel (parcel_no),
    INDEX idx_location (location),
    INDEX idx_status (status)
);

-- Create Ownership Transfers Table
CREATE TABLE IF NOT EXISTS ownership_transfers (
    transfer_id INT PRIMARY KEY AUTO_INCREMENT,
    record_id INT NOT NULL,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    transfer_type ENUM('sale', 'gift', 'inheritance', 'lease', 'other'),
    transfer_date DATE,
    consideration_amount DECIMAL(15,2),
    consideration_currency VARCHAR(3) DEFAULT 'KES',
    document_path VARCHAR(255),
    status ENUM('submitted', 'under_review', 'approved', 'declined', 'cancelled') DEFAULT 'submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (record_id) REFERENCES land_records(record_id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(user_id),
    FOREIGN KEY (to_user_id) REFERENCES users(user_id),
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id),
    INDEX idx_record (record_id),
    INDEX idx_from_user (from_user_id),
    INDEX idx_to_user (to_user_id),
    INDEX idx_status (status),
    INDEX idx_transfer_date (transfer_date)
);

-- Create Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255),
    message TEXT NOT NULL,
    type ENUM('info', 'alert', 'reminder', 'success', 'warning', 'error') DEFAULT 'info',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    action_url VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    is_archived BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_notifications (user_id, is_read, sent_at),
    INDEX idx_type (type),
    INDEX idx_priority (priority)
);

-- Create Admin Actions Table
CREATE TABLE IF NOT EXISTS admin_actions (
    action_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    related_entity_type ENUM('user', 'land_record', 'transfer', 'document', 'other'),
    related_entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id),
    INDEX idx_admin_actions (admin_id, timestamp),
    INDEX idx_action_type (action_type),
    INDEX idx_related_entity (related_entity_type, related_entity_id)
);

-- Create Audit Trail Table
CREATE TABLE IF NOT EXISTS audit_trail (
    audit_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_values JSON,
    new_values JSON,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    changed_by INT NULL,
    ip_address VARCHAR(45),
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_action_time (action, changed_at),
    INDEX idx_user (user_id)
);

-- Create System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json', 'array') DEFAULT 'string',
    category VARCHAR(50),
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(user_id),
    INDEX idx_setting_key (setting_key),
    INDEX idx_category (category)
);

-- ============================================
-- STORED PROCEDURES AND FUNCTIONS
-- ============================================

-- Function to calculate user age
DELIMITER $$
CREATE FUNCTION calculate_age(date_of_birth DATE) 
RETURNS INT
DETERMINISTIC
BEGIN
    RETURN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE());
END$$
DELIMITER ;

-- Procedure to deactivate inactive users
DELIMITER $$
CREATE PROCEDURE deactivate_inactive_users(
    IN days_inactive INT
)
BEGIN
    UPDATE users 
    SET is_active = FALSE 
    WHERE last_login < DATE_SUB(NOW(), INTERVAL days_inactive DAY)
    AND is_active = TRUE
    AND role = 'user';
END$$
DELIMITER ;

-- Trigger to update user updated_at timestamp
DELIMITER $$
CREATE TRIGGER update_user_timestamp 
BEFORE UPDATE ON users 
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

-- Trigger to log user updates
DELIMITER $$
CREATE TRIGGER log_user_changes 
AFTER UPDATE ON users 
FOR EACH ROW
BEGIN
    IF OLD.name != NEW.name OR OLD.email != NEW.email OR OLD.phone != NEW.phone THEN
        INSERT INTO audit_trail (
            user_id, 
            table_name, 
            record_id, 
            action, 
            old_values, 
            new_values,
            changed_by
        ) VALUES (
            NEW.user_id,
            'users',
            NEW.user_id,
            'UPDATE',
            JSON_OBJECT(
                'name', OLD.name,
                'email', OLD.email,
                'phone', OLD.phone
            ),
            JSON_OBJECT(
                'name', NEW.name,
                'email', NEW.email,
                'phone', NEW.phone
            ),
            NEW.user_id
        );
    END IF;
END$$
DELIMITER ;

-- ============================================
-- DEFAULT DATA INSERTION
-- ============================================

-- Insert Default Admin User (password: admin123)
INSERT INTO users (
    name, 
    email, 
    phone, 
    id_number, 
    password, 
    date_of_birth,
    gender,
    address,
    county,
    is_active,
    is_verified,
    role
) VALUES (
    'System Administrator',
    'admin@ardhiyetu.com',
    '0712345678',
    '00000000',
    '$2y$10$HashedPasswordHere', -- Replace with actual hash
    '1980-01-01',
    'male',
    'Administrative Headquarters, Nairobi',
    'Nairobi',
    TRUE,
    TRUE,
    'admin'
);

-- Insert Default System Settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, category, description, is_public) VALUES
('site_name', 'ArdhiYetu', 'string', 'general', 'Website name', TRUE),
('site_description', 'Digital Land Management System', 'string', 'general', 'Website description', TRUE),
('maintenance_mode', '0', 'boolean', 'system', 'Enable maintenance mode', FALSE),
('user_registration', '1', 'boolean', 'user', 'Allow user registration', TRUE),
('email_verification', '1', 'boolean', 'user', 'Require email verification', TRUE),
('max_login_attempts', '5', 'number', 'security', 'Maximum failed login attempts', FALSE),
('session_timeout', '30', 'number', 'security', 'Session timeout in minutes', FALSE),
('default_user_role', 'user', 'string', 'user', 'Default role for new users', FALSE),
('min_password_length', '8', 'number', 'security', 'Minimum password length', TRUE),
('require_strong_password', '1', 'boolean', 'security', 'Require strong passwords', TRUE);

-- ============================================
-- VIEWS FOR COMMON QUERIES
-- ============================================

-- View for active users
CREATE VIEW active_users AS
SELECT 
    user_id,
    name,
    email,
    phone,
    county,
    role,
    created_at,
    last_login
FROM users 
WHERE is_active = TRUE AND is_verified = TRUE;

-- View for pending user verifications
CREATE VIEW pending_verifications AS
SELECT 
    u.user_id,
    u.name,
    u.email,
    u.phone,
    u.county,
    u.created_at,
    ev.token,
    ev.expires_at
FROM users u
LEFT JOIN email_verifications ev ON u.user_id = ev.user_id
WHERE u.is_verified = FALSE 
AND u.is_active = TRUE
AND (ev.verified_at IS NULL OR ev.expires_at > NOW());

-- View for user statistics
CREATE VIEW user_statistics AS
SELECT 
    u.county,
    COUNT(*) as total_users,
    SUM(CASE WHEN u.is_verified = TRUE THEN 1 ELSE 0 END) as verified_users,
    SUM(CASE WHEN u.role = 'admin' THEN 1 ELSE 0 END) as admin_users,
    SUM(CASE WHEN u.role = 'officer' THEN 1 ELSE 0 END) as officer_users,
    SUM(CASE WHEN u.role = 'user' THEN 1 ELSE 0 END) as regular_users,
    AVG(calculate_age(u.date_of_birth)) as avg_age
FROM users u
WHERE u.is_active = TRUE
GROUP BY u.county;

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Composite indexes for better query performance
CREATE INDEX idx_user_composite ON users (is_active, is_verified, role, county);
CREATE INDEX idx_login_composite ON login_history (user_id, login_time DESC);
CREATE INDEX idx_notifications_composite ON notifications (user_id, is_read, sent_at DESC);
CREATE INDEX idx_land_records_composite ON land_records (owner_id, status, registered_at DESC);

-- ============================================
-- COMMENTS AND DOCUMENTATION
-- ============================================

COMMENT ON TABLE users IS 'Stores all user account information including registration data';
COMMENT ON COLUMN users.security_answer IS 'Hashed security answer for account recovery';
COMMENT ON COLUMN users.remember_token IS 'Token for "remember me" functionality';
COMMENT ON COLUMN users.failed_attempts IS 'Count of consecutive failed login attempts';

COMMENT ON TABLE user_profiles IS 'Additional user information and preferences';
COMMENT ON COLUMN user_profiles.preferred_language IS 'User''s preferred language (en=English, sw=Swahili)';

COMMENT ON TABLE email_verifications IS 'Email verification tokens and status';
COMMENT ON TABLE login_history IS 'Historical record of user login attempts';
COMMENT ON TABLE security_logs IS 'Security-related events and actions';

COMMENT ON TABLE user_documents IS 'User uploaded documents for verification';
COMMENT ON COLUMN user_documents.document_type IS 'Type of document uploaded';

COMMENT ON TABLE audit_trail IS 'Comprehensive audit trail for all data changes';
COMMENT ON COLUMN audit_trail.old_values IS 'JSON representation of old values';
COMMENT ON COLUMN audit_trail.new_values IS 'JSON representation of new values';

COMMENT ON TABLE system_settings IS 'System configuration settings';