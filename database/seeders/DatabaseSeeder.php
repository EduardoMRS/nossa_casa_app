<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $systemEmail = env('APP_USER_SYSTEM_EMAIL') ?: 'system@nossacasa.test';
        $systemPassword = env('APP_USER_SYSTEM_PASSWORD') ?: 'password';

        // 1. Cria o usuário do Sistema (Super System admin)
        $superadmin = User::updateOrCreate(
            [
                'email' => $systemEmail,
            ],
            [
                'first_name' => 'System',
                'last_name' => 'Nossa Casa',
                'password' => bcrypt($systemPassword),
                'birth_date' => '2000-01-01',
            ]
        );
        $superadmin->assignRole(UserRole::SYSTEM->value);
        $superadmin->profile()->updateOrCreate(
            ['user_id' => $superadmin->id],
            [
                'phone' => '+55 11 99999-9999',
                'location_lang' => 'pt-BR',
                'avatar_path' => null,
                'gender' => 'male',
            ]
        );

        // 2. Cria comunidade de Testes
        $community = Community::updateOrCreate(
            [
                'slug' => Str::slug('Nossa Comunidade Teste'),
            ],
            [
                'owner_id' => $superadmin->id,
                'name' => 'Nossa Comunidade Teste',
                'description' => 'Comunidade de Testes para o Sistema Nossa Casa',
                'found_date' => '2026-07-16',
                'logo_path' => null,
            ]
        );

        // 3. Cria Igreja de Testes
        $church = Church::updateOrCreate(
            [
                'slug' => Str::slug('Nossa Casa Teste'),
            ],
            [
                'name' => 'Nossa Casa Teste',
                'found_date' => '2026-07-16',
                'domain' => 'nossa.localhost',
            ]
        );

        $church->address()->updateOrCreate(
            [
                'addressable_id' => $church->id,
                'addressable_type' => Church::class,
            ],
            [
                'country' => 'Brasil',
                'state' => 'SP',
                'city' => 'São Paulo',
                'street' => 'Rua Principal',
                'number' => '1000',
                'zipcode' => '01000-000',
            ]
        );
        $church->assignMember($superadmin);
        $community->assignChurch($church);

        // 4. Executa outros seeders
        $this->call(StarterKitSeeder::class);
        $this->call(RelationTesterSeeder::class);
    }
}
