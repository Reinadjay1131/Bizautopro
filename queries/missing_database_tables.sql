-- Missing Database Tables for BizAutoPro
-- This script creates all tables that are referenced in the code but missing from the database
-- Run this script AFTER running bizautopro.sql and enhanced_workflow_migration.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. Create customers table for lead conversions
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive','prospect') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `company` (`company`),
  CONSTRAINT `customers_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Create lead_history table for tracking lead actions
CREATE TABLE IF NOT EXISTS `lead_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `timestamp` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `timestamp` (`timestamp`),
  CONSTRAINT `lead_history_lead_fk` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_history_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Create email_logs table for email tracking
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `subject` varchar(500) NOT NULL,
  `message_body` text DEFAULT NULL,
  `status` enum('sent','failed','pending','queued') NOT NULL,
  `error_message` text DEFAULT NULL,
  `email_type` varchar(100) DEFAULT 'general',
  `sent_by` int(11) DEFAULT NULL,
  `sent_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recipient_email` (`recipient_email`),
  KEY `status` (`status`),
  KEY `email_type` (`email_type`),
  KEY `sent_by` (`sent_by`),
  KEY `sent_at` (`sent_at`),
  CONSTRAINT `email_logs_sent_by_fk` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Create api_sessions table for API authentication
CREATE TABLE IF NOT EXISTS `api_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_active` boolean DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `api_sessions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Create api_logs table for API request monitoring
CREATE TABLE IF NOT EXISTS `api_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` int(11) NOT NULL,
  `response_time` decimal(8,3) DEFAULT NULL,
  `request_body` text DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `endpoint` (`endpoint`),
  KEY `method` (`method`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `ip_address` (`ip_address`),
  CONSTRAINT `api_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Create user_sessions table for web session management
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `user_id` (`user_id`),
  KEY `last_activity` (`last_activity`),
  CONSTRAINT `user_sessions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Create system_settings table for application configuration
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json','array') DEFAULT 'string',
  `category` varchar(100) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `is_public` boolean DEFAULT FALSE,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `category` (`category`),
  KEY `is_public` (`is_public`),
  CONSTRAINT `system_settings_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `description`, `is_public`) VALUES
('app_name', 'BizAutoPro', 'string', 'general', 'Application name', TRUE),
('app_version', '1.0.0', 'string', 'general', 'Application version', TRUE),
('maintenance_mode', 'false', 'boolean', 'system', 'Enable maintenance mode', FALSE),
('max_file_upload_size', '10485760', 'number', 'files', 'Maximum file upload size in bytes (10MB)', FALSE),
('allowed_file_types', '["pdf","doc","docx","jpg","jpeg","png","gif","txt","csv","xlsx"]', 'json', 'files', 'Allowed file upload types', FALSE),
('email_notifications', 'true', 'boolean', 'notifications', 'Enable email notifications', FALSE),
('workflow_auto_assignment', 'true', 'boolean', 'workflows', 'Enable automatic workflow assignment', FALSE),
('lead_auto_scoring', 'true', 'boolean', 'leads', 'Enable automatic lead scoring', FALSE),
('inventory_low_stock_threshold', '10', 'number', 'inventory', 'Default low stock threshold', FALSE),
('session_timeout', '3600', 'number', 'security', 'Session timeout in seconds (1 hour)', FALSE);

COMMIT;

-- Success message
SELECT 'Missing database tables created successfully!' as message;