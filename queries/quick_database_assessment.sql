-- Quick Database Assessment and Setup Script
-- This script can be run to quickly check what's missing and optionally fix it
-- Designed for development and testing purposes

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Disable warnings for cleaner output
SET sql_notes = 0;

SELECT '=== BizAutoPro Database Assessment ===' as message;
SELECT CONCAT('Database: ', DATABASE()) as current_database;
SELECT NOW() as assessment_time;

-- Check if main tables exist
SELECT '--- Checking Core Tables ---' as message;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users') 
         THEN '✅ users table exists' 
         ELSE '❌ users table MISSING' 
    END as users_table;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'leads') 
         THEN '✅ leads table exists' 
         ELSE '❌ leads table MISSING' 
    END as leads_table;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'workflows') 
         THEN '✅ workflows table exists' 
         ELSE '❌ workflows table MISSING' 
    END as workflows_table;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'inventory') 
         THEN '✅ inventory table exists' 
         ELSE '❌ inventory table MISSING' 
    END as inventory_table;

-- Check if extended tables exist
SELECT '--- Checking Extended Tables ---' as message;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'customers') 
         THEN '✅ customers table exists' 
         ELSE '❌ customers table MISSING' 
    END as customers_table;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'lead_history') 
         THEN '✅ lead_history table exists' 
         ELSE '❌ lead_history table MISSING' 
    END as lead_history_table;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'email_logs') 
         THEN '✅ email_logs table exists' 
         ELSE '❌ email_logs table MISSING' 
    END as email_logs_table;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'api_sessions') 
         THEN '✅ api_sessions table exists' 
         ELSE '❌ api_sessions table MISSING' 
    END as api_sessions_table;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'api_logs') 
         THEN '✅ api_logs table exists' 
         ELSE '❌ api_logs table MISSING' 
    END as api_logs_table;

-- Check workflow enhancement tables
SELECT '--- Checking Workflow Enhancement Tables ---' as message;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'workflow_notifications') 
         THEN '✅ workflow_notifications table exists' 
         ELSE '❌ workflow_notifications table MISSING' 
    END as workflow_notifications_table;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'workflow_attachments') 
         THEN '✅ workflow_attachments table exists' 
         ELSE '❌ workflow_attachments table MISSING' 
    END as workflow_attachments_table;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'workflow_automation_rules') 
         THEN '✅ workflow_automation_rules table exists' 
         ELSE '❌ workflow_automation_rules table MISSING' 
    END as workflow_automation_rules_table;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'automation_logs') 
         THEN '✅ automation_logs table exists' 
         ELSE '❌ automation_logs table MISSING' 
    END as automation_logs_table;

-- Check for important columns
SELECT '--- Checking Important Columns ---' as message;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'leads' AND column_name = 'updated_at') 
         THEN '✅ leads.updated_at exists' 
         ELSE '❌ leads.updated_at MISSING' 
    END as leads_updated_at;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'leads' AND column_name = 'converted_at') 
         THEN '✅ leads.converted_at exists' 
         ELSE '❌ leads.converted_at MISSING' 
    END as leads_converted_at;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'last_login') 
         THEN '✅ users.last_login exists' 
         ELSE '❌ users.last_login MISSING' 
    END as users_last_login;

SELECT 
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'workflows' AND column_name = 'priority') 
         THEN '✅ workflows.priority exists' 
         ELSE '❌ workflows.priority MISSING' 
    END as workflows_priority;

-- Show table record counts
SELECT '--- Record Counts ---' as message;

SELECT 
    (SELECT COUNT(*) FROM users) as users_count,
    (SELECT COUNT(*) FROM leads) as leads_count,
    (SELECT COUNT(*) FROM workflows) as workflows_count,
    (SELECT COUNT(*) FROM inventory) as inventory_count;

-- Show any missing critical data
SELECT '--- Data Verification ---' as message;

SELECT 
    CASE WHEN (SELECT COUNT(*) FROM users WHERE role = 'admin') > 0 
         THEN CONCAT('✅ Admin users: ', (SELECT COUNT(*) FROM users WHERE role = 'admin'))
         ELSE '❌ No admin users found' 
    END as admin_users_check;

-- Quick setup commands (commented out - uncomment to run)
SELECT '--- Quick Setup Commands ---' as message;
SELECT 'To fix missing tables, run: SOURCE missing_database_tables.sql' as command1;
SELECT 'To fix missing columns, run: SOURCE missing_database_columns.sql' as command2;
SELECT 'To enhance workflows, run: SOURCE enhanced_workflow_migration.sql' as command3;
SELECT 'To fix consistency, run: SOURCE database_consistency_fixes.sql' as command4;
SELECT 'For complete setup, run: SOURCE complete_database_setup.sql' as command5;

SELECT '=== Assessment Complete ===' as message;

-- Re-enable notes
SET sql_notes = 1;