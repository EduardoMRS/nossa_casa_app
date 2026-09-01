<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            ...$this->profileRules(communityId: $input['community_id'] ?? null),
            'location_lang' => ['nullable', 'string', 'in:pt-BR,en-US'],
            'terms_accepted' => ['required', 'accepted'],
            'password' => $this->passwordRules(),
        ])->validate();

        $nameParts = explode(' ', Str::squish($validated['name']), 2);

        return DB::transaction(function () use ($validated, $nameParts): User {
            $user = User::create([
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
                'email' => $validated['email'],
                'password' => $validated['password'],
                'birth_date' => $validated['birth_date'] ?? null,
            ]);

            $user->profile()->create([
                'phone' => $validated['phone'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'location_lang' => $validated['location_lang']
                    ?? (app()->getLocale() === 'pt' ? 'pt-BR' : 'en-US'),
            ]);

            return $user;
        });
    }
}
