<?php

namespace Database\Factories;

use App\Enums\ContributionType;
use App\Models\Church;
use App\Models\Contribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contribution>
 */
class ContributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'user_id' => null,
            'type' => fake()->randomElement(ContributionType::cases())->value,
            'amount' => fake()->randomFloat(2, 5, 500),
            'contributor_name' => fake()->optional()->name(),
            'message' => fake()->optional()->sentence(),
            'contributed_at' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }

    public function tithe(): static
    {
        return $this->state(['type' => ContributionType::TITHE->value]);
    }

    public function offering(): static
    {
        return $this->state(['type' => ContributionType::OFFERING->value]);
    }
}
