<?php

namespace Database\Factories;

use App\Models\AiQuery;
use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiQuery>
 */
class AiQueryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'openrouter',
            'model' => 'inclusionai/ling-3.0-flash:free',
            'input' => fake()->sentence(),
            'response' => fake()->paragraph(),
            'usage' => [
                'prompt_tokens' => fake()->numberBetween(1, 100),
                'completion_tokens' => fake()->numberBetween(1, 100),
                'total_tokens' => fake()->numberBetween(101, 200),
            ],
            'status' => 'completed',
            'church_id' => Church::factory(),
            'type' => 'translation',
        ];
    }
}
