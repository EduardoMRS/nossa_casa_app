<?php

namespace Database\Seeders;

use App\Models\Community;
use Illuminate\Database\Seeder;

class InitialCommunitySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->communities() as $community) {
            Community::query()->firstOrCreate(
                ['slug' => $community['slug']],
                $community,
            );
        }
    }

    /** @return array<int, array{name: string, slug: string, description: string}> */
    private function communities(): array
    {
        return [
            [
                'name' => 'Protestante',
                'slug' => 'protestante',
                'description' => 'Comunidade para igrejas e denominações protestantes.',
            ],
            [
                'name' => 'Católica',
                'slug' => 'catolica',
                'description' => 'Comunidade para igrejas católicas.',
            ],
            [
                'name' => 'Ortodoxa',
                'slug' => 'ortodoxa',
                'description' => 'Comunidade para igrejas cristãs ortodoxas.',
            ],
            [
                'name' => 'Outras comunidades cristãs',
                'slug' => 'outras-comunidades-cristas',
                'description' => 'Comunidade para outras tradições e movimentos cristãos.',
            ],
        ];
    }
}
