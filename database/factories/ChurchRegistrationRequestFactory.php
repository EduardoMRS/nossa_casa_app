<?php

namespace Database\Factories;

use App\Models\ChurchRegistrationRequest;
use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChurchRegistrationRequest>
 */
class ChurchRegistrationRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'community_id' => Community::factory(),
            'name' => fake()->company().' Church',
            'slug' => fake()->unique()->slug(),
            'domain' => fake()->unique()->domainName(),
            'description' => fake()->paragraph(),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'status' => 'pending',
        ];
    }
}
