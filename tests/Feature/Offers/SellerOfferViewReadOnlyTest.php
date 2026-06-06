<?php

namespace Tests\Feature\Offers;

use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Asserts that visiting the Seller Offer Listing view page is purely read-only:
 * it must never insert or update rows in seller_agent_auction_metas or offer_auctions.
 */
class SellerOfferViewReadOnlyTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAuction(User $user, bool $withLinked): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_draft'    => false,
            'address'     => '1 Test Lane',
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);

        if ($withLinked) {
            $offerAuction = OfferAuction::create(['user_id' => $user->id]);
            SellerAgentAuctionMeta::create([
                'seller_agent_auction_id' => $auction->id,
                'meta_key'                => 'linked_offer_auction_id',
                'meta_value'              => (string) $offerAuction->id,
            ]);
        }

        return $auction;
    }

    /**
     * Scenario (a): a listing that already has linked_offer_auction_id pre-seeded.
     * Calling view() twice must not change row counts in either table.
     */
    public function test_view_does_not_write_when_linked_offer_auction_already_exists(): void
    {
        $user    = User::factory()->create();
        $auction = $this->makeAuction($user, withLinked: true);

        $metaCountBefore         = DB::table('seller_agent_auction_metas')->count();
        $offerAuctionCountBefore = DB::table('offer_auctions')->count();

        $this->actingAs($user)->get(route('offer.listing.seller.view', $auction->id))->assertStatus(200);
        $this->actingAs($user)->get(route('offer.listing.seller.view', $auction->id))->assertStatus(200);

        $this->assertSame($metaCountBefore,         DB::table('seller_agent_auction_metas')->count(),
            'seller_agent_auction_metas row count must not change after viewing a listing that already has a linked OfferAuction.');
        $this->assertSame($offerAuctionCountBefore, DB::table('offer_auctions')->count(),
            'offer_auctions row count must not change after viewing a listing that already has a linked OfferAuction.');
    }

    /**
     * Scenario (b): a listing with NO linked_offer_auction_id.
     * view() must return HTTP 200 and must not insert any rows.
     */
    public function test_view_does_not_write_when_no_linked_offer_auction_exists(): void
    {
        $user    = User::factory()->create();
        $auction = $this->makeAuction($user, withLinked: false);

        $metaCountBefore         = DB::table('seller_agent_auction_metas')->count();
        $offerAuctionCountBefore = DB::table('offer_auctions')->count();

        $this->actingAs($user)->get(route('offer.listing.seller.view', $auction->id))->assertStatus(200);

        $this->assertSame($metaCountBefore,         DB::table('seller_agent_auction_metas')->count(),
            'seller_agent_auction_metas row count must not change when no linked OfferAuction is present.');
        $this->assertSame($offerAuctionCountBefore, DB::table('offer_auctions')->count(),
            'offer_auctions row count must not change when no linked OfferAuction is present.');
    }
}
