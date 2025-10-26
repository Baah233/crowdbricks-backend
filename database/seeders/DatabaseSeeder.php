<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Project;
use App\Models\Pledge;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create some users
        User::factory()->count(5)->create();

        // Create projects with owners
        Project::factory()
        ->count(10)
        ->create([
            'status' => 'published',
        ])
        ->each(function ($project) {
            Pledge::factory()->count(rand(2, 5))->create([
                'project_id' => $project->id,
            ]);
        });

    }
}
