<?php

namespace Database\Factories;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferMetaFactory extends Factory
{
    public function definition()
    {
        return [
            'offer_id'   => Offer::factory(),
            'meta_key'   => $this->faker->unique()->word(),
            'meta_value' => $this->faker->sentence(),
        ];
    }
}
