<?php

namespace Database\Factories;

use App\Enums\LiveStreamStatus;
use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LiveStream>
 */
class LiveStreamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $path = 'stream-'.Str::lower((string) Str::ulid());

        return [
            'church_id' => null,
            'created_by_id' => User::factory(),
            'name' => fake()->sentence(3),
            'path' => $path,
            'source_url' => 'rtsp://camera.example.test/'.$path,
            'source_on_demand' => false,
            'record' => true,
            'is_public' => true,
            'status' => LiveStreamStatus::READY,
            'active_slot' => 1,
        ];
    }
}
