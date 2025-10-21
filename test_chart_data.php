<?php
require 'config.php';

// Get daily revenue data for charts
$daily_revenue_data = [];
$daily_revenue_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_revenue_labels[] = date('M j', strtotime($date));
    
    $revenue_query = $pdo->prepare("SELECT COALESCE(SUM(price * quantity), 0) FROM outbound_sales WHERE DATE(deduction_date) = ?");
    $revenue_query->execute([$date]);
    $daily_revenue_data[] = $revenue_query->fetchColumn() ?: 0;
}

// Get leads data for charts
$leads_data = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM leads 
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

echo "Daily Revenue Labels: " . json_encode($daily_revenue_labels) . "\n";
echo "Daily Revenue Data: " . json_encode(array_map('floatval', $daily_revenue_data)) . "\n";
echo "Leads Data Keys: " . json_encode(array_keys($leads_data)) . "\n";
echo "Leads Data Values: " . json_encode(array_values($leads_data)) . "\n";

echo "\n=== JavaScript Output ===\n";
echo "const realChartData = {\n";
echo "    revenue: {\n";
echo "        labels: " . json_encode($daily_revenue_labels) . ",\n";
echo "        datasets: [{\n";
echo "            label: 'Daily Revenue (₦)',\n";
echo "            data: " . json_encode(array_map('floatval', $daily_revenue_data)) . ",\n";
echo "            borderColor: '#4f46e5',\n";
echo "            backgroundColor: '#4f46e520',\n";
echo "            tension: 0.4,\n";
echo "            fill: true\n";
echo "        }]\n";
echo "    },\n";
echo "    leads: {\n";
echo "        labels: " . json_encode(array_keys($leads_data)) . ",\n";
echo "        datasets: [{\n";
echo "            data: " . json_encode(array_values($leads_data)) . ",\n";
echo "            backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#ef4444']\n";
echo "        }]\n";
echo "    }\n";
echo "};\n";
?>