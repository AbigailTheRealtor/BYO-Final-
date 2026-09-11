<?php

namespace Tests\Feature\Product;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Shared pages that render rows from both products filter their own content.
 *
 * My Listings and My Bids are SHARED routes: a BidYourAgent deployment serves them,
 * so the product gate passes them and cannot see what they list. Both were written
 * for the combined platform, and both were found in browser QA putting BidYourOffer
 * records in front of a BidYourAgent user:
 *
 *   My Listings  Landlord, Buyer and Tenant Offer Listings live in the same tables
 *                as the Hire Agent listings (workflow_type = offer_listing). Only the
 *                seller's were split out, so the other three roles' Offer Listings sat
 *                inside their Hire Agent buckets as if they were Hire Agent listings.
 *   My Bids      Opened, with no type, on bids placed on legacy property auctions —
 *                the target of the agent dashboard's own "My Bids" button — beside
 *                four more BidYourOffer tabs.
 */
class ProductSharedPageContentTest extends TestCase
{
    use DatabaseTransactions;

    private const BIDYOUROFFER_BID_TYPES = [
        'seller-property',
        'landlord-property',
        'buyer-criteria',
        'tenant-criteria',
        'agent-service',
    ];

    private function bidYourAgent(): void
    {
        config(['products.active' => 'bidyouragent', 'products.hosts' => []]);
    }

    private function combined(): void
    {
        config(['products.active' => null, 'products.hosts' => []]);
    }

    /**
     * One listing in a Hire Agent table. `$workflow` null is a legacy row carrying no
     * workflow evidence at all; `offer_listing` is stamped both ways, as the wizards do.
     */
    private function listing(string $role, User $owner, string $title, ?string $workflow): Model
    {
        $attributes = [
            'user_id' => $owner->id, 'title' => $title, 'workflow_type' => $workflow,
            'is_approved' => 1, 'is_draft' => 0, 'is_sold' => 0,
        ];

        switch ($role) {
            case 'landlord':
                $row = LandlordAgentAuction::forceCreate($attributes);
                break;
            case 'buyer':
                $row = BuyerAgentAuction::forceCreate($attributes + ['address' => $title]);
                break;
            default:
                $row = TenantAgentAuction::factory()->active()->create($attributes);
        }

        if ($workflow !== null) {
            Model::unguarded(fn () => $row->meta()->create(['meta_key' => 'workflow_type', 'meta_value' => $workflow]));
        }

        return $row;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function rolesSharingATableWithOfferListings(): array
    {
        return ['landlord' => ['landlord'], 'buyer' => ['buyer'], 'tenant' => ['tenant']];
    }

    /**
     * @test
     * @dataProvider rolesSharingATableWithOfferListings
     */
    public function my_listings_in_bidyouragent_mode_shows_hire_agent_listings_and_no_offer_listings(string $role): void
    {
        $this->bidYourAgent();

        $owner = User::factory()->create(['user_type' => $role]);
        $this->listing($role, $owner, 'HIREROW Hire Agent listing', 'hire_agent');
        $this->listing($role, $owner, 'LEGACYROW Unstamped listing', null);
        $this->listing($role, $owner, 'OFFERROW Offer Listing', 'offer_listing');

        $response = $this->actingAs($owner)->get('/my-listings');

        $response->assertOk();
        $response->assertSee('HIREROW Hire Agent listing');
        // Only a POSITIVE identification hides a row — the route gate's rule. A row
        // with no workflow evidence is not guessed into BidYourOffer and dropped.
        $response->assertSee('LEGACYROW Unstamped listing');
        $response->assertDontSee('OFFERROW Offer Listing');
    }

    /**
     * @test
     * @dataProvider rolesSharingATableWithOfferListings
     */
    public function my_listings_on_the_combined_platform_is_unchanged(string $role): void
    {
        $this->combined();

        $owner = User::factory()->create(['user_type' => $role]);
        $this->listing($role, $owner, 'HIREROW Hire Agent listing', 'hire_agent');
        $this->listing($role, $owner, 'OFFERROW Offer Listing', 'offer_listing');

        $this->actingAs($owner)->get('/my-listings')
            ->assertOk()
            ->assertSee('HIREROW Hire Agent listing')
            ->assertSee('OFFERROW Offer Listing');
    }

    /** @test */
    public function my_bids_in_bidyouragent_mode_opens_on_hire_agent_bids_with_no_bidyouroffer_tabs(): void
    {
        $this->bidYourAgent();

        $agent    = User::factory()->create(['user_type' => 'agent']);
        $response = $this->actingAs($agent)->get('/my-bids');

        $response->assertOk();
        $response->assertViewIs('my-bids.seller-agent');

        foreach (self::BIDYOUROFFER_BID_TYPES as $type) {
            $response->assertDontSee(route('myBids', $type), false);
        }

        $response->assertSee(route('myBids', 'buyer-agent'), false);
        $response->assertSee(route('myBids', 'tenant-agent'), false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function bidYourOfferBidTypes(): array
    {
        return array_combine(self::BIDYOUROFFER_BID_TYPES, array_map(fn ($t) => [$t], self::BIDYOUROFFER_BID_TYPES));
    }

    /**
     * @test
     * @dataProvider bidYourOfferBidTypes
     */
    public function a_bidyouroffer_bid_list_is_refused_in_bidyouragent_mode(string $type): void
    {
        $this->bidYourAgent();

        $agent = User::factory()->create(['user_type' => 'agent']);

        $this->actingAs($agent)->get('/my-bids/' . $type)->assertNotFound();
    }

    /** @test */
    public function hire_agent_bid_lists_are_still_served_in_bidyouragent_mode(): void
    {
        $this->bidYourAgent();

        $agent = User::factory()->create(['user_type' => 'agent']);

        foreach (['seller-agent', 'buyer-agent', 'landlord-agent', 'tenant-agent'] as $type) {
            $this->actingAs($agent)->get('/my-bids/' . $type)->assertOk();
        }
    }

    /** @test */
    public function my_bids_on_the_combined_platform_is_unchanged(): void
    {
        $this->combined();

        $agent    = User::factory()->create(['user_type' => 'agent']);
        $response = $this->actingAs($agent)->get('/my-bids');

        $response->assertOk();
        $response->assertViewIs('my-bids.seller_property');
        $response->assertSee(route('myBids', 'seller-property'), false);

        $this->actingAs($agent)->get('/my-bids/buyer-criteria')->assertOk();
    }
}
