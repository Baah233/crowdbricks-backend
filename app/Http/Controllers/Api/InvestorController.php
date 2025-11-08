<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Cache;

class InvestorController extends Controller
{
    /**
     * Get investor dashboard statistics (cached for 5 minutes)
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Cache stats for 5 minutes to reduce database load
        $stats = Cache::remember("user.{$user->id}.stats", 300, function () use ($user) {
            // Optimized query with single database hit
            $investments = Investment::where('user_id', $user->id)
                ->select('amount', 'status', 'project_id')
                ->get();
            
            $totalInvested = $investments->sum('amount');
            $activeProjects = $investments->where('status', 'confirmed')
                ->unique('project_id')
                ->count();
            
            // Calculate returns from dividends
            $totalReturns = DB::table('dividends')
                ->where('user_id', $user->id)
                ->where('status', 'paid')
                ->sum('amount');
            
            $portfolioValue = $totalInvested + $totalReturns;

            return [
                'totalInvested' => $totalInvested,
                'totalReturns' => $totalReturns,
                'activeProjects' => $activeProjects,
                'portfolioValue' => $portfolioValue,
            ];
        });

        return response()->json($stats);
    }

    /**
     * Get investor's investments (with eager loading optimization)
     */
    public function investments(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Cache investments for 2 minutes
        $investments = Cache::remember("user.{$user->id}.investments", 120, function () use ($user) {
            return Investment::where('user_id', $user->id)
                ->with(['project:id,title,target_funding,current_funding,funding_status,categories'])
                ->latest()
                ->get()
                ->map(function ($investment) {
                    $project = $investment->project;
                    
                    // Extract first category as type if available
                    $type = 'Unknown';
                    if ($project && $project->categories) {
                        $categories = is_string($project->categories) 
                            ? json_decode($project->categories, true) 
                            : $project->categories;
                        $type = is_array($categories) && count($categories) > 0 
                            ? $categories[0] 
                            : 'Unknown';
                    }
                    
                    return [
                        'id' => $investment->id,
                        'title' => $project ? $project->title : 'Unknown Project',
                        'projectId' => $investment->project_id,
                        'type' => $type,
                        'invested' => $investment->amount,
                        'currentValue' => $investment->amount,
                        'progress' => $project && $project->target_funding > 0 
                            ? round(($project->current_funding / $project->target_funding) * 100, 2) 
                            : 0,
                        'status' => ucfirst($investment->status),
                        'date' => $investment->created_at->format('Y-m-d'),
                    ];
                });
        });

        return response()->json($investments);
    }

    /**
     * Get investor's transactions (investments, deposits, and withdrawals)
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $allTransactions = [];
        
        // Get wallet transactions (deposits and withdrawals)
        $walletTransactions = \App\Models\WalletTransaction::where('user_id', $user->id)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->reference ?? 'WT-' . $tx->id,
                    'date' => $tx->created_at->format('Y-m-d H:i'),
                    'desc' => ucfirst($tx->type) . ' - ' . ($tx->notes ?? 'Wallet transaction'),
                    'amount' => $tx->type === 'deposit' ? $tx->amount : -$tx->amount,
                    'method' => ucfirst($tx->payment_method ?? 'N/A'),
                    'status' => ucfirst($tx->status ?? 'completed'),
                    'type' => $tx->type,
                    'created_at' => $tx->created_at,
                ];
            })->toArray();
        
        // Get investment transactions
        $investments = Investment::where('user_id', $user->id)
            ->with('project:id,title')
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => 'INV-' . $inv->id,
                    'date' => $inv->created_at->format('Y-m-d H:i'),
                    'desc' => 'Investment in ' . ($inv->project->title ?? 'Project'),
                    'amount' => -$inv->amount,
                    'method' => ucfirst($inv->payment_method ?? 'Card'),
                    'status' => ucfirst($inv->status),
                    'type' => 'investment',
                    'created_at' => $inv->created_at,
                ];
            })->toArray();
        
        // Merge all transactions
        $allTransactions = array_merge($walletTransactions, $investments);
        
        // Sort by date (most recent first)
        usort($allTransactions, function($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });
        
        // Remove the created_at field (used only for sorting)
        $allTransactions = array_map(function($tx) {
            unset($tx['created_at']);
            return $tx;
        }, $allTransactions);
        
        // Limit to 100 most recent transactions
        $allTransactions = array_slice($allTransactions, 0, 100);

        return response()->json($allTransactions);
    }

    /**
     * Get portfolio value history
     */
    public function portfolioHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get investments grouped by month
        $history = Investment::where('user_id', $user->id)
            ->where('status', '!=', 'rejected')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as date'),
                DB::raw('SUM(amount) as value')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
        
