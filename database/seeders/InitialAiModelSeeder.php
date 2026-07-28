<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InitialAiModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            'openrouter' => [
                [
                    'model_id' => 'inclusionai/ling-3.0-flash:free',
                    'status' => 'active',
                ],
                [
                    'model_id' => 'krea/krea-2-medium-turbo',
                    'status' => 'active',
                ],
                [
                    'model_id' => 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
                    'status' => 'active',
                ],
                [
                    'model_id' => 'cohere/north-mini-code:free',
                    'status' => 'active',
                ],
                [
                    'model_id' => 'google/gemma-4-31b-it:free',
                    'status' => 'active',
                ],
                [
                    'model_id'=> 'openai/gpt-oss-20b:free',
                    'status' => 'active',
                ]
            ]
        ];
        foreach ($providers as $provider => $models) {
            foreach ($models as $index => $modelData) {
                \App\Models\AiModel::updateOrCreate(
                    [
                        'provider' => $provider,
                        'model_id' => $modelData['model_id'],
                    ],
                    [
                        'name' => $modelData['model_id'],
                        'status' => $modelData['status'],
                        'position' => $index,
                    ]
                );
            }
        }
    }
}
