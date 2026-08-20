<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endpoint = fake()->unique()->url();

        return [
            'user_id' => User::factory(),
            'endpoint_hash' => hash('sha256', $endpoint),
            'endpoint' => $endpoint,
            'public_key' => fake()->sha256(),
            'auth_token' => fake()->sha256(),
            'content_encoding' => 'aes128gcm',
            'user_agent' => fake()->userAgent(),
        ];
    }
}
