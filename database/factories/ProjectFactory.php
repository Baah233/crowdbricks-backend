<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\User;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $projectTitles = [
            'East Legon Luxury Apartments',
            'Tema Waterfront Villas',
            'Kumasi Smart Housing Estate',
            'Takoradi Ocean View Towers',
            'Spintex Garden Homes',
            'Cantonments Executive Suites',
            'Adenta Family Residences',
            'Airport Hills Executive Lofts',
            'Osu Modern Condos',
            'Madina Affordable Housing Phase 2',
        ];

        $categories = ['Residential', 'Commercial', 'Mixed-use', 'Affordable', 'Luxury'];

        $title = $this->faker->unique()->randomElement($projectTitles);

        // Try to get an existing developer user, or create one if none exists
        $developer = User::where('user_type', 'developer')->inRandomOrder()->first() ?? 
                     User::factory()->developer()->create();

        return [
            'user_id' => $developer->id,
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(5),
            'short_description' => $this->faker->sentence(10),
            'full_description' => $this->faker->paragraphs(3, true),
            'target_amount' => $this->faker->numberBetween(50000, 5000000),
            'raised_amount' => $this->faker->numberBetween(0, 3000000),
            'category' => $this->faker->randomElement($categories),
            'status' => 'published',
            'is_active' => true,
            'image_path' => 'projects/default.jpg',
        ];
    }
}
