<?php

namespace Database\Factories;

use App\Models\OfferAuction;
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

    /**
     * Produce an OfferAuction eligible for showings (seller role).
     * Role is stored as offer_auction_metas.user_type = 'seller'.
     */
    public function sellerListing()
    {
        return $this->afterCreating(function (OfferAuction $auction) {
            $auction->saveMeta('user_type', 'seller');
        });
    }

    /**
     * Produce an OfferAuction eligible for showings (landlord role).
     * Role is stored as offer_auction_metas.user_type = 'landlord'.
     */
    public function landlordListing()
    {
        return $this->afterCreating(function (OfferAuction $auction) {
            $auction->saveMeta('user_type', 'landlord');
        });
    }

    /**
     * Produce an OfferAuction ineligible for showings (buyer role).
     */
    public function buyerListing()
    {
        return $this->afterCreating(function (OfferAuction $auction) {
            $auction->saveMeta('user_type', 'buyer');
        });
    }

    /**
     * Produce an OfferAuction ineligible for showings (tenant role).
     */
    public function tenantListing()
    {
        return $this->afterCreating(function (OfferAuction $auction) {
            $auction->saveMeta('user_type', 'tenant');
        });
    }
}
