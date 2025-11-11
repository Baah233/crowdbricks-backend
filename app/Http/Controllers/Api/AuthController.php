<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * 📝 Register a new user
     */
    public function register(Request $request)
    {
        // ✅ normalize frontend camelCase field
        if ($request->has('ghanaCard') && !$request->has('ghana_card')) {
            $request->merge(['ghana_card' => $request->ghanaCard]);
        }

        \Log::info('REGISTER PAYLOAD', $request->all());


        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'company'    => 'nullable|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',
            'ghana_card' => 'nullable|string|max:20',
            'user_type'  => 'required|string|in:developer,investor,user,admin',
        ]);

       // Map roles according to user_type
        $role = match ($validated['user_type']) {
            'admin' => 'admin',
            'developer' => 'developer',
            'investor' => 'investor',
            default => 'user',
        };

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'company'    => $validated['company'] ?? null,
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'ghana_card' => $validated['ghana_card'] ?? null,
            'user_type'  => $validated['user_type'],
            'role'       => $role,
            'status'     => 'pending', // New users need admin approval
        ]);

        // Notify all admins about new registration
        $this->notifyAdminsOfNewRegistration($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Your account is pending admin approval.',
            'data' => [
                'token' => $token,
                'user' => $this->formatUser($user),
            ],
        ], 201);
    }

    /**
     * 🔑 Login existing user
     */
    public function login(Request $request)
    {
        // ✅ normalize frontend camelCase field
        if ($request->has('ghanaCard') && !$request->has('ghana_card')) {
            $request->merge(['ghana_card' => $request->ghanaCard]);
        }

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'user_type' => 'required|string|in:developer,investor,admin,user',
            'ghana_card' => 'nullable|string|max:20',
        ]);

        // Match both email and user_type
        $user = User::where('email', $credentials['email'])
            ->where('user_type', $credentials['user_type'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            // Log failed login attempt
            if ($user) {
                $this->logLoginActivity($request, $user, 'failed', 'Invalid password');
            }
            
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        // ✅ Developer Ghana Card verification
        if ($credentials['user_type'] === 'developer') {
            if (!$credentials['ghana_card']) {
                throw ValidationException::withMessages([
                    'ghana_card' => ['Ghana Card number is required for developers.'],
                ]);
            }

            if (strcasecmp($user->ghana_card, $credentials['ghana_card']) !== 0) {
                throw ValidationException::withMessages([
                    'ghana_card' => ['Ghana Card number does not match our records.'],
                ]);
            }
        }

        // Remove old tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Log login activity and get session ID
        $sessionId = $this->logLoginActivity($request, $user, 'success');

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => $this->formatUser($user),
                'session_id' => $sessionId,
            ],
        ], 200);
    }

    /**
     * Log login activity
     */
    protected function logLoginActivity($request, $user, $status, $failureReason = null)
    {
        $deviceService = app(\App\Services\DeviceDetectionService::class);
        $deviceInfo = $deviceService->parseDeviceInfo($request);

        // Check if this is a suspicious login (new device/location)
        $lastLogin = DB::table('login_history')
            ->where('user_id', $user->id)
            ->where('status', 'success')
            ->orderBy('created_at', 'desc')
            ->first();
        
        $isSuspicious = false;
        
        if ($lastLogin) {
            $isSuspicious = $lastLogin->ip_address !== $deviceInfo['ip_address'] ||
                           $lastLogin->device_name !== $deviceInfo['device_name'];
        }

        // Generate session ID
        $sessionId = \Illuminate\Support\Str::uuid();

        // Insert into login_history table
        DB::table('login_history')->insert([
            'user_id' => $user->id,
            'ip_address' => $deviceInfo['ip_address'],
            'user_agent' => $deviceInfo['user_agent'],
            'device_type' => $deviceInfo['device_type'],
            'device_name' => $deviceInfo['device_name'],
            'browser' => $deviceInfo['browser'],
            'platform' => $deviceInfo['platform'],
            'location' => null, // TODO: Add IP geolocation
            'session_id' => $sessionId,
            'status' => $status,
            'failure_reason' => $failureReason,
            'is_suspicious' => $isSuspicious,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send notification if suspicious
        if ($isSuspicious && $status === 'success') {
            // TODO: Send email/SMS notification about new device login
        }

        return $sessionId;
    }

    /**
     * 👤 Get authenticated user info
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'success' => true,
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * 🚪 Logout current user
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * 🔐 Change user password
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Update password
        $user->password = Hash::make($validated['new_password']);
        $user->save();

        // Optionally revoke all other tokens except current
        // $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * 🧩 Helper: format user payload for response
     */
    private function formatUser(User $user)
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'company' => $user->company,
            'user_type' => $user->user_type,
            'ghana_card' => $user->ghana_card,
            'created_at' => $user->created_at,
        ];
    }

    /**
     * 🔔 Notify admins about new user registration
     */
    private function notifyAdminsOfNewRegistration(User $user)
    {
        try {
            // Get all admin users
            $admins = User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                // Create in-app notification
                \App\Models\AdminNotification::create([
                    'user_id' => $admin->id,
                    'type' => 'new_registration',
                    'title' => 'New User Registration',
                    'message' => "{$user->name} ({$user->user_type}) has registered and is awaiting approval.",
                    'data' => [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                        'user_type' => $user->user_type,
                        'registered_at' => $user->created_at->toISOString(),
                    ],
                    'read' => false,
                ]);
            }

            \Log::info('Notified admins about new registration', ['user_id' => $user->id, 'admin_count' => $admins->count()]);
        } catch (\Exception $e) {
            \Log::error('Failed to notify admins about new registration: ' . $e->getMessage());
        }
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address.',
            ], 404);
        }

        // Generate a unique reset token
        $token = bin2hex(random_bytes(32));
        
        // Store the token (you can create a password_resets table or store in user)
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // For now, return the token (in production, send email)
        // TODO: Send email with reset link
        $resetUrl = config('app.frontend_url', 'http://localhost:5173') . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);

        \Log::info('Password reset requested', [
            'email' => $request->email,
            'reset_url' => $resetUrl,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset instructions have been sent to your email.',
            'data' => [
                'reset_url' => $resetUrl, // Remove this in production
            ],
        ]);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $resetRecord = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset token.',
            ], 400);
        }

        // Verify token
        if (!Hash::check($request->token, $resetRecord->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset token.',
            ], 400);
        }

        // Check if token is expired (24 hours)
        if (now()->diffInHours($resetRecord->created_at) > 24) {
            return response()->json([
                'success' => false,
                'message' => 'Reset token has expired. Please request a new one.',
            ], 400);
        }

        // Update password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the used token
        \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully. You can now login with your new password.',
        ]);
    }
}

