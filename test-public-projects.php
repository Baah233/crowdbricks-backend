<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Public Projects API\n";
echo "============================\n\n";

// Simulate the index() method
$projects = \App\Models\Project::with(['developer:id,first_name,last_name', 'images', 'milestones'])
    ->where('approval_status', 'approved')
    ->where('funding_status', '!=', 'completed')
    ->latest()
    ->get();

echo "Total approved projects: " . $projects->count() . "\n\n";

foreach ($projects as $project) {
    echo "Project ID: {$project->id}\n";
    echo "Title: {$project->title}\n";
    echo "Approval Status: {$project->approval_status}\n";
    echo "Funding Status: {$project->funding_status}\n";
    echo "Target Funding: {$project->target_funding}\n";
    echo "Current Funding: {$project->current_funding}\n";
    $devName = isset($project->developer) ? "{$project->developer->first_name} {$project->developer->last_name}" : "Unknown";
    echo "Developer: {$devName}\n";
    echo "Images: " . $project->images->count() . "\n";
    echo "Milestones: " . $project->milestones->count() . "\n";
    echo "\n";
}

echo "============================\n";
echo "Test completed!\n";
