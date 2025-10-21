<?php
session_start();
require 'config.php';
require_once 'email/EmailService.php';
require_once 'email/FileEmailService.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_GET['id'];

// Get user details before approval
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user) {
    // Approve the user
    $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?")->execute([$user_id]);
    
    // Send welcome email (using file-based service for local testing)
    try {
        $emailService = new FileEmailService(); // Using file-based service for local testing
        $emailService->sendApprovalNotification($user['email'], $user['username']);
    } catch (Exception $emailError) {
        error_log("Welcome email failed: " . $emailError->getMessage());
    }
    
    $_SESSION['success'] = "User approved successfully! Welcome email sent.";
} else {
    $_SESSION['error'] = "User not found or already processed.";
}

header("Location: view_pending_users.php");
exit;
?>