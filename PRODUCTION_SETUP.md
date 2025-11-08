# 🚀 Production Setup Guide - Twilio & Email Integration

## ✅ Implementation Complete

All production features have been successfully implemented:

### Features Implemented:
1. ✅ **Twilio SMS Integration** - Real SMS delivery for verification codes
2. ✅ **Laravel Mail Integration** - Email notifications for all events
3. ✅ **Code Expiration** - 15-minute expiration for security codes
4. ✅ **Rate Limiting** - Prevent SMS spam and abuse
5. ✅ **Security Hardening** - Verification codes hidden in production

---

## 📦 New Files Created

### Backend Services
- `app/Services/TwilioService.php` - Twilio SMS service wrapper

### Email Templates (Mailable Classes)
- `app/Mail/UserApprovedMail.php`
- `app/Mail/PhoneChangeApprovedMail.php`
- `app/Mail/PhoneChangeRejectedMail.php`
- `app/Mail/PhoneVerifiedMail.php`

### Email Views (Blade Templates)
- `resources/views/emails/user-approved.blade.php`
- `resources/views/emails/phone-change-approved.blade.php`
- `resources/views/emails/phone-change-rejected.blade.php`
- `resources/views/emails/phone-verified.blade.php`

### Database Migration
- `database/migrations/2025_11_06_232354_add_phone_verification_expiration_to_users.php`

---

## 🔧 Configuration Required

### 1. Twilio Setup (For Real SMS)

#### Step 1: Create Twilio Account
1. Go to https://www.twilio.com/
2. Sign up for a free trial account
3. Get $15 free credit (enough for ~500 SMS)

#### Step 2: Get Credentials
1. Login to Twilio Console: https://console.twilio.com/
2. Copy your **Account SID** and **Auth Token**
3. Get a Twilio phone number:
   - Go to Phone Numbers → Buy a Number
   - Choose a number (free on trial)
   - Note the number (format: +1234567890)

#### Step 3: Update .env File
```env
TWILIO_SID=your_twilio_account_sid
TWILIO_TOKEN=your_twilio_auth_token
TWILIO_FROM=your_twilio_phone_number
```

#### Step 4: Test SMS
```bash
# Run tinker
php artisan tinker

# Test SMS sending
$twilio = app(\App\Services\TwilioService::class);
$twilio->sendSMS('+233123456789', 'Test message from CrowdBricks!');
```

---

### 2. Email Configuration

#### Option A: Gmail SMTP (Development)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="CrowdBricks"
```

**Gmail App Password Setup:**
1. Go to Google Account Settings
2. Security → 2-Step Verification → App passwords
3. Generate app password for "Mail"
4. Use generated password in `.env`

#### Option B: Mailtrap (Testing)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@crowdbricks.com
MAIL_FROM_NAME="CrowdBricks"
```

**Mailtrap Setup:**
1. Go to https://mailtrap.io/
2. Sign up (free plan available)
3. Create inbox
4. Copy SMTP credentials

#### Option C: Production Email Service
For production, use:
- **SendGrid** (99k emails/month free)
- **Mailgun** (5k emails/month free)
- **Amazon SES** (62k emails/month free)

---

### 3. Frontend URL Configuration
```env
FRONTEND_URL=http://localhost:5173
```

Change in production to:
```env
FRONTEND_URL=https://crowdbricks.com
```

---

## 📊 Database Migration

Run the migration to add expiration field:
```bash
php artisan migrate
```

This adds `phone_verification_code_expires_at` column to users table.

---

## 🔒 Security Features Implemented

### 1. Code Expiration
- **Duration**: 15 minutes
- **Implementation**: Timestamp stored in database
- **Check**: Verified before code acceptance

### 2. Rate Limiting
| Endpoint | Limit | Purpose |
|----------|-------|---------|
| `/user/phone/change-request` | 3 per day | Prevent phone hijacking |
| `/user/phone/send-verification` | 5 per hour | Prevent SMS spam |
| `/user/phone/verify` | 10 per hour | Prevent brute force |

