<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .verification-box {
            background: white;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .verification-id {
            font-size: 24px;
            font-weight: bold;
            color: #3b82f6;
            text-align: center;
            padding: 10px;
            background: #eff6ff;
            border-radius: 5px;
            margin: 10px 0;
        }
        .button {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 12px;
        }
        .alert {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Account Approved!</h1>
    </div>
    
    <div class="content">
        <p>Dear {{ $userName }},</p>
        
        <p>Great news! Your CrowdBricks investor account has been approved by our admin team.</p>
        
        <div class="verification-box">
            <h3 style="margin-top: 0;">Your Verification ID</h3>
            <div class="verification-id">{{ $verificationId }}</div>
            <p style="text-align: center; color: #666; margin-bottom: 0;">
                <small>Keep this ID secure for future reference</small>
            </p>
        </div>
        
        <div class="alert">
            <strong>⚠️ Security Requirement:</strong> You must enable Two-Factor Authentication (2FA) before accessing your dashboard. This is a mandatory security measure to protect your investment account.
        </div>
        
        <h3>Next Steps:</h3>
        <ol>
            <li><strong>Login</strong> to your account using your credentials</li>
            <li><strong>Complete 2FA Setup</strong> when prompted (required)</li>
            <li><strong>Verify your phone number</strong> for SMS notifications</li>
            <li><strong>Complete your profile</strong> to unlock all features</li>
            <li><strong>Start investing</strong> in exciting projects!</li>
        </ol>
        
        <div style="text-align: center;">
            <a href="{{ config('app.frontend_url') }}/login" class="button">Login to Your Account</a>
        </div>
        
        <h3>Account Details:</h3>
        <ul>
            <li><strong>Email:</strong> {{ $email }}</li>
            <li><strong>Verification ID:</strong> {{ $verificationId }}</li>
            <li><strong>Status:</strong> Approved ✅</li>
        </ul>
        
        <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
        
        <p>Welcome to CrowdBricks! We're excited to have you on board.</p>
        
        <p>Best regards,<br>
        <strong>The CrowdBricks Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated message from CrowdBricks. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} CrowdBricks. All rights reserved.</p>
    </div>
</body>
</html>
