<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company().' Community',
            'slug' => fake()->unique()->slug(),
            'description' => fake()->paragraph(),
            'found_date' => fake()->optional()->date(),
            'logo_path' => null,
        ];
    }
}
