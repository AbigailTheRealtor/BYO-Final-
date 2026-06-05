<?php

namespace Database\Factories;

use App\Models\OfferAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShowingAvailabilityFactory extends Factory
{
    public function definition()
    {
        $start = $this->faker->time('H:i:s', '17:00:00');
        $end   = date('H:i:s', strtotime($start) + 3600);

        return [
            // Default to a seller listing so generated records are showing-eligible.
            // Use ->state(['offer_auction_id' => $id]) to bind to an existing auction.
            'offer_auction_id' => OfferAuction::factory()->sellerListing(),
            'user_id'          => User::factory(),
            'available_date'   => $this->faker->dateTimeBetween('+1 day', '+60 days')->format('Y-m-d'),
            'start_time'       => $start,
            'end_time'         => $end,
            'notes'            => $this->faker->optional()->sentence(),
            'max_showings'     => $this->faker->optional()->numberBetween(1, 10),
        ];
    }

    public function unlimited()
    {
        return $this->state(fn () => ['max_showings' => null]);
    }

    public function withNotes()
    {
        return $this->state(fn () => ['notes' => $this->faker->sentence()]);
    }

    public function forLandlord()
    {
        return $this->state(fn () => [
            'offer_auction_id' => OfferAuction::factory()->landlordListing(),
        ]);
    }
}
