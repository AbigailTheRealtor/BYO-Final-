<?php

namespace Database\Factories;

use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantAgentAuctionBidFactory extends Factory
{
    protected $model = TenantAgentAuctionBid::class;

    public function definition(): array
    {
        return [
            'user_id'                  => User::factory(),
            'tenant_agent_auction_id'  => TenantAgentAuction::factory(),
            'accepted'                 => null,
            'accepted_date'            => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state([
            'accepted'      => 'accepted',
            'accepted_date' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'accepted'      => 'rejected',
            'accepted_date' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'accepted'      => null,
            'accepted_date' => null,
        ]);
    }
}
