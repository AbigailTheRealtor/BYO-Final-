<?php

namespace Database\Factories;

use App\Models\OfferAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id'          => User::factory(),
            'offer_auction_id' => OfferAuction::factory(),
            'role'             => 'buyer',
            'status'           => 'draft',
            'listing_snapshot' => null,
            'parent_offer_id'  => null,
            'submitted_at'     => null,
            'expires_at'       => null,
        ];
    }

    public function submitted()
    {
        return $this->state(fn () => [
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function accepted()
    {
        return $this->state(fn () => [
            'status'       => 'accepted',
            'submitted_at' => now(),
        ]);
    }

    public function countered()
    {
        return $this->state(fn () => [
            'status'       => 'countered',
            'submitted_at' => now(),
        ]);
    }

    public function rejected()
    {
        return $this->state(fn () => [
            'status'       => 'rejected',
            'submitted_at' => now(),
        ]);
    }

    public function withdrawn()
    {
        return $this->state(fn () => [
            'status'       => 'withdrawn',
            'submitted_at' => now(),
        ]);
    }

    public function expired()
    {
        return $this->state(fn () => [
            'status'       => 'expired',
            'submitted_at' => now(),
        ]);
    }
}
