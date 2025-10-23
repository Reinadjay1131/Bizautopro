-- BizAutoPro Complete Database Initialization Script
-- This script runs all necessary database migrations in the correct order
-- Run this script on a fresh database to set up the complete BizAutoPro system

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Disable foreign key checks during setup
SET FOREIGN_KEY_CHECKS = 0;

SELECT 'Starting BizAutoPro database initialization...' as message;

-- Step 1: Create main database schema
SELECT 'Step 1: Creating main database schema...' as message;
SOURCE bizautopro.sql;

-- Step 2: Create missing tables
SELECT 'Step 2: Creating missing tables...' as message;
SOURCE missing_database_tables.sql;

-- Step 3: Add missing columns
SELECT 'Step 3: Adding missing columns...' as message;
SOURCE missing_database_columns.sql;

-- Step 4: Apply workflow enhancements
SELECT 'Step 4: Applying workflow enhancements...' as message;
SOURCE enhanced_workflow_migration.sql;

-- Step 5: Apply consistency fixes
SELECT 'Step 5: Applying database consistency fixes...' as message;
SOURCE database_consistency_fixes.sql;

-- Step 6: Insert sample data
SELECT 'Step 6: Inserting sample data...' as message;
SOURCE inserts.sql;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Step 7: Create additional sample data for testing
SELECT 'Step 7: Creating additional sample data...' as message;

-- Insert additional workflow test data
INSERT INTO `workflows` (`title`, `description`, `assigned_to`, `status`, `priority`, `due_date`, `created_by`, `category`, `estimated_hours`) VALUES
('Setup New Employee Workstation', 'Configure computer, install software, and create accounts for new hire', 1, 'pending', 'high', DATE_ADD(NOW(), INTERVAL 2 DAY), 2, 'IT', 4.0),
('Monthly Inventory Audit', 'Conduct physical inventory count and reconcile with system records', 2, 'pending', 'medium', DATE_ADD(NOW(), INTERVAL 7 DAY), 1, 'Inventory', 8.0),
('Update Website Content', 'Refresh product descriptions and pricing on company website', 3, 'in_progress', 'medium', DATE_ADD(NOW(), INTERVAL 3 DAY), 2, 'Marketing', 6.0),
('Process Supplier Invoices', 'Review and process pending supplier invoices for payment', 1, 'pending', 'high', DATE_ADD(NOW(), INTERVAL 1 DAY), 1, 'Finance', 2.0),
('Customer Database Cleanup', 'Remove duplicate entries and update outdated customer information', 2, 'pending', 'low', DATE_ADD(NOW(), INTERVAL 14 DAY), 3, 'Data Management', 5.0);

-- Insert sample lead data
INSERT INTO `leads` (`name`, `email`, `phone`, `company`, `status`, `score`, `source`, `priority`, `assigned_to`, `created_by`) VALUES
('Sarah Johnson', 'sarah.johnson@techcorp.com', '+1-555-0101', 'TechCorp Solutions', 'new', 85, 'website', 'high', 2, 1),
('Michael Chen', 'mike@startupvibe.com', '+1-555-0102', 'StartupVibe Inc', 'contacted', 72, 'referral', 'medium', 2, 1),
('Emily Rodriguez', 'emily.r@globaltech.com', '+1-555-0103', 'GlobalTech Industries', 'qualified', 90, 'trade_show', 'high', 3, 2),
('David Thompson', 'd.thompson@localservices.com', '+1-555-0104', 'Local Services LLC', 'contacted', 45, 'cold_call', 'low', 1, 2),
('Lisa Wang', 'lisa@innovatetech.com', '+1-555-0105', 'InnovateTech', 'new', 60, 'linkedin', 'medium', 2, 3);

-- Insert sample customer data
INSERT INTO `customers` (`name`, `email`, `phone`, `company`, `source`, `status`, `created_by`) VALUES
('Robert Anderson', 'robert@successcorp.com', '+1-555-0201', 'Success Corporation', 'converted_lead', 'active', 1),
('Jennifer Martinez', 'jennifer@growthco.com', '+1-555-0202', 'Growth Company', 'converted_lead', 'active', 2),
('Thomas Wilson', 'thomas@stabilfirm.com', '+1-555-0203', 'Stable Firm', 'direct_signup', 'active', 1),
('Amanda Brown', 'amanda@flexisolutions.com', '+1-555-0204', 'Flexi Solutions', 'converted_lead', 'prospect', 3);

-- Insert sample email logs
INSERT INTO `email_logs` (`recipient_email`, `subject`, `status`, `email_type`, `sent_by`) VALUES
('sarah.johnson@techcorp.com', 'Welcome to BizAutoPro - Getting Started Guide', 'sent', 'welcome', 1),
('mike@startupvibe.com', 'Follow-up: Your Demo Session', 'sent', 'follow_up', 2),
('emily.r@globaltech.com', 'Proposal: Custom Workflow Solutions', 'sent', 'proposal', 2),
('robert@successcorp.com', 'Monthly System Update Notification', 'sent', 'notification', 1),
('jennifer@growthco.com', 'Workflow Assignment: Review Quarterly Report', 'sent', 'workflow_notification', 3);

