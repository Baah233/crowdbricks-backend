<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Project;
use App\Models\Investment;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Notifications\UserVerifiedNotification;
use App\Notifications\InvestmentApprovedNotification;

class AdminController extends Controller
{
    /**
     * Helper: check if a user is admin (supports different schemas)
     */
    protected function isAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        // Support multiple conventions: is_admin boolean, role column, user_type column
        if (isset($user->is_admin)) {
            return (bool) $user->is_admin;
        }

        if (isset($user->role) && $user->role === 'admin') {
            return true;
        }

        if (isset($user->user_type) && $user->user_type === 'admin') {
            return true;
        }

        return false;
    }

    /**
     * 🧾 Return global dashboard statistics (safe & defensive)
     */
    public function stats()
    {
        try {
            // defensive: check column names before using them
            $userRoleColumn = Schema::hasColumn('users', 'role') ? 'role' : (Schema::hasColumn('users', 'user_type') ? 'user_type' : null);
            $userStatusColumn = Schema::hasColumn('users', 'status') ? 'status' : null;
            
            // Projects use approval_status instead of status
            $projectStatusColumn = Schema::hasColumn('projects', 'approval_status') ? 'approval_status' : (Schema::hasColumn('projects', 'status') ? 'status' : null);
            
            $investmentAmountColumn = Schema::hasColumn('investments', 'amount') ? 'amount' : null;

            $totalUsers = User::count();

            $totalDevelopers = $userRoleColumn
                ? User::where($userRoleColumn, 'developer')->count()
                : 0;

            $totalInvestors = $userRoleColumn
                ? User::where($userRoleColumn, 'investor')->count()
                : 0;

            $pendingUsers = $userStatusColumn
                ? User::where($userStatusColumn, 'pending')->count()
                : 0;

            $approvedProjects = $projectStatusColumn
                ? Project::where($projectStatusColumn, 'approved')->count()
                : 0;

            $pendingProjects = $projectStatusColumn
                ? Project::where($projectStatusColumn, 'pending')->count()
                : 0;

            $totalInvestments = 0;
            if ($investmentAmountColumn) {
                // sum can return null if no rows so cast to 0
                $totalInvestments = (float) Investment::sum($investmentAmountColumn);
            }

            return response()->json([
                'ok' => true,
                'data' => [
                    'total_users' => $totalUsers,
                    'total_developers' => $totalDevelopers,
                    'total_investors' => $totalInvestors,
                    'pending_users' => $pendingUsers,
                    'approved_projects' => $approvedProjects,
                    'pending_projects' => $pendingProjects,
                    'total_investments' => $totalInvestments,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('AdminController@stats error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Failed to fetch stats', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🧍 Get users for the admin panel (safe selection)
     */
    public function users()
    {
        try {
            // Select a safe set of columns if they exist, otherwise fallback to all
            $available = Schema::getColumnListing('users');

            $fields = array_intersect(['id', 'first_name', 'last_name', 'email', 'user_type', 'role', 'status', 'created_at'], $available);

            // If minimal columns are missing, just fetch default fields to avoid errors
            if (empty($fields)) {
                $users = User::orderByDesc('created_at')->get();
            } else {
                $users = User::select($fields)->orderByDesc('created_at')->get();
            }

            // Normalize shape for frontend
            $result = $users->map(function ($u) {
                return [
                    'id' => $u->id,
                    'first_name' => $u->first_name ?? null,
                    'last_name' => $u->last_name ?? null,
                    'email' => $u->email ?? null,
                    'role' => $u->role ?? $u->user_type ?? null,
                    'status' => $u->status ?? null,
                    'created_at' => $u->created_at ?? null,
                ];
            });

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Log::error('AdminController@users error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Failed to fetch users', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🏗️ Get all projects (safe/defensive)
     */
    public function projects()
    {
        try {
            $available = Schema::getColumnListing('projects');

            // Use approval_status instead of status for projects table
            $selectCols = array_intersect(['id', 'title', 'approval_status', 'target_funding', 'current_funding', 'created_at', 'user_id'], $available);

            // Ensure user relationship is loaded if exists
            $query = Project::query();

            if (in_array('user_id', $selectCols)) {
                $query->with('developer:id,first_name,last_name');
            }

            $projects = $query->select($selectCols ?: ['*'])
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($p) {
                    $developerName = null;
                    if (isset($p->developer) && $p->developer) {
                        $developerName = trim(($p->developer->first_name ?? '') . ' ' . ($p->developer->last_name ?? ''));
                    } elseif (isset($p->user_id)) {
                        $developerName = 'Unknown';
                    }

                    // fallback for funding fields
                    $target = $p->target_funding ?? ($p->target ?? 0) ?? 0;
                    $current = $p->current_funding ?? ($p->funded_amount ?? 0) ?? 0;

                    $progressPercent = 0;
                    if ($target > 0) {
                        $progressPercent = round(($current / $target) * 100, 2);
                    }

                    // Map approval_status to status for frontend compatibility
                    $status = $p->approval_status ?? ($p->status ?? null);

                    return [
                        'id' => $p->id,
                        'title' => $p->title ?? null,
                        'status' => $status,
                        'target_funding' => $target,
                        'current_funding' => $current,
                        'created_at' => $p->created_at ?? null,
                        'developer_id' => $p->user_id ?? null,
                        'developer_name' => $developerName,
                        'progress_percent' => $progressPercent,
                    ];
                });

            return response()->json(['ok' => true, 'data' => $projects]);
        } catch (\Throwable $e) {
            Log::error('AdminController@projects error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Failed to fetch projects', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 💰 Get all investments (safe)
     */
    public function investments()
    {
        try {
            $available = Schema::getColumnListing('investments');

            // Use payment_method instead of method for investments table
            $selectCols = array_intersect(['id', 'user_id', 'project_id', 'amount', 'status', 'payment_method', 'created_at'], $available);
            $query = Investment::query();

            // Eager load relations if present
            if (Schema::hasTable('users')) {
                $query->with('user:id,first_name,last_name');
            }
            if (Schema::hasTable('projects')) {
                $query->with('project:id,title');
            }

            $investments = $query->select($selectCols ?: ['*'])
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($inv) {
                    $investorName = isset($inv->user) ? trim(($inv->user->first_name ?? '') . ' ' . ($inv->user->last_name ?? '')) : 'Unknown';
                    $projectTitle = $inv->project->title ?? ($inv->project_title ?? 'N/A');

                    // Map payment_method to method for frontend compatibility
                    $method = $inv->payment_method ?? ($inv->method ?? null);

                    return [
                        'id' => $inv->id,
                        'investor_id' => $inv->user_id ?? null,
                        'project_id' => $inv->project_id ?? null,
                        'amount' => $inv->amount ?? 0,
                        'status' => $inv->status ?? null,
                        'method' => $method,
                        'created_at' => $inv->created_at ?? null,
                        'investor_name' => $investorName,
                        'project_title' => $projectTitle,
                    ];
                });

            return response()->json(['ok' => true, 'data' => $investments]);
        } catch (\Throwable $e) {
            Log::error('AdminController@investments error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Failed to fetch investments', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * ✅ Approve a user (safe)
     */
    public function approveUser($id)
    {
        try {
            Log::info('AdminController@approveUser called', ['id' => $id]);
            
            $user = User::findOrFail($id);
            
            Log::info('User found', [
                'id' => $user->id, 
                'email' => $user->email,
                'status_before' => $user->status
            ]);

            // if status column exists set status, else no-op but still return user
            if (Schema::hasColumn('users', 'status')) {
                $user->status = 'approved';
                
                // Generate verification ID if user doesn't have one
                if (empty($user->verification_id)) {
                    $user->verification_id = User::generateVerificationId();
                }
                
                // Require 2FA after approval
                $user->two_factor_required = true;
                
                $saved = $user->save();
                
                Log::info('User status updated', [
                    'saved' => $saved,
                    'status_after' => $user->status,
                    'verification_id' => $user->verification_id,
                    'two_factor_required' => true
                ]);
                
                // Send notification to user
                $user->notify(new UserVerifiedNotification());
                Log::info('User verification notification sent', ['user_id' => $user->id]);
                
                // Send approval email
                \Mail::to($user->email)->send(new \App\Mail\UserApprovedMail($user));
                
                // Send SMS notification if phone is verified
                if ($user->phone && $user->phone_verified) {
                    $twilioService = app(\App\Services\TwilioService::class);
                    $twilioService->sendNotification(
                        $user->phone,
                        "Congratulations! Your CrowdBricks account has been approved. Your verification ID is: {$user->verification_id}. Please enable 2FA to continue."
                    );
                }
            } else {
                Log::warning('users table does not have status column');
            }

            return response()->json(['ok' => true, 'message' => 'User approved successfully.', 'user' => $user]);
        } catch (\Throwable $e) {
            Log::error('AdminController@approveUser error: ' . $e->getMessage(), ['id' => $id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Failed to approve user', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve phone change request
     */
    public function approvePhoneChange($id)
    {
        try {
            $user = User::findOrFail($id);
            
            if (!$user->phone_change_request) {
                return response()->json(['ok' => false, 'message' => 'No phone change request found'], 404);
            }

            $newPhone = $user->phone_change_request;

            // Update phone
            $user->phone = $newPhone;
            $user->phone_change_request = null;
            $user->phone_change_status = 'approved';
            $user->phone_verified = false; // User needs to verify new phone
            $user->save();

            // Send email notification
            \Mail::to($user->email)->send(new \App\Mail\PhoneChangeApprovedMail($user, $newPhone));

            // Send SMS to new number
            $twilioService = app(\App\Services\TwilioService::class);
            $twilioService->sendNotification(
                $newPhone,
                "Your phone number change request has been approved by CrowdBricks. Please verify this number to activate it."
            );

            return response()->json([
                'ok' => true,
                'message' => 'Phone change approved',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Failed to approve phone change', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject phone change request
     */
    public function rejectPhoneChange($id)
    {
        try {
            $user = User::findOrFail($id);
            
            $requestedPhone = $user->phone_change_request;
            
            $user->phone_change_request = null;
            $user->phone_change_status = 'rejected';
            $user->save();

            // Send email notification
            \Mail::to($user->email)->send(new \App\Mail\PhoneChangeRejectedMail($user, $requestedPhone));

            return response()->json([
                'ok' => true,
                'message' => 'Phone change rejected'
            ]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Failed to reject phone change', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all phone change requests
     */
    public function getPhoneChangeRequests()
    {
        try {
            $requests = User::whereNotNull('phone_change_request')
                ->where('phone_change_status', 'pending')
                ->get();

            return response()->json(['ok' => true, 'requests' => $requests]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Failed to fetch requests', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * ❌ Reject a user (safe)
     */
    public function rejectUser($id)
    {
        try {
            $user = User::findOrFail($id);

            if (Schema::hasColumn('users', 'status')) {
                $user->status = 'rejected';
                $user->save();
            }

            return response()->json(['ok' => true, 'message' => 'User rejected successfully.', 'user' => $user]);
        } catch (\Throwable $e) {
            Log::error('AdminController@rejectUser error: ' . $e->getMessage(), ['id' => $id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Failed to reject user', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔄 Toggle a user's admin role (supports role/user_type/is_admin)
     */
    public function toggleAdmin($id)
    {
        try {
            $user = User::findOrFail($id);

            if (Schema::hasColumn('users', 'is_admin')) {
                $user->is_admin = !$user->is_admin;
                $user->save();
            } elseif (Schema::hasColumn('users', 'role')) {
                $user->role = ($user->role === 'admin') ? 'user' : 'admin';
                $user->save();
            } elseif (Schema::hasColumn('users', 'user_type')) {
                $user->user_type = ($user->user_type === 'admin') ? 'user' : 'admin';
                $user->save();
            } else {
                // no suitable column to toggle; log and return success with no-op
                Log::warning('toggleAdmin: no admin column exists to toggle', ['user_id' => $id]);
            }

            return response()->json(['ok' => true, 'message' => 'User role toggled successfully.', 'user' => $user]);
        } catch (\Throwable $e) {
            Log::error('AdminController@toggleAdmin error: ' . $e->getMessage(), ['id' => $id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Failed to toggle user role', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🏗️ Approve a project
     */
    public function approveProject($id)
    {
        try {
            Log::info('AdminController@approveProject called', ['id' => $id]);
            
            $project = Project::findOrFail($id);
            
            Log::info('Project found', [
                'id' => $project->id, 
                'title' => $project->title,
                'approval_status_before' => $project->approval_status
            ]);

            // Use approval_status for projects table
            if (Schema::hasColumn('projects', 'approval_status')) {
                $project->approval_status = 'approved';
                $saved = $project->save();
                
                Log::info('Project approval_status updated', [
                    'saved' => $saved,
                    'approval_status_after' => $project->approval_status
                ]);
            } elseif (Schema::hasColumn('projects', 'status')) {
                $project->status = 'approved';
                $saved = $project->save();
                
                Log::info('Project status updated', [
                    'saved' => $saved,
                    'status_after' => $project->status
                ]);
            } else {
                Log::warning('projects table does not have approval_status or status column');
            }

            return response()->json(['ok' => true, 'message' => 'Project approved successfully.', 'project' => $project]);
        } catch (\Throwable $e) {
            Log::error('AdminController@approveProject error: ' . $e->getMessage(), ['id' => $id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Failed to approve project', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 💸 Update an investment status
     */
    public function updateInvestmentStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string|in:pending,confirmed,failed',
            ]);

            $investment = Investment::findOrFail($id);

            if (Schema::hasColumn('investments', 'status')) {
                $oldStatus = $investment->status;
                $investment->status = $validated['status'];
                $investment->save();
                
                // Send notification when investment is approved/confirmed
                if ($validated['status'] === 'confirmed' && $oldStatus !== 'confirmed') {
                    $user = $investment->user;
                    $project = $investment->project;
                    
                    if ($user && $project) {
                        $user->notify(new InvestmentApprovedNotification($investment, $project->title));
                        Log::info('Investment approval notification sent', [
                            'investment_id' => $investment->id,
                            'user_id' => $user->id
                        ]);
                    }
                }
            }

            return response()->json(['ok' => true, 'message' => 'Investment status updated successfully.', 'investment' => $investment]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok' => false, 'message' => 'Validation failed', 'errors' => $ve->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('AdminController@updateInvestmentStatus error: ' . $e->getMessage(), ['id' => $id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Failed to update investment status', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔔 Return notifications (polling)
     */
    public function notifications()
    {
        try {
            $admin = auth()->user();

            if (! $this->isAdmin($admin)) {
                return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
            }

            $notifications = [];

            // Get admin notifications from custom table
            if (Schema::hasTable('admin_notifications')) {
                $adminNotifs = \App\Models\AdminNotification::where('user_id', $admin->id)
                    ->latest()
                    ->take(50)
                    ->get()
                    ->map(function ($n) {
                        return [
                            'id' => $n->id,
                            'type' => $n->type,
                            'title' => $n->title,
                            'body' => $n->message,
                            'date' => $n->created_at ? $n->created_at->toDateTimeString() : null,
                            'read' => $n->read,
                            'data' => $n->data,
                        ];
                    });
                $notifications = array_merge($notifications, $adminNotifs->toArray());
            }

            // If notifications relationship exists, use it; otherwise skip
            if (Schema::hasTable('notifications') && method_exists($admin, 'notifications')) {
                $systemNotifs = $admin->notifications()
                    ->latest()
                    ->take(50)
                    ->get()
                    ->map(function ($n) {
                        return [
                            'id' => $n->id,
                            'type' => $n->data['type'] ?? 'info',
                            'title' => $n->data['title'] ?? 'Notification',
                            'body' => $n->data['body'] ?? '',
                            'date' => $n->created_at ? $n->created_at->toDateTimeString() : null,
                            'read' => isset($n->read_at) && $n->read_at !== null,
                        ];
                    });
                $notifications = array_merge($notifications, $systemNotifs->toArray());
            }

            return response()->json(['ok' => true, 'data' => $notifications]);
        } catch (\Throwable $e) {
            Log::error('AdminController@notifications error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => 'Failed to fetch notifications', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔔 SSE stream (lightweight ping stream - extend for real events)
     *
     * Note: For SSE to work with Sanctum, ensure:
     * - CORS supports credentials (supports_credentials => true)
     * - Frontend EventSource is created with credentials (some browsers vary)
     * If you use a reverse proxy, make sure it doesn't buffer the stream.
     */
    public function stream()
    {
        $admin = auth()->user();

        if (! $this->isAdmin($admin)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
        }

        // Return a simple ping. Extend to push real notifications as needed.
        return response()->stream(function () {
            // a single ping and close (you can convert to a loop for long-lived stream)
            echo "data: " . json_encode(['type' => 'ping', 'time' => now()->toDateTimeString()]) . "\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Get all support tickets
     */
    public function getSupportTickets(Request $request)
    {
        $query = SupportTicket::with(['user', 'assignedAdmin']);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Filter by priority
        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        // Search by subject or message
        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('subject', 'like', '%' . $request->search . '%')
                  ->orWhere('message', 'like', '%' . $request->search . '%');
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($tickets);
    }

    /**
     * Get a single support ticket by ID
     */
    public function getSupportTicketById($id)
    {
        $ticket = SupportTicket::with(['user', 'assignedAdmin', 'messages.user'])->findOrFail($id);
        
        // Mark investor messages as read
        $ticket->messages()
            ->where('is_admin', false)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
        
        return response()->json($ticket);
    }

    /**
     * Respond to a support ticket
     */
    public function respondToTicket(Request $request, $id)
    {
        $request->validate([
            'response' => 'required|string',
            'update_status' => 'boolean',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        
        // Create message in conversation thread
        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $request->response,
            'is_admin' => true,
            'is_read' => false,
        ]);

        // Update ticket metadata
        $ticket->admin_response = $request->response;
        $ticket->responded_at = now();
        $ticket->assigned_to = auth()->id();

        if ($request->update_status && $ticket->status === 'open') {
            $ticket->status = 'in_progress';
        }

        $ticket->save();

        return response()->json([
            'message' => 'Response submitted successfully',
            'ticket' => $ticket->load(['user', 'assignedAdmin', 'messages'])
        ]);
    }

    /**
     * Update ticket status
     */
    public function updateTicketStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $request->status;

        if (in_array($request->status, ['resolved', 'closed'])) {
            $ticket->resolved_at = now();
        }

        $ticket->save();

        return response()->json([
            'message' => 'Ticket status updated successfully',
            'ticket' => $ticket->load(['user', 'assignedAdmin'])
        ]);
    }

    /**
     * Assign ticket to admin
     */
    public function assignTicket(Request $request, $id)
    {
        $request->validate([
            'admin_id' => 'required|exists:users,id',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->assigned_to = $request->admin_id;
        $ticket->save();

        return response()->json([
            'message' => 'Ticket assigned successfully',
            'ticket' => $ticket->load(['user', 'assignedAdmin'])
        ]);
    }

    /**
     * Update ticket priority
     */
    public function updateTicketPriority(Request $request, $id)
    {
        $request->validate([
            'priority' => 'required|in:low,normal,high,urgent',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->priority = $request->priority;
        $ticket->save();

        return response()->json([
            'message' => 'Ticket priority updated successfully',
            'ticket' => $ticket->load(['user', 'assignedAdmin'])
        ]);
    }

    /**
     * Get unread support ticket count
     */
    public function getUnreadTicketCount()
    {
        // Count unread investor messages
        $count = SupportTicketMessage::where('is_admin', false)
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }
}
