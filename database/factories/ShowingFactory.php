<?php

namespace Database\Factories;

use App\Enums\ShowingStatus;
use App\Models\OfferAuction;
use App\Models\ShowingAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShowingFactory extends Factory
{
    public function definition()
    {
        $start = $this->faker->time('H:i:s', '17:00:00');
        $end   = date('H:i:s', strtotime($start) + 3600);

        return [
            'showing_availability_id' => null,
            // Default to a seller listing so generated records are showing-eligible.
            // Use ->state(['offer_auction_id' => $id]) to bind to an existing auction.
            'offer_auction_id'        => OfferAuction::factory()->sellerListing(),
            'requester_id'            => User::factory(),
            'requested_by_agent'      => $this->faker->boolean(20),
            'requested_date'          => $this->faker->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'requested_start_time'    => $start,
            'requested_end_time'      => $end,
            'status'                  => ShowingStatus::REQUESTED,
            'requester_message'       => $this->faker->optional()->sentence(),
            'owner_message'           => null,
            'approved_date'           => null,
            'approved_start_time'     => null,
            'approved_end_time'       => null,
            'canceled_at'             => null,
            'completed_at'            => null,
        ];
    }

    public function approved()
    {
        return $this->state(fn () => [
            'status'              => ShowingStatus::APPROVED,
            'approved_date'       => $this->faker->dateTimeBetween('+1 day', '+14 days')->format('Y-m-d'),
            'approved_start_time' => '10:00:00',
            'approved_end_time'   => '11:00:00',
            'owner_message'       => $this->faker->optional()->sentence(),
        ]);
    }

    public function declined()
    {
        return $this->state(fn () => [
            'status'       => ShowingStatus::DECLINED,
            'owner_message'=> $this->faker->optional()->sentence(),
        ]);
    }

    public function canceled()
    {
        return $this->state(fn () => [
            'status'      => ShowingStatus::CANCELED,
            'canceled_at' => now(),
        ]);
    }

    public function completed()
    {
        return $this->state(fn () => [
            'status'       => ShowingStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function withSlot()
    {
        return $this->state(fn () => [
            'showing_availability_id' => ShowingAvailability::factory(),
        ]);
    }

    public function forLandlord()
    {
        return $this->state(fn () => [
            'offer_auction_id' => OfferAuction::factory()->landlordListing(),
        ]);
    }
}
