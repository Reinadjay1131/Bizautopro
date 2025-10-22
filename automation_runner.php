<?php
/**
 * Automated Workflow Processor
 * This script runs automation rules and can be executed via cron or manually
 * 
 * Usage:
 * - Via cron: */15 * * * * php /path/to/automation_runner.php
 * - Via web: automation_runner.php?key=your_secret_key
 * - Manually: php automation_runner.php
 */

// Security check for web access
if (isset($_SERVER['HTTP_HOST'])) {
    $secret_key = $_GET['key'] ?? '';
    $expected_key = 'bizautopro_automation_2025'; // Change this in production
    
    if ($secret_key !== $expected_key) {
        http_response_code(403);
        die('Unauthorized access');
    }
    
    header('Content-Type: application/json');
}

// Start execution
$start_time = microtime(true);
$output = [];

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/automation_engine.php';
    require_once __DIR__ . '/overdue_alert_system.php';
    
    $output[] = "Starting automation process at " . date('Y-m-d H:i:s');
    
    // Initialize automation engine
    $automation = new WorkflowAutomationEngine($pdo);
    $alert_system = new OverdueAlertSystem($pdo);
    
    // Process overdue alerts first
    $output[] = "Processing overdue alerts...";
    $overdue_results = $alert_system->processOverdueAlerts();
    $output[] = "Overdue alerts processed: " . count($overdue_results) . " workflows checked";
    
    // Process automation rules
    $output[] = "Processing automation rules...";
    $automation_results = $automation->processAllRules();
    
    $successful_rules = 0;
    $failed_rules = 0;
    $total_affected = 0;
    
    foreach ($automation_results as $result) {
        if ($result['success']) {
            $successful_rules++;
            $total_affected += count($result['affected_workflows'] ?? []);
            $output[] = "✓ Rule {$result['rule_id']}: {$result['action']} - " . count($result['affected_workflows'] ?? []) . " workflows affected";
        } else {
            $failed_rules++;
            $output[] = "✗ Rule {$result['rule_id']}: Failed - " . ($result['error'] ?? 'Unknown error');
        }
    }
    
    // Summary
    $execution_time = round(microtime(true) - $start_time, 2);
    $output[] = "Automation completed in {$execution_time} seconds";
    $output[] = "Rules processed: " . count($automation_results);
    $output[] = "Successful: {$successful_rules}, Failed: {$failed_rules}";
    $output[] = "Total workflows affected: {$total_affected}";
    
    // Log execution to database
    $pdo->prepare("
        INSERT INTO automation_logs (rule_id, action_type, affected_workflows, success, executed_at)
        VALUES (0, 'system_run', ?, 1, NOW())
    ")->execute([json_encode([
        'rules_processed' => count($automation_results),
        'successful' => $successful_rules,
        'failed' => $failed_rules,
        'total_affected' => $total_affected,
        'execution_time' => $execution_time
    ])]);
    
    $response = [
        'success' => true,
        'message' => 'Automation completed successfully',
        'details' => [
            'rules_processed' => count($automation_results),
            'successful_rules' => $successful_rules,
            'failed_rules' => $failed_rules,
            'workflows_affected' => $total_affected,
            'execution_time' => $execution_time
        ],
        'output' => $output
    ];
    
} catch (Exception $e) {
    $output[] = "ERROR: " . $e->getMessage();
    
    // Log error to database if possible
    try {
        $pdo->prepare("
            INSERT INTO automation_logs (rule_id, action_type, error_message, success, executed_at)
            VALUES (0, 'system_error', ?, 0, NOW())
        ")->execute([$e->getMessage()]);
    } catch (Exception $logError) {
        $output[] = "Failed to log error to database: " . $logError->getMessage();
    }
    
    $response = [
        'success' => false,
        'message' => 'Automation failed',
        'error' => $e->getMessage(),
        'output' => $output
    ];
}

// Output results
if (isset($_SERVER['HTTP_HOST'])) {
    // Web request - return JSON
    echo json_encode($response, JSON_PRETTY_PRINT);
} else {
    // CLI request - output text
    foreach ($output as $line) {
        echo $line . "\n";
    }
    
    if (!$response['success']) {
        exit(1); // Exit with error code for cron monitoring
    }
}

/**
 * Cron job setup instructions:
 * 
 * 1. Add to crontab (every 15 minutes):
 *    */15 * * * * /usr/bin/php /path/to/bizautopro/automation_runner.php >> /var/log/automation.log 2>&1
 * 
 * 2. For web-based execution (if CLI not available):
 *    */15 * * * * /usr/bin/curl -s "https://yoursite.com/automation_runner.php?key=bizautopro_automation_2025" >> /var/log/automation.log 2>&1
 * 
 * 3. For more frequent overdue checking (every 5 minutes):
 *    */5 * * * * /usr/bin/php /path/to/bizautopro/automation_runner.php >> /var/log/automation.log 2>&1
 * 
 * 4. Daily cleanup (remove old logs):
 *    0 2 * * * /usr/bin/mysql -u username -p'password' -e "DELETE FROM automation_logs WHERE executed_at < DATE_SUB(NOW(), INTERVAL 30 DAY);" database_name
 */
?>