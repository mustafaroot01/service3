<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone' => '9647'.fake()->unique()->numerify('#########'),
            'password' => 'password',
            'status' => UserStatus::ACTIVE,
            'terms_accepted_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Kept for the many call sites that read as "a real, usable account". Every
     * account is verified the moment it exists now, so this only affirms it.
     */
    public function verified(): static
    {
        return $this->state(['status' => UserStatus::ACTIVE]);
    }
}
