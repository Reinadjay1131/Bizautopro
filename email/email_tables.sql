-- Email Logging Table for BizAutoPro Email Notification System
-- Run this query to create the email_logs table

CREATE TABLE IF NOT EXISTS email_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    status ENUM('sending', 'sent', 'failed', 'error') NOT NULL DEFAULT 'sending',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    error_message TEXT NULL,
    template_used VARCHAR(100) NULL,
    INDEX idx_recipient (recipient_email),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- Optional: Create email preferences table for users
CREATE TABLE IF NOT EXISTS user_email_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    email_notifications BOOLEAN DEFAULT TRUE,
    lead_notifications BOOLEAN DEFAULT TRUE,
    inventory_alerts BOOLEAN DEFAULT TRUE,
    workflow_updates BOOLEAN DEFAULT TRUE,
    system_alerts BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_prefs (user_id)
);

-- Insert default email preferences for existing users
INSERT IGNORE INTO user_email_preferences (user_id)
SELECT id FROM users WHERE status = 'approved';