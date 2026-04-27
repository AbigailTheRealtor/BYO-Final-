<?php

namespace Database\Factories;

use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\TenantCounterBidding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantCounterBiddingFactory extends Factory
{
    protected $model = TenantCounterBidding::class;

    public function definition(): array
    {
        return [
            'user_id'                      => User::factory(),
            'tenant_agent_auction_id'      => TenantAgentAuction::factory(),
            'tenant_agent_auction_bid_id'  => TenantAgentAuctionBid::factory(),
            'property_type'                => 'residential',
            'parent_counter_id'            => null,
            'accepted'                     => '0',
            'accepted_date'                => null,
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
}
