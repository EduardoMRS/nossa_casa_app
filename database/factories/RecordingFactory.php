<?php

namespace Database\Factories;

use App\Enums\RecordingStatus;
use App\Models\LiveStream;
use App\Models\Recording;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Recording>
 */
class RecordingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = Str::lower((string) Str::ulid()).'.mp4';

        return [
            'live_stream_id' => LiveStream::factory(),
            'worker_id' => 'worker-test',
            'worker_path' => storage_path('app/media-worker-recordings/'.$filename),
            'worker_path_hash' => hash('sha256', storage_path('app/media-worker-recordings/'.$filename)),
            'filename' => $filename,
            'mime_type' => 'video/mp4',
            'size' => fake()->numberBetween(1024, 1048576),
            'duration' => '15m0s',
            'status' => RecordingStatus::WAITING_UPLOAD,
        ];
    }
}
