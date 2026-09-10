<?php

namespace Tests\Feature\Agent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\OfferAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as MlsMeta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The Agent hub's "View" link, and the record it lands on.
 *
 * THE DEFECT THIS FILE PINS
 * -------------------------
 * `AgentController::offerListings()` lists ROLE listings — rows of
 * `seller_agent_auctions`, `landlord_agent_auctions`, `buyer_agent_auctions`
 * and `tenant_agent_auctions`. `normalizeRoleOfferListing()` then built the
 * View link as
 *
 *     route('offer.listing.view', ['id' => $auction->id, 'role' => $role])
 *
 * while the destination, `AgentController::offerListingView()`, resolved
 *
 *     OfferAuction::where('id', $id)
 *
 * Those are two unrelated primary-key sequences. A seller listing whose id is
 * 7 and an OfferAuction whose id is 7 are different records in different
 * tables describing different things, and nothing in the integer says which
 * one the agent clicked. Where the ids collided the page rendered the WRONG
 * record; where they did not it 404'd on a listing that plainly exists.
 *
 * WHAT THE TESTS ASSERT
 * ---------------------
 * The identity carried in the URL names its own domain, so a role listing can
 * never be mistaken for an OfferAuction. The collision cases below are built
 * deliberately: every one of them creates BOTH records with the SAME integer
 * id and the SAME owner, which is the only arrangement in which the old code
 * silently succeeded rather than 404'ing.
 */
class AgentSharedListingIdentityTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['user_type' => 'seller']);

        config(['offer.playoff_access.allowed_user_ids' => '*']);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * An OfferAuction forced onto a chosen id, carrying its own recognisable
     * title so a page that rendered it instead of the role listing is visible
     * in the assertion rather than inferred.
     */
    private function decoyOfferAuction(int $id, string $title = 'DECOY OFFER AUCTION'): OfferAuction
    {
        $auction = OfferAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => $title,
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        // Force the collision. Both records now answer to the same integer.
        OfferAuction::where('id', $auction->id)->update(['id' => $id]);

        $fresh = OfferAuction::find($id);
        $fresh->saveMeta('listing_title', $title);
        $fresh->saveMeta('listing_status', 'DECOY STATUS');
        $fresh->saveMeta('listing_role', 'seller');

        return $fresh->fresh();
    }

    private function sellerListing(int $id, array $meta = []): SellerAgentAuction
    {
        $listing = SellerAgentAuction::create([
            'user_id'     => $this->owner->id,
            'address'     => '1 Seller Way',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        SellerAgentAuction::where('id', $listing->id)->update(['id' => $id]);

        $listing = SellerAgentAuction::find($id);

        // Marks the row as an Offer Listing rather than a Hire Agent listing,
        // which is what puts it in the Offer Listings hub at all.
        $listing->saveMeta('workflow_type', 'offer_listing');
        $listing->saveMeta('listing_title', 'SELLER SUBJECT LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function landlordListing(int $id, array $meta = []): LandlordAgentAuction
    {
        $listing = LandlordAgentAuction::create([
            'user_id'     => $this->owner->id,
            'title'       => 'LANDLORD SUBJECT LISTING',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        LandlordAgentAuction::where('id', $listing->id)->update(['id' => $id]);

        $listing = LandlordAgentAuction::find($id);
        $listing->saveMeta('listing_title', 'LANDLORD SUBJECT LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function buyerListing(int $id, array $meta = []): BuyerAgentAuction
    {
        $listing = BuyerAgentAuction::create([
            'user_id'     => $this->owner->id,
            'address'     => '3 Buyer Way',
            'title'       => 'BUYER SUBJECT LISTING',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        BuyerAgentAuction::where('id', $listing->id)->update(['id' => $id]);

        $listing = BuyerAgentAuction::find($id);
        $listing->saveMeta('listing_title', 'BUYER SUBJECT LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    private function tenantListing(int $id, array $meta = []): TenantAgentAuction
    {
        // TenantAgentAuction declares neither $fillable nor $guarded, so it is
        // built attribute by attribute rather than mass-assigned. The table has
        // no `title` column either — the hub falls back to `listing_title`.
        $listing = new TenantAgentAuction();
        $listing->user_id     = $this->owner->id;
        $listing->is_draft    = false;
        $listing->is_approved = true;
        $listing->save();

        TenantAgentAuction::where('id', $listing->id)->update(['id' => $id]);

        $listing = TenantAgentAuction::find($id);
        $listing->saveMeta('listing_title', 'TENANT SUBJECT LISTING');

        foreach ($meta as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        return $listing->fresh();
    }

    /**
     * The hub's own View link for a given role listing — read out of the real
     * hub page rather than reconstructed here, so the test exercises the link
     * an agent actually clicks.
     */
    private function hubViewLink(string $role, int $listingId): string
    {
        $response = $this->actingAs($this->owner)->get(route('agent.offer-listings'));
        $response->assertStatus(200);

        $listings = $response->original->getData()['listings'];

        $match = collect($listings)->first(
            fn ($l) => $l['role'] === $role && (int) $l['id'] === $listingId
        );

        $this->assertNotNull(
            $match,
            "the hub did not list the {$role} listing #{$listingId} it was given",
        );

        return $match['view_route'];
    }

    // ── 1. A role-listing id is not an OfferAuction id ──────────────────────

    /** @test */
    public function hub_view_link_does_not_address_a_role_listing_by_a_bare_integer(): void
    {
        $this->sellerListing(4242);

        $link = $this->hubViewLink('seller', 4242);
        $path = parse_url($link, PHP_URL_PATH);

        $this->assertNotSame(
            '/offer/listing/view/4242',
            $path,
            'the hub addressed a seller_agent_auctions primary key as if it were an offer_auctions primary key',
        );
    }

    // ── 2 & 5. The colliding OfferAuction is never rendered ────────────────

    /** @test */
    public function seller_hub_view_resolves_the_seller_listing_not_the_colliding_offer_auction(): void
    {
        $this->decoyOfferAuction(4243);
        $this->sellerListing(4243);

        $body = $this->render($this->hubViewLink('seller', 4243));

        $this->assertStringContainsString('SELLER SUBJECT LISTING', $body);
        $this->assertStringNotContainsString('DECOY OFFER AUCTION', $body);
        $this->assertStringNotContainsString('DECOY STATUS', $body);
    }

    /** @test */
    public function landlord_hub_view_resolves_the_landlord_listing_not_the_colliding_offer_auction(): void
    {
        $this->decoyOfferAuction(4244);
        $this->landlordListing(4244);

        $body = $this->render($this->hubViewLink('landlord', 4244));

        $this->assertStringContainsString('LANDLORD SUBJECT LISTING', $body);
        $this->assertStringNotContainsString('DECOY OFFER AUCTION', $body);
        $this->assertStringNotContainsString('DECOY STATUS', $body);
    }

    /** @test */
    public function buyer_hub_view_resolves_the_buyer_listing_not_the_colliding_offer_auction(): void
    {
        $this->decoyOfferAuction(4245);
        $this->buyerListing(4245);

        $body = $this->render($this->hubViewLink('buyer', 4245));

        $this->assertStringContainsString('BUYER SUBJECT LISTING', $body);
        $this->assertStringNotContainsString('DECOY OFFER AUCTION', $body);
        $this->assertStringNotContainsString('DECOY STATUS', $body);
    }

    /** @test */
    public function tenant_hub_view_resolves_the_tenant_listing_not_the_colliding_offer_auction(): void
    {
        $this->decoyOfferAuction(4246);
        $this->tenantListing(4246);

        $body = $this->render($this->hubViewLink('tenant', 4246));

        $this->assertStringContainsString('TENANT SUBJECT LISTING', $body);
        $this->assertStringNotContainsString('DECOY OFFER AUCTION', $body);
        $this->assertStringNotContainsString('DECOY STATUS', $body);
    }

    // ── 3. The listing stays identifiable with no OfferAuction at all ──────

    /** @test */
    public function a_role_listing_with_no_linked_offer_auction_still_resolves(): void
    {
        $this->sellerListing(4247);

        $this->assertSame(
            0,
            OfferAuction::count(),
            'this case is only meaningful with no OfferAuction in the database at all',
        );

        $this->assertStringContainsString(
            'SELLER SUBJECT LISTING',
            $this->render($this->hubViewLink('seller', 4247)),
        );
    }

    // ── 4 & F. A missing record fails safely ───────────────────────────────

    /** @test */
    public function an_absent_role_listing_is_a_404_not_another_record(): void
    {
        $this->decoyOfferAuction(4248);

        $this->actingAs($this->owner)
            ->get('/offer/listing/view/seller-4248')
            ->assertStatus(404);
    }

    /** @test */
    public function another_users_role_listing_is_a_404(): void
    {
        $stranger = User::factory()->create(['user_type' => 'seller']);

        $listing = SellerAgentAuction::create([
            'user_id'     => $stranger->id,
            'address'     => '9 Stranger Way',
            'is_draft'    => false,
            'is_approved' => true,
        ]);

        $this->actingAs($this->owner)
            ->get('/offer/listing/view/seller-' . $listing->id)
            ->assertStatus(404);
    }

    /** @test */
    public function an_unrecognised_identity_token_is_a_404(): void
    {
        $this->decoyOfferAuction(4249);

        foreach (['agent-4249', 'seller-0', 'seller-abc', 'seller-', '-4249', 'seller-4249-x'] as $token) {
            $this->actingAs($this->owner)
                ->get('/offer/listing/view/' . $token)
                ->assertStatus(404, "token '{$token}' must not resolve to any record");
        }
    }

    // ── The legacy integer form is untouched ───────────────────────────────

    /** @test */
    public function a_bare_integer_still_addresses_an_offer_auction(): void
    {
        $offerAuction = $this->decoyOfferAuction(4250, 'LEGACY OFFER PLAYOFF LISTING');

        $body = $this->render(route('offer.listing.view', $offerAuction->id));

        $this->assertStringContainsString('LEGACY OFFER PLAYOFF LISTING', $body);
    }

    // ── G / H. Seller and Landlord MLS effective status ────────────────────

    /** @test */
    public function seller_mls_linked_listing_shows_the_effective_pending_status(): void
    {
        $this->sellerListing(4251, [
            'listing_status'             => 'Active',
            MlsMeta::META_LISTING_KEY    => 'STELLAR-1',
            MlsMeta::META_STANDARD_STATUS => 'Pending',
        ]);

        $body = $this->render($this->hubViewLink('seller', 4251));

        $this->assertSame(
            'Pending',
            $this->listingStatusRow($body),
            'the Agent page must print the effective MLS status, not the stored one',
        );
    }

    /** @test */
    public function landlord_mls_linked_listing_shows_the_effective_pending_status(): void
    {
        $this->landlordListing(4252, [
            'listing_status'              => 'Active',
            MlsMeta::META_LISTING_KEY     => 'STELLAR-2',
            MlsMeta::META_STANDARD_STATUS => 'Pending',
        ]);

        $body = $this->render($this->hubViewLink('landlord', 4252));

        $this->assertSame('Pending', $this->listingStatusRow($body));
    }

    /** @test */
    public function a_refreshed_mls_status_is_consumed_on_the_next_render(): void
    {
        $listing = $this->sellerListing(4253, [
            'listing_status'              => 'Active',
            MlsMeta::META_LISTING_KEY     => 'STELLAR-3',
            MlsMeta::META_STANDARD_STATUS => 'Pending',
        ]);

        $link = $this->hubViewLink('seller', 4253);

        $this->assertSame('Pending', $this->listingStatusRow($this->render($link)));

        $listing->saveMeta(MlsMeta::META_STANDARD_STATUS, 'Closed');

        $this->assertSame(
            'Closed',
            $this->listingStatusRow($this->render($link)),
            'a refreshed feed status must reach the page without any other change',
        );
    }

    /** @test */
    public function a_manual_seller_listing_keeps_its_stored_status(): void
    {
        $this->sellerListing(4254, ['listing_status' => 'Active']);

        $this->assertSame('Active', $this->listingStatusRow($this->render($this->hubViewLink('seller', 4254))));
    }

    // ── J / K. Buyer and Tenant semantics are unchanged ────────────────────

    /** @test */
    public function buyer_status_semantics_are_unchanged_by_mls_meta(): void
    {
        $this->buyerListing(4255, [
            'listing_status'              => 'Active',
            MlsMeta::META_LISTING_KEY     => 'STELLAR-4',
            MlsMeta::META_STANDARD_STATUS => 'Pending',
        ]);

        $this->assertSame(
            'Active',
            $this->listingStatusRow($this->render($this->hubViewLink('buyer', 4255))),
            'Buyer keeps the stored value: no MLS behaviour is added to this role',
        );
    }

    /** @test */
    public function tenant_status_semantics_are_unchanged_by_mls_meta(): void
    {
        $this->tenantListing(4256, [
            'listing_status'              => 'Active',
            MlsMeta::META_LISTING_KEY     => 'STELLAR-5',
            MlsMeta::META_STANDARD_STATUS => 'Pending',
        ]);

        $this->assertSame(
            'Active',
            $this->listingStatusRow($this->render($this->hubViewLink('tenant', 4256))),
        );
    }

    // ── L. Presentation writes nothing ─────────────────────────────────────

    /** @test */
    public function rendering_the_page_mutates_no_stored_value(): void
    {
        $listing = $this->sellerListing(4257, [
            'listing_status'              => 'Active',
            MlsMeta::META_LISTING_KEY     => 'STELLAR-6',
            MlsMeta::META_STANDARD_STATUS => 'Pending',
        ]);

        $before   = $listing->fresh()->meta->pluck('meta_value', 'meta_key')->sort()->toArray();
        $oaBefore = OfferAuction::count();

        $this->render($this->hubViewLink('seller', 4257));

        $this->assertSame(
            $before,
            $listing->fresh()->meta->pluck('meta_value', 'meta_key')->sort()->toArray(),
            'the shared Agent page must not write to the listing it renders',
        );

        $this->assertSame(
            $oaBefore,
            OfferAuction::count(),
            'the page must not create an OfferAuction to make an id line up',
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function render(string $url): string
    {
        $response = $this->actingAs($this->owner)->get($url);
        $response->assertStatus(200);

        return $response->getContent();
    }

    /**
     * The rendered value of the "Listing Status" row, or null when the row was
     * not rendered at all — an absent value has never produced a row and must
     * not start now.
     */
    private function listingStatusRow(string $html): ?string
    {
        if (! preg_match('/Listing Status<\/div>\s*<div[^>]*>(.*?)<\/div>/s', $html, $m)) {
            return null;
        }

        return trim(strip_tags($m[1]));
    }
}
