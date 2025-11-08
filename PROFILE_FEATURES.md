# Profile System Features Documentation

## Overview
Comprehensive profile management system with security features, admin oversight, and progress tracking.

## Features Implemented

### 1. Profile Picture Upload
- **Upload Endpoint**: `POST /api/v1/user/profile-picture`
- **Validation**: Images only (jpeg, png, jpg, gif), max 2MB
- **Storage**: `storage/app/public/profile_pictures/`
- **Access**: `/storage/profile_pictures/{filename}` (via public symlink)
- **Progress Tracking**: Real-time upload progress (0-100%)
- **Old File Cleanup**: Automatically deletes previous profile picture

**Frontend Implementation**:
- Click-to-upload circular avatar with camera icon overlay
- Image preview with circular crop
- Upload progress bar
- Success/error notifications

### 2. Profile Completion Tracking
- **Calculation**: Weighted 100-point system
- **Update Method**: `updateProfileCompletion()` in User model
- **Fields & Weights**:
  - `first_name`: 10%
  - `last_name`: 10%
  - `email`: 10%
  - `phone`: 15%
  - `phone_verified`: 15%
  - `profile_picture`: 20%
  - `two_factor_enabled`: 20%
  - **Total**: 100%

**Display**:
- Large progress bar on Profile page (color-coded: red <50%, yellow 50-80%, green >80%)
- Mini progress bar in InvestorDashboard sidebar
- Percentage display (0-100%)

### 3. Phone Verification System
**Send Verification Code**:
- **Endpoint**: `POST /api/v1/user/phone/send-verification`
- **Process**: Generates 6-digit code, stores in `phone_verification_code`
- **TODO**: Integrate Twilio/SMS provider (currently returns code in response for dev)

**Verify Phone**:
- **Endpoint**: `POST /api/v1/user/phone/verify`
- **Parameters**: `{ "code": "123456" }`
- **Success**: Sets `phone_verified = true`, updates profile completion
- **UI**: Modal with 6-digit code input, auto-format to numbers only

### 4. Phone Change Admin Approval Workflow
**Request Phone Change**:
- **Endpoint**: `POST /api/v1/user/phone/change-request`
- **Parameters**: `{ "phone": "+233XXXXXXXXX" }`
- **Process**: 
  - Sets `phone_change_request` to new number
  - Sets `phone_change_status = 'pending'`
  - Requires admin approval before update

**Admin Approval**:
- **Get Requests**: `GET /api/v1/admin/phone-change-requests`
- **Approve**: `POST /api/v1/admin/phone-change/{id}/approve`
  - Updates `phone` to requested value
  - Clears `phone_change_request`
  - Sets `phone_change_status = 'approved'`
  - Resets `phone_verified = false` (user must verify new number)
- **Reject**: `POST /api/v1/admin/phone-change/{id}/reject`
  - Clears `phone_change_request`
  - Sets `phone_change_status = 'rejected'`

**Frontend**:
- User sees pending status badge on Profile page
- Admin sees card with all pending requests in AdminUsers
- Approve/Reject buttons per request

### 5. Mandatory 2FA After Admin Approval
**Backend Logic**:
- When admin approves user (`POST /admin/users/{id}/approve`):
  - Sets `two_factor_required = true`
- When user successfully enables 2FA (`POST /user/2fa/verify`):
  - Clears `two_factor_required = false`

**Frontend Enforcement**:
- On InvestorDashboard load, checks `user.two_factor_required`
- Shows blocking modal if true (cannot close until 2FA enabled)
- Modal displays: "Your account was recently approved. Please enable Two-Factor Authentication to continue."
- Integrates existing 2FA setup flow
- Redirects to dashboard after successful setup

## Database Schema Changes

### Migration: `2025_11_06_225327_add_phone_verification_and_profile_picture_to_users.php`

**New Columns**:
```php
$table->string('phone')->nullable()->after('email'); // Conditional (if not exists)
$table->boolean('phone_verified')->default(false);
$table->string('phone_verification_code')->nullable();
$table->string('phone_change_request')->nullable();
$table->enum('phone_change_status', ['pending', 'approved', 'rejected'])->nullable();
$table->string('profile_picture')->nullable();
$table->boolean('two_factor_required')->default(false);
$table->integer('profile_completion')->default(0);
```

