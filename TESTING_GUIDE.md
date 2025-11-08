# Testing Guide - Profile Features

## Setup Complete ✅
- ✅ Backend server running: http://127.0.0.1:8001
- ✅ Frontend server running: http://localhost:5173 (check terminal for exact port)
- ✅ Database migrations applied
- ✅ Storage symlink created
- ✅ All API endpoints registered

## Quick Test Flow

### 1. Test Profile Picture Upload
1. Navigate to Profile page (`/profile` or click profile from dashboard)
2. Click the circular avatar with camera icon
3. Select an image (jpeg/png, max 2MB)
4. Watch upload progress bar (0-100%)
5. **Expected**: Image appears in avatar, profile completion increases by 20%

### 2. Test Phone Verification
1. In Profile page, click "Edit Profile"
2. Add phone number (e.g., "+233123456789")
3. Click "Save Changes"
4. Click "Verify Now" button in orange warning box
5. **Check API response** for 6-digit code (appears in browser console or alert)
6. Enter code in modal
7. Click "Verify"
8. **Expected**: Green checkmark appears, profile completion increases by 15%

### 3. Test Phone Change Admin Approval
**As User:**
1. Go to Profile → Edit Profile
2. Change phone number to different value
3. Save
4. **Expected**: Blue banner appears: "Phone Change Pending" with requested number

**As Admin:**
1. Navigate to Admin Users page (`/admin/users`)
2. **Expected**: Card at top showing phone change request
3. See old phone → new phone with user details
4. Click "Approve" or "Reject"
5. **Expected**: User's phone updated (if approved), user must re-verify new number

### 4. Test Profile Completion Progress
1. Create new user account (or use existing with incomplete profile)
2. Check initial completion % (should be low - just name + email)
3. Add phone → verify increases by 15%
4. Verify phone → increases by 15%
5. Upload profile picture → increases by 20%
6. Enable 2FA → increases by 20%
7. **Expected**: Progress bar color changes:
   - Red (<50%)
   - Yellow (50-80%)
   - Green (>80%)

### 5. Test Mandatory 2FA Enforcement
**As Admin:**
1. Go to Admin Users page
2. Find pending user
3. Click "Approve User"
4. **Expected**: `two_factor_required = true` set in database

**As User:**
1. Login as newly approved user
2. **Expected**: Blocking modal appears immediately
3. Try to close/dismiss → **Cannot close**
4. Click "Enable 2FA Now"
5. Follow 2FA setup (secret code displayed)
6. Enter code to verify
7. **Expected**: Modal closes, `two_factor_required = false`, dashboard accessible

### 6. Test Dashboard Sidebar Updates
1. Navigate to Investor Dashboard
2. Check sidebar profile section
3. **Expected**: 
   - Profile picture shows in circular avatar (if uploaded)
   - Mini progress bar displays below name
   - Percentage shows (0-100%)
   - Color matches completion level

## Database Verification

### Check User Table
```sql
SELECT 
  id, 
  email, 
  phone, 
  phone_verified, 
  phone_change_request, 
  phone_change_status, 
  profile_picture, 
  two_factor_required, 
  two_factor_enabled,
  profile_completion 
FROM users 
WHERE email = 'your_test_user@example.com';
```

### Expected Values After Full Profile
```
phone: "+233123456789"
phone_verified: 1
phone_change_request: NULL
phone_change_status: NULL
profile_picture: "profile_pictures/abc123.jpg"
two_factor_required: 0
two_factor_enabled: 1
profile_completion: 100
```

## API Testing with Postman/Thunder Client

### 1. Upload Profile Picture
```http
POST http://127.0.0.1:8001/api/v1/user/profile-picture
Authorization: Bearer {your_token}
Content-Type: multipart/form-data

Body (form-data):
profile_picture: [select image file]
```

**Expected Response:**
```json
{
  "profile_picture": "http://127.0.0.1:8001/storage/profile_pictures/abc123.jpg"
}
```

### 2. Send Phone Verification
```http
POST http://127.0.0.1:8001/api/v1/user/phone/send-verification
Authorization: Bearer {your_token}
```

**Expected Response:**
```json
{
  "message": "Verification code sent",
  "code": "123456"
}
```

### 3. Verify Phone
```http
POST http://127.0.0.1:8001/api/v1/user/phone/verify
Authorization: Bearer {your_token}
Content-Type: application/json

{
  "code": "123456"
}
```

**Expected Response:**
```json
{
  "message": "Phone verified successfully",
  "verified": true
}
```

