<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Approve Functionality\n";
echo "==============================\n\n";

// Test User Approval
echo "1. Testing User Approval:\n";
$user = \App\Models\User::find(1);
echo "User ID: {$user->id}\n";
echo "User Email: {$user->email}\n";
echo "Before Status: {$user->status}\n";

$user->status = 'approved';
$saved = $user->save();
echo "Save result: " . ($saved ? "SUCCESS" : "FAILED") . "\n";

$user->refresh();
echo "After Status: {$user->status}\n\n";

// Test Project Approval
echo "2. Testing Project Approval:\n";
$project = \App\Models\Project::find(1);
if ($project) {
    echo "Project ID: {$project->id}\n";
    echo "Project Title: {$project->title}\n";
    echo "Before approval_status: {$project->approval_status}\n";
    
    $project->approval_status = 'approved';
    $saved = $project->save();
    echo "Save result: " . ($saved ? "SUCCESS" : "FAILED") . "\n";
    
    $project->refresh();
    echo "After approval_status: {$project->approval_status}\n\n";
} else {
    echo "No project found\n\n";
}

// Verify the changes
echo "3. Verification:\n";
$user = \App\Models\User::find(1);
echo "User 1 status: {$user->status}\n";

$project = \App\Models\Project::find(1);
if ($project) {
    echo "Project 1 approval_status: {$project->approval_status}\n";
}

echo "\n==============================\n";
echo "Test completed!\n";
