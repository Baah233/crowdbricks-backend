# 🚀 QUICK REFERENCE - Production Features

## ⚡ At a Glance

### 1️⃣ Twilio SMS
```env
TWILIO_SID=AC...
TWILIO_TOKEN=...
TWILIO_FROM=+1234567890
```
**Test:** `php artisan tinker` → `app(\App\Services\TwilioService::class)->sendSMS('+233...', 'Test')`

---

### 2️⃣ Email Notifications
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=app-password
```
**Test:** `Mail::to('user@example.com')->send(new \App\Mail\UserApprovedMail($user))`

---

### 3️⃣ Code Expiration
- ⏰ **Duration**: 15 minutes
- 🔄 **Auto-cleanup**: On verification
- ❌ **Expired**: "Verification code has expired. Please request a new one."

---

### 4️⃣ Rate Limiting
| Action | Limit |
|--------|-------|
| Change phone | 3/day |
| Send code | 5/hour |
| Verify code | 10/hour |

**Override:** Exceeded = 429 Too Many Requests

---

### 5️⃣ Code Security
- 🔒 **Production**: Code sent via SMS only (not in response)
- 🧪 **Local**: Code in response for testing
- ✅ **Check**: `config('app.env') === 'local'`

---

## 📋 Setup Checklist

### Development
- [ ] Run migration: `php artisan migrate`
- [ ] Update `.env` with mail settings
- [ ] Test email: `php artisan tinker` + Mail test
- [ ] Optional: Add Twilio for real SMS

### Production
- [ ] Set `APP_ENV=production`
- [ ] Add Twilio credentials to `.env`
- [ ] Configure production email (SendGrid/Mailgun)
- [ ] Set `FRONTEND_URL=https://yourdomain.com`
- [ ] Test SMS to real phone
- [ ] Test email to real inbox
- [ ] Verify no codes in API responses

---

## 🆘 Troubleshooting

### SMS not sending?
1. Check Twilio credentials in `.env`
2. Verify phone format: +countrycode (e.g., +233123456789)
3. Check Twilio Console logs
4. Verify account has balance

### Email not sending?
1. Check SMTP credentials
2. Test with Mailtrap first
3. Check spam folder
4. Review `storage/logs/laravel.log`

### Rate limit issues?
1. Clear cache: `php artisan cache:clear`
2. Check route middleware
3. Adjust limits in `routes/api.php`

### Code expiration not working?
1. Run migration: `php artisan migrate`
2. Check `phone_verification_code_expires_at` column exists
3. Verify datetime cast in User model

---

## 📁 Files Created

### Services
- `app/Services/TwilioService.php`

### Mailables
- `app/Mail/UserApprovedMail.php`
- `app/Mail/PhoneChangeApprovedMail.php`
- `app/Mail/PhoneChangeRejectedMail.php`
- `app/Mail/PhoneVerifiedMail.php`

### Email Templates
- `resources/views/emails/user-approved.blade.php`
- `resources/views/emails/phone-change-approved.blade.php`
- `resources/views/emails/phone-change-rejected.blade.php`
- `resources/views/emails/phone-verified.blade.php`

### Migration
- `database/migrations/2025_11_06_232354_add_phone_verification_expiration_to_users.php`

### Documentation
- `PRODUCTION_SETUP.md` - Full setup guide
- `PRODUCTION_FEATURES_SUMMARY.md` - Feature overview
- `QUICK_REFERENCE.md` - This file

---

## 🔗 Useful Links

- **Twilio Console**: https://console.twilio.com/
- **Mailtrap**: https://mailtrap.io/
- **SendGrid**: https://sendgrid.com/
- **Laravel Mail Docs**: https://laravel.com/docs/11.x/mail
- **Laravel Rate Limiting**: https://laravel.com/docs/11.x/routing#rate-limiting

---

## 💡 Pro Tips

1. **Use Mailtrap** for email testing (catches all emails)
2. **Monitor logs** during first week: `tail -f storage/logs/laravel.log`
3. **Start with Twilio trial** ($15 free credit)
4. **Set up alerts** for high SMS usage
5. **Keep rate limits strict** to control costs

---

## ✅ Verification Commands

```bash
# Check migration ran
php artisan migrate:status | grep expiration

# Test Twilio service exists
php artisan tinker
app(\App\Services\TwilioService::class)

# Check mail configuration
php artisan config:show mail

# Verify rate limiting
php artisan route:list | grep throttle

# Check environment
php artisan env
```

---

**All features implemented and ready for production! 🎉**
