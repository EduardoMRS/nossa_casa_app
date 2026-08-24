<?php

namespace App\Actions\Churches;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransferChurchMembership
{
    public function handle(User $user, Church $church): void
    {
        DB::transaction(function () use ($user, $church): void {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'community_id' => $church->community_id,
                    'church_id' => $church->id,
                ],
            );

            if ($user->role !== UserRole::SYSTEM && $user->role !== UserRole::MEMBER) {
                $user->update(['role' => UserRole::MEMBER]);
            }

            $church->members()->syncWithoutDetaching([
                $user->id => ['role' => $user->role->value],
            ]);
        });
    }
}
