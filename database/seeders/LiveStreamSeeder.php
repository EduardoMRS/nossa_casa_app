<?php

namespace Database\Seeders;

use App\Models\LiveStream;
use Illuminate\Database\Seeder;

class LiveStreamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LiveStream::factory()->count(10)->create();
    }
}
