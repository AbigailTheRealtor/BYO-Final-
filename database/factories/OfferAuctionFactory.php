<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferAuctionFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id'     => User::factory(),
            'title'       => $this->faker->sentence(4),
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];
    }
}
