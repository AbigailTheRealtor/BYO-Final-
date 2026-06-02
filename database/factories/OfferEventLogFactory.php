<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferEventLogFactory extends Factory
{
    public function definition()
    {
        return [
            'offer_id'    => Offer::factory(),
            'actor_id'    => User::factory(),
            'actor_role'  => 'buyer',
            'event_type'  => 'submitted',
            'from_status' => 'draft',
            'to_status'   => 'submitted',
            'metadata'    => null,
            'ip_address'  => $this->faker->ipv4(),
        ];
    }
}
