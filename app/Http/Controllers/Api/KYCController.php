<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FileSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KYCController extends Controller
{
    protected $fileService;

    public function __construct(FileSecurityService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Get KYC status for current user
     */
    public function status()
    {
        $user = Auth::user();

        $kyc = DB::table('kyc_verifications')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $trustScore = $this->calculateTrustScore($user->id);

        return response()->json([
            'status' => $kyc?->status ?? 'not_submitted',
            'document_type' => $kyc?->document_type,
            'submitted_at' => $kyc?->submitted_at,
            'reviewed_at' => $kyc?->reviewed_at,
            'rejection_reason' => $kyc?->rejection_reason,
            'trust_score' => $trustScore,
            'verification_required' => !$kyc || $kyc->status !== 'approved',
        ]);
    }

    /**
     * Upload KYC documents
     */
    public function upload(Request $request)
    {
        $request->validate([
            'document_type' => 'required|in:national_id,passport,drivers_license,business_registration,land_title,tax_certificate',
            'document_number' => 'required|string|max:100',
            'document_front' => 'required|file|mimes:jpeg,jpg,png,pdf|max:10240',
            'document_back' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:10240',
            'selfie' => 'nullable|file|mimes:jpeg,jpg,png|max:5120',
        ]);

        $user = Auth::user();

        // Check if already verified
        $existing = DB::table('kyc_verifications')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();

        if ($existing) {
            return response()->json(['error' => 'KYC already verified'], 400);
        }

        // Validate front document
        $frontFile = $request->file('document_front');
        $frontValidation = $this->fileService->validateUpload($frontFile);
        if (!$frontValidation['valid']) {
            return response()->json(['error' => $frontValidation['error']], 400);
        }

        // Scan for viruses
        if (!$this->fileService->scanForViruses($frontFile)) {
            return response()->json(['error' => 'File failed security scan'], 400);
        }

        // Store front document
        $frontPath = $this->fileService->storeSecurely($frontFile, 'kyc', $user->id);

        // Handle back document if provided
        $backPath = null;
        if ($request->hasFile('document_back')) {
            $backFile = $request->file('document_back');
            $backValidation = $this->fileService->validateUpload($backFile);
            
            if (!$backValidation['valid']) {
                return response()->json(['error' => $backValidation['error']], 400);
            }

            if (!$this->fileService->scanForViruses($backFile)) {
                return response()->json(['error' => 'Back document failed security scan'], 400);
            }

            $backPath = $this->fileService->storeSecurely($backFile, 'kyc', $user->id);
        }

        // Handle selfie if provided
        $selfiePath = null;
        if ($request->hasFile('selfie')) {
            $selfieFile = $request->file('selfie');
            $selfieValidation = $this->fileService->validateUpload($selfieFile);
            
            if (!$selfieValidation['valid']) {
                return response()->json(['error' => $selfieValidation['error']], 400);
            }

            $selfiePath = $this->fileService->storeSecurely($selfieFile, 'kyc', $user->id);
        }

        // Insert KYC record
        $kycId = DB::table('kyc_verifications')->insertGetId([
            'user_id' => $user->id,
            'document_type' => $request->document_type,
            'document_number' => $request->document_number,
            'document_front_path' => $frontPath,
            'document_back_path' => $backPath,
            'selfie_path' => $selfiePath,
            'status' => 'pending',
            'verification_method' => 'manual',
            'submitted_at' => now(),
            'trust_score' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log audit event
        $this->logAuditEvent($user, 'kyc_submitted', $kycId);

        return response()->json([
            'success' => true,
            'message' => 'KYC documents submitted successfully',
            'kyc_id' => $kycId,
            'status' => 'pending',
        ], 201);
    }

    /**
     * Get KYC documents (admin only)
     */
    public function getDocuments(Request $request, $kycId)
    {
        $user = Auth::user();

        $kyc = DB::table('kyc_verifications')
            ->where('id', $kycId)
            ->first();

        if (!$kyc) {
            return response()->json(['error' => 'KYC record not found'], 404);
        }

        // Only allow user to view their own documents, or admin to view any
        if ($kyc->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $documents = [];

        if ($kyc->document_front_path) {
            $documents['front'] = $this->fileService->getSignedUrl($kyc->document_front_path, 15);
        }

        if ($kyc->document_back_path) {
            $documents['back'] = $this->fileService->getSignedUrl($kyc->document_back_path, 15);
        }

        if ($kyc->selfie_path) {
            $documents['selfie'] = $this->fileService->getSignedUrl($kyc->selfie_path, 15);
        }

        return response()->json([
            'kyc_id' => $kyc->id,
            'status' => $kyc->status,
            'document_type' => $kyc->document_type,
            'documents' => $documents,
            'submitted_at' => $kyc->submitted_at,
        ]);
    }

    /**
     * Approve KYC (admin only)
     */
    public function approve(Request $request, $kycId)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'trust_score' => 'nullable|integer|min:0|max:100',
        ]);

        $kyc = DB::table('kyc_verifications')
            ->where('id', $kycId)
            ->first();

        if (!$kyc) {
            return response()->json(['error' => 'KYC record not found'], 404);
        }

        // Update KYC status
        DB::table('kyc_verifications')
            ->where('id', $kycId)
            ->update([
                'status' => 'approved',
                'trust_score' => $request->get('trust_score', 80),
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'expires_at' => now()->addYears(2), // KYC valid for 2 years
                'updated_at' => now(),
            ]);

        // Log audit event
        $kycUser = DB::table('users')->find($kyc->user_id);
        $this->logAuditEvent($kycUser, 'kyc_approved', $kycId);

        return response()->json([
            'success' => true,
            'message' => 'KYC approved successfully',
        ]);
    }

    /**
     * Reject KYC (admin only)
     */
    public function reject(Request $request, $kycId)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $kyc = DB::table('kyc_verifications')
            ->where('id', $kycId)
            ->first();

        if (!$kyc) {
            return response()->json(['error' => 'KYC record not found'], 404);
        }

        // Update KYC status
        DB::table('kyc_verifications')
            ->where('id', $kycId)
            ->update([
                'status' => 'rejected',
                'rejection_reason' => $request->reason,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        // Log audit event
        $kycUser = DB::table('users')->find($kyc->user_id);
        $this->logAuditEvent($kycUser, 'kyc_rejected', $kycId);

        return response()->json([
            'success' => true,
            'message' => 'KYC rejected',
        ]);
    }

    /**
     * Calculate trust score
     */
    private function calculateTrustScore(int $userId): int
    {
        $score = 0;

        // KYC approved (+50)
        $kyc = DB::table('kyc_verifications')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->first();

        if ($kyc) {
            $score += 50;
        }

        // Email verified (+20)
        $user = DB::table('users')->find($userId);
        if ($user && $user->email_verified_at) {
            $score += 20;
        }

        // 2FA enabled (+15)
        $twoFactor = DB::table('two_factor_auth')
            ->where('user_id', $userId)
            ->where('enabled', true)
            ->first();

        if ($twoFactor) {
            $score += 15;
        }

        // No suspicious activity (+15)
        $suspicious = DB::table('login_history')
            ->where('user_id', $userId)
            ->where('is_suspicious', true)
            ->where('created_at', '>', now()->subDays(30))
            ->count();

        if ($suspicious === 0) {
            $score += 15;
        }

        return min($score, 100);
    }

    /**
     * Log audit event
     */
    private function logAuditEvent($user, string $action, $kycId)
    {
        DB::table('audit_logs')->insert([
            'user_id' => $user->id ?? null,
            'action' => $action,
            'model_type' => 'KYCVerification',
            'model_id' => $kycId,
            'description' => "KYC action: {$action}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'risk_level' => 'high',
            'flagged' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
