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
            'terms_accepted_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function verified(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill([
            'phone_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ])->save());
    }

    public function unverified(): static
    {
        return $this->afterCreating(fn (User $user) => $user->forceFill([
            'phone_verified_at' => null,
            'status' => UserStatus::PENDING,
        ])->save());
    }
}
