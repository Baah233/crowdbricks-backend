<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\Investment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeveloperController extends Controller
{
    /**
     * Get comprehensive developer dashboard stats
     */
    public function stats()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $totalProjects = Project::where('user_id', $user->id)->count();
        $approvedProjects = Project::where('user_id', $user->id)->where('approval_status', 'approved')->count();
        $pendingProjects = Project::where('user_id', $user->id)->where('approval_status', 'pending')->count();
        $activeFundings = Project::where('user_id', $user->id)->where('funding_status', 'funding')->count();
        
        $totalRaised = Project::where('projects.user_id', $user->id)
            ->leftJoin('investments', function($join) {
                $join->on('projects.id', '=', 'investments.project_id')
                     ->where('investments.status', '=', 'confirmed');
            })
            ->sum('investments.amount') ?? 0;

        $totalGoal = Project::where('user_id', $user->id)->sum('target_funding') ?? 0;
        
        // Get unique investors count
        $uniqueInvestors = Investment::whereIn('project_id', function($query) use ($user) {
            $query->select('id')
                ->from('projects')
                ->where('user_id', $user->id);
        })->where('status', 'confirmed')
          ->distinct('user_id')
          ->count('user_id');

        // Calculate average ROI (mocked for now - would need actual ROI tracking)
        $avgROI = 12.5;
        
        // Success rate (funded projects / total projects)
        $successRate = $totalProjects > 0 ? 
            (Project::where('user_id', $user->id)->where('funding_status', 'funded')->count() / $totalProjects * 100) : 0;

        // Average time to fund (in days)
        $avgTimeToFund = 45; // Mocked - would calculate from project created_at to funded_at

        // Developer trust level (based on successful projects)
        $trustLevel = $this->calculateTrustLevel($user->id);

        return response()->json([
            'totalProjects' => $totalProjects,
            'approvedProjects' => $approvedProjects,
            'pendingProjects' => $pendingProjects,
            'activeFundings' => $activeFundings,
            'totalRaised' => $totalRaised,
            'totalGoal' => $totalGoal,
            'investors' => $uniqueInvestors,
            'avgROI' => $avgROI,
            'successRate' => round($successRate, 1),
            'avgTimeToFund' => $avgTimeToFund,
            'trustLevel' => $trustLevel,
        ]);
    }

    /**
     * Get funding timeline data for charts
     */
    public function fundingTimeline(Request $request)
    {
        $projectId = $request->get('project_id');
        $days = $request->get('days', 30);

        $query = Investment::query()
            ->where('status', 'confirmed')
            ->where('created_at', '>=', Carbon::now()->subDays($days));

        if ($projectId) {
            $query->where('project_id', $projectId);
        } else {
            // Get all projects for this developer
            $query->whereIn('project_id', function($q) {
                $q->select('id')
                    ->from('projects')
                    ->where('user_id', Auth::id());
            });
        }

        $timeline = $query->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as amount'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($timeline);
    }

    /**
     * Get investor engagement metrics
     */
    public function investorEngagement(Request $request)
    {
        $projectId = $request->get('project_id');

        if (!$projectId) {
            return response()->json(['error' => 'Project ID required'], 400);
        }

        $project = Project::where('id', $projectId)
            ->where('user_id', Auth::id())
            ->first();

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        // Mock engagement data - in production, track views, saves, follows
        $views = rand(500, 2000);
        $saves = rand(50, 200);
        $follows = rand(20, 100);
        $investments = Investment::where('project_id', $projectId)->count();
        
        $conversionRate = $views > 0 ? ($investments / $views * 100) : 0;

        return response()->json([
            'views' => $views,
            'saves' => $saves,
            'follows' => $follows,
            'investments' => $investments,
            'conversionRate' => round($conversionRate, 2),
        ]);
    }

    /**
     * Get revenue breakdown
     */
    public function revenueBreakdown(Request $request)
    {
        $projectId = $request->get('project_id');

        $query = Investment::where('status', 'confirmed');

        if ($projectId) {
            $query->where('project_id', $projectId);
        } else {
            $query->whereIn('project_id', function($q) {
                $q->select('id')
                    ->from('projects')
                    ->where('user_id', Auth::id());
            });
        }

        $totalRaised = $query->sum('amount');
        $platformFee = $totalRaised * 0.05; // 5% platform fee
        $netPayout = $totalRaised - $platformFee;

        return response()->json([
            'totalRaised' => $totalRaised,
            'platformFee' => $platformFee,
            'netPayout' => $netPayout,
            'feePercentage' => 5,
        ]);
    }

    /**
     * Get financial dashboard data
     */
    public function financialDashboard()
    {
        $user = Auth::user();

        // Calculate wallet balance (net payout from all confirmed investments)
        $totalRaised = Investment::whereIn('project_id', function($q) use ($user) {
            $q->select('id')->from('projects')->where('user_id', $user->id);
        })->where('status', 'confirmed')->sum('amount');

        $platformFee = $totalRaised * 0.05;
        $walletBalance = $totalRaised - $platformFee;

        // Pending payouts (projects that are funded but not yet paid out)
        $pendingPayouts = 0; // Would track actual payout requests

        // Recent transactions
        $transactions = Transaction::where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'walletBalance' => $walletBalance,
            'pendingPayouts' => $pendingPayouts,
            'totalRaised' => $totalRaised,
            'platformFee' => $platformFee,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Get top performing project
     */
    public function topPerformingProject()
    {
        $topProject = Project::where('user_id', Auth::id())
            ->withCount(['investments' => function($q) {
                $q->where('status', 'confirmed');
            }])
            ->orderBy('investments_count', 'desc')
            ->first();

        return response()->json($topProject);
    }

    /**
     * Calculate developer trust level
     */
    private function calculateTrustLevel($userId)
    {
        $totalProjects = Project::where('user_id', $userId)->count();
        $fundedProjects = Project::where('user_id', $userId)->where('funding_status', 'funded')->count();
        
        if ($totalProjects === 0) return ['level' => 'New', 'score' => 0];

        $successRate = ($fundedProjects / $totalProjects) * 100;

        if ($successRate >= 80 && $totalProjects >= 5) {
            return ['level' => 'Diamond', 'score' => 95];
        } elseif ($successRate >= 60 && $totalProjects >= 3) {
            return ['level' => 'Gold', 'score' => 75];
        } elseif ($successRate >= 40 && $totalProjects >= 2) {
            return ['level' => 'Silver', 'score' => 50];
        } elseif ($totalProjects >= 1) {
            return ['level' => 'Bronze', 'score' => 25];
        }

        return ['level' => 'New', 'score' => 0];
    }

    public function projects()
    {
        $user = Auth::user();
        
        $projects = Project::where('user_id', $user->id)
            ->withCount(['investments' => function($q) {
                $q->where('status', 'confirmed');
            }])
            ->with(['images', 'investments' => function($q) {
                $q->where('status', 'confirmed');
            }])
            ->latest()
            ->get();

        // Add computed fields for each project
        return response()->json($projects->map(function($project) {
            // Calculate real funding from confirmed investments
            $project->current_funding = $project->investments->sum('amount');
            
            // Count unique investors - use groupBy to ensure uniqueness
            $uniqueInvestors = $project->investments->groupBy('user_id');
            $project->investor_count = $uniqueInvestors->count();
            
            // Add funding percentage
            $project->funding_percentage = $project->target_funding > 0 
                ? round(($project->current_funding / $project->target_funding) * 100, 2)
                : 0;
            
            // Don't include full investments data in list view to reduce payload
            unset($project->investments);
            
            return $project;
        }));
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'title' => 'required|string|max:255',
            'goal' => 'required|numeric|min:1000',
            'status' => 'required|string',
        ]);

        $project = Project::create([
            'user_id' => Auth::id(),
            'title' => $data['title'],
            'goal' => $data['goal'],
            'raised_amount' => 0,
            'status' => $data['status'],
        ]);

        return response()->json($project, 201);
    }

    public function transactions()
    {
        return Transaction::where('user_id', Auth::id())->latest()->limit(10)->get();
    }

    /**
     * Get single project with full details
     */
    public function getProject($id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['images', 'documents', 'milestones', 'updates', 'investments' => function($q) {
                $q->where('status', 'confirmed')->with('user:id,name,email');
            }])
            ->withCount(['investments' => function($q) {
                $q->where('status', 'confirmed');
            }])
            ->firstOrFail();

        // Calculate real funding
        $project->current_funding = $project->investments->sum('amount');
        
        // Count unique investors properly using groupBy
        $uniqueInvestors = $project->investments->groupBy('user_id');
        $project->investor_count = $uniqueInvestors->count();
        
        $project->funding_percentage = $project->target_funding > 0 
            ? round(($project->current_funding / $project->target_funding) * 100, 2)
            : 0;

        return response()->json($project);
    }

    /**
     * Add project update
     */
    public function addUpdate(Request $request, $id)
    {
        $project = Project::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'nullable|string|in:milestone,progress,announcement,financial',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $updateData = [
            'project_id' => $project->id,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'type' => $validated['type'] ?? 'progress',
        ];

        // Handle image upload if provided
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('project-updates', 'public');
            $updateData['image_url'] = $imagePath;
        }

        $update = \App\Models\ProjectUpdate::create($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Project update added successfully',
            'update' => $update
        ], 201);
    }

    /**
     * Get developer notifications
     */
    public function notifications()
    {
        $user = Auth::user();
        
        // Get notifications (would use Laravel's notification system in production)
        // For now, return mock notifications
        $notifications = [
            [
                'id' => 1,
                'type' => 'investment',
                'title' => 'New Investment Received',
                'message' => 'Your project received a $5,000 investment',
                'time' => Carbon::now()->subMinutes(30)->toISOString(),
                'read' => false,
            ],
            [
                'id' => 2,
                'type' => 'milestone',
                'title' => 'Funding Milestone Reached',
                'message' => 'Your project reached 75% funding',
                'time' => Carbon::now()->subHours(2)->toISOString(),
                'read' => false,
            ],
            [
                'id' => 3,
                'type' => 'approval',
                'title' => 'Project Approved',
                'message' => 'Your project has been approved and is now live',
                'time' => Carbon::now()->subDays(1)->toISOString(),
                'read' => true,
            ],
        ];

        return response()->json($notifications);
    }

    /**
     * Get developer trust level
     */
    public function trustLevel()
    {
        $user = Auth::user();
        $trustData = $this->calculateTrustLevel($user->id);

        // Add additional trust metrics
        $totalProjects = Project::where('user_id', $user->id)->count();
        $fundedProjects = Project::where('user_id', $user->id)->where('funding_status', 'funded')->count();
        $totalRaised = Investment::whereIn('project_id', function($q) use ($user) {
            $q->select('id')->from('projects')->where('user_id', $user->id);
        })->where('status', 'confirmed')->sum('amount');

        return response()->json([
            'level' => $trustData['level'],
            'score' => $trustData['score'],
            'totalProjects' => $totalProjects,
            'fundedProjects' => $fundedProjects,
            'totalRaised' => $totalRaised,
            'badges' => $this->getTrustBadges($user->id),
        ]);
    }

    /**
     * Get trust badges earned by developer
     */
    private function getTrustBadges($userId)
    {
        $badges = [];
        
        $totalProjects = Project::where('user_id', $userId)->count();
        $fundedProjects = Project::where('user_id', $userId)->where('funding_status', 'funded')->count();
        
        if ($totalProjects >= 1) {
            $badges[] = ['name' => 'First Project', 'icon' => '🎯'];
        }
        if ($totalProjects >= 5) {
            $badges[] = ['name' => '5 Projects', 'icon' => '🏆'];
        }
        if ($fundedProjects >= 3) {
            $badges[] = ['name' => 'Funding Expert', 'icon' => '💰'];
        }
        
        return $badges;
    }
}
