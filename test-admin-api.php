<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Admin API Endpoints\n";
echo "===========================\n\n";

// Test Users
echo "1. Testing Users endpoint:\n";
$users = \App\Models\User::select(['id', 'first_name', 'last_name', 'email', 'user_type', 'role', 'status', 'created_at'])
    ->orderByDesc('created_at')
    ->get()
    ->map(function ($u) {
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
echo "Users count: " . $users->count() . "\n";
echo json_encode(['ok' => true, 'data' => $users], JSON_PRETTY_PRINT) . "\n\n";

// Test Projects
echo "2. Testing Projects endpoint:\n";
$projects = \App\Models\Project::with('developer:id,first_name,last_name')
    ->orderByDesc('created_at')
    ->get()
    ->map(function ($p) {
        $developerName = isset($p->developer) ? trim(($p->developer->first_name ?? '') . ' ' . ($p->developer->last_name ?? '')) : 'Unknown';
        $target = $p->target_funding ?? 0;
        $current = $p->current_funding ?? 0;
        $progressPercent = $target > 0 ? round(($current / $target) * 100, 2) : 0;
        
        return [
            'id' => $p->id,
            'title' => $p->title ?? null,
            'status' => $p->approval_status ?? null,
            'target_funding' => $target,
            'current_funding' => $current,
            'created_at' => $p->created_at ?? null,
            'developer_id' => $p->user_id ?? null,
            'developer_name' => $developerName,
            'progress_percent' => $progressPercent,
        ];
    });
echo "Projects count: " . $projects->count() . "\n";
echo json_encode(['ok' => true, 'data' => $projects], JSON_PRETTY_PRINT) . "\n\n";

// Test Investments
echo "3. Testing Investments endpoint:\n";
$investments = \App\Models\Investment::with(['user:id,first_name,last_name', 'project:id,title'])
    ->orderByDesc('created_at')
    ->get()
    ->map(function ($inv) {
        $investorName = isset($inv->user) ? trim(($inv->user->first_name ?? '') . ' ' . ($inv->user->last_name ?? '')) : 'Unknown';
        $projectTitle = $inv->project->title ?? 'N/A';
        
        return [
            'id' => $inv->id,
            'investor_id' => $inv->user_id ?? null,
            'project_id' => $inv->project_id ?? null,
            'amount' => $inv->amount ?? 0,
            'status' => $inv->status ?? null,
            'method' => $inv->payment_method ?? null,
            'created_at' => $inv->created_at ?? null,
            'investor_name' => $investorName,
            'project_title' => $projectTitle,
        ];
    });
echo "Investments count: " . $investments->count() . "\n";
echo json_encode(['ok' => true, 'data' => $investments], JSON_PRETTY_PRINT) . "\n\n";

// Test Stats
echo "4. Testing Stats endpoint:\n";
$stats = [
    'total_users' => \App\Models\User::count(),
    'total_developers' => \App\Models\User::where('user_type', 'developer')->count(),
    'total_investors' => \App\Models\User::where('user_type', 'investor')->count(),
    'pending_users' => \App\Models\User::where('status', 'pending')->count(),
    'approved_projects' => \App\Models\Project::where('approval_status', 'approved')->count(),
    'pending_projects' => \App\Models\Project::where('approval_status', 'pending')->count(),
    'total_investments' => (float) \App\Models\Investment::sum('amount'),
];
echo json_encode(['ok' => true, 'data' => $stats], JSON_PRETTY_PRINT) . "\n";

echo "\n===========================\n";
echo "Test completed successfully!\n";
