<?php

namespace Database\Factories;

use App\Enums\NotificationCategory;
use App\Enums\PushTransport;
use App\Models\DevicePushToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DevicePushToken>
 */
class DevicePushTokenFactory extends Factory
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
            'transport' => PushTransport::FCM,
            'token_hash' => hash('sha256', $token = fake()->sha256()),
            'token' => $token,
            'app_version' => '1.0.0',
            'locale' => 'pt_BR',
            'enabled_categories' => collect(NotificationCategory::cases())->map->value->all(),
            'last_seen_at' => now(),
        ];
    }
}
