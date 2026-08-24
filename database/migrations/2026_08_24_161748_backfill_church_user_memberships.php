<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('user_profiles')
            ->whereNotNull('church_id')
            ->orderBy('id')
            ->chunk(500, function ($profiles): void {
                $roles = DB::table('users')
                    ->whereIn('id', $profiles->pluck('user_id'))
                    ->pluck('role', 'id');
                $timestamp = now();

                DB::table('church_user')->insertOrIgnore(
                    $profiles->map(fn ($profile): array => [
                        'church_id' => $profile->church_id,
                        'user_id' => $profile->user_id,
                        'role' => $roles[$profile->user_id] ?? null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])->all(),
                );
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('church_user')->delete();
    }
};