        // Calculate cumulative values
        $cumulative = 0;
        $result = $history->map(function ($item) use (&$cumulative) {
            $cumulative += $item->value;
            return [
                'date' => $item->date,
                'value' => $cumulative,
            ];
        });

        // If no history, return current month with 0
        if ($result->isEmpty()) {
            return response()->json([[
                'date' => now()->format('Y-m'),
                'value' => 0,
            ]]);
        }

        return response()->json($result);
    }

    /**
     * Get investor profile
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->updateProfileCompletion();
        
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'phone' => $user->phone,
            'phone_verified' => $user->phone_verified ?? false,
            'phone_change_request' => $user->phone_change_request,
            'phone_change_status' => $user->phone_change_status,
            'status' => $user->status,
            'verification_id' => $user->verification_id,
            'two_factor_enabled' => $user->two_factor_enabled ?? false,
            'two_factor_required' => $user->two_factor_required ?? false,
            'email_notifications' => $user->email_notifications ?? true,
            'sms_notifications' => $user->sms_notifications ?? false,
            'profile_picture' => $user->profile_picture ? url('storage/' . $user->profile_picture) : null,
            'profile_completion' => $user->profile_completion ?? 0,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
            ]
        ]);
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'email_notifications' => 'sometimes|boolean',
            'sms_notifications' => 'sometimes|boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Preferences updated successfully',
            'preferences' => [
                'email_notifications' => $user->email_notifications,
                'sms_notifications' => $user->sms_notifications ?? false,
            ]
        ]);
    }

    /**
     * Enable two-factor authentication
     */
    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        // Generate a random 6-digit secret
        $secret = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication enabled',
            'secret' => $secret,
        ]);
    }

    /**
     * Disable two-factor authentication
     */
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication disabled',
        ]);
    }

    /**
     * Verify two-factor code
     */
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        if ($user->two_factor_secret === $validated['code']) {
            // Mark 2FA as no longer required after successful setup
            $user->two_factor_required = false;
            $user->save();
            
            return response()->json([
                'message' => 'Code verified successfully',
                'verified' => true,
            ]);
        }

        return response()->json([
            'message' => 'Invalid code',
            'verified' => false,
        ], 400);
    }

    /**
     * Request phone change (requires admin approval)
     */
    public function requestPhoneChange(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $oldPhone = $user->phone;

        $user->update([
            'phone_change_request' => $validated['phone'],
            'phone_change_status' => 'pending',
        ]);

        // Notify all admins about the phone change request
        $admins = User::where(function($query) {
            $query->where('role', 'admin')
                  ->orWhere('user_type', 'admin');
        })->get();
        
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\PhoneChangeRequestNotification(
                $user,
                $validated['phone'],
                $oldPhone
            ));
        }

        return response()->json([
            'message' => 'Phone change request submitted for admin approval',
            'phone_change_request' => $validated['phone'],
        ]);
    }

    /**
     * Send phone verification code
     */
    public function sendPhoneVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->phone) {
            return response()->json(['message' => 'No phone number on file'], 400);
        }

        // Generate 6-digit code
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Set expiration to 15 minutes from now
        $expiresAt = now()->addMinutes(15);
        
        $user->update([
            'phone_verification_code' => $code,
            'phone_verification_code_expires_at' => $expiresAt,
        ]);

        // Send SMS via Twilio
        $twilioService = app(\App\Services\TwilioService::class);
        $smsSent = $twilioService->sendVerificationCode($user->phone, $code);

        if (!$smsSent) {
            \Log::warning('Twilio not configured. Development mode: Verification code would be sent to ' . $user->phone);
            // In development, you can still return code for testing
            if (config('app.env') === 'local') {
                return response()->json([
                    'message' => 'Verification code generated (dev mode)',
                    'code' => $code, // REMOVE IN PRODUCTION
                    'expires_at' => $expiresAt,
                ]);
            }
        }

        return response()->json([
            'message' => 'Verification code sent to ' . substr($user->phone, -4),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Verify phone with code
     */
    public function verifyPhone(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        // Check if code has expired
        if ($user->phone_verification_code_expires_at && $user->phone_verification_code_expires_at->isPast()) {
            return response()->json([
                'message' => 'Verification code has expired. Please request a new one.',
                'verified' => false,
            ], 400);
        }

        if ($user->phone_verification_code === $validated['code']) {
            $user->update([
                'phone_verified' => true,
                'phone_verification_code' => null,
                'phone_verification_code_expires_at' => null,
            ]);

            $user->updateProfileCompletion();

            // Send confirmation email
            \Mail::to($user->email)->send(new \App\Mail\PhoneVerifiedMail($user));

            // Send SMS confirmation
            $twilioService = app(\App\Services\TwilioService::class);
            $twilioService->sendNotification(
                $user->phone,
                "Your phone number has been successfully verified on CrowdBricks. You will now receive SMS notifications."
            );

            return response()->json([
                'message' => 'Phone verified successfully',
                'verified' => true,
            ]);
        }

        return response()->json([
            'message' => 'Invalid verification code',
            'verified' => false,
        ], 400);
    }

    /**
     * Upload profile picture
     */
    public function uploadProfilePicture(Request $request): JsonResponse
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
        ]);

        $user = $request->user();

        // Delete old profile picture if exists
        if ($user->profile_picture && \Storage::exists('public/' . $user->profile_picture)) {
            \Storage::delete('public/' . $user->profile_picture);
        }

        // Store new profile picture
        $path = $request->file('profile_picture')->store('profile_pictures', 'public');

        $user->update([
            'profile_picture' => $path,
        ]);

        $user->updateProfileCompletion();

        return response()->json([
            'message' => 'Profile picture uploaded successfully',
            'profile_picture' => url('storage/' . $path),
        ]);
    }

    /**
     * Get user's login activity history
     */
    public function loginActivities(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $activities = $user->loginActivities()
            ->latest('login_at')
            ->limit(20)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'device' => $activity->device_name,
                    'browser' => $activity->browser,
                    'platform' => $activity->platform,
                    'ip_address' => $activity->ip_address,
                    'location' => $activity->location ?: 'Unknown',
                    'login_at' => $activity->login_at->format('Y-m-d H:i:s'),
                    'logout_at' => $activity->logout_at?->format('Y-m-d H:i:s'),
                    'status' => $activity->status,
                    'is_suspicious' => $activity->is_suspicious,
                    'is_current' => $activity->logout_at === null && $activity->status === 'success',
                    'session_duration' => $activity->session_duration,
                ];
            });

        return response()->json($activities);
    }

    /**
     * Logout a specific device/session
     */
    public function logoutDevice(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        $activity = $user->loginActivities()->findOrFail($id);
        
        if ($activity->logout_at) {
            return response()->json([
                'message' => 'This session is already logged out',
            ], 400);
        }
        
        $activity->update([
            'logout_at' => now(),
        ]);

        return response()->json([
            'message' => 'Device logged out successfully',
        ]);
    }

    /**
     * Get user's dividend history (cached for 3 minutes)
     */
    public function dividends(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $data = Cache::remember("user.{$user->id}.dividends", 180, function () use ($user) {
            $dividends = $user->dividends()
                ->with(['project:id,title', 'investment:id,amount'])
                ->get()
                ->map(function ($dividend) {
                    return [
                        'id' => $dividend->id,
                        'project_title' => $dividend->project?->title ?? 'Unknown Project',
                        'amount' => $dividend->amount,
                        'percentage' => $dividend->percentage,
                        'type' => ucfirst($dividend->type),
                        'status' => ucfirst($dividend->status),
                        'declaration_date' => $dividend->declaration_date->format('Y-m-d'),
                        'payment_date' => $dividend->payment_date?->format('Y-m-d'),
                        'payment_method' => $dividend->payment_method,
                        'yield' => $dividend->yield,
                        'is_overdue' => $dividend->isOverdue(),
                    ];
                });

            // Calculate summary stats
            $totalEarned = $dividends->where('status', 'Paid')->sum('amount');
            $totalPending = $dividends->whereIn('status', ['Pending', 'Processing'])->sum('amount');
            $nextPayment = $dividends->where('status', 'Pending')->sortBy('payment_date')->first();

            return [
                'dividends' => $dividends,
                'summary' => [
                    'total_earned' => $totalEarned,
                    'total_pending' => $totalPending,
                    'next_payment' => $nextPayment ? [
                        'amount' => $nextPayment['amount'],
                        'date' => $nextPayment['payment_date'],
                        'project' => $nextPayment['project_title'],
                    ] : null,
                    'total_count' => $dividends->count(),
                ],
            ];
        });

        return response()->json($data);
    }

    /**
     * Generate tax report for a given year
     */
    public function taxReport(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);
        $user = $request->user();

        // Get all investments for the year
        $investments = Investment::where('user_id', $user->id)
            ->whereYear('created_at', $year)
            ->get();

        // Get all dividends for the year
        $dividends = Dividend::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereYear('payment_date', $year)
            ->get();

        // Get all transactions for the year
        $transactions = \App\Models\WalletTransaction::where('user_id', $user->id)
            ->whereYear('created_at', $year)
            ->get();

        $report = [
            'year' => $year,
            'user' => [
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'verification_id' => $user->verification_id,
            ],
            'investments' => [
                'total_invested' => $investments->sum('amount'),
                'count' => $investments->count(),
                'breakdown' => $investments->groupBy(function ($inv) {
                    return $inv->created_at->format('M');
                })->map(fn($group) => $group->sum('amount')),
            ],
            'income' => [
                'dividends' => $dividends->sum('amount'),
                'count' => $dividends->count(),
                'breakdown' => $dividends->groupBy('type')->map(fn($group) => [
                    'amount' => $group->sum('amount'),
                    'count' => $group->count(),
                ]),
            ],
            'transactions' => [
                'deposits' => $transactions->where('type', 'deposit')->sum('amount'),
                'withdrawals' => $transactions->where('type', 'withdrawal')->sum('amount'),
                'count' => $transactions->count(),
            ],
            'summary' => [
                'total_income' => $dividends->sum('amount'),
                'total_invested' => $investments->sum('amount'),
                'net_position' => $dividends->sum('amount') - $investments->sum('amount'),
            ],
            'generated_at' => now()->toDateTimeString(),
        ];

        return response()->json($report);
    }

    /**
     * Submit a support ticket
     */
    public function submitSupportTicket(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'category' => 'required|in:general,investment,wallet,account,technical,other',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'subject' => $request->subject,
            'message' => $request->message,
            'category' => $request->category,
            'status' => 'open',
            'priority' => 'normal',
        ]);

        // Create the first message in the conversation
        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'is_admin' => false,
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'Support ticket submitted successfully',
            'ticket' => $ticket->load('user'),
        ], 201);
    }

    /**
     * Get investor's support tickets
     */
    public function getSupportTickets(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->with(['messages' => function($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Add unread count for each ticket
        $tickets->each(function ($ticket) {
            $ticket->unread_count = $ticket->getUnreadMessagesCount(false); // false = investor view
        });

        return response()->json($tickets);
    }

    /**
     * Get a specific support ticket with all messages
     */
    public function getSupportTicketById(Request $request, $id): JsonResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->with(['messages.user', 'assignedAdmin'])
            ->firstOrFail();

        // Mark admin messages as read
        $ticket->messages()
            ->where('is_admin', true)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json($ticket);
    }

    /**
     * Reply to a support ticket
     */
    public function replyToSupportTicket(Request $request, $id): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $ticket = SupportTicket::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        // Create the reply message
        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'is_admin' => false,
            'is_read' => false,
        ]);

        // Update ticket status if it was resolved
        if ($ticket->status === 'resolved') {
            $ticket->update(['status' => 'in_progress']);
        }

        return response()->json([
            'message' => 'Reply sent successfully',
            'data' => $message->load('user'),
        ]);
    }

    /**
     * Get unread support messages count
     */
    public function getUnreadSupportMessagesCount(Request $request): JsonResponse
    {
        $count = SupportTicketMessage::whereHas('ticket', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })
        ->where('is_admin', true)
        ->where('is_read', false)
        ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * AI Chat Assistant - Handles chat messages and returns AI responses
     */
    public function aiChat(Request $request): JsonResponse
    {
        $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|in:user,assistant,system',
            'messages.*.text' => 'required|string',
        ]);


        try {
            $user = $request->user(); // Can be null for guests
            $messages = $request->input('messages');

            // Get the last user message for fallback
            $lastUserMessage = '';
            foreach (array_reverse($messages) as $msg) {
                if ($msg['role'] === 'user') {
                    $lastUserMessage = strtolower($msg['text']);
                    break;
                }
            }

            // Try OpenAI first, but fall back to smart responses if it fails
            try {
                // Convert messages to OpenAI format
                $openAiMessages = array_map(function ($msg) {
                    return [
                        'role' => $msg['role'],
                        'content' => $msg['text']
                    ];
                }, $messages);

                // Add system context about Crowdbricks
                array_unshift($openAiMessages, [
                    'role' => 'system',
                    'content' => 'You are Crowdbricks Assistant, a helpful AI for the Crowdbricks real estate crowdfunding platform. 
                    You help users understand:
                    - How to invest in real estate projects (minimum ₵500)
                    - Expected returns and ROI information
                    - KYC/verification requirements
                    - Payment methods (Mobile Money, Bank Transfer)
                    - Project details and investment opportunities
                    - Platform fees and processes
                    
                    Be friendly, concise, and professional. Always mention specific amounts in Ghana Cedis (₵).
                    If you don\'t know something specific about a project, suggest they check the project details page or contact support.'
                ]);

                // Call OpenAI API
                $client = \OpenAI::client(config('services.openai.api_key'));
                
                $response = $client->chat()->create([
                    'model' => config('services.openai.model', 'gpt-3.5-turbo'),
                    'messages' => $openAiMessages,
                    'max_tokens' => 500,
                    'temperature' => 0.7,
                ]);

                $reply = $response->choices[0]->message->content ?? $this->getSmartFallbackResponse($lastUserMessage);

            } catch (\Exception $aiError) {
                // OpenAI failed (rate limit, no credits, etc.) - use smart fallback
                \Log::warning('OpenAI unavailable, using fallback: ' . $aiError->getMessage());
                $reply = $this->getSmartFallbackResponse($lastUserMessage);
            }

            // Log the interaction for analytics
            \Log::info('AI Chat interaction', [
                'user_id' => $user ? $user->id : 'guest',
                'user_type' => $user ? 'authenticated' : 'visitor',
                'message_count' => count($messages),
            ]);

            return response()->json([
                'reply' => $reply,
                'message' => $reply,
            ]);

        } catch (\Exception $e) {
            \Log::error('AI Chat error: ' . $e->getMessage());
            
            return response()->json([
                'reply' => $this->getSmartFallbackResponse(''),
                'error' => config('app.debug') ? $e->getMessage() : 'Service temporarily unavailable',
            ]);
        }
    }

    /**
     * Provide smart fallback responses when OpenAI is unavailable
     * Covers 20+ topics and common questions
     */
    private function getSmartFallbackResponse($message)
    {
        $msg = strtolower($message);

        // INVESTMENT PROCESS
        if (strpos($msg, 'invest') !== false || strpos($msg, 'how to') !== false || strpos($msg, 'start') !== false) {
            return "📋 **How to Invest in Crowdbricks:**\n\n1️⃣ **Sign up** for a free account\n2️⃣ **Browse projects** - Check details, ROI, and timeline\n3️⃣ **Pledge at least ₵500** (minimum investment)\n4️⃣ **Complete payment** via Mobile Money or Bank Transfer\n5️⃣ **Get confirmed** once we verify your payment\n6️⃣ **Track progress** in your investor dashboard\n\n💡 You'll start earning returns once the project is fully funded and operational!";
        }

        // RETURNS, ROI, AND PROFITS
        if (strpos($msg, 'return') !== false || strpos($msg, 'roi') !== false || strpos($msg, 'profit') !== false || strpos($msg, 'earn') !== false || strpos($msg, 'dividend') !== false) {
            return "💰 **About Returns & Earnings:**\n\n• Returns vary by project type (rental, flip, commercial)\n• Typical ROI ranges from 12% to 25% annually\n• Check each project's detail page for specific projections\n• Dividends are paid quarterly or monthly (project-dependent)\n• Returns come from rental income or property sales\n• Track your portfolio value in real-time on your dashboard\n\n📊 **Example:** ₵10,000 investment at 15% ROI = ₵1,500/year in returns!";
        }

        // KYC AND VERIFICATION
        if (strpos($msg, 'kyc') !== false || strpos($msg, 'verification') !== false || strpos($msg, 'verify') !== false || strpos($msg, 'identity') !== false) {
            return "🪪 **Verification & KYC:**\n\n**For Developers:**\n• Complete KYC required to list projects and receive funds\n• Upload Ghana Card, Passport, or Driver's License\n• Business registration documents (if company)\n• Bank account verification\n\n**For Investors:**\n• Optional for small investments (under ₵10,000)\n• Required for investments over ₵10,000\n• Protects both you and the platform\n\n⏱️ **Processing time:** 24-48 hours\n✅ **All data is encrypted and secure**";
        }

        // PLATFORM OVERVIEW
        if (strpos($msg, 'crowdbricks') !== false || strpos($msg, 'platform') !== false || strpos($msg, 'what is') !== false || strpos($msg, 'about') !== false) {
            return "🏗️ **Welcome to Crowdbricks!**\n\nGhana's premier real estate crowdfunding platform connecting investors with property developers.\n\n**How it works:**\n• Developers list verified projects\n• Investors fund projects starting from ₵500\n• Everyone shares in the profits\n\n**Benefits:**\n✅ Invest in real estate without huge capital\n✅ Diversify across multiple properties\n✅ Transparent, secure, and regulated\n✅ Support local development in Ghana\n\n🎯 It's like owning real estate, minus the hassle!";
        }

        // PAYMENT METHODS
        if (strpos($msg, 'pay') !== false || strpos($msg, 'momo') !== false || strpos($msg, 'mobile money') !== false || strpos($msg, 'bank') !== false || strpos($msg, 'transfer') !== false) {
            return "💳 **Payment Methods:**\n\n**Mobile Money:**\n• MTN Mobile Money\n• Vodafone Cash\n• AirtelTigo Money\n• Instant confirmation\n\n**Bank Transfer:**\n• All major Ghanaian banks supported\n• Confirmation within 24 hours\n• Lower fees for large amounts\n\n💵 **Minimum investment:** ₵500\n🔒 **All payments are secure and encrypted**\n\n📱 Choose your preferred method at checkout!";
        }

        // MINIMUM INVESTMENT
        if (strpos($msg, 'minimum') !== false || strpos($msg, 'least') !== false || strpos($msg, 'how much') !== false || strpos($msg, 'start with') !== false) {
            return "💵 **Investment Minimums:**\n\n• **Minimum per project:** ₵500\n• **Recommended starting amount:** ₵2,000-₵5,000\n• **No maximum limit** - invest as much as you want!\n\n**Why ₵500?**\n✅ Makes real estate accessible to everyone\n✅ Allows portfolio diversification\n✅ Low barrier to entry\n\n💡 **Pro tip:** Spread ₵5,000 across 5 projects instead of putting it all in one!";
        }

        // RISKS AND SAFETY
        if (strpos($msg, 'risk') !== false || strpos($msg, 'safe') !== false || strpos($msg, 'secure') !== false || strpos($msg, 'guarantee') !== false || strpos($msg, 'protect') !== false) {
            return "🛡️ **Safety & Risk Management:**\n\n**How we protect you:**\n✅ All developers undergo strict KYC verification\n✅ Projects are vetted by our team\n✅ Legal agreements protect investor rights\n✅ Funds held in escrow until project milestones\n✅ Transparent reporting and updates\n\n**Risks to know:**\n⚠️ Real estate investments carry market risk\n⚠️ Project delays may occur\n⚠️ Returns are projections, not guarantees\n\n💡 **Best practice:** Diversify across multiple projects to minimize risk!";
        }

        // WITHDRAWAL AND LIQUIDITY
        if (strpos($msg, 'withdraw') !== false || strpos($msg, 'cash out') !== false || strpos($msg, 'liquidity') !== false || strpos($msg, 'sell') !== false || strpos($msg, 'exit') !== false) {
            return "💸 **Withdrawals & Liquidity:**\n\n**Dividend Withdrawals:**\n• Request withdrawal anytime from your wallet\n• Processed within 1-3 business days\n• Available via Mobile Money or Bank Transfer\n• No withdrawal fees for amounts over ₵100\n\n**Selling Your Stake:**\n• Use our marketplace to sell to other investors (coming soon)\n• Or wait until project completion\n• Early exit may involve small fees\n\n📊 **Track your available balance** in your dashboard wallet!";
        }

        // PROJECT TYPES
        if (strpos($msg, 'project') !== false || strpos($msg, 'types') !== false || strpos($msg, 'property') !== false || strpos($msg, 'real estate') !== false) {
            return "🏘️ **Types of Projects:**\n\n**1. Residential Development**\n• Apartment buildings, housing estates\n• ROI: 12-18% annually\n• Returns from sales or rentals\n\n**2. Commercial Properties**\n• Office buildings, shopping centers\n• ROI: 15-25% annually\n• Long-term rental income\n\n**3. Land Banking**\n• Strategic land acquisition\n• ROI: 20-30% over 2-5 years\n• Capital appreciation\n\n**4. Renovation Projects**\n• Fix and flip opportunities\n• ROI: 15-20% in 6-12 months\n• Quick turnaround\n\n📂 Browse all active projects on our homepage!";
        }

        // TIMELINE AND DURATION
        if (strpos($msg, 'timeline') !== false || strpos($msg, 'duration') !== false || strpos($msg, 'how long') !== false || strpos($msg, 'when') !== false) {
            return "⏰ **Investment Timelines:**\n\n**Funding Phase:**\n• 30-90 days to reach funding goal\n• Your money held in escrow until fully funded\n• Refunded if project doesn't reach target\n\n**Development Phase:**\n• 6-24 months depending on project type\n• Regular updates on progress\n• Milestone-based fund releases\n\n**Return Phase:**\n• Rental projects: Monthly/quarterly dividends\n• Flip projects: Lump sum at completion\n• Long-term holds: 2-5 years\n\n📅 Each project page shows its specific timeline!";
        }

        // TAXES
        if (strpos($msg, 'tax') !== false || strpos($msg, 'taxation') !== false || strpos($msg, 'taxed') !== false) {
            return "💼 **Tax Information:**\n\n**Investment Income:**\n• Dividends are subject to Ghana's tax laws\n• Capital gains tax may apply on profits\n• We provide annual tax reports\n• Consult a tax professional for specifics\n\n**Tax Documents:**\n• Download from your dashboard\n• Available after each fiscal year\n• Shows all dividends and gains\n\n📊 **Access tax reports** in Dashboard → Tax Overview\n\n⚠️ This is general info - consult a tax advisor for personal advice!";
        }

        // FEES AND CHARGES
        if (strpos($msg, 'fee') !== false || strpos($msg, 'charge') !== false || strpos($msg, 'cost') !== false || strpos($msg, 'commission') !== false) {
            return "💵 **Platform Fees:**\n\n**For Investors:**\n• **No signup fees** - completely free to join\n• **No investment fees** - invest the full amount\n• **Small withdrawal fee** - ₵5 for amounts under ₵100\n• **Performance fee** - Only 5% of profits earned\n\n**For Developers:**\n• Platform fee: 3-5% of funds raised\n• Success-based pricing\n• Marketing support included\n\n✅ **What you see is what you get - no hidden charges!**";
        }

        // ACCOUNT AND SIGNUP
        if (strpos($msg, 'account') !== false || strpos($msg, 'sign up') !== false || strpos($msg, 'register') !== false || strpos($msg, 'create') !== false) {
            return "📝 **Creating Your Account:**\n\n**Easy 3-Step Process:**\n1️⃣ **Click 'Sign Up'** on our homepage\n2️⃣ **Enter your details** - email, name, phone number\n3️⃣ **Verify your email** - click the link we send\n\n**What you need:**\n• Valid email address\n• Ghanaian phone number\n• Password (min 8 characters)\n\n**Account types:**\n• **Investor** - to invest in projects\n• **Developer** - to list projects\n• **Both** - you can be both!\n\n🚀 **Takes less than 2 minutes to get started!**";
        }

        // DASHBOARD AND TRACKING
        if (strpos($msg, 'dashboard') !== false || strpos($msg, 'track') !== false || strpos($msg, 'monitor') !== false || strpos($msg, 'portfolio') !== false) {
            return "📊 **Your Investor Dashboard:**\n\n**Overview Tab:**\n• Total invested amount\n• Current portfolio value\n• Total returns earned\n• Active investments count\n\n**Investments Tab:**\n• List of all your projects\n• Individual performance tracking\n• Payment history\n\n**Wallet Tab:**\n• Available balance\n• Pending dividends\n• Withdrawal options\n\n**Analytics:**\n• Portfolio growth charts\n• ROI breakdown by project\n• Dividend payment history\n\n🎯 **Real-time updates** keep you informed 24/7!";
        }

        // DEVELOPER QUESTIONS
        if (strpos($msg, 'developer') !== false || strpos($msg, 'list project') !== false || strpos($msg, 'raise fund') !== false) {
            return "🏗️ **For Developers:**\n\n**List Your Project:**\n1️⃣ Complete KYC verification\n2️⃣ Submit project details and documents\n3️⃣ Our team reviews (3-5 days)\n4️⃣ Project goes live for funding\n5️⃣ Receive funds as milestones are met\n\n**Requirements:**\n• Valid business registration\n• Detailed project plan\n• Financial projections\n• Land documents or permits\n• Track record (if available)\n\n**Benefits:**\n✅ Access to investor network\n✅ Faster funding than banks\n✅ Marketing support\n✅ Flexible repayment terms\n\n📧 **Contact us** to start listing your project!";
        }

        // SUPPORT AND HELP
        if (strpos($msg, 'support') !== false || strpos($msg, 'help') !== false || strpos($msg, 'contact') !== false || strpos($msg, 'email') !== false || strpos($msg, 'phone') !== false) {
            return "📞 **Get Help & Support:**\n\n**Contact Methods:**\n• **Email:** support@crowdbricks.com\n• **Phone:** +233 XX XXX XXXX\n• **Live Chat:** Available on website (Mon-Fri, 9AM-6PM)\n• **Support Tickets:** Submit via dashboard\n\n**Response Times:**\n• Live chat: Instant during business hours\n• Email: Within 24 hours\n• Phone: Mon-Fri, 9AM-6PM GMT\n\n**Common Issues:**\n• Payment problems → Check wallet/transaction history\n• Account access → Use password reset\n• Project questions → View project details page\n\n💬 **We're here to help you succeed!**";
        }

        // REFERRAL AND BONUSES
        if (strpos($msg, 'referral') !== false || strpos($msg, 'refer') !== false || strpos($msg, 'bonus') !== false || strpos($msg, 'invite') !== false) {
            return "🎁 **Referral Program:**\n\n**Earn by Referring:**\n• Invite friends to join Crowdbricks\n• Earn ₵50 when they make their first investment\n• They get ₵25 bonus too!\n• No limit to referrals\n\n**How it works:**\n1️⃣ Get your unique referral link from dashboard\n2️⃣ Share with friends and family\n3️⃣ They sign up using your link\n4️⃣ Both earn bonuses when they invest!\n\n**Bonus credited instantly** to your wallet\n\n🤝 **Win-win for everyone!**";
        }

        // MOBILE APP
        if (strpos($msg, 'app') !== false || strpos($msg, 'mobile') !== false || strpos($msg, 'android') !== false || strpos($msg, 'ios') !== false || strpos($msg, 'download') !== false) {
            return "📱 **Mobile App:**\n\n**Coming Soon!**\n• Currently in development\n• Will be available on iOS and Android\n• All features accessible on mobile web for now\n\n**Mobile Web Features:**\n✅ Fully responsive design\n✅ Browse and invest from your phone\n✅ Track portfolio on the go\n✅ Receive push notifications\n\n**Current Access:**\n🌐 Visit crowdbricks.com from any mobile browser\n📲 Add to home screen for app-like experience\n\n🔔 **Sign up to get notified** when the app launches!";
        }

        // SECURITY AND PRIVACY
        if (strpos($msg, 'security') !== false || strpos($msg, 'privacy') !== false || strpos($msg, 'data') !== false || strpos($msg, 'encryption') !== false) {
            return "🔐 **Security & Privacy:**\n\n**How we protect you:**\n• 256-bit SSL encryption on all data\n• Two-factor authentication (2FA) available\n• Regular security audits\n• Secure payment gateways\n• Data stored on encrypted servers\n\n**Your Privacy:**\n• We never share your personal data\n• Compliant with data protection laws\n• You control your information\n• Transparent privacy policy\n\n**Best Practices:**\n✅ Enable 2FA on your account\n✅ Use a strong, unique password\n✅ Never share your login details\n✅ Log out on shared devices\n\n🛡️ **Your security is our priority!**";
        }

        // SUCCESS STORIES
        if (strpos($msg, 'success') !== false || strpos($msg, 'testimonial') !== false || strpos($msg, 'review') !== false || strpos($msg, 'example') !== false) {
            return "⭐ **Success Stories:**\n\n**Investor Testimonial:**\n*\"I started with ₵2,000 across 4 projects. After 1 year, I've earned ₵360 in returns (18% ROI). Now I invest monthly!\"* - Kwame A.\n\n**Developer Success:**\n*\"Raised ₵500,000 in 45 days for my residential project. Traditional banks wanted 6 months!\"* - Ama D.\n\n**Platform Stats:**\n• Over 10,000 investors\n• ₵50M+ invested to date\n• Average ROI: 16.5%\n• 95% investor satisfaction\n\n📈 **Join thousands of successful investors today!**";
        }

        // COMPARISON WITH ALTERNATIVES
        if (strpos($msg, 'vs') !== false || strpos($msg, 'compare') !== false || strpos($msg, 'better than') !== false || strpos($msg, 'difference') !== false) {
            return "⚖️ **Crowdbricks vs Alternatives:**\n\n**vs Traditional Real Estate:**\n✅ Lower entry point (₵500 vs ₵100,000+)\n✅ No property management hassle\n✅ Higher liquidity\n✅ Diversification possible\n\n**vs Bank Savings:**\n✅ Much higher returns (15% vs 8%)\n✅ Asset-backed investment\n✅ Inflation protection\n\n**vs Stocks:**\n✅ More predictable returns\n✅ Tangible asset backing\n✅ Less volatile\n✅ Local market focus\n\n🎯 **Best for:** Ghanaians wanting real estate exposure without huge capital!";
        }

        // DEFAULT COMPREHENSIVE RESPONSE
        return "💡 **I'm your Crowdbricks Assistant!**\n\n**Popular Topics:**\n• 💰 How to invest & get started\n• 📊 Expected returns & ROI\n• 💳 Payment methods (MoMo, Bank)\n• 🏗️ Types of projects available\n• 🛡️ Safety & risk management\n• 💸 Withdrawals & liquidity\n• 🪪 KYC & verification\n• 📱 Platform features\n• 💵 Fees & charges\n• 📞 Support & contact\n\n**Try asking:**\n• \"How do I invest ₵5,000?\"\n• \"What are the risks?\"\n• \"How do I withdraw my returns?\"\n• \"What projects can I invest in?\"\n\n📧 **Need human help?** Contact support@crowdbricks.com";
    }
}








