<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\AiModel;
use App\Models\Church;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StarterKitSeeder extends Seeder
{
    /**
     * Seed the reusable configuration required by every test church.
     */
    public function run(): void
    {
        $this->seedAiModels();

        Church::query()->each(function (Church $church): void {
            $this->seedCategories($church);
            $this->seedSettings($church);
        });
    }

    private function seedCategories(Church $church): void
    {
        $categorySets = [
            CategoryType::POST->value => ['Avisos', 'Testemunhos', 'Devocionais', 'Comunidade'],
            CategoryType::EVENT->value => ['Culto', 'Jovens', 'Kids', 'Casais', 'Ação social'],
            CategoryType::MEDIA->value => ['Galeria', 'Culto ao vivo', 'Música', 'Vídeo'],
            CategoryType::FORM->value => ['Inscrições', 'Visitantes', 'Voluntariado', 'Intercessão'],
            CategoryType::LIBRARY->value => ['Bíblia', 'Estudo', 'Devocional', 'Material de apoio'],
            CategoryType::CLASSROOM->value => ['Kids', 'Adolescentes', 'Adultos', 'Liderança'],
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
        Setting::query()->updateOrCreate(
            ['church_id' => $church->id],
            ['options' => [
                'branding' => [
                    'brand_name' => $church->name,
                    'tagline' => 'Uma casa para toda a família.',
                    'primary_color' => '#1e3a8a',
                    'secondary_color' => '#0f766e',
                    'accent_color' => '#f59e0b',
                    'font_family' => 'Manrope, ui-sans-serif',
                    'contact_email' => 'contato@'.Str::slug($church->name).'.test',
                ],
            ]],
        );
    }

    private function seedAiModels(): void
    {
        foreach ([
            ['provider' => 'openai', 'model_id' => 'gpt-4.1-mini', 'name' => 'GPT-4.1 Mini', 'position' => 1],
            ['provider' => 'google', 'model_id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash', 'position' => 2],
        ] as $model) {
            AiModel::query()->updateOrCreate(
                ['model_id' => $model['model_id']],
                [...$model, 'status' => 'active', 'context_length' => 128000, 'input_modalities' => 'text,image', 'output_modalities' => 'text'],
            );
        }
    }
}
