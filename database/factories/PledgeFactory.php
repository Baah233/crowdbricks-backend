<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class PledgeFactory extends Factory
{
    public function definition(): array
    {
        // Try to find an existing investor, or create one if none exists
        $investor = User::where('user_type', 'investor')->inRandomOrder()->first()
            ?? User::factory()->investor()->create();

        // Project will be assigned by seeder, but we can use a fallback just in case
        $project = Project::inRandomOrder()->first();

        return [
            'user_id' => $investor->id,
            'project_id' => $project?->id, // seeder can override this
            'amount' => $this->faker->numberBetween(500, 20000),
        ];
    }
}