## API Endpoints Summary

### User Endpoints
| Method | Endpoint | Purpose | Auth |
|--------|----------|---------|------|
| GET | `/api/v1/user/profile` | Get profile (includes new fields) | User |
| PUT | `/api/v1/user/profile` | Update profile | User |
| POST | `/api/v1/user/phone/change-request` | Request phone change | User |
| POST | `/api/v1/user/phone/send-verification` | Send verification code | User |
| POST | `/api/v1/user/phone/verify` | Verify phone with code | User |
| POST | `/api/v1/user/profile-picture` | Upload profile picture | User |

### Admin Endpoints
| Method | Endpoint | Purpose | Auth |
|--------|----------|---------|------|
| GET | `/api/v1/admin/phone-change-requests` | Get pending requests | Admin |
| POST | `/api/v1/admin/phone-change/{id}/approve` | Approve phone change | Admin |
| POST | `/api/v1/admin/phone-change/{id}/reject` | Reject phone change | Admin |

## Profile API Response Example

```json
{
  "id": 1,
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "+233123456789",
  "phone_verified": true,
  "phone_change_request": null,
  "phone_change_status": null,
  "profile_picture": "http://localhost/storage/profile_pictures/abc123.jpg",
  "profile_completion": 85,
  "two_factor_enabled": true,
  "two_factor_required": false,
  "status": "approved",
  "verification_id": "CB-2024-001",
  "email_notifications": true,
  "sms_notifications": true
}
```

## Testing Checklist

### Profile Picture Upload
1. ✅ Click camera icon on profile page
2. ✅ Select image file (jpeg/png/jpg/gif)
3. ✅ Verify upload progress shows 0-100%
4. ✅ Check file saved to `storage/app/public/profile_pictures/`
5. ✅ Verify accessible at `/storage/profile_pictures/{filename}`
6. ✅ Confirm profile completion increases by 20%
7. ✅ Upload new picture → verify old one deleted

### Phone Verification
1. ✅ Add phone number in edit mode
2. ✅ Click "Verify Now" button
3. ✅ Verify 6-digit code sent (check API response for dev)
4. ✅ Enter code in modal
5. ✅ Click "Verify" → check `phone_verified = true`
6. ✅ Confirm profile completion increases by 15%
7. ✅ Verify green checkmark appears next to phone

### Phone Change Admin Approval
1. ✅ User edits phone number → save
2. ✅ Verify pending badge appears on Profile page
3. ✅ Admin opens AdminUsers page
4. ✅ Verify phone change request card appears
5. ✅ Admin clicks "Approve"
6. ✅ Verify user's phone updated, `phone_verified = false`
7. ✅ User must re-verify new phone number
8. ✅ Test "Reject" flow → phone stays same

### Profile Completion
1. ✅ New user → verify completion starts at low % (only email + name)
2. ✅ Add phone → verify increases by 15%
3. ✅ Verify phone → verify increases by 15%
4. ✅ Upload profile picture → verify increases by 20%
5. ✅ Enable 2FA → verify increases by 20%
6. ✅ Check profile page progress bar color (red/yellow/green)
7. ✅ Verify mini progress bar in dashboard sidebar

### Mandatory 2FA
1. ✅ Create new user account
2. ✅ Admin approves user
3. ✅ Verify `two_factor_required = true` in database
4. ✅ Login as user → verify blocking modal appears
5. ✅ Try to close modal → verify cannot dismiss
6. ✅ Click "Enable 2FA Now" → follow setup flow
7. ✅ Complete 2FA verification
8. ✅ Verify `two_factor_required = false`
9. ✅ Verify modal dismissed, dashboard accessible

### InvestorDashboard Sidebar
1. ✅ Upload profile picture → verify shows in circular avatar
2. ✅ Verify progress bar displays below name
3. ✅ Check color coding (red <50%, yellow 50-80%, green >80%)
4. ✅ Verify percentage updates in real-time