### 3. Code Security
- **Development**: Code returned in API response (local only)
- **Production**: Code sent ONLY via SMS (secure)
- **Environment Check**: `config('app.env') === 'local'`

---

## 📧 Email Notifications Integrated

### User Approved Email
- **Trigger**: Admin approves user account
- **Sent To**: User email
- **Also SMS**: If phone verified
- **Template**: `emails.user-approved`
- **Content**: Welcome message, verification ID, 2FA requirement

### Phone Change Approved Email
- **Trigger**: Admin approves phone change
- **Sent To**: User email
- **Also SMS**: To new phone number
- **Template**: `emails.phone-change-approved`
- **Content**: New phone number, verification instructions

### Phone Change Rejected Email
- **Trigger**: Admin rejects phone change
- **Sent To**: User email
- **Template**: `emails.phone-change-rejected`
- **Content**: Rejection notice, support contact

### Phone Verified Email
- **Trigger**: User successfully verifies phone
- **Sent To**: User email
- **Also SMS**: Confirmation to verified number
- **Template**: `emails.phone-verified`
- **Content**: Success message, unlocked features

---

## 🧪 Testing Guide

### Test SMS (Development Mode)
```bash
# Without Twilio configured
# Code appears in API response (local env only)

POST /api/v1/user/phone/send-verification
Authorization: Bearer {token}

Response:
{
  "message": "Verification code generated (dev mode)",
  "code": "123456",  // Only in local environment
  "expires_at": "2025-11-07 15:30:00"
}
```

### Test SMS (Production Mode)
```bash
# With Twilio configured
# Code sent via SMS only

POST /api/v1/user/phone/send-verification
Authorization: Bearer {token}

Response:
{
  "message": "Verification code sent to ****6789",
  "expires_at": "2025-11-07 15:30:00"
}

# Check your phone for SMS!
```

### Test Email
```bash
# Test in tinker
php artisan tinker

$user = User::first();
Mail::to($user->email)->send(new \App\Mail\UserApprovedMail($user));
```

### Test Rate Limiting
```bash
# Send verification 6 times in 1 hour
# 6th request should fail with 429 Too Many Requests

for i in {1..6}; do
  curl -X POST http://127.0.0.1:8001/api/v1/user/phone/send-verification \
    -H "Authorization: Bearer $TOKEN"
done
```

### Test Code Expiration
```bash
# 1. Send verification code
POST /api/v1/user/phone/send-verification

# 2. Wait 16 minutes (or update expires_at in database to past)
UPDATE users SET phone_verification_code_expires_at = NOW() - INTERVAL 1 MINUTE WHERE id = 1;

# 3. Try to verify
POST /api/v1/user/phone/verify
Body: { "code": "123456" }

# Expected Response:
{
  "message": "Verification code has expired. Please request a new one.",
  "verified": false
}
```

---

## 📝 Code Changes Summary

### Modified Files

#### `app/Http/Controllers/Api/InvestorController.php`
**Changes:**
- `sendPhoneVerification()`: 
  - Integrated Twilio SMS sending
  - Added 15-minute expiration
  - Removed code from production response
  - Only returns code in local environment
- `verifyPhone()`:
  - Added expiration check
  - Sends email on success
  - Sends SMS confirmation
  - Clears expiration after verification

#### `app/Http/Controllers/Api/Admin/AdminController.php`
**Changes:**
- `approveUser()`:
  - Sends email notification
  - Sends SMS if phone verified
- `approvePhoneChange()`:
  - Sends email notification
  - Sends SMS to new number
- `rejectPhoneChange()`:
  - Sends email notification

#### `routes/api.php`
**Changes:**
- Added throttle middleware to phone routes:
  - Change request: 3/day
  - Send verification: 5/hour
  - Verify code: 10/hour

