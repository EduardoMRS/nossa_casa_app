<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\ChurchTerminology;
use Illuminate\Database\Seeder;

class TerminologySeeder extends Seeder
{
    public function __construct(private ChurchTerminology $terminology) {}

    public function run(): void
    {
        Setting::query()->eachById(function (Setting $setting): void {
            $options = is_array($setting->options) ? $setting->options : [];
            $savedTerminology = is_array($options['terminology'] ?? null)
                ? $options['terminology']
                : [];
            $options['terminology'] = $this->terminology->selections($savedTerminology);

            $setting->update(['options' => $options]);
        });
    }
}
