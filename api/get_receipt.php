<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$receipt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($receipt_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid receipt ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE receipt_id = ?");
    $stmt->execute([$receipt_id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($receipt) {
        echo json_encode(['success' => true, 'receipt' => $receipt]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Receipt not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
