<?php

namespace Tests\Feature\SmartTags\Concerns;

use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;

/**
 * Native listings shaped the way the Offer Listing wizards store them.
 */
trait MakesSmartTagListings
{
    protected function makeOwner(string $type = 'seller'): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    /**
     * @param array<string, mixed> $meta arrays are JSON-encoded, as saveMeta() does
     */
    protected function sellerListing(User $owner, array $meta = [], string $workflow = 'offer_listing', array $columns = []): SellerAgentAuction
    {
        $listing = SellerAgentAuction::create(array_merge([
            'user_id'     => $owner->id,
            'address'     => '1 Smart Tag Way',
            'is_draft'    => false,
            'is_approved' => true,
        ], $columns));

        $listing->saveMeta('workflow_type', $workflow);
        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    /**
     * @param array<string, mixed> $meta
     */
    protected function landlordListing(User $owner, array $meta = [], string $workflow = 'offer_listing', array $columns = []): LandlordAgentAuction
    {
        $listing = LandlordAgentAuction::create(array_merge([
            'user_id'     => $owner->id,
            'title'       => 'Smart Tag Rental',
            'is_draft'    => false,
            'is_approved' => true,
        ], $columns));

        $listing->saveMeta('workflow_type', $workflow);
        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }
}