#### `config/services.php`
**Added:**
```php
'twilio' => [
    'sid' => env('TWILIO_SID'),
    'token' => env('TWILIO_TOKEN'),
    'from' => env('TWILIO_FROM'),
],
```

#### `config/app.php`
**Added:**
```php
'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
```

#### `app/Models/User.php`
**Added:**
```php
protected $casts = [
    'email_verified_at' => 'datetime',
    'phone_verification_code_expires_at' => 'datetime',
];
```

---

## 🌍 Environment Variables Checklist

### Required for Production
```env
# App
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crowdbricks.com
FRONTEND_URL=https://crowdbricks.com

# Twilio SMS
TWILIO_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_TOKEN=your_auth_token
TWILIO_FROM=+1234567890

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.xxxxxxxxxxxxxxxxxxxxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@crowdbricks.com
MAIL_FROM_NAME="CrowdBricks"
```

---

## 🚨 Important Security Notes

### 1. Never Commit Secrets
```bash
# .env should be in .gitignore
# Never push Twilio credentials to GitHub
```

### 2. Use Environment-Specific Behavior
```php
// Code only returned in local environment
if (config('app.env') === 'local') {
    return response()->json(['code' => $code]);
}
```

### 3. Rate Limiting Prevents:
- SMS spam attacks (cost money)
- Brute force code guessing
- Phone number enumeration
- DDoS on SMS endpoints

### 4. Code Expiration Prevents:
- Old codes being reused
- Interception attacks
- Replay attacks

---

## 💰 Cost Estimation

### Twilio SMS Pricing
- **US/Canada**: $0.0075 per SMS
- **Ghana (+233)**: ~$0.02 per SMS
- **International**: $0.01-0.05 per SMS

**Example:**
- 1000 users × 2 codes each = 2000 SMS
- Cost: 2000 × $0.02 = **$40/month**

### Email Pricing
- **SendGrid Free**: 100/day = 3000/month
- **Mailgun Free**: 5000/month
- **Cost**: **$0** for small scale

---

## 📊 Monitoring & Logs

### Check SMS Logs
```bash
# Laravel logs
tail -f storage/logs/laravel.log | grep "SMS"
```

### Check Email Logs
```bash
# Email delivery
tail -f storage/logs/laravel.log | grep "Mail"
```

### Check Rate Limiting
```bash
# Throttle hits
tail -f storage/logs/laravel.log | grep "Throttle"
```

---

## ✅ Production Deployment Checklist

Before going live:

- [ ] Twilio account funded (at least $20)
- [ ] Twilio credentials in `.env`
- [ ] Email service configured (SendGrid/Mailgun)
- [ ] `APP_ENV=production` in `.env`
- [ ] `FRONTEND_URL` points to production domain
- [ ] Migration run: `php artisan migrate`
- [ ] Test SMS sending to real phone
- [ ] Test email delivery to real email
- [ ] Test rate limiting works
- [ ] Test code expiration works
- [ ] Verify no codes in API response (production)
- [ ] Monitor logs for first week

---

## 🎉 Success!

All production features are now implemented:
- ✅ Real SMS delivery via Twilio
- ✅ Beautiful HTML email templates
- ✅ 15-minute code expiration
- ✅ Rate limiting (3/day, 5/hour, 10/hour)
- ✅ Environment-aware code visibility
- ✅ Comprehensive error handling
- ✅ Email + SMS for all events

**Ready for production deployment!**

---

## 📞 Support

### Twilio Issues
- Check Twilio Console logs
- Verify phone number format (+countrycode)
- Check account balance

### Email Issues
- Verify SMTP credentials
- Check spam folder
- Review `storage/logs/laravel.log`

### Rate Limiting Issues
- Clear cache: `php artisan cache:clear`
- Check route middleware
- Verify throttle limits

---

**For questions, refer to:**
- Twilio Docs: https://www.twilio.com/docs
- Laravel Mail: https://laravel.com/docs/11.x/mail
- Laravel Throttle: https://laravel.com/docs/11.x/routing#rate-limiting
