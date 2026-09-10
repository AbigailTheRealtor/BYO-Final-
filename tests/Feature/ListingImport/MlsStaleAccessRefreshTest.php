<?php

namespace Tests\Feature\ListingImport;

use App\Models\BridgeProperty;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Services\ListingImport\QuickImport\MlsQuickImportService;
use App\Services\ListingImport\Sync\MlsStaleAccessRefresher;
use App\Services\ListingImport\Sync\MlsSyncDemandQueue;
use App\Services\ListingImport\Sync\MlsSyncOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * STALE-ON-ACCESS, AND THE REQUEST STORM THAT MUST NOT HAPPEN.
 *
 * Opening a listing page is the one event whose frequency this application does
 * not control. If a page render could become a Bridge request, then a crawler, a
 * link on social media, or one popular property turns a single listing into
 * thousands of outbound calls — against a provider whose rate limit is nowhere
 * documented in this repository.
 *
 * The design's answer is not a counter but a division: the OWNER's own view of
 * their own stale listing refreshes synchronously; every other viewer causes no
 * outbound request at all and merely leaves a hint for the scheduled sweep.
 *
 * These tests exist to prove that division holds, in both directions.
 */
class MlsStaleAccessRefreshTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'ACCESS-KEY';
    private const MLS = 'ACCESS-MLS';

    /** @var \Closure(): \GuzzleHttp\Promise\PromiseInterface */
    private $sourceResponder;

    private int $sourceRequests = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceResponder = fn () => Http::response(['value' => []], 200);

        Http::fake(function () {
            $this->sourceRequests++;

            return ($this->sourceResponder)();
        });

        config([
            'bridge.dataset'                         => 'test-dataset',
            'bridge.token'                           => 'test-token',
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'mls_media.enabled'                      => true,
            'mls_media.license_acknowledged'         => true,
            'mls_media.roles'                        => ['seller', 'landlord'],
            'mls_sync.enabled'                       => true,
            'mls_sync.lazy_refresh_enabled'          => true,
            'mls_sync.roles'                         => ['seller', 'landlord'],
            'mls_sync.freshness_minutes'             => 60,
            'mls_sync.terminal_freshness_minutes'    => 1440,
            'mls_sync.retry_after_minutes'           => 30,
        ]);

        app(MlsSyncDemandQueue::class)->flush();
    }

    // =====================================================================
    // Harness
    // =====================================================================

    private function raw(array $overrides = []): array
    {
        return array_merge([
            'ListingKey'                     => self::KEY,
            'ListingId'                      => self::MLS,
            'StandardStatus'                 => 'Active',
            'MlsStatus'                      => 'Active',
            'PropertyType'                   => 'Residential',
            'UnparsedAddress'                => '6817 Stones Throw Circle N',
            'City'                           => 'ST PETERSBURG',
            'StateOrProvince'                => 'FL',
            'PostalCode'                     => '33710',
            'ListPrice'                      => 184900,
            'BedroomsTotal'                  => 2,
            'ModificationTimestamp'          => '2026-09-01T10:00:00.000Z',
            'IDXParticipationYN'             => true,
            'InternetEntireListingDisplayYN' => true,
            'InternetAddressDisplayYN'       => true,
            'Media'                          => [],
        ], $overrides);
    }

    private function importedListing(): SellerAgentAuction
    {
        $raw = $this->raw();

        BridgeProperty::where('listing_key', $raw['ListingKey'])->delete();
        BridgeProperty::create([
            'listing_key'            => $raw['ListingKey'],
            'listing_id'             => $raw['ListingId'],
            'standard_status'        => $raw['StandardStatus'],
            'mls_status'             => $raw['MlsStatus'],
            'property_type'          => $raw['PropertyType'],
            'unparsed_address'       => $raw['UnparsedAddress'],
            'city'                   => $raw['City'],
            'state_or_province'      => $raw['StateOrProvince'],
            'postal_code'            => $raw['PostalCode'],
            'list_price'             => $raw['ListPrice'],
            'bedrooms_total'         => $raw['BedroomsTotal'],
            'modification_timestamp' => $raw['ModificationTimestamp'],
            'raw_json'               => json_encode($raw),
            'imported_at'            => now(),
        ]);

        $result = app(MlsQuickImportService::class)->lookup(self::MLS, 'seller');
        $this->assertTrue($result->isFound());

        return app(Meta::class)->materialise('seller', User::factory()->create()->id, $result);
    }

    /** Mark the listing as last synced long enough ago to be stale. */
    private function makeStale(object $listing): void
    {
        $listing->saveMeta(Meta::META_SYNCED_AT, now()->subHours(6)->toIso8601String());
        $listing->saveMeta(Meta::META_SYNC_ERROR, null);
    }

    private function makeFresh(object $listing): void
    {
        $listing->saveMeta(Meta::META_SYNCED_AT, now()->subMinutes(5)->toIso8601String());
        $listing->saveMeta(Meta::META_SYNC_ERROR, null);
    }

    private function access(object $listing, bool $owner): MlsSyncOutcome
    {
        return app(MlsStaleAccessRefresher::class)->onAccess($listing->fresh(), 'seller', $owner);
    }

    private function sourceReturns(array $raw): void
    {
        $this->sourceResponder = fn () => Http::response(['value' => [$raw]], 200);
    }

    // =====================================================================
    // 1. The public path sends nothing, ever
    // =====================================================================

    /** @test */
    public function an_anonymous_visitor_on_a_stale_listing_causes_no_bridge_request(): void
    {
        $listing = $this->importedListing();
        $this->makeStale($listing);

        $before  = $this->sourceRequests;
        $outcome = $this->access($listing, owner: false);

        $this->assertSame($before, $this->sourceRequests, 'A public page render must never reach Bridge');

        // And it does not claim the listing is fresh, because it is not.
        $this->assertSame(MlsSyncOutcome::LOCKED, $outcome->status);
    }

    /**
     * @test
     *
     * THE STORM TEST. Twenty visitors, one stale listing, zero requests. This is
     * structural rather than rationed: there is no counter to tune, because the
     * expensive work was never on this path.
     */
    public function twenty_simultaneous_visitors_on_one_stale_listing_produce_no_requests_at_all(): void
    {
        $listing = $this->importedListing();
        $this->makeStale($listing);

        $before = $this->sourceRequests;

        for ($i = 0; $i < 20; $i++) {
            $this->access($listing, owner: false);
        }

        $this->assertSame($before, $this->sourceRequests);
    }

    /** @test */
    public function a_public_view_of_a_stale_listing_leaves_a_hint_for_the_sweep(): void
    {
        $listing = $this->importedListing();
        $this->makeStale($listing);

        $this->access($listing, owner: false);

        $this->assertContains(
            $listing->id,
            app(MlsSyncDemandQueue::class)->pending('seller'),
            'The view should have been recorded so the sweep reconciles this listing first'
        );
    }

    /** @test */
    public function a_public_view_of_a_fresh_listing_leaves_no_hint_and_sends_nothing(): void
    {
        $listing = $this->importedListing();
        $this->makeFresh($listing);

        $before  = $this->sourceRequests;
        $outcome = $this->access($listing, owner: false);

        $this->assertSame(MlsSyncOutcome::FRESH, $outcome->status);
        $this->assertSame($before, $this->sourceRequests);
        $this->assertSame([], app(MlsSyncDemandQueue::class)->pending('seller'));
    }

    // =====================================================================
    // 2. The owner path refreshes — once
    // =====================================================================

    /** @test */
    public function the_owner_opening_their_own_stale_listing_gets_a_refresh(): void
    {
        $listing = $this->importedListing();
        $this->makeStale($listing);

        $this->sourceReturns($this->raw([
            'ListPrice'             => 179900,
            'ModificationTimestamp' => '2026-09-08T10:00:00.000Z',
        ]));

        $outcome = $this->access($listing, owner: true);

        $this->assertTrue($outcome->isSynced());
        $this->assertSame('179900', (string) $listing->fresh()->get->toArray()[Meta::META_LIST_PRICE]);
    }

    /** @test */
    public function the_owner_opening_a_fresh_listing_causes_no_request(): void
    {
        $listing = $this->importedListing();
        $this->makeFresh($listing);

        $before  = $this->sourceRequests;
        $outcome = $this->access($listing, owner: true);

        $this->assertSame(MlsSyncOutcome::FRESH, $outcome->status);
        $this->assertSame($before, $this->sourceRequests, 'The freshness window is the first gate and it is local');
    }

    /**
     * @test
     *
     * Repeated owner views collapse to one fetch — the freshness window closes
     * behind the first one. That is what stops an owner refreshing their browser
     * from being a way to spend requests.
     */
    public function repeated_owner_views_produce_one_fetch_not_one_each(): void
    {
        $listing = $this->importedListing();
        $this->makeStale($listing);

        $this->sourceReturns($this->raw(['ModificationTimestamp' => '2026-09-08T10:00:00.000Z']));

        $before = $this->sourceRequests;

        for ($i = 0; $i < 5; $i++) {
            $this->access($listing, owner: true);
        }

        $this->assertSame(1, $this->sourceRequests - $before);
    }

    /**
     * @test
     *
     * A provider fault on the owner's page must render the page, not a stack
     * trace, and must not blank the data already stored.
     */
    public function an_owner_refresh_that_fails_leaves_the_listing_intact(): void
    {
        $listing = $this->importedListing();
        $this->makeStale($listing);

        $this->sourceResponder = fn () => Http::response('', 503);

        $outcome = $this->access($listing, owner: true);

        $this->assertSame(MlsSyncOutcome::UNAVAILABLE, $outcome->status);
        $this->assertSame('184900', (string) $listing->fresh()->get->toArray()['maximum_budget']);
    }

    // =====================================================================
    // 3. Everything that must be inert
    // =====================================================================

    /** @test */
    public function a_manual_listing_is_never_refreshed_and_never_hinted(): void
    {
        $manual = SellerAgentAuction::create([
            'user_id'  => User::factory()->create()->id,
            'address'  => '1 Manual Street',
            'is_draft' => false,
        ]);

        $before = $this->sourceRequests;

        foreach ([true, false] as $owner) {
            $outcome = app(MlsStaleAccessRefresher::class)->onAccess($manual, 'seller', $owner);
            $this->assertSame(MlsSyncOutcome::NOT_MLS_LINKED, $outcome->status);
        }

        $this->assertSame($before, $this->sourceRequests);
        $this->assertSame([], app(MlsSyncDemandQueue::class)->pending('seller'));
    }

    /** @test */
    public function the_lazy_refresh_gate_makes_even_the_owner_path_inert(): void
    {
        config(['mls_sync.lazy_refresh_enabled' => false]);

        $listing = $this->importedListing();
        $this->makeStale($listing);

        $before  = $this->sourceRequests;
        $outcome = $this->access($listing, owner: true);

        $this->assertSame(MlsSyncOutcome::DISABLED, $outcome->status);
        $this->assertSame($before, $this->sourceRequests);
    }

    /** @test */
    public function the_master_gate_makes_the_access_path_inert(): void
    {
        config(['mls_sync.enabled' => false]);

        $listing = $this->importedListing();
        $this->makeStale($listing);

        $before = $this->sourceRequests;

        $this->assertSame(MlsSyncOutcome::DISABLED, $this->access($listing, owner: true)->status);
        $this->assertSame($before, $this->sourceRequests);
    }

    /** @test */
    public function a_role_with_no_source_record_is_unsupported_rather_than_refreshed(): void
    {
        $listing = $this->importedListing();
        $this->makeStale($listing);

        $before  = $this->sourceRequests;
        $outcome = app(MlsStaleAccessRefresher::class)->onAccess($listing->fresh(), 'buyer', true);

        $this->assertSame(MlsSyncOutcome::UNSUPPORTED, $outcome->status);
        $this->assertSame($before, $this->sourceRequests);
    }

    // =====================================================================
    // 4. The hint is a hint about order, never about eligibility
    // =====================================================================

    /** @test */
    public function a_terminal_listing_viewed_publicly_is_not_hinted_because_it_is_not_stale(): void
    {
        $listing = $this->importedListing();

        // Closed, last synced two hours ago: due on the live window, fresh on
        // the terminal one. The access path must use the same rule the sweep
        // does, or a sold listing would be promoted on every page view forever.
        $listing->saveMeta(Meta::META_STANDARD_STATUS, 'Closed');
        $listing->saveMeta(Meta::META_SYNCED_AT, now()->subHours(2)->toIso8601String());

        $outcome = $this->access($listing, owner: false);

        $this->assertSame(MlsSyncOutcome::FRESH, $outcome->status);
        $this->assertSame([], app(MlsSyncDemandQueue::class)->pending('seller'));
    }
    // =====================================================================
    // 5. The trigger is actually wired to the page
    // =====================================================================

    /**
     * @test
     *
     * END TO END, THROUGH THE REAL ROUTE. The refresher being correct is not the
     * feature; the feature is that opening the page invokes it. Without this,
     * MlsStaleAccessRefresher is unreachable code and stale-on-access does not
     * exist however well it is tested in isolation.
     */
    public function the_owner_loading_the_real_listing_page_refreshes_a_stale_listing(): void
    {
        $listing = $this->importedListing();
        $this->makeStale($listing);

        // The listing must be publicly viewable for the route not to 404.
        $listing->is_draft    = false;
        $listing->is_approved = true;
        $listing->save();

        $this->sourceReturns($this->raw([
            'ListPrice'             => 172500,
            'ModificationTimestamp' => '2026-09-09T10:00:00.000Z',
        ]));

        $this->actingAs(\App\Models\User::find($listing->user_id))
            ->get(route('offer.listing.seller.view', ['id' => $listing->id]))
            ->assertSuccessful();

        $this->assertSame(
            '172500',
            (string) $listing->fresh()->get->toArray()[Meta::META_LIST_PRICE],
            'Opening the page as the owner did not refresh the stale MLS data'
        );
    }

    /**
     * @test
     *
     * The same route, as an anonymous visitor: the page renders, and Bridge is
     * not contacted. This is the assertion that matters most in the whole file —
     * it is the one a crawler would exercise thousands of times.
     */
    public function an_anonymous_visitor_loading_the_real_listing_page_causes_no_bridge_request(): void
    {
        $listing = $this->importedListing();
        $this->makeStale($listing);

        $listing->is_draft    = false;
        $listing->is_approved = true;
        $listing->save();

        $before = $this->sourceRequests;

        $this->get(route('offer.listing.seller.view', ['id' => $listing->id]))->assertSuccessful();

        $this->assertSame($before, $this->sourceRequests, 'A public listing page render reached Bridge');

        // And the visit was recorded so the sweep prioritises it.
        $this->assertContains($listing->id, app(MlsSyncDemandQueue::class)->pending('seller'));
    }

    /**
     * @test
     *
     * A provider outage must not take the listing page down with it.
     */
    public function the_page_still_renders_when_the_provider_is_unreachable(): void
    {
        $listing = $this->importedListing();
        $this->makeStale($listing);

        $listing->is_draft    = false;
        $listing->is_approved = true;
        $listing->save();

        $this->sourceResponder = fn () => Http::response('', 503);

        $this->actingAs(\App\Models\User::find($listing->user_id))
            ->get(route('offer.listing.seller.view', ['id' => $listing->id]))
            ->assertSuccessful();
    }
}
