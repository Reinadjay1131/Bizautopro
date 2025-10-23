-- Missing Database Columns for BizAutoPro
-- This script adds missing columns to existing tables
-- Run this script AFTER running bizautopro.sql and missing_database_tables.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. Add missing columns to leads table
ALTER TABLE `leads` 
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
ADD COLUMN IF NOT EXISTS `converted_at` DATETIME NULL AFTER `updated_at`,
ADD COLUMN IF NOT EXISTS `converted_by` INT(11) NULL AFTER `converted_at`,
ADD COLUMN IF NOT EXISTS `source` VARCHAR(100) DEFAULT 'website' AFTER `score`,
ADD COLUMN IF NOT EXISTS `last_contacted` DATETIME NULL AFTER `source`,
ADD COLUMN IF NOT EXISTS `next_follow_up` DATETIME NULL AFTER `last_contacted`,
ADD COLUMN IF NOT EXISTS `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' AFTER `next_follow_up`,
ADD COLUMN IF NOT EXISTS `tags` JSON NULL AFTER `priority`,
ADD COLUMN IF NOT EXISTS `qualification_notes` TEXT NULL AFTER `tags`;

-- Add foreign key constraint for converted_by
ALTER TABLE `leads`
ADD CONSTRAINT IF NOT EXISTS `leads_converted_by_fk` FOREIGN KEY (`converted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Add indexes for better performance
ALTER TABLE `leads`
ADD INDEX IF NOT EXISTS `idx_updated_at` (`updated_at`),
ADD INDEX IF NOT EXISTS `idx_converted_at` (`converted_at`),
ADD INDEX IF NOT EXISTS `idx_source` (`source`),
ADD INDEX IF NOT EXISTS `idx_priority` (`priority`),
ADD INDEX IF NOT EXISTS `idx_last_contacted` (`last_contacted`),
ADD INDEX IF NOT EXISTS `idx_next_follow_up` (`next_follow_up`);

-- 2. Add missing columns to users table
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `last_login` TIMESTAMP NULL AFTER `status`,
ADD COLUMN IF NOT EXISTS `login_count` INT DEFAULT 0 AFTER `last_login`,
ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT DEFAULT 0 AFTER `login_count`,
ADD COLUMN IF NOT EXISTS `locked_until` TIMESTAMP NULL AFTER `failed_login_attempts`,
ADD COLUMN IF NOT EXISTS `profile_picture` VARCHAR(500) NULL AFTER `locked_until`,
ADD COLUMN IF NOT EXISTS `phone` VARCHAR(50) NULL AFTER `profile_picture`,
ADD COLUMN IF NOT EXISTS `department` VARCHAR(100) NULL AFTER `phone`,
ADD COLUMN IF NOT EXISTS `job_title` VARCHAR(100) NULL AFTER `department`,
ADD COLUMN IF NOT EXISTS `is_active` BOOLEAN DEFAULT TRUE AFTER `job_title`,
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `is_active`;

-- Add indexes for users table
ALTER TABLE `users`
ADD INDEX IF NOT EXISTS `idx_last_login` (`last_login`),
ADD INDEX IF NOT EXISTS `idx_department` (`department`),
ADD INDEX IF NOT EXISTS `idx_is_active` (`is_active`),
ADD INDEX IF NOT EXISTS `idx_updated_at` (`updated_at`);

-- 3. Add missing columns to inventory table
ALTER TABLE `inventory`
ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) DEFAULT 'General' AFTER `supplier_id`,
ADD COLUMN IF NOT EXISTS `location` VARCHAR(255) NULL AFTER `category`,
ADD COLUMN IF NOT EXISTS `barcode` VARCHAR(255) NULL AFTER `location`,
ADD COLUMN IF NOT EXISTS `weight` DECIMAL(8,2) NULL AFTER `barcode`,
ADD COLUMN IF NOT EXISTS `dimensions` VARCHAR(100) NULL AFTER `weight`,
ADD COLUMN IF NOT EXISTS `status` ENUM('active', 'discontinued', 'out_of_stock') DEFAULT 'active' AFTER `dimensions`,
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `status`,
ADD COLUMN IF NOT EXISTS `created_by` INT(11) NULL AFTER `created_at`;

