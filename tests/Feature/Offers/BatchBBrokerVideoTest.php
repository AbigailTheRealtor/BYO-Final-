<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batch B regression guards (launch-audit remediation).
 *
 *   #1 Landlord Broker Compensation & Agency Agreement Terms render on CREATE (and EDIT)
 *      for blank / Residential / Commercial property types. Persistence for the agency props
 *      already existed in saveAllMetadata() (create + edit) — only the markup was un-wired.
 *   #5 Seller / Landlord / Tenant detail views embed a video via the canonical VideoEmbedHelper
 *      (YouTube/Vimeo) and fall back to a safe raw link for unsupported URLs.
 *   #2 Landlord create submit / draft / edit publish paths are not validation-blocked
 *      (verify-only — no code change; the audit's tab-jump hypothesis was inaccurate).
 *
 * NOTE: code verification only. Per Owner Decision #4 all items stay
 * "CODE COMPLETE — HUMAN BROWSER QA REQUIRED" until human browser QA runs (env has no browser).
 */
class BatchBBrokerVideoTest extends TestCase
{
    use DatabaseTransactions;

    private function agent(): User
    {
        return User::factory()->create(['user_type' => 'agent']);
    }

    /** All required publish fields so LandlordPublishValidation passes on store()/update(). */
    private function setLandlordPublishFields($component): void
    {
        $component->set('listing_title', 'Batch B Landlord Listing')
            ->set('property_type', 'Residential Property')
            ->set('first_name', 'Test')
            ->set('last_name', 'Agent')
            ->set('phone_number', '5551234567')
            ->set('email', 'agent@example.com')
            ->set('desired_lease_length', ['12 Months'])
            ->set('auction_type', 'Traditional');
    }

    private function makeLandlordAuction(User $user, bool $isDraft = false): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => $user->id,
            'title'       => 'Batch B Landlord Listing',
            'is_draft'    => $isDraft,
            'is_approved' => !$isDraft,
            'is_sold'     => false,
        ]);

        LandlordAgentAuctionMeta::insert([
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'workflow_type',       'meta_value' => 'offer_listing'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'first_name',          'meta_value' => 'Test'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'last_name',           'meta_value' => 'Agent'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'phone_number',        'meta_value' => '5551234567'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'email',               'meta_value' => 'agent@example.com'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'property_type',        'meta_value' => 'Residential Property'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'desired_lease_length', 'meta_value' => json_encode(['12 Months'])],
        ]);

        $offerAuction = OfferAuction::create(['user_id' => $user->id]);
        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'linked_offer_auction_id',
            'meta_value'                => (string) $offerAuction->id,
        ]);

        return $auction;
    }

    // ─── #1 · Broker Compensation & Agency Agreement Terms render ──────────────

    public function test_landlord_create_shows_broker_comp_and_agency_terms_when_type_blank(): void
    {
        // Default property_type is '' (blank) on create.
        Livewire::actingAs($this->agent())
            ->test(LandlordOfferListing::class)
            ->assertSee("Broker Commission Structure")   // broker comp (now ungated)
            ->assertSee('Landlord Agency Agreement Timeframe');      // agency term (ungated)
    }

    public function test_landlord_create_residential_shows_gated_agency_terms(): void
    {
        Livewire::actingAs($this->agent())
            ->test(LandlordOfferListing::class)
            ->set('property_type', 'Residential Property')
            ->assertSee('Protection Period Timeframe')               // Residential-gated agency term
            ->assertSee('Payment Timing for Broker Fees')           // newly-wired, Residential-gated
            // literal-template apostrophes render raw in the DOM → assert unescaped (escape=false)
            ->assertSee("Tenant's Broker Commission Structure", false)   // newly-wired (Residential branch)
            ->assertSee("Landlord's Broker Commission Structure", false);
    }

    public function test_landlord_create_commercial_shows_broker_comp_and_ungated_agency_terms(): void
    {
        Livewire::actingAs($this->agent())
            ->test(LandlordOfferListing::class)
            ->set('property_type', 'Commercial Property')
            // literal-template apostrophes render raw in the DOM → assert unescaped (escape=false)
            ->assertSee("Landlord's Broker Commission Structure", false)  // must show for Commercial
            ->assertSee("Tenant's Broker Commission Structure", false)    // tenant_broker has a Commercial branch
            ->assertSee('Landlord Agency Agreement Timeframe')       // ungated → shows for Commercial
            ->assertDontSee('Protection Period Timeframe')           // Residential-gated → hidden (by design)
            ->assertDontSee('Payment Timing for Broker Fees');       // Residential-gated → hidden (by design)
    }

    public function test_landlord_edit_shows_broker_comp_and_agency_terms(): void
    {
        $user    = $this->agent();
        $auction = $this->makeLandlordAuction($user);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->assertSee("Broker Commission Structure")
            ->assertSee('Landlord Agency Agreement Timeframe');
    }

    /** The now-visible agency field must actually submit + persist through store(). */
    public function test_landlord_create_persists_wired_agency_timeframe(): void
    {
        $user      = $this->agent();
        $component = Livewire::actingAs($user)->test(LandlordOfferListing::class);
        $this->setLandlordPublishFields($component);
        $component->set('agency_agreement_timeframe', '6 Months')
            ->call('store')
            ->assertHasNoErrors();

        $auction = LandlordAgentAuction::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($auction, 'store() should create a Landlord listing.');
        $saved = LandlordAgentAuctionMeta::where('landlord_agent_auction_id', $auction->id)
            ->where('meta_key', 'agency_agreement_timeframe')->value('meta_value');
        $this->assertSame('6 Months', $saved, 'Wired agency_agreement_timeframe must persist via saveAllMetadata().');
    }

    /** The two newly-wired partials (payment_timing, tenant_broker_commission) must also persist. */
    public function test_landlord_create_persists_newly_wired_broker_terms(): void
    {
        $user      = $this->agent();
        $component = Livewire::actingAs($user)->test(LandlordOfferListing::class);
        $this->setLandlordPublishFields($component);
        $component->set('broker_fee_timing', 'Deducted from Rent Collected')
            ->set('tenant_broker_commission_structure', "Landlord to Pay Tenant's Broker Separately")
            ->call('store')
            ->assertHasNoErrors();

        $auction = LandlordAgentAuction::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($auction, 'store() should create a Landlord listing.');

        $timing = LandlordAgentAuctionMeta::where('landlord_agent_auction_id', $auction->id)
            ->where('meta_key', 'broker_fee_timing')->value('meta_value');
        $this->assertSame('Deducted from Rent Collected', $timing, 'payment_timing prop must persist.');

        $tenantBroker = LandlordAgentAuctionMeta::where('landlord_agent_auction_id', $auction->id)
            ->where('meta_key', 'tenant_broker_commission_structure')->value('meta_value');
        $this->assertSame("Landlord to Pay Tenant's Broker Separately", $tenantBroker,
            'tenant_broker_commission prop must persist.');
    }

    // ─── #2 · Landlord submit / draft / edit are not validation-blocked ────────

    public function test_landlord_create_valid_submit_redirects(): void
    {
        $component = Livewire::actingAs($this->agent())->test(LandlordOfferListing::class);
        $this->setLandlordPublishFields($component);
        $component->call('store')->assertHasNoErrors()->assertRedirect();
    }

    public function test_landlord_create_draft_saves_without_publish_validation(): void
    {
        // Draft is lenient — a nearly-empty draft must not raise publish validation errors.
        Livewire::actingAs($this->agent())
            ->test(LandlordOfferListing::class)
            ->set('listing_title', 'Draft only')
            ->call('saveDraft')
            ->assertHasNoErrors();
    }

    public function test_landlord_edit_publish_redirects(): void
    {
        $user    = $this->agent();
        $auction = $this->makeLandlordAuction($user);

        Livewire::actingAs($user)
            ->test(LandlordOfferListingEdit::class, ['auctionId' => $auction->id])
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect();
    }

    // ─── #4 · Seller Business submit is not validation-blocked server-side ─────

    /**
     * #4 (Batch B, Owner Decision #4): the "Business listing cannot submit" report is a
     * client-side issue, NOT a server-side validation block. The audit's P1-4 hypothesis was
     * that a Business-specific multi-select value missing from a SellerPublishValidation `in:`
     * whitelist silently fails store(). This test disproves that: it fills the Business-only
     * multi-selects (licenses, sale_includes, building_features) with valid whitelist values
     * and asserts store() publishes with NO validation errors. Per owner decision, no
     * production code (SellerPublishValidation) is changed — this is verification/evidence only.
     */
    public function test_seller_business_submit_passes_with_business_multiselects(): void
    {
        Livewire::actingAs($this->agent())
            ->test(SellerOfferListing::class)
            ->set('listing_title', 'Business Opportunity Listing')
            ->set('property_type', 'Business')
            ->set('first_name', 'Alice')
            ->set('last_name', 'Agent')
            ->set('phone_number', '5551234567')
            ->set('email', 'alice@example.com')
            ->set('auction_type', 'Traditional')
            ->set('licenses', ['Beer/Wine', 'Liquor'])
            ->set('sale_includes', ['Business', 'Equipment/Fixtures', 'Goodwill'])
            ->set('building_features', ['Reception', 'Waiting Room', 'Elevator'])
            ->call('store')
            ->assertHasNoErrors()
            ->assertRedirect();
    }

    // ─── #5 · Detail views embed via VideoEmbedHelper with safe fallback ───────

    private function makeSellerView(User $user, string $key, string $url): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'address' => '1 Test Lane',
        ]);
        $offer = OfferAuction::create(['user_id' => $user->id]);
        SellerAgentAuctionMeta::insert([
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'workflow_type',          'meta_value' => 'offer_listing'],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => 'linked_offer_auction_id','meta_value' => (string) $offer->id],
            ['seller_agent_auction_id' => $auction->id, 'meta_key' => $key,                      'meta_value' => $url],
        ]);
        return $auction;
    }

    public function test_seller_view_embeds_youtube_and_falls_back_for_unsupported(): void
    {
        $user = User::factory()->create();

        $yt = $this->makeSellerView($user, 'video_tour_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->actingAs($user)->get(route('offer.listing.seller.view', $yt->id))
            ->assertStatus(200)
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ');

        $other = $this->makeSellerView($user, 'video_tour_url', 'https://example.com/my-tour-123');
        $this->actingAs($user)->get(route('offer.listing.seller.view', $other->id))
            ->assertStatus(200)
            ->assertSee('https://example.com/my-tour-123')       // safe raw-link fallback
            ->assertDontSee('youtube.com/embed')
            ->assertDontSee('player.vimeo.com/video');
    }

    public function test_landlord_view_embeds_youtube_video(): void
    {
        $user    = User::factory()->create();
        $auction = LandlordAgentAuction::create([
            'user_id' => $user->id, 'is_approved' => true, 'is_draft' => false,
        ]);
        LandlordAgentAuctionMeta::insert([
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'workflow_type',  'meta_value' => 'offer_listing'],
            ['landlord_agent_auction_id' => $auction->id, 'meta_key' => 'video_tour_url', 'meta_value' => 'https://youtu.be/dQw4w9WgXcQ'],
        ]);

        $this->actingAs($user)->get(route('offer.listing.landlord.view', $auction->id))
            ->assertStatus(200)
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ');
    }

    public function test_tenant_view_embeds_youtube_video_link(): void
    {
        $user    = User::factory()->create();
        $auction = TenantAgentAuction::forceCreate([
            'user_id' => $user->id, 'is_approved' => true, 'is_draft' => false,
        ]);
        TenantAgentAuctionMeta::insert([
            ['tenant_agent_auction_id' => $auction->id, 'meta_key' => 'workflow_type', 'meta_value' => 'offer_listing'],
            ['tenant_agent_auction_id' => $auction->id, 'meta_key' => 'video_link',    'meta_value' => 'https://vimeo.com/123456789'],
        ]);

        $this->actingAs($user)->get(route('offer.listing.tenant.view', $auction->id))
            ->assertStatus(200)
            ->assertSee('https://player.vimeo.com/video/123456789');
    }
}
