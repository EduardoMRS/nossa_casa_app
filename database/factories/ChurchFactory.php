<?php

namespace Database\Factories;

use App\Enums\ChurchStatus;
use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Church>
 */
class ChurchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Church',
            'slug' => fake()->unique()->slug(),
            'domain' => null,
            'community_id' => null,
            'status' => ChurchStatus::ACTIVE,
            'found_date' => fake()->optional()->date(),
        ];
    }
}
