<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Church;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Cria um endereço base
        $address = Address::create([
            'country' => 'Brasil',
            'state' => 'SP',
            'city' => 'São Paulo',
            'street' => 'Rua Principal',
            'number' => '1000',
            'zipcode' => '01000-000',
        ]);

        // 2. Cria a Igreja Matriz
        $church = Church::create([
            'name' => 'Nossa Casa Matriz',
            'slug' => Str::slug('Nossa Casa Matriz'),
            'address_id' => $address->id,
            'found_date' => '2020-01-01',
        ]);

        // 3. Cria o usuário Super Admin
        $superadmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@nossacasa.app',
            'password' => bcrypt('password'), // Altere em produção
            'role' => UserRole::SUPERADMIN,
            'church_id' => $church->id,
        ]);

        // 4. Cria o Profile do Super Admin
        UserProfile::create([
            'user_id' => $superadmin->id,
            'location_lang' => 'pt-BR',
        ]);

         User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::USER,
            'church_id' => $church->id,
        ]);
    }
}
