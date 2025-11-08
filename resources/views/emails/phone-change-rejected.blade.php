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
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
            border-left: 4px solid #ef4444;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .phone-number {
            font-size: 18px;
            padding: 5px 0;
        }
        .rejected {
            color: #ef4444;
            text-decoration: line-through;
        }
        .kept {
            color: #10b981;
            font-weight: bold;
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
        .info-box {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>❌ Phone Change Request Rejected</h1>
    </div>
    
    <div class="content">
        <p>Dear {{ $userName }},</p>
        
        <p>We're writing to inform you that your phone number change request has been <strong>rejected</strong> by our admin team.</p>
        
        <div class="phone-box">
            <h3 style="margin-top: 0;">Phone Number Status</h3>
            <div class="phone-number rejected">
                <strong>Requested:</strong> {{ $requestedPhone }}
            </div>
            <div class="phone-number kept">
                <strong>Current (Active):</strong> {{ $currentPhone ?: 'Not set' }}
            </div>
        </div>
        
        <div class="info-box">
            <strong>ℹ️ What this means:</strong> Your phone number remains unchanged. The requested change to {{ $requestedPhone }} has not been applied to your account.
        </div>
        
        <h3>Common Reasons for Rejection:</h3>
        <ul>
            <li>Invalid or incorrect phone number format</li>
            <li>Phone number already associated with another account</li>
            <li>Suspicious or unusual change request</li>
            <li>Incomplete verification documentation</li>
            <li>Security concerns</li>
        </ul>
        
        <h3>What You Can Do:</h3>
        <ol>
            <li><strong>Contact Support</strong> to understand the reason for rejection</li>
            <li><strong>Submit a new request</strong> with correct information if needed</li>
            <li><strong>Keep your current number</strong> verified and active</li>
        </ol>
        
        <div style="text-align: center;">
            <a href="{{ config('app.frontend_url') }}/help" class="button">Contact Support</a>
        </div>
        
        <p>If you believe this rejection was made in error or if you have questions, please don't hesitate to contact our support team.</p>
        
        <p>Best regards,<br>
        <strong>The CrowdBricks Team</strong></p>
    </div>
    
    <div class="footer">
        <p>This is an automated message from CrowdBricks. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} CrowdBricks. All rights reserved.</p>
    </div>
</body>
</html>
