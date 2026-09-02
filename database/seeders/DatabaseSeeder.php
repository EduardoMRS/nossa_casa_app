<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            InitialAiModelSeeder::class,
            SystemUserSeeder::class,
            InitialCommunitySeeder::class,
            StarterKitSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DevelopmentDataSeeder::class);
        }
    }
}
