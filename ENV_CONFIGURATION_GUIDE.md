# 🔧 .ENV CONFIGURATION GUIDE

## 📋 Current Status

Your `.env` file has been updated with all necessary configuration options. Here's how to set everything up:

---

## 🚀 QUICK START (For Testing Locally)

### Option 1: Test Without Real SMS/Email (Current Setup)
✅ **Already configured!** You can test immediately with:
- Emails logged to `storage/logs/laravel.log`
- SMS codes shown in API response (local mode)

**No additional setup needed for local testing!**

---

## 📧 EMAIL CONFIGURATION OPTIONS

### Current Setting: `MAIL_MAILER=log`
- Emails are written to `storage/logs/laravel.log`
- Good for testing without sending real emails
- **No cost, no setup required**

### Recommended: Mailtrap (Free Testing)
**Best for development - catches all emails in fake inbox**

1. **Sign up**: https://mailtrap.io (free account)
2. **Get credentials** from your inbox
3. **Update .env**:
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=sandbox.smtp.mailtrap.io
   MAIL_PORT=2525
   MAIL_USERNAME=1a2b3c4d5e6f7g
   MAIL_PASSWORD=1a2b3c4d5e6f7g
   MAIL_ENCRYPTION=tls
   ```
4. **Test**: All emails go to Mailtrap inbox (won't spam real users)

### Option: Gmail (Quick Setup)
**For testing with real email delivery**

1. **Enable 2FA** on your Gmail account
2. **Create App Password**:
   - Go to Google Account → Security
   - 2-Step Verification → App passwords
   - Generate password for "Mail"
3. **Update .env**:
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@gmail.com
   MAIL_PASSWORD=xxxx xxxx xxxx xxxx
   MAIL_ENCRYPTION=tls
   ```

### Option: SendGrid (Production)
**Free tier: 100 emails/day**

1. **Sign up**: https://sendgrid.com
2. **Create API Key**:
   - Settings → API Keys → Create API Key
   - Choose "Full Access"
   - Copy the key (shown only once!)
3. **Update .env**:
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.sendgrid.net
   MAIL_PORT=587
   MAIL_USERNAME=apikey
   MAIL_PASSWORD=SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   MAIL_ENCRYPTION=tls
   ```

---

## 📱 TWILIO SMS CONFIGURATION

### Current Setting: Empty (Development Mode)
- SMS codes shown in API response
- Good for testing without sending real SMS
- **No cost, no setup required**

### Setup Twilio (For Real SMS)

#### Step 1: Create Account
1. Go to https://www.twilio.com/
2. Sign up (free trial gives $15 credit)
3. Verify your email and phone

#### Step 2: Get Credentials
1. Login to **Twilio Console**: https://console.twilio.com/
2. Find on dashboard:
   - **Account SID** (starts with "AC")
   - **Auth Token** (click to reveal)
3. Get a phone number:
   - Phone Numbers → Manage → Buy a Number
   - Choose any number (free on trial)
   - Note the number (format: +1234567890)

#### Step 3: Update .env
```env
TWILIO_SID=your_twilio_account_sid
TWILIO_TOKEN=your_twilio_auth_token
TWILIO_FROM=your_twilio_phone_number
```

#### Step 4: Test
```bash
php artisan tinker

$twilio = app(\App\Services\TwilioService::class);
$twilio->sendSMS('+233123456789', 'Test from CrowdBricks!');
```

**Trial Limitations:**
- Can only send to verified phone numbers
- To verify: Twilio Console → Phone Numbers → Verified Caller IDs
- Upgrade to paid account to send to any number

---

## 🧪 TESTING CONFIGURATION

### Test Email (Without Mailtrap/Gmail)
Current setup logs to `storage/logs/laravel.log`. To test:

```bash
php artisan tinker

$user = User::first();
Mail::to($user->email)->send(new \App\Mail\UserApprovedMail($user));

# Check the log file
tail storage/logs/laravel.log
```

### Test Email (With Mailtrap)
After configuring Mailtrap:
1. Send test email (same command above)
2. Open Mailtrap inbox
3. See beautiful HTML email!

### Test SMS (Development Mode)
Without Twilio configured:
```bash
# API will return code in response
POST /api/v1/user/phone/send-verification

Response:
{
  "message": "Verification code generated (dev mode)",
  "code": "123456"  // ← Code shown for testing
}
```

### Test SMS (With Twilio)
After configuring Twilio:
```bash
# API sends real SMS
POST /api/v1/user/phone/send-verification

