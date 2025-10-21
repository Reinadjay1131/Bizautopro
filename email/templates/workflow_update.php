<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflow Update</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #6f42c1; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 20px; }
        .button { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { background: #6c757d; color: white; padding: 10px; text-align: center; font-size: 12px; }
        .update { background: #e2e3f0; border: 1px solid #c8c9e3; padding: 15px; border-radius: 5px; margin: 10px 0; color: #383d41; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 BizAutoPro</h1>
            <h2>Workflow Update</h2>
        </div>
        
        <div class="content">
            <div class="update">
                <strong>📋 Workflow Status Changed:</strong> There's been an update to your assigned workflow.
            </div>
            
            <h3>Workflow Details:</h3>
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr style="background: #e9ecef;">
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Workflow Title:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;"><?= htmlspecialchars($workflow_title) ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Current Status:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;">
                        <span style="background: #28a745; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px;">
                            <?= strtoupper(htmlspecialchars($workflow_status)) ?>
                        </span>
                    </td>
                </tr>
                <tr style="background: #e9ecef;">
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Update Time:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= date('Y-m-d H:i:s') ?></td>
                </tr>
            </table>
            
            <h4>🎯 Required Actions:</h4>
            <ul>
                <li>📋 <strong>Review Changes:</strong> Check the updated workflow details</li>
                <li>✅ <strong>Update Progress:</strong> Mark completed tasks</li>
                <li>👥 <strong>Coordinate Team:</strong> Communicate with team members</li>
                <li>📅 <strong>Check Deadlines:</strong> Verify upcoming milestones</li>
                <li>📊 <strong>Report Status:</strong> Update project managers</li>
            </ul>
            
            <div style="text-align: center; margin: 20px 0;">
                <a href="<?= $workflow_link ?>" class="button">🔍 View Workflow</a>
            </div>
            
            <h4>📊 Workflow Status Guide:</h4>
            <ul>
                <li>🔵 <strong>PENDING:</strong> Waiting to start</li>
                <li>🟡 <strong>IN_PROGRESS:</strong> Currently being worked on</li>
                <li>🟢 <strong>COMPLETED:</strong> Successfully finished</li>
                <li>🔴 <strong>ON_HOLD:</strong> Temporarily paused</li>
                <li>⚫ <strong>CANCELLED:</strong> No longer needed</li>
            </ul>
            
            <p><strong>Team Collaboration:</strong><br>
            Use the workflow management system to collaborate with team members, share updates, and track progress efficiently.</p>
            
            <p><em>This notification was generated automatically by the BizAutoPro workflow management system.</em></p>
        </div>
        
        <div class="footer">
            <p>© 2025 Created by NOYB FUNDAMENTAL | BizAutoPro System</p>
            <p>This update was sent regarding your assigned workflow.</p>
        </div>
    </div>
</body>
</html>