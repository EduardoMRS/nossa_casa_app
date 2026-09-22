<?php

namespace App\Actions\Churches;

use App\Enums\CategoryType;
use App\Models\Church;
use App\Models\Setting;
use App\Support\ChurchTerminology;
use Illuminate\Support\Str;

final class ProvisionChurchStarterKit
{
    public function __construct(private ChurchTerminology $terminology) {}

    public function handle(Church $church): void
    {
        $this->seedCategories($church);
        $this->seedSettings($church);
    }

    private function seedCategories(Church $church): void
    {
        $categorySets = [
            CategoryType::POST->value => (array) trans('church.starter_kit.categories.post'),
            CategoryType::EVENT->value => (array) trans('church.starter_kit.categories.event'),
            CategoryType::MEDIA->value => (array) trans('church.starter_kit.categories.media'),
            CategoryType::FORM->value => (array) trans('church.starter_kit.categories.form'),
            CategoryType::LIBRARY->value => (array) trans('church.starter_kit.categories.library'),
            CategoryType::CLASSROOM->value => (array) trans('church.starter_kit.categories.classroom'),
        ];

        foreach ($categorySets as $type => $categories) {
            foreach ($categories as $name) {
                $church->categories()->updateOrCreate(
                    ['slug' => Str::slug($name), 'type' => $type],
                    ['name' => $name, 'type' => $type],
                );
            }
        }
    }

    private function seedSettings(Church $church): void
    {
        $setting = Setting::query()->firstOrNew(['church_id' => $church->id]);
        $options = is_array($setting->options) ? $setting->options : [];
        $savedBranding = is_array($options['branding'] ?? null) ? $options['branding'] : [];
        $savedTerminology = is_array($options['terminology'] ?? null) ? $options['terminology'] : [];

        $options['branding'] = array_merge([
            'brand_name' => $church->name,
            'tagline' => __('church.starter_kit.tagline'),
            'primary_color' => '#1e3a8a',
            'secondary_color' => '#0f766e',
            'accent_color' => '#f59e0b',
            'font_family' => 'Manrope, ui-sans-serif',
            'contact_email' => 'contact@'.Str::slug($church->name).'.test',
        ], $savedBranding);
        $options['terminology'] = $this->terminology->selections($savedTerminology);
        $options['terminology_source'] = 'inherited';

        $setting->options = $options;
        $setting->save();
    }
}