Response:
{
  "message": "Verification code sent to ****6789"
  // Code NOT in response - check your phone!
}
```

---

## 🔍 VERIFY YOUR CONFIGURATION

### Check Email Config
```bash
php artisan tinker
config('mail.mailers.smtp')
```

### Check Twilio Config
```bash
php artisan tinker
config('services.twilio')
```

### Test Twilio Connection
```bash
php artisan tinker
$twilio = app(\App\Services\TwilioService::class);
$twilio->isConfigured()  // Should return true/false
```

### Send Test Email
```bash
php artisan tinker
Mail::raw('Test email from CrowdBricks', function($message) {
    $message->to('your-email@example.com')->subject('Test');
});
```

---

## 📊 CONFIGURATION MATRIX

| Feature | Current | For Testing | For Production |
|---------|---------|-------------|----------------|
| **Email** | `log` | Mailtrap | SendGrid/Mailgun |
| **SMS** | Dev mode | Dev mode | Twilio |
| **Cost** | $0 | $0 | ~$40/month SMS |

---

## 🎯 RECOMMENDED SETUP BY STAGE

### Stage 1: Local Development (Current)
```env
MAIL_MAILER=log
TWILIO_SID=          # Leave empty
TWILIO_TOKEN=        # Leave empty
TWILIO_FROM=         # Leave empty
```
✅ No setup needed, works immediately

### Stage 2: Testing with Team
```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
# ... Mailtrap credentials

TWILIO_SID=AC...     # Add Twilio trial
TWILIO_TOKEN=...
TWILIO_FROM=+1...
```
✅ Real email preview, real SMS to verified numbers

### Stage 3: Production
```env
APP_ENV=production
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
# ... SendGrid credentials

TWILIO_SID=AC...     # Upgraded Twilio account
TWILIO_TOKEN=...
TWILIO_FROM=+1...
```
✅ Real emails + SMS to all users

---

## ⚠️ IMPORTANT NOTES

### Development vs Production Behavior

**Development (APP_ENV=local):**
- ✅ Verification codes shown in API response
- ✅ Can test without real SMS
- ✅ Easier debugging

**Production (APP_ENV=production):**
- 🔒 Verification codes NOT in API response
- 🔒 Codes sent via SMS only
- 🔒 More secure

### Gmail App Password
If using Gmail, you MUST use app password, not your regular password:
1. Enable 2FA first
2. Generate app password
3. Use 16-character password (with spaces removed)

### Twilio Trial
- ✅ $15 free credit
- ⚠️ Can only send to verified numbers
- ⚠️ Messages include "Sent from a Twilio trial account"
- 💰 Upgrade to remove restrictions

### Email Delivery
If emails go to spam:
1. Verify sender email
2. Set up SPF/DKIM records (SendGrid helps)
3. Use professional email service (not Gmail)

---

## 🔧 TROUBLESHOOTING

### Emails not sending?
```bash
# Check mail configuration
php artisan config:show mail

# Clear config cache
php artisan config:clear

# Check logs
tail -f storage/logs/laravel.log | grep -i mail
```

### SMS not sending?
```bash
# Check Twilio config
php artisan tinker
config('services.twilio')

# Test Twilio service
$twilio = app(\App\Services\TwilioService::class);
$twilio->sendSMS('+1234567890', 'test');

# Check logs
tail -f storage/logs/laravel.log | grep -i sms
```

### Rate limiting not working?
```bash
# Clear cache
php artisan cache:clear

# Verify routes
php artisan route:list | grep throttle
```

---

## 📞 QUICK REFERENCE

### Mailtrap
- **URL**: https://mailtrap.io
- **Cost**: Free
- **Purpose**: Email testing (catches all emails)

### Gmail SMTP
- **Host**: smtp.gmail.com
- **Port**: 587
- **Encryption**: TLS
- **Requires**: App password

### SendGrid
- **URL**: https://sendgrid.com
- **Cost**: Free tier (100/day)
- **Purpose**: Production emails

### Twilio
- **URL**: https://www.twilio.com
- **Cost**: $15 trial, then pay-as-go
- **Purpose**: Real SMS delivery

---

## ✅ CHECKLIST

Before going to production:

- [ ] Email configured (SendGrid or Mailgun)
- [ ] Twilio configured with valid credentials
- [ ] Test email sending to real inbox
- [ ] Test SMS sending to real phone
- [ ] Set `APP_ENV=production`
- [ ] Verify codes NOT in API response (production)
- [ ] Set `FRONTEND_URL=https://yourdomain.com`
- [ ] Monitor costs for first week

---

## 🎉 YOU'RE ALL SET!

### Current Setup:
- ✅ Email: Logging to file (ready for testing)
- ✅ SMS: Development mode (codes in response)
- ✅ All features working locally

### To Enable Real Delivery:
1. **For Email**: Configure Mailtrap (5 min setup)
2. **For SMS**: Configure Twilio (10 min setup)

### Need Help?
- 📖 Full guide: `PRODUCTION_SETUP.md`
- 📋 Quick ref: `QUICK_REFERENCE.md`
- 📊 Summary: `PRODUCTION_FEATURES_SUMMARY.md`

**Everything is configured and ready to test!** 🚀
