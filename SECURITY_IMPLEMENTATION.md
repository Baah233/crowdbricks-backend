# 🔒 Security Implementation for Developer Dashboard

## ✅ COMPLETED - Backend Security Infrastructure

### 1. Database Security Tables (ALL MIGRATED ✅)

#### `two_factor_auth` Table
- Stores 2FA configuration per user
- Supports multiple methods: app (TOTP), SMS, email
- Encrypted secret storage
- 10 recovery codes (backup access)
- Phone number for SMS 2FA
- Failed attempts tracking
- Account lockout mechanism (`locked_until`)
- **Status**: Migrated & Active

#### `login_history` Table
- Comprehensive login activity tracking
- Fields:
  - IP address, user agent, device type
  - Device name, browser, platform (iOS/Android/Windows/macOS)
  - Location (city, country via IP geolocation)
  - Session ID for tracking
  - Status: success/failed/blocked
  - Failure reason tracking
  - Suspicious activity flag
  - Logout timestamp
- **Indexes**: user_id + created_at, ip_address + status, session_id
- **Status**: Migrated & Active

#### `audit_logs` Table
- Every developer action logged
- Fields:
  - User ID, action type, model type & ID
  - Description of action
  - Old values vs New values (JSON comparison)
  - IP address, user agent
  - Risk level: low/medium/high/critical
  - Flagged for suspicious activity
- **Use Cases**:
  - Project created/updated/deleted
  - Withdrawal requested
  - KYC documents uploaded
  - Settings changed
  - Team member added
- **Status**: Migrated & Active

#### `kyc_verifications` Table
- KYC document verification tracking
- Document types supported:
  - National ID
  - Passport
  - Driver's License
  - Business Registration
  - Land Title
  - Tax Certificate
- Fields:
  - Document number
  - Front/back photo paths (encrypted)
  - Selfie for liveness check
  - Status: pending/under_review/approved/rejected/expired
  - Verification method: manual, Smile ID, Trulioo
  - Third-party API reference ID
  - Rejection reason
  - Reviewed by (admin user_id)
  - Submission, review, expiration timestamps
  - Trust score (0-100)
- **Status**: Migrated & Active

#### `developer_wallets` Table
- Secure financial tracking
- Fields:
  - Unique wallet_id (UUID)
  - Balance (available funds)
  - Pending balance (in escrow)
  - Lifetime earnings
  - Currency (GHS default)
  - Transaction PIN (hashed for withdrawals)
  - Auto-withdraw toggle
  - Withdrawal account & provider (MTN, Vodafone, Bank)
  - Failed withdrawal attempt counter
  - Account lockout (`locked_until`)
  - Is_active flag
- **Status**: Migrated & Active

---

### 2. Security Middleware (`AuditLog.php`) ✅

**Purpose**: Automatically log all high-risk developer actions

**Features**:
- Audits POST, PUT, PATCH, DELETE requests
- Excludes sensitive fields (password, token, secret)
- Calculates risk levels:
  - **Critical**: Withdrawals, payouts
  - **High**: KYC, verification, deletions, updates
  - **Medium**: General updates
  - **Low**: Read operations
- Automatic flagging of suspicious activity:
  - More than 20 actions in 5 minutes
  - All critical actions flagged by default
- Silently fails to not break requests
- Logs:
  - User ID, action, model type/ID
  - IP address, user agent
  - Request data (sanitized)
  - Timestamp

**Status**: Created & Ready for Registration

---

### 3. Security Controller (`SecurityController.php`) ✅

**Endpoints Created**:

#### Security Overview
```
GET /api/v1/security/overview
```
**Returns**:
- 2FA enabled status
- 2FA method (app/sms/email)
- Recent 10 logins
- Active sessions count
- Suspicious activity count (last 30 days)
- Security score (0-100)

**Security Score Calculation**:
- Base: 50 points
- 2FA enabled: +30 points
- No suspicious logins (30 days): +20 points
- **Max**: 100 points

#### 2FA Management

**Enable 2FA**:
```
POST /api/v1/security/2fa/enable
Body: { method: 'app' | 'sms' | 'email' }
```
**Returns**:
- Secret key
- QR code URL (Google Charts API)
- 10 recovery codes

**Verify 2FA**:
```
POST /api/v1/security/2fa/verify
Body: { code: '123456' }
```
**Action**: Enables 2FA after successful verification

**Disable 2FA**:
```
POST /api/v1/security/2fa/disable
Body: { password: 'user_password' }
```
**Requirements**: Password verification

