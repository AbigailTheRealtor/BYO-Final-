<?php

namespace Database\Factories;

use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantAgentAuctionFactory extends Factory
{
    protected $model = TenantAgentAuction::class;

    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'auction_type'  => 'Open Auction',
            'is_approved'   => true,
            'is_draft'      => false,
            'is_sold'       => false,
            'auction_ended' => false,
        ];
    }

    public function active(): static
    {
        return $this->state([
            'is_approved'   => true,
            'is_draft'      => false,
            'is_sold'       => false,
            'auction_ended' => false,
        ]);
    }

    public function sold(): static
    {
        return $this->state([
            'is_sold'   => true,
            'sold_date' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'auction_ended' => true,
        ]);
    }
}
