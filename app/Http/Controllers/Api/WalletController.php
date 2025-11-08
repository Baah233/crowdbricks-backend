<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    /**
     * Get wallet balance and info
     */
    public function getWallet(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get or create wallet
        $wallet = $user->wallet()->firstOrCreate([
            'user_id' => $user->id
        ], [
            'balance' => 0,
            'currency' => 'GHS',
            'status' => 'active'
        ]);

        return response()->json([
            'wallet' => [
                'id' => $wallet->id,
                'balance' => $wallet->balance,
                'currency' => $wallet->currency,
                'status' => $wallet->status,
            ]
        ]);
    }

    /**
     * Deposit funds into wallet
     */
    public function deposit(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:momo,card,bank_transfer',
            'payment_reference' => 'nullable|string',
        ]);

        $user = $request->user();
        
        // Get or create wallet
        $wallet = $user->wallet()->firstOrCreate([
            'user_id' => $user->id
        ], [
            'balance' => 0,
            'currency' => 'GHS',
            'status' => 'active'
        ]);

        if ($wallet->status !== 'active') {
            return response()->json(['message' => 'Wallet is not active'], 422);
        }

        DB::beginTransaction();
        try {
            // In a real app, you would integrate with a payment gateway here
            // For now, we'll create a pending transaction that would be confirmed by payment webhook
            
            $transaction = $wallet->credit(
                $request->amount,
                'deposit',
                [
                    'payment_method' => $request->payment_method,
                    'payment_reference' => $request->payment_reference,
                    'ip_address' => $request->ip(),
                ]
            );

            $transaction->update([
                'reference' => 'DEP-' . strtoupper(Str::random(10)),
                'payment_method' => $request->payment_method,
                'payment_reference' => $request->payment_reference,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Deposit successful',
                'transaction' => [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'balance' => $wallet->fresh()->balance,
                ]
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Wallet deposit failed: ' . $e->getMessage());
            return response()->json(['message' => 'Deposit failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Withdraw funds from wallet
     */
    public function withdraw(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:momo,bank_transfer',
            'account_details' => 'required|array',
        ]);

        $user = $request->user();
        $wallet = $user->wallet;

        if (!$wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        if ($wallet->status !== 'active') {
            return response()->json(['message' => 'Wallet is not active'], 422);
        }

        if ($wallet->balance < $request->amount) {
            return response()->json(['message' => 'Insufficient balance'], 422);
        }

        DB::beginTransaction();
        try {
            // In a real app, you would integrate with a payment gateway here
            // For now, we'll create the withdrawal transaction
            
            $transaction = $wallet->debit(
                $request->amount,
                'withdrawal',
                [
                    'payment_method' => $request->payment_method,
                    'account_details' => $request->account_details,
                    'ip_address' => $request->ip(),
                ]
            );

            $transaction->update([
                'reference' => 'WTH-' . strtoupper(Str::random(10)),
                'payment_method' => $request->payment_method,
                'notes' => 'Withdrawal request submitted',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Withdrawal request submitted successfully',
                'transaction' => [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'balance' => $wallet->fresh()->balance,
                ]
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Wallet withdrawal failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get wallet transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->wallet;

        if (!$wallet) {
            return response()->json(['transactions' => []]);
        }

        $transactions = $wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'balance_before' => $transaction->balance_before,
                    'balance_after' => $transaction->balance_after,
                    'reference' => $transaction->reference,
                    'status' => $transaction->status,
                    'payment_method' => $transaction->payment_method,
                    'notes' => $transaction->notes,
                    'date' => $transaction->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json($transactions);
    }

    /**
     * Set/Update transaction PIN for developer wallet
     */
    public function setTransactionPin(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'pin' => 'required|digits:4',
            'pin_confirmation' => 'required|same:pin',
        ]);

        $user = $request->user();

        // Verify password
        if (!\Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Invalid password'], 401);
        }

        // Get or create developer wallet
        $wallet = DB::table('developer_wallets')
            ->where('user_id', $user->id)
            ->first();

        if (!$wallet) {
            DB::table('developer_wallets')->insert([
                'user_id' => $user->id,
                'wallet_id' => Str::uuid(),
                'balance' => 0,
                'pending_balance' => 0,
                'lifetime_earnings' => 0,
                'currency' => 'GHS',
                'transaction_pin_hash' => \Hash::make($request->pin),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('developer_wallets')
                ->where('user_id', $user->id)
                ->update([
                    'transaction_pin_hash' => \Hash::make($request->pin),
                    'failed_withdrawal_attempts' => 0,
                    'locked_until' => null,
                    'updated_at' => now(),
                ]);
        }

        // Log audit event
        $this->logAuditEvent($user, 'transaction_pin_set');

        return response()->json([
            'success' => true,
            'message' => 'Transaction PIN set successfully',
        ]);
    }

    /**
     * Verify transaction PIN
     */
    public function verifyPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => 'required|digits:4',
        ]);

        $user = $request->user();

        $wallet = DB::table('developer_wallets')
            ->where('user_id', $user->id)
            ->first();

        if (!$wallet) {
            return response()->json(['error' => 'Wallet not found'], 404);
        }

        // Check if wallet is locked
        if ($wallet->locked_until && now()->lt($wallet->locked_until)) {
            return response()->json(['error' => 'Wallet is temporarily locked'], 423);
        }

        // Verify PIN
        if (!\Hash::check($request->pin, $wallet->transaction_pin_hash)) {
            // Increment failed attempts
            $failedAttempts = $wallet->failed_withdrawal_attempts + 1;
            $lockedUntil = null;

            // Lock wallet after 3 failed attempts (1 hour lockout)
            if ($failedAttempts >= 3) {
                $lockedUntil = now()->addHour();
            }

            DB::table('developer_wallets')
                ->where('user_id', $user->id)
                ->update([
                    'failed_withdrawal_attempts' => $failedAttempts,
                    'locked_until' => $lockedUntil,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'error' => 'Invalid PIN',
                'attempts_remaining' => max(0, 3 - $failedAttempts),
            ], 401);
        }

        // Reset failed attempts on success
        DB::table('developer_wallets')
            ->where('user_id', $user->id)
            ->update([
                'failed_withdrawal_attempts' => 0,
                'locked_until' => null,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'PIN verified',
        ]);
    }

    /**
     * Request withdrawal with double verification (password + PIN or 2FA)
     */
    public function requestSecureWithdrawal(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:10|max:100000',
            'password' => 'required|string',
            'pin' => 'required|digits:4',
            'withdrawal_account' => 'required|string|max:100',
            'withdrawal_provider' => 'required|in:MTN,Vodafone,AirtelTigo,Bank',
        ]);

        $user = $request->user();

        // Verify password
        if (!\Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid password'], 401);
        }

        $wallet = DB::table('developer_wallets')
            ->where('user_id', $user->id)
            ->first();

        if (!$wallet) {
            return response()->json(['error' => 'Wallet not found'], 404);
        }

        // Check if wallet is locked
        if ($wallet->locked_until && now()->lt($wallet->locked_until)) {
            return response()->json(['error' => 'Wallet is temporarily locked'], 423);
        }

        // Check if wallet is active
        if (!$wallet->is_active) {
            return response()->json(['error' => 'Wallet is not active'], 403);
        }

        // Verify PIN
        if (!\Hash::check($request->pin, $wallet->transaction_pin_hash)) {
            $failedAttempts = $wallet->failed_withdrawal_attempts + 1;
            $lockedUntil = $failedAttempts >= 3 ? now()->addHour() : null;

            DB::table('developer_wallets')
                ->where('user_id', $user->id)
                ->update([
                    'failed_withdrawal_attempts' => $failedAttempts,
                    'locked_until' => $lockedUntil,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'error' => 'Invalid PIN',
                'attempts_remaining' => max(0, 3 - $failedAttempts),
            ], 401);
        }

        // Check balance
        if ($wallet->balance < $request->amount) {
            return response()->json(['error' => 'Insufficient balance'], 422);
        }

        DB::beginTransaction();
        try {
            // Deduct from balance
            DB::table('developer_wallets')
                ->where('user_id', $user->id)
                ->decrement('balance', $request->amount);

            // Create withdrawal record (would integrate with payment gateway)
            $withdrawalId = DB::table('wallet_transactions')->insertGetId([
                'user_id' => $user->id,
                'type' => 'withdrawal',
                'amount' => $request->amount,
                'status' => 'pending',
                'payment_method' => $request->withdrawal_provider,
                'account_details' => json_encode([
                    'account' => $request->withdrawal_account,
                    'provider' => $request->withdrawal_provider,
                ]),
                'reference' => 'WTH-' . strtoupper(Str::random(10)),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log audit event
            $this->logAuditEvent($user, 'withdrawal_requested', $withdrawalId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted',
                'withdrawal_id' => $withdrawalId,
                'amount' => $request->amount,
                'status' => 'pending',
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Secure withdrawal failed: ' . $e->getMessage());
            return response()->json(['error' => 'Withdrawal failed'], 500);
        }
    }

    /**
     * Toggle auto-withdraw setting
     */
    public function toggleAutoWithdraw(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'withdrawal_account' => 'nullable|string|max:100',
            'withdrawal_provider' => 'nullable|in:MTN,Vodafone,AirtelTigo,Bank',
        ]);

        $user = $request->user();

        DB::table('developer_wallets')
            ->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'auto_withdraw' => $request->enabled,
                    'withdrawal_account' => $request->withdrawal_account,
                    'withdrawal_provider' => $request->withdrawal_provider,
                    'updated_at' => now(),
                ]
            );

        return response()->json([
            'success' => true,
            'auto_withdraw' => $request->enabled,
        ]);
    }

    /**
     * Get developer wallet details
     */
    public function getDeveloperWallet(Request $request): JsonResponse
    {
        $user = $request->user();

        $wallet = DB::table('developer_wallets')
            ->where('user_id', $user->id)
            ->first();

        if (!$wallet) {
            return response()->json([
                'wallet' => null,
                'has_pin' => false,
            ]);
        }

        return response()->json([
            'wallet' => [
                'balance' => $wallet->balance,
                'pending_balance' => $wallet->pending_balance,
                'lifetime_earnings' => $wallet->lifetime_earnings,
                'currency' => $wallet->currency,
                'auto_withdraw' => $wallet->auto_withdraw,
                'withdrawal_account' => $wallet->withdrawal_account,
                'withdrawal_provider' => $wallet->withdrawal_provider,
                'is_active' => $wallet->is_active,
                'is_locked' => $wallet->locked_until && now()->lt($wallet->locked_until),
            ],
            'has_pin' => !empty($wallet->transaction_pin_hash),
        ]);
    }

    /**
     * Log audit event
     */
    private function logAuditEvent($user, string $action, $modelId = null)
    {
        DB::table('audit_logs')->insert([
            'user_id' => $user->id ?? null,
            'action' => $action,
            'model_type' => 'DeveloperWallet',
            'model_id' => $modelId,
            'description' => "Wallet action: {$action}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'risk_level' => 'critical',
            'flagged' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
