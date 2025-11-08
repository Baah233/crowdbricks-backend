<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SecurityController extends Controller
{
    /**
     * Get security dashboard overview
     */
    public function overview()
    {
        $user = Auth::user();

        // Get 2FA status
        $twoFactorAuth = DB::table('two_factor_auth')
            ->where('user_id', $user->id)
            ->first();

        // Get recent login history
        $recentLogins = DB::table('login_history')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get active sessions count
        $activeSessions = DB::table('login_history')
            ->where('user_id', $user->id)
            ->whereNull('logout_at')
            ->count();

        // Check for suspicious activity
        $suspiciousLogins = DB::table('login_history')
            ->where('user_id', $user->id)
            ->where('is_suspicious', true)
            ->where('created_at', '>', now()->subDays(30))
            ->count();

        // Get security score
        $securityScore = $this->calculateSecurityScore($user);

        return response()->json([
            'twoFactorEnabled' => $twoFactorAuth?->enabled ?? false,
            'twoFactorMethod' => $twoFactorAuth?->method ?? null,
            'recentLogins' => $recentLogins,
            'activeSessions' => $activeSessions,
            'suspiciousActivity' => $suspiciousLogins,
            'securityScore' => $securityScore,
        ]);
    }

    /**
     * Enable 2FA - Generate secret and QR code
     */
    public function enable2FA(Request $request)
    {
        $user = Auth::user();
        
        // Generate simple secret (in production, use Google2FA library)
        $secret = bin2hex(random_bytes(16));

        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();

        // Store temporarily (not enabled yet)
        DB::table('two_factor_auth')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'user_id' => $user->id,
                'enabled' => false,
                'method' => $request->get('method', 'app'),
                'secret' => encrypt($secret),
                'recovery_codes' => json_encode($recoveryCodes),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Generate QR code URL (simplified)
        $qrCodeUrl = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=otpauth://totp/" . 
                     urlencode(config('app.name')) . ":" . urlencode($user->email) . 
                     "?secret=" . $secret . "&issuer=" . urlencode(config('app.name'));

        return response()->json([
            'secret' => $secret,
            'qrCodeUrl' => $qrCodeUrl,
            'recoveryCodes' => $recoveryCodes,
            'message' => 'Scan this QR code with your authenticator app',
        ]);
    }

    /**
     * Verify 2FA code
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'code' => 'required|string|min:6',
        ]);

        $user = Auth::user();
        $twoFactorAuth = DB::table('two_factor_auth')
            ->where('user_id', $user->id)
            ->first();

        if (!$twoFactorAuth) {
            return response()->json(['error' => '2FA not initiated'], 400);
        }

        // In production, verify with Google2FA library
        // For now, accept any 6-digit code for demo
        if (strlen($request->code) === 6) {
            // Enable 2FA
            DB::table('two_factor_auth')
                ->where('user_id', $user->id)
                ->update([
                    'enabled' => true,
                    'verified_at' => now(),
                    'updated_at' => now(),
                ]);

            // Log security event
            $this->logSecurityEvent($user, 'two_factor_enabled');

            return response()->json([
                'success' => true,
                'message' => '2FA has been enabled successfully',
            ]);
        }

        return response()->json(['error' => 'Invalid verification code'], 400);
    }

    /**
     * Disable 2FA
     */
    public function disable2FA(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid password'], 401);
        }

        // Disable 2FA
        DB::table('two_factor_auth')
            ->where('user_id', $user->id)
            ->update([
                'enabled' => false,
                'updated_at' => now(),
            ]);

        // Log security event
        $this->logSecurityEvent($user, 'two_factor_disabled');

        return response()->json([
            'success' => true,
            'message' => '2FA has been disabled',
        ]);
    }

    /**
     * Get login history
     */
    public function loginHistory(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 20);

        $history = DB::table('login_history')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($history);
    }

    /**
     * Get active sessions
     */
    public function activeSessions()
    {
        $user = Auth::user();

        $sessions = DB::table('login_history')
            ->where('user_id', $user->id)
            ->whereNull('logout_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($sessions);
    }

    /**
     * Terminate a specific session
     */
    public function terminateSession(Request $request, $sessionId)
    {
        $user = Auth::user();

        $session = DB::table('login_history')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        DB::table('login_history')
            ->where('id', $sessionId)
            ->update(['logout_at' => now()]);

        // Log security event
        $this->logSecurityEvent($user, 'session_terminated', ['session_id' => $sessionId]);

        return response()->json([
            'success' => true,
            'message' => 'Session terminated successfully',
        ]);
    }

    /**
     * Terminate all sessions except current
     */
    public function terminateAllSessions(Request $request)
    {
        $user = Auth::user();
        $currentSessionId = $request->header('X-Session-ID');

        DB::table('login_history')
            ->where('user_id', $user->id)
            ->whereNull('logout_at')
            ->where('session_id', '!=', $currentSessionId)
            ->update(['logout_at' => now()]);

        // Log security event
        $this->logSecurityEvent($user, 'all_sessions_terminated');

        return response()->json([
            'success' => true,
            'message' => 'All other sessions have been terminated',
        ]);
    }

    /**
     * Generate recovery codes
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = Str::upper(Str::random(4) . '-' . Str::random(4));
        }
        return $codes;
    }

    /**
     * Calculate security score (0-100)
     */
    private function calculateSecurityScore($user): int
    {
        $score = 50; // Base score

        // 2FA enabled (+30)
        $twoFactorAuth = DB::table('two_factor_auth')
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->first();
        if ($twoFactorAuth) $score += 30;

        // No suspicious logins in last 30 days (+20)
        $suspicious = DB::table('login_history')
            ->where('user_id', $user->id)
            ->where('is_suspicious', true)
            ->where('created_at', '>', now()->subDays(30))
            ->count();
        if ($suspicious === 0) $score += 20;

        return min($score, 100);
    }

    /**
     * Log security event to audit_logs
     */
    private function logSecurityEvent($user, string $action, array $data = [])
    {
        DB::table('audit_logs')->insert([
            'user_id' => $user->id,
            'action' => $action,
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => "Security action: {$action}",
            'new_values' => json_encode($data),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'risk_level' => 'high',
            'flagged' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