#### Login History & Sessions

**Get Login History**:
```
GET /api/v1/security/login-history?per_page=20
```
**Returns**: Paginated login history

**Get Active Sessions**:
```
GET /api/v1/security/active-sessions
```
**Returns**: All sessions without logout timestamp

**Terminate Specific Session**:
```
DELETE /api/v1/security/sessions/{sessionId}
```
**Action**: Logs out specific device/session

**Terminate All Sessions**:
```
POST /api/v1/security/sessions/terminate-all
Header: X-Session-ID (current session to keep)
```
**Action**: Logs out all other devices except current

**Status**: All routes added to `routes/api.php` ✅

---

## 🔧 INTEGRATION REQUIREMENTS

### 1. Middleware Registration

Add to `bootstrap/app.php` or `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    // ... existing middleware
    'audit.log' => \App\Http\Middleware\AuditLog::class,
];
```

Apply to developer routes in `routes/api.php`:

```php
Route::middleware(['auth:sanctum', 'audit.log'])->group(function () {
    // All developer/investor routes
});
```

### 2. Enhanced AuthController Login

Update `AuthController@login` to:
1. Record login attempt in `login_history` table
2. Check for suspicious activity:
   - Multiple failed attempts from same IP
   - Login from new device/location
   - Login from blocked country
3. Detect device type, browser, platform
4. Generate session ID
5. Store geolocation data

**Pseudo-code**:
```php
public function login(Request $request) {
    // ... existing validation
    
    // Device detection
    $agent = new Agent();
    $device = $agent->isMobile() ? 'mobile' : ($agent->isTablet() ? 'tablet' : 'desktop');
    $browser = $agent->browser();
    $platform = $agent->platform();
    
    // IP geolocation (use ipapi.co or ip-api.com)
    $location = $this->getLocationFromIp($request->ip());
    
    // Check if login is suspicious
    $isSuspicious = $this->detectSuspiciousLogin($user, $request->ip(), $location);
    
    // Record login
    DB::table('login_history')->insert([
        'user_id' => $user->id,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'device_type' => $device,
        'device_name' => $agent->device(),
        'browser' => $browser,
        'platform' => $platform,
        'location' => $location,
        'session_id' => Str::uuid(),
        'status' => 'success',
        'is_suspicious' => $isSuspicious,
        'created_at' => now(),
    ]);
    
    // If suspicious, send email alert
    if ($isSuspicious) {
        Mail::to($user->email)->send(new SuspiciousLoginAlert($user, $location));
    }
    
    // ... return token
}
```

### 3. Rate Limiting

Add to `app/Http/Kernel.php`:

```php
'api' => [
    'throttle:60,1', // 60 requests per minute
],
```

For sensitive endpoints:

```php
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/withdraw', ...);
    Route::post('/payout', ...);
    Route::post('/kyc/upload', ...);
});
```

### 4. CORS Configuration

Update `config/cors.php`:

```php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => [
    'https://crowdbricks.io',
    'https://app.crowdbricks.io',
],
'allowed_origins_patterns' => [],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

### 5. File Upload Security (PENDING)

Create `FileSecurityService`:

```php
class FileSecurityService {
    public function validateUpload(UploadedFile $file): bool {
        // Check file type
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return false;
        }
        
        // Check file size (max 10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            return false;
        }
        
        // Check for malware (integrate ClamAV)
        if (!$this->scanForViruses($file)) {
            return false;
        }
        
        return true;
    }
    
    public function storeSecurely(UploadedFile $file, string $type): string {
        // Generate random filename
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        
        // Store in private directory (not public/)
        $path = $file->storeAs('private/kyc/' . $type, $filename);
        
        // Encrypt path in database
        return encrypt($path);
    }
    
    public function getSignedUrl(string $encryptedPath): string {
        $path = decrypt($encryptedPath);
        
        // Generate temporary URL (valid for 10 minutes)
        return Storage::temporaryUrl($path, now()->addMinutes(10));
    }
}
```

---

## 📱 FRONTEND INTEGRATION (PENDING)

### Security Tab UI Requirements

Add to `DeveloperDashboardNew.jsx`:

```jsx
// Add Security icon import
import { Lock, Key, Shield, AlertTriangle, Check, X, Smartphone } from "lucide-react";

// Add Security tab trigger (between compliance and integrations)
<TabsTrigger value="security" className="flex flex-col items-center gap-1 py-3">
  <Lock className="h-4 w-4" />
  <span className="text-xs">Security</span>
</TabsTrigger>

