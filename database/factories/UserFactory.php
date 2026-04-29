<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'first_name'        => $this->faker->firstName(),
            'last_name'         => $this->faker->lastName(),
            'name'              => $this->faker->name(),
            'user_type'         => 'buyer',
            'email'             => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token'    => Str::random(10),
            // phone, short_id, user_name: filled by UserObserver::creating()
            // is_approved, is_super, is_deleted: DB-level defaults (false)
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a user with user_type = 'agent'.
     * Requires the users_user_type_check constraint to include 'agent'
     * (migration 2026_04_29_000001_add_agent_to_users_user_type_check).
     */
    public function asAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'agent',
        ]);
    }

    /**
     * Create an admin user.
     */
    public function asAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'admin',
        ]);
    }

    /**
     * Create a buyer agent user.
     */
    public function asBuyerAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'buyer_agent',
        ]);
    }

    /**
     * Create a seller agent user.
     */
    public function asSellerAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'seller_agent',
        ]);
    }
}
