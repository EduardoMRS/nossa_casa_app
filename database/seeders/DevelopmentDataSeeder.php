<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevelopmentDataSeeder extends Seeder
{
    public function run(): void
    {
        $systemUser = User::query()->updateOrCreate(
            ['email' => config('app.system_user.email')],
            [
                'first_name' => 'System',
                'last_name' => 'Nossa Casa',
                'password' => Hash::make(config('app.system_user.password')),
                'birth_date' => '2000-01-01',
            ],
        );
        $systemUser->assignRole(UserRole::SYSTEM->value);
        $systemUser->profile()->updateOrCreate(
            ['user_id' => $systemUser->id],
            [
                'phone' => '+55 11 99999-9999',
                'location_lang' => 'pt-BR',
                'avatar_path' => null,
                'gender' => 'male',
            ],
        );

        $community = Community::query()->updateOrCreate(
            ['slug' => Str::slug('Nossa Comunidade Teste')],
            [
                'owner_id' => $systemUser->id,
                'name' => 'Nossa Comunidade Teste',
                'description' => 'Comunidade de Testes para o Sistema Nossa Casa',
                'found_date' => '2026-07-16',
                'logo_path' => null,
            ],
        );

        $church = Church::query()->updateOrCreate(
            ['slug' => Str::slug('Nossa Casa Teste')],
            [
                'name' => 'Nossa Casa Teste',
                'found_date' => '2026-07-16',
                'domain' => domainBase('nossa'),
            ],
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
            ],
        );
        $church->assignMember($systemUser);
        $community->assignChurch($church);

        $this->call(RelationTesterSeeder::class);
    }
}