## SMS Integration (TODO)

### Twilio Setup
1. Sign up at https://www.twilio.com/
2. Get credentials: Account SID, Auth Token, Phone Number
3. Add to `.env`:
   ```env
   TWILIO_SID=your_account_sid
   TWILIO_TOKEN=your_auth_token
   TWILIO_FROM=+1234567890
   ```
4. Install SDK: `composer require twilio/sdk`
5. Update `InvestorController::sendPhoneVerification()`:
   ```php
   use Twilio\Rest\Client;
   
   $twilio = new Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));
   $twilio->messages->create($user->phone, [
       'from' => env('TWILIO_FROM'),
       'body' => "Your CrowdBricks verification code is: {$code}"
   ]);
   ```
6. **REMOVE** code from API response (security)

## Email Notifications (TODO)

### Laravel Mail Configuration
1. Configure mail in `.env`:
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mailtrap.io
   MAIL_PORT=2525
   MAIL_USERNAME=your_username
   MAIL_PASSWORD=your_password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@crowdbricks.com
   MAIL_FROM_NAME="CrowdBricks"
   ```

2. Create notification emails:
   - `UserApprovedMail` - sent when admin approves user
   - `PhoneChangeApprovedMail` - sent when phone change approved
   - `PhoneChangeRejectedMail` - sent when phone change rejected

3. Update controllers to dispatch emails:
   ```php
   use Illuminate\Support\Facades\Mail;
   
   // In AdminController::approveUser()
   Mail::to($user->email)->send(new UserApprovedMail($user));
   
   // In AdminController::approvePhoneChange()
   Mail::to($user->email)->send(new PhoneChangeApprovedMail($user));
   ```

## Security Considerations

1. **Phone Verification**: Required for transaction security
2. **Admin Approval**: Prevents unauthorized phone changes (account takeover protection)
3. **Mandatory 2FA**: Enforced after approval for high-security accounts
4. **File Upload**: Validated (type, size), stored securely with Laravel storage
5. **Profile Picture**: Old files deleted to prevent storage bloat
6. **Phone Code**: 6-digit random, stored hashed (TODO in production)

## Files Modified

### Backend
- `database/migrations/2025_11_06_225327_add_phone_verification_and_profile_picture_to_users.php` (NEW)
- `app/Models/User.php` (3 changes)
- `app/Http/Controllers/Api/Admin/AdminController.php` (4 new methods)
- `app/Http/Controllers/Api/InvestorController.php` (6 changes)
- `routes/api.php` (10 new endpoints)

### Frontend
- `src/pages/ProfileEnhanced.jsx` (NEW - 600+ lines)
- `src/pages/InvestorDashboard.jsx` (sidebar updates)
- `src/pages/AdminUsers.jsx` (phone change requests section)
- `src/App.jsx` (router update)

## Progress Weights Rationale

| Field | Weight | Reason |
|-------|--------|--------|
| `first_name` | 10% | Basic required field |
| `last_name` | 10% | Basic required field |
| `email` | 10% | Basic required field |
| `phone` | 15% | Security feature (transaction verification) |
| `phone_verified` | 15% | Confirms phone ownership |
| `profile_picture` | 20% | Personalization, identity verification |
| `two_factor_enabled` | 20% | Critical security feature |

**Total**: 100% (prioritizes security features over basic info)

## Next Steps

1. ✅ **Complete**: Backend infrastructure (database, models, controllers, routes)
2. ✅ **Complete**: Frontend Profile page with all features
3. ✅ **Complete**: InvestorDashboard sidebar updates
4. ✅ **Complete**: AdminUsers phone approval UI
5. ⏳ **Pending**: SMS integration (Twilio)
6. ⏳ **Pending**: Email notifications (Laravel Mail)
7. ⏳ **Pending**: Production testing with real users

## Support

For issues or questions, contact the development team or refer to Laravel documentation:
- Laravel Storage: https://laravel.com/docs/11.x/filesystem
- Laravel Mail: https://laravel.com/docs/11.x/mail
- Twilio PHP SDK: https://www.twilio.com/docs/libraries/php
