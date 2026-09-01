<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class SystemUserSeeder extends Seeder
{
    public function run(): void
    {
        $systemUserConfig = config('app.system_user', []);

        User::updateOrCreate(
            ['email' => $systemUserConfig['email'] ?? 'system@nossacasa.test'],
            [
                'first_name' => $systemUserConfig['name'] ?? 'System',
                'last_name' => '',
                'email' => $systemUserConfig['email'] ?? 'system@nossacasa.test',
                'password' => $systemUserConfig['password'] ?? 'password',
                'email_verified_at' => now(),
                'role' => UserRole::SYSTEM,
            ]
        );
    }
}
