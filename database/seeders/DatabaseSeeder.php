<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Church;
use App\Models\Community;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Cria o usuário do Sistema (Super System admin)
        $superadmin = User::updateOrCreate(
            [
                'email' => env('APP_USER_SYSTEM_EMAIL'),
            ],
            [
                'first_name' => 'System',
                'last_name' => 'Nossa Casa',
                'password' => bcrypt(env('APP_USER_SYSTEM_PASSWORD')),
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
    }
}
