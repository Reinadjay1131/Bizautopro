-- Enhanced Workflow System Database Migration
-- This script adds new fields while preserving existing data

-- 1. Add new columns to workflows table
ALTER TABLE `workflows` 
ADD COLUMN `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' AFTER `status`,
ADD COLUMN `due_date` DATETIME NULL AFTER `priority`,
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
ADD COLUMN `estimated_hours` DECIMAL(5,2) NULL AFTER `due_date`,
ADD COLUMN `actual_hours` DECIMAL(5,2) NULL AFTER `estimated_hours`,
ADD COLUMN `completed_by` INT(11) NULL AFTER `actual_hours`,
ADD COLUMN `completion_date` DATETIME NULL AFTER `completed_by`,
ADD COLUMN `attachment_path` VARCHAR(500) NULL AFTER `completion_date`,
ADD COLUMN `category` VARCHAR(100) DEFAULT 'General' AFTER `attachment_path`,
ADD COLUMN `completion_notes` TEXT NULL AFTER `category`;

-- 2. Add foreign key constraint for completed_by
ALTER TABLE `workflows`
ADD CONSTRAINT `workflows_completed_by_fk` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 3. Add indexes for better performance
ALTER TABLE `workflows`
ADD INDEX `idx_priority` (`priority`),
ADD INDEX `idx_due_date` (`due_date`),
ADD INDEX `idx_category` (`category`),
ADD INDEX `idx_status_priority` (`status`, `priority`),
ADD INDEX `idx_assigned_due` (`assigned_to`, `due_date`);

-- 4. Create workflow_notifications table for tracking alerts
CREATE TABLE IF NOT EXISTS `workflow_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_type` ENUM('assigned', 'due_soon', 'overdue', 'completed', 'cancelled') NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_user_unread` (`user_id`, `is_read`),
  INDEX `idx_workflow_type` (`workflow_id`, `notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Create workflow_attachments table for file management
CREATE TABLE IF NOT EXISTS `workflow_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_id` int(11) NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `uploaded_by` INT(11) NOT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_workflow` (`workflow_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Create workflow_automation_rules table
CREATE TABLE IF NOT EXISTS `workflow_automation_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_name` VARCHAR(255) NOT NULL,
  `trigger_type` ENUM('category_based', 'overdue', 'priority_escalation') NOT NULL,
  `trigger_condition` JSON NOT NULL,
  `action_type` ENUM('auto_assign', 'send_notification', 'escalate_priority') NOT NULL,
  `action_data` JSON NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_trigger_active` (`trigger_type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add automation_logs table for tracking automation execution
CREATE TABLE IF NOT EXISTS automation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    affected_workflows JSON NULL,
    error_message TEXT NULL,
    success BOOLEAN DEFAULT FALSE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rule_id) REFERENCES workflow_automation_rules(id) ON DELETE CASCADE
);

-- Insert default automation rules for common scenarios
INSERT INTO `workflow_automation_rules` (`rule_name`, `trigger_type`, `trigger_condition`, `action_type`, `action_data`) VALUES
('IT Tasks Auto-Assignment', 'category_based', '{"category": "IT"}', 'auto_assign', '{"assign_to_role": "admin"}'),
('High Priority Escalation', 'overdue', '{"hours_overdue": 24, "priority": "high"}', 'escalate_priority', '{"new_priority": "urgent"}'),
('Overdue Notification', 'overdue', '{"hours_overdue": 12}', 'send_notification', '{"notify_admin": true}');

-- 8. Update existing workflows with default values
UPDATE `workflows` SET 
  `category` = 'General',
  `priority` = 'medium',
  `updated_at` = `created_at`
WHERE `category` IS NULL OR `priority` IS NULL;