### 4. Request Phone Change
```http
POST http://127.0.0.1:8001/api/v1/user/phone/change-request
Authorization: Bearer {your_token}
Content-Type: application/json

{
  "phone": "+233987654321"
}
```

**Expected Response:**
```json
{
  "message": "Phone change request submitted for admin approval"
}
```

### 5. Get Phone Change Requests (Admin)
```http
GET http://127.0.0.1:8001/api/v1/admin/phone-change-requests
Authorization: Bearer {admin_token}
```

**Expected Response:**
```json
[
  {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+233123456789",
    "phone_change_request": "+233987654321",
    "phone_change_status": "pending"
  }
]
```

### 6. Approve Phone Change (Admin)
```http
POST http://127.0.0.1:8001/api/v1/admin/phone-change/1/approve
Authorization: Bearer {admin_token}
```

**Expected Response:**
```json
{
  "message": "Phone change approved successfully"
}
```

## Troubleshooting

### Issue: "Storage link not found"
**Solution**: Run `php artisan storage:link` in backend directory

### Issue: "Failed to upload profile picture"
**Check**:
- File size < 2MB
- File type is image (jpeg/png/jpg/gif)
- Storage directory writable: `storage/app/public/profile_pictures/`

### Issue: "Phone verification code not working"
**Check**:
- Code is exactly 6 digits
- Code copied from API response (for dev mode)
- Code not expired (currently no expiration, but add in production)

### Issue: "Phone change request not appearing in admin panel"
**Check**:
- User's `phone_change_status = 'pending'` in database
- Admin endpoint returning data: `GET /api/v1/admin/phone-change-requests`
- Frontend loading requests: check console for errors

### Issue: "2FA modal not appearing after approval"
**Check**:
- User's `two_factor_required = true` in database
- User's `two_factor_enabled = false` (already enabled users skip modal)
- Frontend checking user.two_factor_required on load

### Issue: "Profile completion not updating"
**Check**:
- Backend calling `$user->updateProfileCompletion()` after changes
- Calculate method includes all 7 fields
- Weights sum to 100%

## Browser Console Checks

### Check Profile Data
```javascript
// In browser console
console.log(JSON.parse(localStorage.getItem('user')));
```

**Expected Fields:**
```javascript
{
  profile_picture: "http://...",
  profile_completion: 85,
  phone_verified: true,
  two_factor_required: false,
  // ... other fields
}
```

### Check API Responses
Open browser DevTools → Network tab → Filter by "api"
- Check `/api/v1/user/profile` response includes new fields
- Verify `/api/v1/user/profile-picture` returns image URL
- Check `/api/v1/user/phone/verify` returns success

## Production Checklist (Before Going Live)

- [ ] **Remove** verification code from API response in `sendPhoneVerification()`
- [ ] Integrate Twilio for real SMS delivery
- [ ] Configure Laravel Mail for email notifications
- [ ] Add phone code expiration (15 minutes recommended)
- [ ] Hash verification codes before storing (Security::hash())
- [ ] Add rate limiting to prevent SMS spam (Laravel throttle middleware)
- [ ] Set up file upload limits in production environment
- [ ] Configure proper CORS for production domain
- [ ] Add logging for phone change approvals (audit trail)
- [ ] Test with real phone numbers (international format)
- [ ] Add backup codes for 2FA (in case of phone loss)
- [ ] Implement phone number validation (libphonenumber)

## Success Indicators

✅ **All working correctly if:**
1. Profile picture uploads and displays in avatar + sidebar
2. Upload progress shows 0-100%
3. Phone verification sends code (check alert for dev)
4. Entering code marks phone_verified = true
5. Profile completion shows correct percentage (0-100%)
6. Progress bar color-coded (red/yellow/green)
7. Phone change creates admin approval request
8. Admin sees pending requests card
9. Admin approve/reject updates user's phone
10. Mandatory 2FA modal blocks access after approval
11. Completing 2FA clears two_factor_required flag
12. Dashboard sidebar shows profile picture + progress

## Test User Credentials

**Investor Account:**
- Email: investor@crowdbricks.com
- Password: password

**Admin Account:**
- Email: admin@crowdbricks.com
- Password: password

*(Adjust based on your seeded data)*

---

**Happy Testing! 🎉**

If you encounter any issues, check:
1. Browser console for JavaScript errors
2. Backend logs: `storage/logs/laravel.log`
3. Network tab for API response errors
4. Database values using SQL queries above
