<?php
session_start();
require 'config.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_GET['id'];
$pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?")->execute([$user_id]);

$_SESSION['success'] = "User approved successfully!";
header("Location: view_pending_users.php");
exit;
?>