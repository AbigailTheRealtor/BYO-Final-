<?php

namespace Tests\Feature\Offers;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BuyerTenantCriteriaResponseOfferTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['user_type' => 'seller']);
        $this->app['config']->set(
            'offer.playoff_access.allowed_user_ids',
            [$this->user->id]
        );
    }

    // ── Buyer criteria — first POST creates bridge + Offer, redirects ─────────

    public function test_buyer_criteria_post_creates_offer_and_redirects_to_offers_show(): void
    {
        $buyerAuction = BuyerAgentAuction::create([
            'user_id'     => $this->user->id,
            'title'       => 'Test Buyer Criteria',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        BuyerAgentAuctionMeta::create([
            'buyer_agent_auction_id' => $buyerAuction->id,
            'meta_key'               => 'workflow_type',
            'meta_value'             => 'offer_listing',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('offers.store'), [
                'offer_auction_id' => $buyerAuction->id,
                'role'             => 'buyer',
                'listing_type'     => 'buyer_criteria',
            ]);

        $bridgeListingId = "buyer_criteria:{$buyerAuction->id}";
        $bridge = OfferAuction::where('listing_id', $bridgeListingId)->first();
        $this->assertNotNull($bridge, "A bridge OfferAuction row with listing_id='{$bridgeListingId}' must be created.");

        $offer = Offer::where('offer_auction_id', $bridge->id)
            ->where('role', 'buyer')
            ->where('user_id', $this->user->id)
            ->latest()
            ->first();
        $this->assertNotNull($offer, 'An Offer draft with role=buyer must be created.');

        $response->assertRedirect(route('offers.show', $offer));
    }

    // ── Buyer criteria — second POST reuses the same bridge row ──────────────

    public function test_buyer_criteria_repeated_post_does_not_duplicate_bridge_row(): void
    {
        $buyerAuction = BuyerAgentAuction::create([
            'user_id'     => $this->user->id,
            'title'       => 'Test Buyer Criteria Dedup',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        BuyerAgentAuctionMeta::create([
            'buyer_agent_auction_id' => $buyerAuction->id,
            'meta_key'               => 'workflow_type',
            'meta_value'             => 'offer_listing',
        ]);

        $payload = [
            'offer_auction_id' => $buyerAuction->id,
            'role'             => 'buyer',
            'listing_type'     => 'buyer_criteria',
        ];

        $this->actingAs($this->user)->post(route('offers.store'), $payload);
        $this->actingAs($this->user)->post(route('offers.store'), $payload);

        $bridgeListingId = "buyer_criteria:{$buyerAuction->id}";
        $bridgeCount = OfferAuction::where('listing_id', $bridgeListingId)->count();
        $this->assertSame(1, $bridgeCount,
            "Exactly one OfferAuction bridge row must exist for listing_id='{$bridgeListingId}' even after two POSTs.");
    }

    // ── Tenant criteria — first POST creates bridge + Offer, redirects ────────

    public function test_tenant_criteria_post_creates_offer_and_redirects_to_offers_show(): void
    {
        $tenantAuction = TenantAgentAuction::forceCreate([
            'user_id'     => $this->user->id,
            'title'       => 'Test Tenant Criteria',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        TenantAgentAuctionMeta::create([
            'tenant_agent_auction_id' => $tenantAuction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('offers.store'), [
                'offer_auction_id' => $tenantAuction->id,
                'role'             => 'tenant',
                'listing_type'     => 'tenant_criteria',
            ]);

        $bridgeListingId = "tenant_criteria:{$tenantAuction->id}";
        $bridge = OfferAuction::where('listing_id', $bridgeListingId)->first();
        $this->assertNotNull($bridge, "A bridge OfferAuction row with listing_id='{$bridgeListingId}' must be created.");

        $offer = Offer::where('offer_auction_id', $bridge->id)
            ->where('role', 'tenant')
            ->where('user_id', $this->user->id)
            ->latest()
            ->first();
        $this->assertNotNull($offer, 'An Offer draft with role=tenant must be created.');

        $response->assertRedirect(route('offers.show', $offer));
    }

    // ── Tenant criteria — second POST reuses the same bridge row ─────────────

    public function test_tenant_criteria_repeated_post_does_not_duplicate_bridge_row(): void
    {
        $tenantAuction = TenantAgentAuction::forceCreate([
            'user_id'     => $this->user->id,
            'title'       => 'Test Tenant Criteria Dedup',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        TenantAgentAuctionMeta::create([
            'tenant_agent_auction_id' => $tenantAuction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);

        $payload = [
            'offer_auction_id' => $tenantAuction->id,
            'role'             => 'tenant',
            'listing_type'     => 'tenant_criteria',
        ];

        $this->actingAs($this->user)->post(route('offers.store'), $payload);
        $this->actingAs($this->user)->post(route('offers.store'), $payload);

        $bridgeListingId = "tenant_criteria:{$tenantAuction->id}";
        $bridgeCount = OfferAuction::where('listing_id', $bridgeListingId)->count();
        $this->assertSame(1, $bridgeCount,
            "Exactly one OfferAuction bridge row must exist for listing_id='{$bridgeListingId}' even after two POSTs.");
    }

    // ── Buyer listing HTML — listing_type=buyer_criteria in all four forms ──────

    public function test_buyer_listing_view_contains_listing_type_buyer_criteria_in_all_four_forms(): void
    {
        $buyerAuction = BuyerAgentAuction::create([
            'user_id'     => $this->user->id,
            'title'       => 'HTML Buyer Criteria Check',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        BuyerAgentAuctionMeta::create([
            'buyer_agent_auction_id' => $buyerAuction->id,
            'meta_key'               => 'workflow_type',
            'meta_value'             => 'offer_listing',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.buyer.view', $buyerAuction->id));

        $response->assertStatus(200);
        $body = $response->getContent();

        $count = substr_count($body, 'value="buyer_criteria"');
        $this->assertSame(4, $count,
            "The hidden input value=\"buyer_criteria\" must appear exactly 4 times (one per form); found {$count}.");
    }

    // ── Tenant listing HTML — listing_type=tenant_criteria in all four forms ────

    public function test_tenant_listing_view_contains_listing_type_tenant_criteria_in_all_four_forms(): void
    {
        $tenantAuction = TenantAgentAuction::forceCreate([
            'user_id'     => $this->user->id,
            'title'       => 'HTML Tenant Criteria Check',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        TenantAgentAuctionMeta::create([
            'tenant_agent_auction_id' => $tenantAuction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.tenant.view', $tenantAuction->id));

        $response->assertStatus(200);
        $body = $response->getContent();

        $count = substr_count($body, 'value="tenant_criteria"');
        $this->assertSame(4, $count,
            "The hidden input value=\"tenant_criteria\" must appear exactly 4 times (one per form); found {$count}.");
    }

    // ── Backward compatibility — no listing_type uses original validation path ─

    public function test_store_without_listing_type_uses_original_validation_path(): void
    {
        $offerAuction = OfferAuction::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->post(route('offers.store'), [
                'offer_auction_id' => $offerAuction->id,
                'role'             => 'buyer',
            ]);

        $offer = Offer::where('offer_auction_id', $offerAuction->id)
            ->where('role', 'buyer')
            ->where('user_id', $this->user->id)
            ->latest()
            ->first();

        $this->assertNotNull($offer, 'An Offer draft must be created via the original validation path.');
        $response->assertRedirect(route('offers.show', $offer));
    }
}
