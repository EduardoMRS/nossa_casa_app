<?php

use App\Enums\CategoryType;
use App\Models\Church;
use Database\Seeders\StarterKitSeeder;

test('a newly created church receives its starter kit', function () {
    $church = Church::factory()->create();
    $settings = $church->settings()->firstOrFail();

    expect($church->categories()->count())->toBe(25)
        ->and($church->categories()->where('type', CategoryType::POST)->count())->toBe(4)
        ->and($church->categories()->where('type', CategoryType::EVENT)->count())->toBe(5)
        ->and($church->settings()->exists())->toBeTrue()
        ->and($settings->options['branding']['brand_name'])->toBe($church->name)
        ->and($settings->options['terminology']['units']['headquarters'])->toBe('church')
        ->and($settings->options['terminology']['units']['branch'])->toBe('branch')
        ->and($settings->options['terminology']['roles']['church_leader'])->toBe('church_leader');
});

test('starter kit seeding remains idempotent for existing churches', function () {
    $church = Church::factory()->create();
    $setting = $church->settings()->firstOrFail();
    $options = $setting->options;
    $options['branding']['brand_name'] = 'Custom name';
    $options['terminology']['roles']['church_leader'] = 'pastor';
    $setting->update(['options' => $options]);

    $this->seed(StarterKitSeeder::class);
    $this->seed(StarterKitSeeder::class);

    expect($church->categories()->count())->toBe(25)
        ->and($church->settings()->count())->toBe(1)
        ->and($setting->fresh()->options['branding']['brand_name'])->toBe('Custom name')
        ->and($setting->fresh()->options['terminology']['roles']['church_leader'])->toBe('pastor');
});
