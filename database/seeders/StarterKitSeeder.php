<?php

namespace Database\Seeders;

use App\Actions\Churches\ProvisionChurchStarterKit;
use App\Models\Church;
use Illuminate\Database\Seeder;

class StarterKitSeeder extends Seeder
{
    public function __construct(private ProvisionChurchStarterKit $provisionChurchStarterKit) {}

    public function run(): void
    {
        Church::query()->each(function (Church $church): void {
            $this->provisionChurchStarterKit->handle($church);
        });

        $this->call(TerminologySeeder::class);
    }
}
