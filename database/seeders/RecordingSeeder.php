<?php

namespace Database\Seeders;

use App\Models\Recording;
use Illuminate\Database\Seeder;

class RecordingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Recording::factory()->count(10)->create();
    }
}
