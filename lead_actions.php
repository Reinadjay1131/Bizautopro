<?php
session_start();
require 'config.php';

// Authorization check - admin, manager, or sales can perform actions
if (!in_array($_SESSION['role'], ['admin', 'manager', 'sales'])) {
    $_SESSION['error'] = "Unauthorized access";
    header("Location: dashboard.php");
    exit;
}

// Verify action and ID parameters
if (!isset($_GET['action'])) {
    $_SESSION['error'] = "No action specified";
    header("Location: leads.php");
    exit;
}

$action = $_GET['action'];
$lead_id = $_GET['id'] ?? null;

if (!$lead_id) {
    $_SESSION['error'] = "No lead ID specified";
    header("Location: leads.php");
    exit;
}

try {
    // Get the lead first to verify it exists and check permissions
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch();

    if (!$lead) {
        throw new Exception("Lead not found");
    }

    // Check if user has permission to modify this lead
    $canEdit = ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager' || 
               $lead['assigned_to'] == $_SESSION['user_id']);

    switch ($action) {
        case 'convert':
            // Only qualified leads can be converted
            if ($lead['status'] !== 'qualified') {
                throw new Exception("Only qualified leads can be converted");
            }

            // Begin transaction
            $pdo->beginTransaction();

            // 1. Create customer record
            $stmt = $pdo->prepare("
                INSERT INTO customers 
                (name, email, phone, company, source, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $lead['name'],
                $lead['email'],
                $lead['phone'],
                $lead['company'] ?? null,
                $lead['source'],
                $_SESSION['user_id']
            ]);
            $customer_id = $pdo->lastInsertId();

            // 2. Update lead status to 'converted'
            $stmt = $pdo->prepare("
                UPDATE leads 
                SET status = 'converted', converted_at = NOW(), converted_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $lead_id]);

            // 3. Record the conversion in history
            $stmt = $pdo->prepare("
                INSERT INTO lead_history 
                (lead_id, user_id, action, details, timestamp) 
                VALUES (?, ?, 'converted', ?, NOW())
            ");
            $stmt->execute([
                $lead_id,
                $_SESSION['user_id'],
                "Converted to customer #$customer_id"
            ]);

            $pdo->commit();

            $_SESSION['success'] = "Lead successfully converted to customer #$customer_id";
            break;

        default:
            throw new Exception("Invalid action specified");
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to leads page
header("Location: leads.php");
exit;
?>