// Add Security tab content
<TabsContent value="security">
  <SecuritySection dark={dark} />
</TabsContent>
```

### SecuritySection Component

Create `SecuritySection.jsx`:

```jsx
function SecuritySection({ dark }) {
  const [securityData, setSecurityData] = useState(null);
  const [show2FASetup, setShow2FASetup] = useState(false);
  const [qrCode, setQrCode] = useState(null);
  const [recoveryCodes, setRecoveryCodes] = useState([]);
  
  useEffect(() => {
    fetchSecurityOverview();
  }, []);
  
  const fetchSecurityOverview = async () => {
    const res = await api.get('/security/overview');
    setSecurityData(res.data);
  };
  
  const enable2FA = async () => {
    const res = await api.post('/security/2fa/enable', { method: 'app' });
    setQrCode(res.data.qrCodeUrl);
    setRecoveryCodes(res.data.recoveryCodes);
    setShow2FASetup(true);
  };
  
  const verify2FA = async (code) => {
    await api.post('/security/2fa/verify', { code });
    toast({ title: "2FA Enabled", description: "Your account is now more secure!" });
    fetchSecurityOverview();
    setShow2FASetup(false);
  };
  
  return (
    <div className="space-y-6">
      {/* Security Score Card */}
      <GlassmorphicCard dark={dark}>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Shield className="h-5 w-5 text-green-500" />
            Security Score: {securityData?.securityScore || 0}/100
          </CardTitle>
        </CardHeader>
        <CardContent>
          <Progress value={securityData?.securityScore || 0} className="h-3" />
          <p className="text-sm text-slate-600 dark:text-slate-400 mt-2">
            {securityData?.securityScore >= 80 ? "Excellent" : 
             securityData?.securityScore >= 60 ? "Good" : "Needs Improvement"}
          </p>
        </CardContent>
      </GlassmorphicCard>
      
      {/* 2FA Section */}
      <GlassmorphicCard dark={dark}>
        <CardHeader>
          <CardTitle>Two-Factor Authentication</CardTitle>
          <CardDescription>Add an extra layer of security</CardDescription>
        </CardHeader>
        <CardContent>
          {securityData?.twoFactorEnabled ? (
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <CheckCircle className="h-5 w-5 text-green-600" />
                <span className="font-medium">2FA is enabled</span>
              </div>
              <Button variant="outline" onClick={() => disable2FA()}>
                Disable
              </Button>
            </div>
          ) : (
            <div>
              <p className="text-sm text-slate-600 dark:text-slate-400 mb-4">
                Protect your account with two-factor authentication
              </p>
              <Button onClick={enable2FA}>
                <Key className="h-4 w-4 mr-2" />
                Enable 2FA
              </Button>
            </div>
          )}
        </CardContent>
      </GlassmorphicCard>
      
      {/* Login History */}
      <GlassmorphicCard dark={dark}>
        <CardHeader>
          <CardTitle>Recent Login Activity</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {securityData?.recentLogins?.map((login, idx) => (
              <div key={idx} className="flex items-start justify-between p-3 bg-slate-50 dark:bg-slate-800 rounded-lg">
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <Smartphone className="h-4 w-4 text-blue-500" />
                    <span className="font-medium">{login.device_type} • {login.browser}</span>
                    {login.is_suspicious && <AlertTriangle className="h-4 w-4 text-red-500" />}
                  </div>
                  <p className="text-xs text-slate-500 mt-1">
                    {login.location} • {login.ip_address}
                  </p>
                  <p className="text-xs text-slate-400">
                    {new Date(login.created_at).toLocaleString()}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </GlassmorphicCard>
      
      {/* Active Sessions */}
      <GlassmorphicCard dark={dark}>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle>Active Sessions ({securityData?.activeSessions || 0})</CardTitle>
            <Button variant="destructive" size="sm">
              Terminate All
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-slate-600 dark:text-slate-400">
            Manage devices that are currently logged in to your account
          </p>
        </CardContent>
      </GlassmorphicCard>
    </div>
  );
}
```

---

## 🚀 DEPLOYMENT CHECKLIST

### Required Environment Variables

Add to `.env`:

```env
# Security Settings
APP_URL=https://crowdbricks.io
FRONTEND_URL=https://app.crowdbricks.io

# 2FA Settings
GOOGLE_2FA_ENABLED=true

# Rate Limiting
RATE_LIMIT_PER_MINUTE=60
RATE_LIMIT_SENSITIVE=10

# Session Settings
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

# File Upload
MAX_UPLOAD_SIZE=10240 # KB (10MB)
ALLOWED_MIME_TYPES=image/jpeg,image/png,application/pdf

# IP Geolocation API
IPAPI_KEY=your_api_key_here

# Malware Scanning (optional)
CLAMAV_ENABLED=false
VIRUSTOTAL_API_KEY=your_key_here
```

### Database Indexes (Already Applied)

All necessary indexes created in migrations:
- `two_factor_auth`: user_id (unique), (user_id, enabled)
- `login_history`: (user_id, created_at), (ip_address, status), session_id
- `audit_logs`: (user_id, created_at), (model_type, model_id), (action, created_at), (risk_level, flagged)
- `kyc_verifications`: (user_id, status), (document_type, status), third_party_reference
- `developer_wallets`: user_id (unique), (wallet_id, is_active)

### Security Headers (Add to Nginx/Apache)

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "no-referrer-when-downgrade" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';" always;
```

### Laravel Security Commands

```bash
# Generate application key
php artisan key:generate

# Cache config for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate

# Clear sensitive caches in production
php artisan config:clear
php artisan cache:clear
```

---

## 📊 MONITORING & ALERTS

### Set Up Alerts For:

1. **Critical Actions** (from audit_logs):
   - Withdrawal requests > $10,000
   - Account settings changed
   - 2FA disabled
   - KYC documents rejected

2. **Suspicious Activity** (from login_history):
   - Multiple failed login attempts (5+ in 10 minutes)
   - Login from new country
   - Login from multiple IPs simultaneously
   - Flagged logins

3. **Security Breaches**:
   - More than 10 flagged actions per user per day
   - Rapid-fire API requests (20+ in 5 minutes)
   - Password reset requests (3+ in 1 hour)

### Laravel Telescope Integration

Install for real-time monitoring:

```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

View security logs: `https://app.crowdbricks.io/telescope`

---

## 🎯 SECURITY SCORE CALCULATION

Current implementation in `SecurityController`:

```
Base Score: 50 points
+ 2FA Enabled: +30 points
+ No Suspicious Logins (30 days): +20 points
= Maximum: 100 points
```

### Future Enhancements:

```
+ KYC Verified: +10 points
+ Email Verified: +5 points
+ Phone Verified: +5 points
+ Strong Password (12+ chars, special chars): +5 points
+ Login Alerts Enabled: +3 points
+ Withdrawal PIN Set: +5 points
+ Business Verified: +7 points
```

---

## 📝 TESTING CHECKLIST

### Backend Tests

- [ ] 2FA enable/verify/disable flow
- [ ] Login history recording
- [ ] Audit log creation on actions
- [ ] Rate limiting enforcement
- [ ] Session termination
- [ ] Security score calculation
- [ ] Suspicious login detection

### Frontend Tests

- [ ] Security tab displays correctly
- [ ] 2FA QR code generation
- [ ] Recovery codes download
- [ ] Login history pagination
- [ ] Active sessions list
- [ ] Session termination button
- [ ] Security score visualization

### Integration Tests

- [ ] E2E 2FA setup flow
- [ ] Login from new device triggers alert
- [ ] Audit log captures project creation
- [ ] Withdrawal attempt logs correctly
- [ ] Rate limit blocks after threshold

---

## 🔒 SECURITY FEATURES SUMMARY

| Feature | Status | Priority |
|---------|--------|----------|
| **Database Tables** | ✅ Complete | Critical |
| **2FA System** | ✅ Backend Ready | Critical |
| **Login History** | ✅ Complete | High |
| **Audit Logging** | ✅ Middleware Ready | High |
| **Session Management** | ✅ Backend Ready | High |
| **Security Score** | ✅ Calculated | Medium |
| **KYC Structure** | ✅ Database Ready | High |
| **Wallet Security** | ✅ Database Ready | Critical |
| **Rate Limiting** | 🔶 Routes Ready | High |
| **CORS Config** | 🔶 Pending | Medium |
| **File Upload Security** | ❌ Not Started | High |
| **Frontend Security UI** | ❌ Not Started | Medium |
| **Email Alerts** | ❌ Not Started | Medium |
| **IP Geolocation** | ❌ Not Started | Low |
| **Malware Scanning** | ❌ Not Started | Low |

**Legend**:  
✅ = Complete  
🔶 = Partially Complete  
❌ = Not Started

---

**Next Steps**: Implement frontend Security tab and integrate file upload security service.

**Version**: 1.0.0  
**Last Updated**: November 7, 2025  
**Maintained By**: CrowdBricks Security Team
