<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Approved</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 20px; }
        .button { display: inline-block; background: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { background: #6c757d; color: white; padding: 10px; text-align: center; font-size: 12px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 10px 0; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Welcome to BizAutoPro!</h1>
            <h2>Account Approved</h2>
        </div>
        
        <div class="content">
            <div class="success">
                <strong>✅ Congratulations!</strong> Your account has been approved and is now active.
            </div>
            
            <h3>Hello <?= htmlspecialchars($username) ?>!</h3>
            
            <p>Great news! Your BizAutoPro account registration has been approved by our administrators. You can now access the system and start using all available features.</p>
            
            <h4>What you can do now:</h4>
            <ul>
                <li>🔐 Log in to your account</li>
                <li>🏗️ Access your role-specific dashboard</li>
                <li>📊 Manage leads, inventory, and workflows</li>
                <li>👥 Collaborate with your team members</li>
                <li>📱 Use the API for third-party integrations</li>
            </ul>
            
            <div style="text-align: center; margin: 20px 0;">
                <a href="<?= $login_link ?>" class="button">🚀 Login to BizAutoPro</a>
            </div>
            
            <h4>Getting Started Tips:</h4>
            <ul>
                <li>Update your profile information in the settings</li>
                <li>Familiarize yourself with the dashboard features</li>
                <li>Check out the help documentation</li>
                <li>Contact support if you need assistance</li>
            </ul>
            
            <p><strong>Need Help?</strong><br>
            If you have any questions or need assistance, please don't hesitate to contact our support team or your system administrator.</p>
            
            <p><em>Welcome aboard and thank you for choosing BizAutoPro!</em></p>
        </div>
        
        <div class="footer">
            <p>© 2025 Created by NOYB FUNDAMENTAL | BizAutoPro System</p>
            <p>This is an automated welcome message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>