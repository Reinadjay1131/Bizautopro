<?php
require 'config.php';

echo "=== ANALYTICS DATA CHECK ===\n";

// Check if we have any outbound_sales data
$total_sales = $pdo->query('SELECT COUNT(*) FROM outbound_sales')->fetchColumn();
echo 'Total outbound_sales records: ' . $total_sales . "\n";

if ($total_sales > 0) {
    echo "\nSample outbound_sales data:\n";
    $sample = $pdo->query('SELECT deduction_date, price, quantity FROM outbound_sales ORDER BY deduction_date DESC LIMIT 3')->fetchAll();
    foreach ($sample as $row) {
        echo '  Date: ' . $row['deduction_date'] . ', Price: ₦' . $row['price'] . ', Qty: ' . $row['quantity'] . "\n";
    }
}

// Check last 7 days revenue
echo "\nLast 7 days revenue:\n";
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $revenue_query = $pdo->prepare('SELECT COALESCE(SUM(price * quantity), 0) FROM outbound_sales WHERE DATE(deduction_date) = ?');
    $revenue_query->execute([$date]);
    $revenue = $revenue_query->fetchColumn();
    echo '  ' . $date . ': ₦' . number_format($revenue) . "\n";
}

// Check leads data
echo "\nLeads by status:\n";
$leads = $pdo->query('SELECT status, COUNT(*) as count FROM leads GROUP BY status')->fetchAll();
foreach ($leads as $lead) {
    echo '  ' . $lead['status'] . ': ' . $lead['count'] . "\n";
}
?>