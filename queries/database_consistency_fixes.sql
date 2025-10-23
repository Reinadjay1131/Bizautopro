-- Database Consistency Fixes for BizAutoPro
-- This script fixes naming inconsistencies and standardizes the database schema
-- Run this script AFTER running all other migration scripts

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. Fix users table column naming inconsistency
-- Change _created_at to created_at for consistency
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `is_active`;

-- Copy data from old column to new column
UPDATE `users` SET `created_at` = `_created_at` WHERE `created_at` IS NULL;

-- Note: We keep both columns for now to ensure compatibility
-- After verifying all code works with created_at, _created_at can be dropped

-- 2. Standardize all timestamp columns to use consistent naming
-- Ensure all tables use created_at, updated_at, deleted_at pattern

-- 3. Add soft delete support to major tables
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL AFTER `updated_at`,
ADD COLUMN IF NOT EXISTS `deleted_by` INT(11) NULL AFTER `deleted_at`;

ALTER TABLE `leads`
ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL AFTER `qualification_notes`,
ADD COLUMN IF NOT EXISTS `deleted_by` INT(11) NULL AFTER `deleted_at`;

ALTER TABLE `workflows`
ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL AFTER `completion_notes`,
ADD COLUMN IF NOT EXISTS `deleted_by` INT(11) NULL AFTER `deleted_at`;

ALTER TABLE `inventory`
ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL AFTER `created_by`,
ADD COLUMN IF NOT EXISTS `deleted_by` INT(11) NULL AFTER `deleted_at`;

ALTER TABLE `suppliers`
ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL AFTER `updated_at`,
ADD COLUMN IF NOT EXISTS `deleted_by` INT(11) NULL AFTER `deleted_at`;

ALTER TABLE `customers`
ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL AFTER `updated_at`,
ADD COLUMN IF NOT EXISTS `deleted_by` INT(11) NULL AFTER `deleted_at`;

-- Add foreign key constraints for deleted_by columns
ALTER TABLE `users`
ADD CONSTRAINT IF NOT EXISTS `users_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `leads`
ADD CONSTRAINT IF NOT EXISTS `leads_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `workflows`
ADD CONSTRAINT IF NOT EXISTS `workflows_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory`
ADD CONSTRAINT IF NOT EXISTS `inventory_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `suppliers`
ADD CONSTRAINT IF NOT EXISTS `suppliers_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `customers`
ADD CONSTRAINT IF NOT EXISTS `customers_deleted_by_fk` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 4. Add indexes for soft delete columns
ALTER TABLE `users`
ADD INDEX IF NOT EXISTS `idx_deleted_at` (`deleted_at`);

ALTER TABLE `leads`
ADD INDEX IF NOT EXISTS `idx_deleted_at` (`deleted_at`);

ALTER TABLE `workflows`
ADD INDEX IF NOT EXISTS `idx_deleted_at` (`deleted_at`);

ALTER TABLE `inventory`
ADD INDEX IF NOT EXISTS `idx_deleted_at` (`deleted_at`);

ALTER TABLE `suppliers`
ADD INDEX IF NOT EXISTS `idx_deleted_at` (`deleted_at`);

ALTER TABLE `customers`
ADD INDEX IF NOT EXISTS `idx_deleted_at` (`deleted_at`);

-- 5. Create views for active (non-deleted) records
CREATE OR REPLACE VIEW `active_users` AS
SELECT * FROM `users` WHERE `deleted_at` IS NULL;

CREATE OR REPLACE VIEW `active_leads` AS
SELECT * FROM `leads` WHERE `deleted_at` IS NULL;

CREATE OR REPLACE VIEW `active_workflows` AS
SELECT * FROM `workflows` WHERE `deleted_at` IS NULL;

CREATE OR REPLACE VIEW `active_inventory` AS
SELECT * FROM `inventory` WHERE `deleted_at` IS NULL;

CREATE OR REPLACE VIEW `active_suppliers` AS
SELECT * FROM `suppliers` WHERE `deleted_at` IS NULL;

CREATE OR REPLACE VIEW `active_customers` AS
SELECT * FROM `customers` WHERE `deleted_at` IS NULL;

-- 6. Standardize foreign key naming convention
-- All foreign keys should follow the pattern: tablename_columnname_fk

-- Check if foreign keys exist before adding them (some may already exist)
SET @fk_exists = 0;

-- Workflows foreign keys
SELECT COUNT(*) INTO @fk_exists FROM information_schema.TABLE_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'workflows' AND CONSTRAINT_NAME = 'workflows_assigned_to_fk';

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE workflows ADD CONSTRAINT workflows_assigned_to_fk FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "workflows_assigned_to_fk already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk_exists FROM information_schema.TABLE_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'workflows' AND CONSTRAINT_NAME = 'workflows_created_by_fk';

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE workflows ADD CONSTRAINT workflows_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE',
    'SELECT "workflows_created_by_fk already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Leads foreign keys
SELECT COUNT(*) INTO @fk_exists FROM information_schema.TABLE_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND CONSTRAINT_NAME = 'leads_assigned_to_fk';

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE leads ADD CONSTRAINT leads_assigned_to_fk FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "leads_assigned_to_fk already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk_exists FROM information_schema.TABLE_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND CONSTRAINT_NAME = 'leads_created_by_fk';

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE leads ADD CONSTRAINT leads_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "leads_created_by_fk already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Inventory foreign keys
SELECT COUNT(*) INTO @fk_exists FROM information_schema.TABLE_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND CONSTRAINT_NAME = 'inventory_supplier_id_fk';

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE inventory ADD CONSTRAINT inventory_supplier_id_fk FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL',
    'SELECT "inventory_supplier_id_fk already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Create audit log table for tracking all database changes
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('CREATE','UPDATE','DELETE','RESTORE') NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `table_name` (`table_name`),
  KEY `record_id` (`record_id`),
  KEY `action` (`action`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `audit_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Create comprehensive indexes for better performance
-- Composite indexes for common query patterns

-- Users table performance indexes
ALTER TABLE `users`
ADD INDEX IF NOT EXISTS `idx_role_status` (`role`, `status`),
ADD INDEX IF NOT EXISTS `idx_email_status` (`email`, `status`),
ADD INDEX IF NOT EXISTS `idx_username_status` (`username`, `status`);

-- Leads table performance indexes
ALTER TABLE `leads`
ADD INDEX IF NOT EXISTS `idx_status_assigned` (`status`, `assigned_to`),
ADD INDEX IF NOT EXISTS `idx_created_by_status` (`created_by`, `status`),
ADD INDEX IF NOT EXISTS `idx_score_status` (`score`, `status`),
ADD INDEX IF NOT EXISTS `idx_source_status` (`source`, `status`);

-- Workflows table performance indexes
ALTER TABLE `workflows`
ADD INDEX IF NOT EXISTS `idx_status_assigned` (`status`, `assigned_to`),
ADD INDEX IF NOT EXISTS `idx_priority_status` (`priority`, `status`),
ADD INDEX IF NOT EXISTS `idx_created_by_status` (`created_by`, `status`),
ADD INDEX IF NOT EXISTS `idx_due_date_status` (`due_date`, `status`);

-- Inventory table performance indexes
ALTER TABLE `inventory`
ADD INDEX IF NOT EXISTS `idx_category_status` (`category`, `status`),
ADD INDEX IF NOT EXISTS `idx_supplier_status` (`supplier_id`, `status`),
ADD INDEX IF NOT EXISTS `idx_reorder_quantity` (`reorder_level`, `quantity`);

COMMIT;

-- Success message
SELECT 'Database consistency fixes applied successfully!' as message;