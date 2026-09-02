<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Seeder;

class InitialCommunitySeeder extends Seeder
{
    public function run(): void
    {
        $systemUserId = User::query()
            ->where('role', UserRole::SYSTEM)
            ->value('id');

        foreach ($this->communities() as $community) {
            $record = Community::query()->firstOrCreate(
                ['slug' => $community['slug']],
                [...$community, 'owner_id' => $systemUserId],
            );

            if (! $record->owner_id && $systemUserId) {
                $record->update(['owner_id' => $systemUserId]);
            }
        }
    }

    /** @return array<int, array{name: string, slug: string, description: string, default_locale: string}> */
    private function communities(): array
    {
        return [
            [
                'name' => 'Protestante',
                'slug' => 'protestante',
                'description' => 'Comunidade para igrejas e denominações protestantes.',
                'default_locale' => 'pt',
            ],
            [
                'name' => 'Católica',
                'slug' => 'catolica',
                'description' => 'Comunidade para igrejas católicas.',
                'default_locale' => 'pt',
            ],
            [
                'name' => 'Ortodoxa',
                'slug' => 'ortodoxa',
                'description' => 'Comunidade para igrejas cristãs ortodoxas.',
                'default_locale' => 'pt',
            ],
            [
                'name' => 'Outras comunidades cristãs',
                'slug' => 'outras-comunidades-cristas',
                'description' => 'Comunidade para outras tradições e movimentos cristãos.',
                'default_locale' => 'pt',
            ],
        ];
    }
}
