# 🚀 QUICK START - Profile System Testing

## ✅ System Status: READY

### Servers Running
- **Backend**: http://127.0.0.1:8001 ✅
- **Frontend**: http://localhost:5174 ✅

---

## 🎯 60-Second Test

### Step 1: Open Frontend
Visit: **http://localhost:5174**

### Step 2: Login as Investor
- Email: `investor@crowdbricks.com`
- Password: `password`
*(or use your test account)*

### Step 3: Navigate to Profile
Click your name → Profile (or go to `/profile`)

### Step 4: Upload Profile Picture
1. Click the circular avatar with camera icon
2. Select any image (jpeg/png, max 2MB)
3. **Watch**: Progress bar shows 0-100%
4. **Result**: Picture appears, completion increases by 20%

### Step 5: Verify Phone
1. Click "Edit Profile"
2. Add phone: `+233123456789`
3. Click "Save Changes"
4. Click **"Verify Now"** button (orange warning)
5. **Check alert** for 6-digit code
6. Enter code in modal
7. Click "Verify"
8. **Result**: Green checkmark ✅, completion increases by 15%

### Step 6: Check Dashboard
1. Go back to Dashboard
2. **Look at sidebar**: Profile picture shows, progress bar displays

### Step 7: Test Admin Approval (Optional)
1. Logout → Login as admin
2. Go to Admin Users
3. **See**: Phone change requests card (if any pending)
4. Test approve/reject buttons

---

## 🎨 What to Look For

### Profile Page
- ✨ Large profile completion progress bar (gradient blue/purple)
- 🖼️ Circular avatar with camera overlay on hover
- 📊 Percentage display (0-100%)
- 📱 Phone verification section with "Verify Now" button
- 🔔 Phone change pending banner (if request submitted)
- 🎨 Color-coded progress:
  - 🔴 Red (<50%)
  - 🟡 Yellow (50-80%)
  - 🟢 Green (>80%)

### Dashboard Sidebar
- 🖼️ Profile picture in circular avatar
- 📊 Mini progress bar below name
- ✅ Phone verified badge (green checkmark)
- 📈 Percentage updates in real-time

### Admin Panel
- 📋 Phone Change Requests card (if pending requests exist)
- 👤 User details with old → new phone
- ✅ Approve button (green)
- ❌ Reject button (red)

---

## 🐛 Quick Troubleshooting

### Issue: Can't see profile picture after upload
**Fix**: Check browser console for errors, verify file < 2MB

### Issue: Phone verification code doesn't work
**Fix**: For development, code appears in alert popup. Copy exactly 6 digits.

### Issue: Profile completion not updating
**Fix**: Refresh the page after making changes

### Issue: 2FA modal blocking access
**Solution**: This is intended! Complete 2FA setup to proceed.

---

## 📚 Full Documentation

For detailed information, see:
1. **IMPLEMENTATION_SUMMARY.md** - Complete project overview
2. **PROFILE_FEATURES.md** - Feature documentation
3. **TESTING_GUIDE.md** - Comprehensive testing instructions

---

## 🎉 Features to Test

- [x] Profile picture upload with progress
- [x] Profile completion tracking (0-100%)
- [x] Phone verification with 6-digit code
- [x] Phone change admin approval workflow
- [x] Mandatory 2FA after admin approval
- [x] Dashboard sidebar updates
- [x] Admin phone request management

---

## ⚡ Key Shortcuts

| Action | Shortcut |
|--------|----------|
| Open Profile | Click name in navbar |
| Edit Profile | Click "Edit Profile" button |
| Upload Picture | Click avatar |
| Verify Phone | Click "Verify Now" |
| Admin Panel | `/admin/users` |

---

## 💾 Database Quick Check

**To verify changes in database:**
```sql
-- Run in phpMyAdmin or MySQL Workbench
SELECT 
  first_name,
  last_name,
  phone,
  phone_verified,
  profile_picture,
  profile_completion
FROM users 
WHERE email = 'investor@crowdbricks.com';
```

**Expected after full test:**
- `phone_verified`: 1
- `profile_picture`: profile_pictures/xxxxx.jpg
- `profile_completion`: 85+ (depends on 2FA)

---

## 🚨 Important Notes

1. **SMS codes** currently show in alerts (for development)
   - In production: Integrate Twilio to send real SMS
   
2. **Profile pictures** stored in `storage/app/public/profile_pictures/`
   - Accessible via `/storage/profile_pictures/`
   
3. **Phone changes** require admin approval for security
   - Prevents account takeover attacks
   
4. **Mandatory 2FA** blocks access after admin approval
   - Cannot dismiss modal until 2FA completed

---

## ✨ Success Indicators

**Everything Working If You See:**
- ✅ Upload progress bar (0-100%)
- ✅ Profile completion percentage updating
- ✅ Profile picture in avatar + sidebar
- ✅ Phone verified green checkmark
- ✅ Progress bar color changing (red → yellow → green)
- ✅ Phone change pending badge (if requested)
- ✅ Admin panel showing requests (if admin)

---

**Ready to Test! Open http://localhost:5174 and start exploring! 🎉**

**Questions?** Check TESTING_GUIDE.md for detailed instructions.
