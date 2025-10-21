<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New User Registration</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 20px; }
        .button { display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { background: #6c757d; color: white; padding: 10px; text-align: center; font-size: 12px; }
        .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏢 BizAutoPro</h1>
            <h2>New User Registration</h2>
        </div>
        
        <div class="content">
            <div class="alert">
                <strong>⚠️ Action Required:</strong> A new user has registered and requires approval.
            </div>
            
            <h3>Registration Details:</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="background: #e9ecef;">
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Username:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($username) ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Email:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($email) ?></td>
                </tr>
                <tr style="background: #e9ecef;">
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Requested Role:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= htmlspecialchars($role) ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd;"><strong>Registration Time:</strong></td>
                    <td style="padding: 8px; border: 1px solid #ddd;"><?= date('Y-m-d H:i:s') ?></td>
                </tr>
            </table>
            
            <div style="text-align: center; margin: 20px 0;">
                <a href="<?= $approval_link ?>" class="button">📋 Review Registration</a>
            </div>
            
            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>Review the user's credentials and role request</li>
                <li>Verify their authorization for the requested role</li>
                <li>Approve or reject the registration through the admin panel</li>
            </ul>
            
            <p><em>This is an automated notification from the BizAutoPro system.</em></p>
        </div>
        
        <div class="footer">
            <p>© 2025 Created by NOYB FUNDAMENTAL | BizAutoPro System</p>
            <p>This email was sent to admin users for user registration approval.</p>
        </div>
    </div>
</body>
</html>