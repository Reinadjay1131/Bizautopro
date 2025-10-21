<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Low Stock Alert</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 20px; }
        .button { display: inline-block; background: #fd7e14; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { background: #6c757d; color: white; padding: 10px; text-align: center; font-size: 12px; }
        .alert { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ BizAutoPro Alert</h1>
            <h2>Low Stock Warning</h2>
        </div>
        
        <div class="content">
            <div class="alert">
                <strong>🚨 Urgent:</strong> Inventory levels have reached the reorder threshold.
            </div>
            
            <h3>Product Details:</h3>
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr style="background: #e9ecef;">
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Product Name:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($product_name) ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Current Stock:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd; color: #dc3545; font-weight: bold;"><?= $current_stock ?> units</td>
                </tr>
                <tr style="background: #e9ecef;">
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Reorder Level:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= $reorder_level ?> units</td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Alert Time:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= date('Y-m-d H:i:s') ?></td>
                </tr>
            </table>
            
            <h4>📋 Recommended Actions:</h4>
            <ul>
                <li>Review current stock levels</li>
                <li>Check pending orders from suppliers</li>
                <li>Place immediate reorder if necessary</li>
                <li>Update reorder levels if needed</li>
                <li>Monitor sales trends for this product</li>
            </ul>
            
            <div style="text-align: center; margin: 20px 0;">
                <a href="<?= $inventory_link ?>" class="button">📦 Manage Inventory</a>
            </div>
            
            <p><strong>Impact:</strong> Low stock levels may lead to:</p>
            <ul>
                <li>❌ Stockouts and lost sales</li>
                <li>📉 Customer dissatisfaction</li>
                <li>⏰ Delayed order fulfillment</li>
                <li>💰 Lost revenue opportunities</li>
            </ul>
            
            <p><em>This is an automated alert from the BizAutoPro inventory management system.</em></p>
        </div>
        
        <div class="footer">
            <p>© 2025 Created by NOYB FUNDAMENTAL | BizAutoPro System</p>
            <p>This alert was sent to inventory managers and administrators.</p>
        </div>
    </div>
</body>
</html>