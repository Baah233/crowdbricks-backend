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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        .success-box {
            background: white;
            border-left: 4px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            text-align: center;
        }
        .checkmark {
            font-size: 60px;
            color: #10b981;
            margin: 20px 0;
        }
        .phone-number {
            font-size: 24px;
            font-weight: bold;
            color: #10b981;
            padding: 10px;
            background: #ecfdf5;
            border-radius: 5px;
            margin: 10px 0;
        }
        .button {
            display: inline-block;
            background: #10b981;
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
        .benefit {
            background: #ecfdf5;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 3px solid #10b981;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✅ Phone Verified!</h1>
    </div>
    
    <div class="content">
        <p>Dear {{ $userName }},</p>
        
        <div class="success-box">
            <div class="checkmark">✓</div>
            <h2 style="color: #10b981; margin: 10px 0;">Successfully Verified</h2>
            <div class="phone-number">{{ $phone }}</div>
            <p style="color: #666; margin-top: 15px;">
                Your phone number has been verified and is now active on your account.
            </p>
        </div>
        
        <h3>🎉 You've Unlocked:</h3>
        
        <div class="benefit">
            <strong>📱 SMS Notifications</strong>
            <p style="margin: 5px 0 0 0;">Receive instant alerts for investments, approvals, and important updates</p>
        </div>
        
        <div class="benefit">
            <strong>🔒 Enhanced Security</strong>
            <p style="margin: 5px 0 0 0;">Phone-based verification for secure transactions</p>
        </div>
        
        <div class="benefit">
            <strong>📊 Profile Completion</strong>
            <p style="margin: 5px 0 0 0;">Your profile completion score has increased by 30%</p>
        </div>
        
        <div class="benefit">
            <strong>💼 Full Access</strong>
            <p style="margin: 5px 0 0 0;">All platform features are now available to you</p>
        </div>
        
        <h3>What's Next?</h3>
        <ul>
            <li>Complete your profile for even more benefits</li>
            <li>Upload a profile picture (+20% completion)</li>
            <li>Enable Two-Factor Authentication (+20% completion)</li>
            <li>Start exploring investment opportunities</li>
        </ul>
        
        <div style="text-align: center;">
            <a href="{{ config('app.frontend_url') }}/dashboard/investor" class="button">Go to Dashboard</a>
        </div>
        
        <p style="margin-top: 30px;">You're all set! Your verified phone number will be used for:</p>
        <ul>
            <li>Investment confirmation messages</li>
            <li>Security alerts and notifications</li>
            <li>Account activity updates</li>
            <li>Important announcements</li>
        </ul>
        
        <p>Thank you for securing your CrowdBricks account!</p>
        
        <p>Best regards,<br>
        <strong>The CrowdBricks Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated message from CrowdBricks. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} CrowdBricks. All rights reserved.</p>
    </div>
</body>
</html>
