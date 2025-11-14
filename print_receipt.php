<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$receipt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($receipt_id <= 0) {
    die('Invalid receipt ID');
}

$stmt = $pdo->prepare("SELECT * FROM receipts WHERE receipt_id = ?");
$stmt->execute([$receipt_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die('Receipt not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo htmlspecialchars($receipt['receipt_number']); ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }
        .receipt-container {
            border: 2px solid #000;
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px dashed #000;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 5px 0;
        }
        .receipt-info {
            margin: 20px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 5px 0;
        }
        .info-label {
            font-weight: bold;
            width: 40%;
        }
        .info-value {
            width: 60%;
            text-align: right;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 20px 0;
        }
        .total-section {
            background: #f0f0f0;
            padding: 15px;
            margin: 20px 0;
            border: 1px solid #000;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px dashed #000;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
        }
        .badge-sale { background: #28a745; color: white; }
        .badge-damaged { background: #dc3545; color: white; }
        .badge-internal { background: #ffc107; color: black; }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">
            🖨️ Print Receipt
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; margin-left: 10px;">
            ❌ Close
        </button>
    </div>

    <div class="receipt-container">
        <div class="header">
            <h1>📦 BizAutoPro</h1>
            <p>Inventory Management System</p>
            <p>Deduction Receipt</p>
        </div>

        <div class="receipt-info">
            <div class="info-row">
                <span class="info-label">Receipt Number:</span>
                <span class="info-value"><?php echo htmlspecialchars($receipt['receipt_number']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date & Time:</span>
                <span class="info-value"><?php echo date('F d, Y h:i A', strtotime($receipt['created_at'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Transaction Type:</span>
                <span class="info-value">
                    <span class="badge badge-<?php echo $receipt['transaction_type']; ?>">
                        <?php echo strtoupper($receipt['transaction_type']); ?>
                    </span>
                </span>
            </div>
        </div>

        <div class="divider"></div>

        <div class="receipt-info">
            <div class="info-row">
                <span class="info-label">Product Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($receipt['product_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">SKU:</span>
                <span class="info-value"><?php echo htmlspecialchars($receipt['sku']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Quantity Deducted:</span>
                <span class="info-value"><?php echo number_format($receipt['quantity']); ?></span>
            </div>

            <?php if ($receipt['unit_price'] > 0): ?>
            <div class="info-row">
                <span class="info-label">Unit Price:</span>
                <span class="info-value">$<?php echo number_format($receipt['unit_price'], 2); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($receipt['total_amount'] > 0): ?>
        <div class="total-section">
            <div class="info-row" style="font-size: 18px;">
                <span class="info-label">TOTAL AMOUNT:</span>
                <span class="info-value">$<?php echo number_format($receipt['total_amount'], 2); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="divider"></div>

        <div class="receipt-info">
            <div class="info-row">
                <span class="info-label">Stock Before:</span>
                <span class="info-value"><?php echo number_format($receipt['stock_before']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Stock After:</span>
                <span class="info-value"><?php echo number_format($receipt['stock_after']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Deducted By:</span>
                <span class="info-value"><?php echo htmlspecialchars($receipt['deducted_by_name']); ?></span>
            </div>
            <?php if (!empty($receipt['reason'])): ?>
            <div class="info-row">
                <span class="info-label">Reason:</span>
                <span class="info-value"><?php echo htmlspecialchars($receipt['reason']); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>This is a computer-generated receipt.</p>
            <p>No signature required.</p>
            <p>Thank you for using BizAutoPro!</p>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
