<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Assignment</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #17a2b8; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 20px; }
        .button { display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { background: #6c757d; color: white; padding: 10px; text-align: center; font-size: 12px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #0c5460; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 BizAutoPro</h1>
            <h2>New Lead Assignment</h2>
        </div>
        
        <div class="content">
            <div class="info">
                <strong>📢 New Opportunity:</strong> A new lead has been assigned to you.
            </div>
            
            <h3>Lead Information:</h3>
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr style="background: #e9ecef;">
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Lead ID:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">#<?= $lead_id ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Company:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;"><?= htmlspecialchars($company_name) ?></td>
                </tr>
                <tr style="background: #e9ecef;">
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Contact Person:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($contact_person) ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Assignment Date:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= date('Y-m-d H:i:s') ?></td>
                </tr>
            </table>
            
            <h4>🎯 Next Steps:</h4>
            <ol>
                <li><strong>Review lead details</strong> - Check all provided information</li>
                <li><strong>Initial contact</strong> - Reach out within 24 hours</li>
                <li><strong>Qualify the lead</strong> - Assess potential and requirements</li>
                <li><strong>Update status</strong> - Keep the system current</li>
                <li><strong>Schedule follow-up</strong> - Plan next interactions</li>
            </ol>
            
            <div style="text-align: center; margin: 20px 0;">
                <a href="<?= $lead_link ?>" class="button">📋 View Lead Details</a>
            </div>
            
            <h4>💡 Best Practices:</h4>
            <ul>
                <li>🕐 <strong>Quick Response:</strong> Contact within 24 hours for best results</li>
                <li>📝 <strong>Take Notes:</strong> Document all interactions</li>
                <li>🔄 <strong>Update Status:</strong> Keep the team informed of progress</li>
                <li>📞 <strong>Follow Up:</strong> Maintain regular contact schedule</li>
                <li>🤝 <strong>Close Deal:</strong> Move qualified leads through the sales funnel</li>
            </ul>
            
            <p><strong>Need Support?</strong><br>
            Contact your sales manager or use the team collaboration features if you need assistance with this lead.</p>
            
            <p><em>This lead assignment was generated automatically by the BizAutoPro CRM system.</em></p>
        </div>
        
        <div class="footer">
            <p>© 2025 Created by NOYB FUNDAMENTAL | BizAutoPro System</p>
            <p>This notification was sent regarding your lead assignment.</p>
        </div>
    </div>
</body>
</html>