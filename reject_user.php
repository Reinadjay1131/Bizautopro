<?php
session_start();
require 'config.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_GET['id'];
$pdo->prepare("DELETE FROM users WHERE id = ? AND status = 'pending'")->execute([$user_id]);

$_SESSION['success'] = "User registration rejected!";
header("Location: view_pending_users.php");
exit;
?>