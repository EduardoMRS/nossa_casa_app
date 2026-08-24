<?php

namespace Database\Factories;

use App\Models\MobileSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MobileSession>
 */
class MobileSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => (string) Str::ulid(),
            'device_name' => fake()->randomElement(['Pixel', 'iPhone']),
            'platform' => fake()->randomElement(['android', 'ios']),
            'app_version' => '1.0.0',
            'token_family' => (string) Str::ulid(),
            'active_slot' => 1,
            'expires_at' => now()->addDays(30),
            'last_used_at' => now(),
            'last_ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