-- Insert sample automation rules (if not already inserted)
INSERT IGNORE INTO `workflow_automation_rules` (`rule_name`, `trigger_type`, `trigger_condition`, `action_type`, `action_data`) VALUES
('Urgent Task Escalation', 'overdue', '{"hours_overdue": 2, "priority": "urgent"}', 'send_notification', '{"notify_admin": true, "email_management": true}'),
('Finance Task Auto-Assignment', 'category_based', '{"category": "Finance"}', 'auto_assign', '{"assign_to_role": "manager"}'),
('Daily Overdue Check', 'overdue', '{"hours_overdue": 24}', 'escalate_priority', '{"notify_assigned": true}');

-- Step 8: Create database views for reporting
SELECT 'Step 8: Creating database views for reporting...' as message;

-- Active records view (excluding soft-deleted)
CREATE OR REPLACE VIEW `dashboard_summary` AS
SELECT 
    'leads' as entity_type,
    COUNT(*) as total_count,
    COUNT(CASE WHEN status = 'new' THEN 1 END) as new_count,
    COUNT(CASE WHEN status = 'qualified' THEN 1 END) as qualified_count,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_count
FROM active_leads
UNION ALL
SELECT 
    'workflows' as entity_type,
    COUNT(*) as total_count,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as new_count,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as qualified_count,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_count
FROM active_workflows
UNION ALL
SELECT 
    'customers' as entity_type,
    COUNT(*) as total_count,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as new_count,
    COUNT(CASE WHEN status = 'prospect' THEN 1 END) as qualified_count,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_count
FROM active_customers;

-- Workflow performance view
CREATE OR REPLACE VIEW `workflow_performance` AS
SELECT 
    w.id,
    w.title,
    w.priority,
    w.status,
    w.estimated_hours,
    w.actual_hours,
    u1.username as assigned_to_name,
    u2.username as created_by_name,
    w.created_at,
    w.due_date,
    w.completion_date,
    CASE 
        WHEN w.status = 'completed' THEN 'Completed'
        WHEN w.due_date < NOW() THEN 'Overdue'
        WHEN w.due_date < DATE_ADD(NOW(), INTERVAL 1 DAY) THEN 'Due Soon'
        ELSE 'On Track'
    END as timeline_status,
    DATEDIFF(COALESCE(w.completion_date, NOW()), w.created_at) as days_in_progress
FROM workflows w
LEFT JOIN users u1 ON w.assigned_to = u1.id
LEFT JOIN users u2 ON w.created_by = u2.id
WHERE w.deleted_at IS NULL;

-- Lead conversion funnel view
CREATE OR REPLACE VIEW `lead_conversion_funnel` AS
SELECT 
    l.source,
    COUNT(*) as total_leads,
    COUNT(CASE WHEN l.status = 'contacted' THEN 1 END) as contacted,
    COUNT(CASE WHEN l.status = 'qualified' THEN 1 END) as qualified,
    COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as converted,
    ROUND(COUNT(CASE WHEN l.status = 'converted' THEN 1 END) * 100.0 / COUNT(*), 2) as conversion_rate
FROM leads l
WHERE l.deleted_at IS NULL
GROUP BY l.source;

-- Step 9: Verify database integrity
SELECT 'Step 9: Verifying database integrity...' as message;

-- Check for missing foreign key references
SELECT 'Checking foreign key integrity...' as message;

-- Step 10: Create database maintenance procedures
SELECT 'Step 10: Creating maintenance procedures...' as message;

DELIMITER $$

-- Procedure to clean up old sessions
CREATE PROCEDURE IF NOT EXISTS CleanupOldSessions()
BEGIN
    DELETE FROM api_sessions WHERE expires_at < NOW();
    DELETE FROM user_sessions WHERE expires_at < NOW();
    SELECT CONCAT('Cleaned up expired sessions at ', NOW()) as message;
END$$

-- Procedure to archive old logs
CREATE PROCEDURE IF NOT EXISTS ArchiveOldLogs()
BEGIN
    DECLARE days_to_keep INT DEFAULT 90;
    
    DELETE FROM api_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    DELETE FROM email_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    DELETE FROM automation_logs WHERE executed_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    SELECT CONCAT('Archived logs older than ', days_to_keep, ' days at ', NOW()) as message;
END$$

-- Procedure to update user statistics
CREATE PROCEDURE IF NOT EXISTS UpdateUserStatistics()
BEGIN
    UPDATE users u SET 
        u.login_count = (
            SELECT COUNT(*) FROM user_sessions s 
            WHERE s.user_id = u.id
        );
    
    SELECT 'Updated user statistics' as message;
END$$

DELIMITER ;

COMMIT;

-- Final verification
SELECT 'BizAutoPro database initialization completed successfully!' as message;

-- Display summary
SELECT 
    'Tables Created' as category,
    COUNT(*) as count
FROM information_schema.tables 
WHERE table_schema = DATABASE()
UNION ALL
SELECT 
    'Views Created' as category,
    COUNT(*) as count
FROM information_schema.views 
WHERE table_schema = DATABASE()
UNION ALL
SELECT 
    'Procedures Created' as category,
    COUNT(*) as count
FROM information_schema.routines 
WHERE routine_schema = DATABASE() AND routine_type = 'PROCEDURE';

-- Show critical tables status
SELECT 
    table_name,
    table_rows as 'Record Count',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as 'Size (MB)'
FROM information_schema.tables 
WHERE table_schema = DATABASE()
    AND table_name IN ('users', 'leads', 'workflows', 'customers', 'inventory', 'suppliers')
ORDER BY table_name;