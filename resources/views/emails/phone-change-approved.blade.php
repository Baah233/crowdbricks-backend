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
        .phone-box {
            background: white;
            border-left: 4px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            text-align: center;
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
        .info-box {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✅ Phone Number Updated!</h1>
    </div>
    
    <div class="content">
        <p>Dear {{ $userName }},</p>
        
        <p>Your phone number change request has been <strong>approved</strong> by our admin team.</p>
        
        <div class="phone-box">
            <h3 style="margin-top: 0;">Your New Phone Number</h3>
            <div class="phone-number">{{ $newPhone }}</div>
        </div>
        
        <div class="info-box">
            <strong>🔔 Important:</strong> For security reasons, you must verify your new phone number before it can be used for transactions and SMS notifications.
        </div>
        
        <h3>Next Steps:</h3>
        <ol>
            <li><strong>Login</strong> to your CrowdBricks account</li>
            <li><strong>Go to your Profile</strong> page</li>
            <li><strong>Click "Verify Now"</strong> to receive a verification code</li>
            <li><strong>Enter the code</strong> to complete verification</li>
        </ol>
        
        <div style="text-align: center;">
            <a href="{{ config('app.frontend_url') }}/profile" class="button">Verify Phone Number</a>
        </div>
        
        <p><strong>Why verify?</strong></p>
        <ul>
            <li>Enable SMS notifications for important updates</li>
            <li>Secure your transactions with phone-based verification</li>
            <li>Increase your profile completion score</li>
        </ul>
        
        <p>If you did not request this change, please contact our support team immediately.</p>
        
        <p>Best regards,<br>
        <strong>The CrowdBricks Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated message from CrowdBricks. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} CrowdBricks. All rights reserved.</p>
    </div>
</body>
</html>
