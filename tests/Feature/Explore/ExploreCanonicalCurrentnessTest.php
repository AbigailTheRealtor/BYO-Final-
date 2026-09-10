<?php

namespace Tests\Feature\Explore;

use App\Models\SellerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\User;
use App\Services\Bridge\BridgeApiService;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Services\ListingImport\Sync\MlsListingSyncService;
use App\Support\Listing\ListingPriceDisplay;
use App\Support\Listing\ListingStatusDisplay;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\Feature\Explore\Support\FakeBridgeApi;
use Tests\TestCase;

/**
 * Explore is current. Is the page a consumer LANDS ON current too?
 *
 * THE TWO STORES, AND WHY THIS TEST EXISTS
 * ----------------------------------------
 * Explore's discovery makes `bridge_properties` current. The canonical Seller
 * and Landlord pages do not read `bridge_properties` — they read EAV meta on
 * the listing (`mls_standard_status` via `ListingStatusDisplay`,
 * `mls_list_price` via `ListingPriceDisplay`), and the only thing that writes
 * those is `MlsListingSyncService`.
 *
 * So the two surfaces have two independent freshness paths onto two different
 * stores, and Explore's pass does not shorten the canonical page's. With the
 * MLS sync flags off, a consumer can therefore see Pending / $525,000 on the
 * map and Active / $535,000 on the listing they click through to.
 *
 * These tests PIN THAT, rather than describing it — and then pin that the
 * existing sync closes it. Encoding the gap is the point: an assertion is the
 * only form of this finding that cannot quietly stop being true.
 *
 * NOTHING HERE IS A FIX. No replacement mechanism is built, Explore writes no
 * listing meta, and the remedy is entirely the existing sync being switched on.
 */
class ExploreCanonicalCurrentnessTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    private FakeBridgeApi $provider;

    /** The state Explore's discovery leaves behind: the source has moved on. */
    private const CURRENT_STATUS = 'Pending';
    private const CURRENT_SALE_PRICE = 525000;
    private const CURRENT_RENT = 2650;

    /** The state stored on the listing at import time, months ago. */
    private const STALE_STATUS = 'Active';
    private const STALE_SALE_PRICE = 535000;
    private const STALE_RENT = 2750;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'explore.enabled'                => true,
            'explore.discovery.enabled'      => true,
            'explore.google.browser_key'     => null,
            'mls_media.enabled'              => true,
            'mls_media.license_acknowledged' => true,
            // The shipped posture: every MLS sync gate closed.
            'mls_sync.enabled'               => false,
            'mls_sync.schedule.enabled'      => false,
            'mls_sync.lazy_refresh_enabled'  => false,
        ]);

        $this->provider = new FakeBridgeApi();
        $this->app->instance(BridgeApiService::class, $this->provider);
    }

    /* ── A / B — SALE ───────────────────────────────────────────────────── */

    /**
     * §10-A / §10-B. The scenario, end to end, in the shipped configuration.
     *
     * @test
     */
    public function explore_is_current_while_the_seller_page_is_not_until_sync_is_enabled(): void
    {
        [$listingKey, $auction] = $this->seedStaleSellerListing();

        // Stellar has moved on, and Explore's discovery pass sees it.
        $this->provider->records = [$this->providerRecord([
            'listing_key'     => $listingKey,
            'standard_status' => self::CURRENT_STATUS,
            'list_price'      => self::CURRENT_SALE_PRICE,
        ])];

        // ── Explore: current. ──
        //
        // 'Pending' is outside explore.public_statuses, so the marker correctly
        // disappears — Explore does not publish a property that has left the
        // open market. The PANEL is where the current values are read, and it
        // refreshes the single record before answering.
        $panel = $this->getJson('/api/explore/listings/' . $listingKey)->json();

        $this->assertSame([], array_column(
            $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())->json('listings'),
            'id'
        ), 'a Pending property is no longer eligible for the public map');

        // The panel re-decides eligibility on the refreshed record, so it 404s
        // rather than presenting a Pending listing as available.
        $this->assertArrayHasKey('error', $panel);

        // And the shared MLS store now holds the current facts, because the
        // panel's refresh went through the existing normalizer.
        $this->assertDatabaseHas('bridge_properties', [
            'listing_key'     => $listingKey,
            'standard_status' => self::CURRENT_STATUS,
            'list_price'      => self::CURRENT_SALE_PRICE,
        ]);

        // ── The canonical Seller page: STALE, and this is the finding. ──
        $auction->refresh()->load('meta');

        $this->assertSame(
            self::STALE_STATUS,
            ListingStatusDisplay::for($auction),
            'the canonical page reads listing meta, which Explore does not write'
        );

        $this->assertSame(
            (float) self::STALE_SALE_PRICE,
            ListingPriceDisplay::forSeller($auction->get->toArray())->mlsListPrice(),
            'and the same is true of the price'
        );
    }

    /**
     * The remedy is the EXISTING sync, not a new mechanism. With the master gate
     * open, one sync brings the canonical page to the same values Explore shows.
     *
     * @test
     */
    public function enabling_the_existing_sync_makes_the_seller_page_current(): void
    {
        [$listingKey, $auction] = $this->seedStaleSellerListing();

        $this->provider->records = [$this->providerRecord([
            'listing_key'     => $listingKey,
            'standard_status' => self::CURRENT_STATUS,
            'list_price'      => self::CURRENT_SALE_PRICE,
        ])];

        config(['mls_sync.enabled' => true]);

        app(MlsListingSyncService::class)->sync($auction, 'seller', force: true);

        $auction->refresh()->load('meta');

        $this->assertSame(self::CURRENT_STATUS, ListingStatusDisplay::for($auction));
        $this->assertSame(
            (float) self::CURRENT_SALE_PRICE,
            ListingPriceDisplay::forSeller($auction->get->toArray())->mlsListPrice()
        );
    }

    /* ── C / D — RENT ───────────────────────────────────────────────────── */

    /** §10-C / §10-D. The same gap, on the landlord side. @test */
    public function the_landlord_page_is_stale_until_sync_is_enabled(): void
    {
        [$listingKey, $auction] = $this->seedStaleLandlordListing();

        $this->provider->records = [$this->providerRentalRecord([
            'listing_key'     => $listingKey,
            'standard_status' => self::CURRENT_STATUS,
            'list_price'      => self::CURRENT_RENT,
        ])];

        $this->getJson('/api/explore/listings/' . $listingKey);

        $this->assertDatabaseHas('bridge_properties', [
            'listing_key' => $listingKey,
            'list_price'  => self::CURRENT_RENT,
        ]);

        $auction->refresh()->load('meta');

        $this->assertSame(self::STALE_STATUS, ListingStatusDisplay::for($auction));
        $this->assertSame(
            (float) self::STALE_RENT,
            ListingPriceDisplay::forLandlord($auction->get->toArray())->mlsListPrice()
        );
    }

    /** @test */
    public function enabling_the_existing_sync_makes_the_landlord_page_current(): void
    {
        [$listingKey, $auction] = $this->seedStaleLandlordListing();

        $this->provider->records = [$this->providerRentalRecord([
            'listing_key'     => $listingKey,
            'standard_status' => self::CURRENT_STATUS,
            'list_price'      => self::CURRENT_RENT,
        ])];

        config(['mls_sync.enabled' => true]);

        app(MlsListingSyncService::class)->sync($auction, 'landlord', force: true);

        $auction->refresh()->load('meta');

        $this->assertSame(self::CURRENT_STATUS, ListingStatusDisplay::for($auction));
        $this->assertSame(
            (float) self::CURRENT_RENT,
            ListingPriceDisplay::forLandlord($auction->get->toArray())->mlsListPrice(),
            'a lease record\'s ListPrice IS the rent, and the landlord rule re-checks that'
        );
    }

    /* ── the boundary itself ────────────────────────────────────────────── */

    /**
     * Explore must never write listing meta. The canonical page's facts are the
     * sync's to move — `MlsSyncFieldPolicy` decides which of them may, from both
     * ends — and a second writer reaching into that store from a public map
     * endpoint is exactly the parallel ingestion path that must not exist.
     *
     * @test
     */
    public function explore_never_writes_listing_meta(): void
    {
        [$listingKey, $auction] = $this->seedStaleSellerListing();

        $this->provider->records = [$this->providerRecord([
            'listing_key'     => $listingKey,
            'standard_status' => 'Active',
            'list_price'      => self::CURRENT_SALE_PRICE,
        ])];

        $before = $auction->meta()->pluck('meta_value', 'meta_key')->toArray();

        $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())->assertOk();
        $this->getJson('/api/explore/listings/' . $listingKey)->assertOk();

        $after = $auction->fresh()->meta()->pluck('meta_value', 'meta_key')->toArray();

        $this->assertSame($before, $after, 'Explore touched listing meta');
    }

    /**
     * The gates, asserted rather than described: the on-view refresh needs BOTH
     * the master switch and the lazy switch, and even then a non-owner gets no
     * outbound request — only a demand hint for the next sweep.
     *
     * @test
     */
    public function the_canonical_on_view_refresh_needs_both_sync_gates(): void
    {
        [$listingKey, $auction] = $this->seedStaleSellerListing();

        $refresher = app(\App\Services\ListingImport\Sync\MlsStaleAccessRefresher::class);

        $disabled = \App\Services\ListingImport\Sync\MlsSyncOutcome::DISABLED;

        config(['mls_sync.enabled' => false, 'mls_sync.lazy_refresh_enabled' => false]);
        $this->assertSame($disabled, $refresher->onAccess($auction, 'seller', true)->status);

        config(['mls_sync.enabled' => true, 'mls_sync.lazy_refresh_enabled' => false]);
        $this->assertSame($disabled, $refresher->onAccess($auction, 'seller', true)->status,
            'the master switch alone does not open the on-view path');

        config(['mls_sync.enabled' => false, 'mls_sync.lazy_refresh_enabled' => true]);
        $this->assertSame($disabled, $refresher->onAccess($auction, 'seller', true)->status,
            'nor does the lazy switch alone');

        // Both open, but a NON-owner still sends nothing — the view is recorded
        // as demand for the next sweep instead. So a public visitor's page is
        // current only because a SWEEP already made it current, which is why
        // MLS_SYNC_SCHEDULE_ENABLED is the flag that matters for consumers.
        config(['mls_sync.enabled' => true, 'mls_sync.lazy_refresh_enabled' => true]);
        $this->assertNotSame($disabled, $refresher->onAccess($auction, 'seller', false)->status);
        $this->assertSame(0, $this->provider->providerRequestCount(),
            'a non-owner view must not spend a provider request');
    }

    /* ── helpers ────────────────────────────────────────────────────────── */

    /** @return array{0:string,1:SellerAgentAuction} */
    private function seedStaleSellerListing(): array
    {
        $bridge = $this->makeListing([
            'standard_status' => self::STALE_STATUS,
            'list_price'      => self::STALE_SALE_PRICE,
        ]);
        $bridge->forceFill(['imported_at' => now()->subDays(30)])->save();

        $auction = SellerAgentAuction::create([
            'user_id'     => User::factory()->create()->id,
            'is_draft'    => false,
            'is_approved' => true,
            'is_archived' => false,
        ]);

        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta(Meta::META_LISTING_KEY, $bridge->listing_key);
        $auction->saveMeta(Meta::META_MLS_NUMBER, (string) $bridge->listing_id);
        $auction->saveMeta(Meta::META_STANDARD_STATUS, self::STALE_STATUS);
        $auction->saveMeta(Meta::META_LIST_PRICE, (string) self::STALE_SALE_PRICE);
        $auction->saveMeta(Meta::META_SOURCE_PTYPE, 'Residential');

        return [(string) $bridge->listing_key, $auction->fresh()->load('meta')];
    }

    /** @return array{0:string,1:LandlordAgentAuction} */
    private function seedStaleLandlordListing(): array
    {
        $bridge = $this->makeRental([
            'standard_status' => self::STALE_STATUS,
            'list_price'      => self::STALE_RENT,
        ]);
        $bridge->forceFill(['imported_at' => now()->subDays(30)])->save();

        $auction = LandlordAgentAuction::create([
            'user_id'     => User::factory()->create()->id,
            'is_draft'    => false,
            'is_approved' => true,
            'is_archived' => false,
        ]);

        $auction->saveMeta('workflow_type', 'offer_listing');
        $auction->saveMeta(Meta::META_LISTING_KEY, $bridge->listing_key);
        $auction->saveMeta(Meta::META_MLS_NUMBER, (string) $bridge->listing_id);
        $auction->saveMeta(Meta::META_STANDARD_STATUS, self::STALE_STATUS);
        $auction->saveMeta(Meta::META_LIST_PRICE, (string) self::STALE_RENT);
        $auction->saveMeta(Meta::META_SOURCE_PTYPE, 'Residential Lease');

        return [(string) $bridge->listing_key, $auction->fresh()->load('meta')];
    }
}