-- Add constraints and indexes for inventory
ALTER TABLE `inventory`
ADD CONSTRAINT IF NOT EXISTS `inventory_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory`
ADD INDEX IF NOT EXISTS `idx_category` (`category`),
ADD INDEX IF NOT EXISTS `idx_barcode` (`barcode`),
ADD INDEX IF NOT EXISTS `idx_status` (`status`),
ADD INDEX IF NOT EXISTS `idx_created_at` (`created_at`);

-- 4. Add missing columns to suppliers table
ALTER TABLE `suppliers`
ADD COLUMN IF NOT EXISTS `contact_person` VARCHAR(255) NULL AFTER `contact_email`,
ADD COLUMN IF NOT EXISTS `website` VARCHAR(255) NULL AFTER `contact_person`,
ADD COLUMN IF NOT EXISTS `tax_id` VARCHAR(100) NULL AFTER `website`,
ADD COLUMN IF NOT EXISTS `payment_terms` VARCHAR(100) DEFAULT 'Net 30' AFTER `tax_id`,
ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `payment_terms`,
ADD COLUMN IF NOT EXISTS `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active' AFTER `notes`,
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `status`;

-- Add indexes for suppliers
ALTER TABLE `suppliers`
ADD INDEX IF NOT EXISTS `idx_status` (`status`),
ADD INDEX IF NOT EXISTS `idx_updated_at` (`updated_at`);

-- 5. Enhance inventory_transactions table
ALTER TABLE `inventory_transactions`
ADD COLUMN IF NOT EXISTS `reference_number` VARCHAR(100) NULL AFTER `reason`,
ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `reference_number`,
ADD COLUMN IF NOT EXISTS `batch_id` VARCHAR(100) NULL AFTER `notes`,
ADD COLUMN IF NOT EXISTS `location_from` VARCHAR(255) NULL AFTER `batch_id`,
ADD COLUMN IF NOT EXISTS `location_to` VARCHAR(255) NULL AFTER `location_from`;

-- Add indexes for inventory_transactions
ALTER TABLE `inventory_transactions`
ADD INDEX IF NOT EXISTS `idx_reference_number` (`reference_number`),
ADD INDEX IF NOT EXISTS `idx_batch_id` (`batch_id`);

-- 6. Update workflow status enum to include more statuses
ALTER TABLE `workflows` 
MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled', 'in_progress', 'on_hold', 'review') NOT NULL DEFAULT 'pending';

-- 7. Update lead status enum to include conversion statuses
ALTER TABLE `leads` 
MODIFY COLUMN `status` ENUM('new', 'contacted', 'qualified', 'lost', 'converted', 'closed_won', 'closed_lost', 'follow_up') NOT NULL DEFAULT 'new';

-- 8. Update user status enum to be more comprehensive
ALTER TABLE `users` 
MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'suspended', 'inactive', 'locked') DEFAULT 'pending';

-- 9. Add trigger to update last_updated timestamp for inventory
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `inventory_update_timestamp` 
BEFORE UPDATE ON `inventory` 
FOR EACH ROW 
BEGIN 
    SET NEW.last_updated = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

-- 10. Update existing records to have proper timestamps where missing
UPDATE `leads` SET `updated_at` = `created_at` WHERE `updated_at` IS NULL;
UPDATE `users` SET `updated_at` = `_created_at` WHERE `updated_at` IS NULL;
UPDATE `inventory` SET `created_at` = `last_updated` WHERE `created_at` IS NULL;

-- 11. Add default values to existing records
UPDATE `leads` SET `source` = 'website' WHERE `source` IS NULL OR `source` = '';
UPDATE `leads` SET `priority` = 'medium' WHERE `priority` IS NULL;
UPDATE `inventory` SET `category` = 'General' WHERE `category` IS NULL OR `category` = '';
UPDATE `inventory` SET `status` = 'active' WHERE `status` IS NULL;
UPDATE `suppliers` SET `payment_terms` = 'Net 30' WHERE `payment_terms` IS NULL OR `payment_terms` = '';
UPDATE `suppliers` SET `status` = 'active' WHERE `status` IS NULL;
UPDATE `users` SET `is_active` = TRUE WHERE `is_active` IS NULL;

COMMIT;

-- Success message
SELECT 'Missing database columns added successfully!' as message;