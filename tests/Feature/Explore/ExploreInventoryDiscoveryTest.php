<?php

namespace Tests\Feature\Explore;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeApiService;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Explore\ExploreInventoryService;
use App\Services\Explore\ExploreTransactionType;
use App\Services\Explore\ExploreViewport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Explore\Concerns\MakesExploreListings;
use Tests\Feature\Explore\Support\FakeBridgeApi;
use Tests\TestCase;

/**
 * Explore shows CURRENT Stellar inventory, not whatever an old cache happens to
 * hold — §14 A through M.
 *
 * The seam is the network boundary and nothing above it: FakeBridgeApi
 * subclasses the real BridgeApiService, so every class between the controller
 * and the provider is the production one. That is what makes "Explore reuses the
 * existing ingestion architecture" a testable claim rather than a description.
 */
class ExploreInventoryDiscoveryTest extends TestCase
{
    use DatabaseTransactions;
    use MakesExploreListings;

    private FakeBridgeApi $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'explore.enabled'                => true,
            'explore.discovery.enabled'      => true,
            'explore.google.browser_key'     => null,
            'mls_media.enabled'              => true,
            'mls_media.license_acknowledged' => true,
            'bridge.lazy_ttl_minutes'        => 60,
        ]);

        $this->provider = new FakeBridgeApi();
        $this->app->instance(BridgeApiService::class, $this->provider);
    }

    private function listings(array $query = []): array
    {
        $query = array_merge(['bbox' => $this->bboxAroundDefault()], $query);

        return $this->getJson('/api/explore/listings?' . http_build_query($query))->json();
    }

    /** @return list<string> */
    private function keys(array $query = []): array
    {
        return array_column($this->listings($query)['listings'] ?? [], 'id');
    }

    /* ── A / F — discovery, not cache archaeology ───────────────────────── */

    /**
     * §14-A / §14-F / §16.2. The headline requirement: a listing this
     * application has never seen — never searched, never imported, with no
     * Seller or Landlord record — reaches Explore because the provider says it
     * is there now.
     *
     * @test
     */
    public function a_previously_unseen_stellar_listing_enters_explore_through_discovery(): void
    {
        $record = $this->providerRecord(['listing_key' => 'NEVER-SEEN-1']);
        $this->provider->records = [$record];

        $this->assertSame(0, BridgeProperty::count(), 'precondition: nothing cached');

        $this->assertSame(['NEVER-SEEN-1'], $this->keys());

        // It was ingested through the shared pipeline, so it is now a normal
        // bridge_properties row like any other import produced.
        $this->assertSame(1, BridgeProperty::where('listing_key', 'NEVER-SEEN-1')->count());
    }

    /** @test */
    public function discovery_requires_no_prior_seller_or_landlord_import(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'NO-BYO-LISTING'])];

        $projected = $this->listings()['listings'][0];

        $this->assertSame('NO-BYO-LISTING', $projected['id']);
        $this->assertNull($projected['canonical_url'], 'no BidYourOffer listing exists, and none was created');
        $this->assertSame(0, \App\Models\SellerAgentAuction::count());
        $this->assertSame(0, \App\Models\LandlordAgentAuction::count());
    }

    /* ── B / C — both markets ───────────────────────────────────────────── */

    /** @test */
    public function a_current_sale_listing_is_discovered(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'SALE-1', 'list_price' => 525000])];

        $projected = $this->listings(['transaction_type' => 'sale'])['listings'][0];

        $this->assertSame('SALE-1', $projected['id']);
        $this->assertSame('sale', $projected['transaction_type']);
        $this->assertSame('$525,000', $projected['display_price']);
    }

    /** @test */
    public function a_current_rental_listing_is_discovered(): void
    {
        $this->provider->records = [$this->providerRentalRecord(['listing_key' => 'RENT-1', 'list_price' => 2750])];

        $projected = $this->listings(['transaction_type' => 'rent'])['listings'][0];

        $this->assertSame('RENT-1', $projected['id']);
        $this->assertSame('rent', $projected['transaction_type']);
        $this->assertSame('$2,750/mo', $projected['display_price']);
    }

    /**
     * §11. Discovery is not Seller-only. With no filter, both markets are
     * discovered — one pass each, because sale and rent are different provider
     * queries.
     *
     * @test
     */
    public function both_markets_are_discovered_when_no_filter_is_applied(): void
    {
        $this->provider->records = [
            $this->providerRecord(['listing_key' => 'SALE-1']),
            $this->providerRentalRecord(['listing_key' => 'RENT-1']),
        ];

        $keys = $this->keys();

        sort($keys);
        $this->assertSame(['RENT-1', 'SALE-1'], $keys);
        $this->assertCount(2, $this->provider->paginatedCalls, 'one pass per transaction type, not per listing');
    }

    /** @test */
    public function the_discovery_filter_asks_for_the_right_property_types(): void
    {
        $this->provider->records = [$this->providerRecord()];

        $this->listings(['transaction_type' => 'rent']);

        $filter = $this->provider->paginatedCalls[0]['filter'];

        $this->assertStringContainsString("StandardStatus eq 'Active'", $filter);
        $this->assertStringContainsString("PropertyType eq 'Residential Lease'", $filter);
        $this->assertStringContainsString("PropertyType eq 'Commercial Lease'", $filter);
        $this->assertStringNotContainsString("PropertyType eq 'Commercial Sale'", $filter);
        $this->assertMatchesRegularExpression('/Latitude ge [\d.\-]+ and Latitude le [\d.\-]+/', $filter);
        $this->assertMatchesRegularExpression('/Longitude ge [\d.\-]+ and Longitude le [\d.\-]+/', $filter);
    }

    /* ── D / E / I — freshness beats the local row ──────────────────────── */

    /**
     * §14-D. A locally stored row says Active; the provider now says Pending.
     * Explore reflects the provider.
     *
     * @test
     */
    public function a_locally_active_listing_the_provider_now_calls_pending_disappears(): void
    {
        $stale = $this->makeListing(['listing_key' => 'WENT-PENDING', 'standard_status' => 'Active']);
        $stale->forceFill(['imported_at' => now()->subDays(3)])->save();

        // The provider's Active query no longer returns it — that is what a
        // status change looks like from a bounding-box search.
        $this->provider->records = [];

        $this->assertSame([], $this->keys());
        $this->assertSame(1, BridgeProperty::where('listing_key', 'WENT-PENDING')->count(),
            'withheld from display, never deleted');
    }

    /**
     * §14-E. The newest authoritative price wins over the stored one.
     *
     * @test
     */
    public function the_newest_authoritative_sale_price_is_used(): void
    {
        $stale = $this->makeListing(['listing_key' => 'PRICE-CUT', 'list_price' => 599000]);
        $stale->forceFill(['imported_at' => now()->subDays(3)])->save();

        $this->provider->records = [
            $this->providerRecord(['listing_key' => 'PRICE-CUT', 'list_price' => 525000]),
        ];

        $this->assertSame('$525,000', $this->listings()['listings'][0]['display_price']);
    }

    /**
     * §14-I. And for a rental, where the same field means a rent.
     *
     * @test
     */
    public function the_newest_authoritative_rent_is_used(): void
    {
        $stale = $this->makeRental(['listing_key' => 'RENT-CUT', 'list_price' => 3200]);
        $stale->forceFill(['imported_at' => now()->subDays(3)])->save();

        $this->provider->records = [
            $this->providerRentalRecord(['listing_key' => 'RENT-CUT', 'list_price' => 2895]),
        ];

        $this->assertSame('$2,895/mo', $this->listings(['transaction_type' => 'rent'])['listings'][0]['display_price']);
    }

    /* ── G / H — losing eligibility ─────────────────────────────────────── */

    /**
     * §14-G. A listing the provider now reports as non-displayable disappears,
     * even though the local row said it was fine an hour ago.
     *
     * @test
     */
    public function a_listing_that_loses_idx_participation_disappears(): void
    {
        $stale = $this->makeListing(['listing_key' => 'LOST-IDX']);
        $stale->forceFill(['imported_at' => now()->subDays(3)])->save();

        $this->provider->records = [
            $this->providerRecord(['listing_key' => 'LOST-IDX'], ['IDXParticipationYN' => false]),
        ];

        $this->assertSame([], $this->keys());
    }

    /** §14-H. @test */
    public function a_listing_the_feed_bars_from_the_internet_disappears(): void
    {
        $stale = $this->makeListing(['listing_key' => 'NO-INTERNET']);
        $stale->forceFill(['imported_at' => now()->subDays(3)])->save();

        $this->provider->records = [
            $this->providerRecord(['listing_key' => 'NO-INTERNET'], ['InternetEntireListingDisplayYN' => false]),
        ];

        $this->assertSame([], $this->keys());
    }

    /* ── J — request volume ─────────────────────────────────────────────── */

    /**
     * §14-J / §12. The central performance claim: request volume is a function
     * of viewports, not of markers.
     *
     * @test
     */
    public function a_viewport_of_many_listings_costs_one_pass_not_one_request_per_marker(): void
    {
        $records = [];
        for ($i = 1; $i <= 25; $i++) {
            $records[] = $this->providerRecord(['listing_key' => 'BULK-' . $i]);
        }
        $this->provider->records = $records;

        $this->assertCount(25, $this->listings(['transaction_type' => 'sale'])['listings']);
        $this->assertCount(1, $this->provider->paginatedCalls);
    }

    /**
     * Panning inside one tile is free: the snapped discovery box hashes to the
     * same fetch-cache key, so the second request sends nothing.
     *
     * Without the snap, the cache key would change with every pixel and "reuse
     * the existing fetch cache" would mean a provider request per camera nudge.
     *
     * @test
     */
    public function panning_within_a_tile_reuses_the_existing_fetch_cache(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'TILED'])];

        $this->listings(['transaction_type' => 'sale']);
        $this->assertCount(1, $this->provider->paginatedCalls);

        // A small pan — well inside one 0.05° tile.
        $nudged = implode(',', [
            self::LAT - 0.049, self::LNG - 0.049,
            self::LAT + 0.049, self::LNG + 0.049,
        ]);

        $payload = $this->listings(['transaction_type' => 'sale', 'bbox' => $nudged]);

        $this->assertCount(1, $this->provider->paginatedCalls, 'the warm tile sent no second request');
        $this->assertSame('cached', $payload['discovery']['status']);
        $this->assertTrue($payload['discovery']['complete']);
    }

    /** @test */
    public function the_discovery_box_is_snapped_outwards_so_it_always_contains_the_viewport(): void
    {
        config(['explore.discovery.tile_degrees' => 0.05]);

        $viewport = ExploreViewport::fromString('27.7676,-82.6403,27.7699,-82.6390');
        $box      = app(ExploreInventoryService::class)->discoveryBox($viewport);

        $this->assertLessThanOrEqual($viewport->south, $box['south']);
        $this->assertLessThanOrEqual($viewport->west,  $box['west']);
        $this->assertGreaterThanOrEqual($viewport->north, $box['north']);
        $this->assertGreaterThanOrEqual($viewport->east,  $box['east']);
    }

    /* ── degraded and disabled states ───────────────────────────────────── */

    /**
     * A provider outage must not empty the map — that would state a
     * neighbourhood has nothing for sale, which is a claim about the world
     * rather than about our connectivity. Last-known rows are served, and the
     * response says they are.
     *
     * @test
     */
    public function a_provider_outage_serves_last_known_rows_and_says_so(): void
    {
        $known = $this->makeListing(['listing_key' => 'LAST-KNOWN']);
        $known->forceFill(['imported_at' => now()->subDays(3)])->save();

        $this->provider->shouldFail = true;

        $payload = $this->listings();

        $this->assertSame(['LAST-KNOWN'], array_column($payload['listings'], 'id'));
        $this->assertSame('unavailable', $payload['discovery']['status']);
        $this->assertTrue($payload['discovery']['degraded']);
        $this->assertFalse($payload['discovery']['complete']);
    }

    /**
     * §14-G / §6. A PARTIAL pass must not suppress anything.
     *
     * When pagination hits a ceiling, a listing missing from the result set
     * means "we stopped asking", not "it is gone". Withholding on that reading
     * would hide real, current inventory — so the confirmation rule is switched
     * off and the response reports `complete: false` instead.
     *
     * @test
     */
    public function a_partial_pass_does_not_suppress_unconfirmed_rows(): void
    {
        // A locally-stored row far outside the freshness window. On a COMPLETE
        // pass it would be withheld.
        $stale = $this->makeListing(['listing_key' => 'STALE-BUT-REAL']);
        $stale->forceFill(['imported_at' => now()->subDays(30)])->save();

        // The provider returns more than one record while the per-pass record
        // ceiling is one, so the importer stops early and reports the pass
        // partial.
        config(['explore.discovery.max_records' => 1]);

        $this->provider->records = [
            $this->providerRecord(['listing_key' => 'FRESH-A']),
            $this->providerRecord(['listing_key' => 'FRESH-B']),
        ];

        $payload = $this->listings(['transaction_type' => 'sale']);

        $this->assertSame('partial', $payload['discovery']['status']);
        $this->assertFalse($payload['discovery']['complete']);
        $this->assertFalse($payload['discovery']['degraded']);

        $this->assertContains(
            'STALE-BUT-REAL',
            array_column($payload['listings'], 'id'),
            'an unconfirmed row must survive a pass that never finished looking'
        );
    }

    /**
     * And the complementary half: on a COMPLETE pass the same row IS withheld,
     * because there the absence is evidence.
     *
     * @test
     */
    public function a_complete_pass_does_suppress_unconfirmed_rows(): void
    {
        $stale = $this->makeListing(['listing_key' => 'STALE-BUT-REAL']);
        $stale->forceFill(['imported_at' => now()->subDays(30)])->save();

        $this->provider->records = [$this->providerRecord(['listing_key' => 'FRESH-A'])];

        $payload = $this->listings(['transaction_type' => 'sale']);

        $this->assertTrue($payload['discovery']['complete']);
        $this->assertSame(['FRESH-A'], array_column($payload['listings'], 'id'));

        // Withheld from display, never deleted.
        $this->assertSame(1, BridgeProperty::where('listing_key', 'STALE-BUT-REAL')->count());
    }

    /**
     * With discovery off, Explore is cache-only — and labels itself, so a thin
     * answer is not mistaken for a thin market.
     *
     * @test
     */
    public function discovery_disabled_is_reported_rather_than_disguised(): void
    {
        config(['explore.discovery.enabled' => false]);

        $stale = $this->makeListing(['listing_key' => 'CACHE-ONLY']);
        $stale->forceFill(['imported_at' => now()->subDays(30)])->save();

        $payload = $this->listings();

        $this->assertSame('disabled', $payload['discovery']['status']);
        $this->assertFalse($payload['discovery']['complete']);
        $this->assertSame(0, $this->provider->providerRequestCount(), 'nothing outbound while the gate is closed');

        // The row is still served: withholding it would be asserting it is gone
        // on the strength of a pass that never ran.
        $this->assertSame(['CACHE-ONLY'], array_column($payload['listings'], 'id'));
    }

    /* ── selected-property freshness (§8) ───────────────────────────────── */

    /**
     * The panel re-asks about the one record it is about to publish, using the
     * existing single-record refresh, and re-runs eligibility on the answer.
     *
     * @test
     */
    public function the_property_panel_refreshes_a_stale_record_before_publishing_it(): void
    {
        $stale = $this->makeListing(['listing_key' => 'PANEL-1', 'list_price' => 599000]);
        $stale->forceFill(['imported_at' => now()->subDays(3)])->save();

        $this->provider->records = [
            $this->providerRecord(['listing_key' => 'PANEL-1', 'list_price' => 525000]),
        ];

        $this->getJson('/api/explore/listings/PANEL-1')
            ->assertOk()
            ->assertJsonPath('listing.display_price', '$525,000');

        $this->assertCount(1, $this->provider->singleCalls, 'one record, one request');
        $this->assertStringContainsString("ListingKey eq 'PANEL-1'", $this->provider->singleCalls[0]['filter']);
    }

    /**
     * §9. And when the refresh shows it is no longer publishable, the panel
     * 404s rather than presenting it as available.
     *
     * @test
     */
    public function a_property_that_became_ineligible_404s_from_the_panel(): void
    {
        $stale = $this->makeListing(['listing_key' => 'GONE-1']);
        $stale->forceFill(['imported_at' => now()->subDays(3)])->save();

        $this->provider->records = [
            $this->providerRecord(['listing_key' => 'GONE-1'], ['InternetEntireListingDisplayYN' => false]),
        ];

        $this->getJson('/api/explore/listings/GONE-1')->assertStatus(404);
    }

    /**
     * A record already inside the freshness window costs nothing. Opening the
     * same panel repeatedly must not be a request each time.
     *
     * @test
     */
    public function a_freshly_confirmed_record_is_not_re_fetched_for_the_panel(): void
    {
        $this->makeListing(['listing_key' => 'FRESH-1']);

        $this->getJson('/api/explore/listings/FRESH-1')->assertOk();
        $this->getJson('/api/explore/listings/FRESH-1')->assertOk();

        $this->assertSame(0, $this->provider->providerRequestCount());
    }

    /* ── K / L / M — architecture ───────────────────────────────────────── */

    /**
     * §14-K / §14-L. Explore runs through the ONE existing importer. There is no
     * Explore-specific MLS client, importer, synchroniser or storage.
     *
     * @test
     */
    public function explore_discovery_runs_through_the_existing_import_pipeline(): void
    {
        $this->provider->records = [$this->providerRecord(['listing_key' => 'PIPELINE-1'])];

        $this->listings(['transaction_type' => 'sale']);

        // Ingested into the shared table by the shared normalizer.
        $this->assertSame(1, BridgeProperty::where('listing_key', 'PIPELINE-1')->count());

        // The fetch-cache row is the existing one, namespaced by an Explore role.
        $this->assertTrue(
            \App\Models\BridgeCriteriaFetchCache::where('role', 'explore_sale')->exists(),
            'the existing criteria fetch cache is what bounds Explore discovery'
        );

        // And the entry point is the shared importer, not a parallel one.
        $this->assertTrue(
            method_exists(LazyBridgeImportService::class, 'importForCriteria'),
            'Explore must call the existing importer'
        );
    }

    /** §14-L. @test */
    public function no_explore_specific_importer_or_synchroniser_exists(): void
    {
        $exploreClasses = glob(app_path('Services/Explore/*.php')) ?: [];

        // Comments are stripped before scanning. These classes DOCUMENT which
        // shared pipeline they defer to, by name, and that documentation is the
        // point — a scan that tripped over it would punish the explanation
        // rather than the behaviour.
        $executable = static function (string $path): string {
            $source = (string) file_get_contents($path);
            $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);

            return (string) preg_replace('#(^|[^:])//.*$#m', '$1', $source);
        };

        foreach ($exploreClasses as $path) {
            $source = $executable($path);
            $name   = basename($path);

            // No Explore class may talk HTTP, or hold its own provider client.
            foreach (['Http::', 'GuzzleHttp', 'curl_init', 'file_get_contents(\'http'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, "{$name} must not reach the network itself");
            }

            // Only the translator may reference the importer at all.
            if ($name !== 'ExploreInventoryService.php') {
                $this->assertStringNotContainsString('BridgeApiService', $source, "{$name} must not hold a provider client");
                $this->assertStringNotContainsString('LazyBridgeImportService', $source, "{$name} must not import");
            }
        }

        // And nothing in Explore writes to bridge_properties: the shared
        // normalizer owns that table.
        foreach ($exploreClasses as $path) {
            $source = $executable($path);
            foreach (['BridgeProperty::create', 'BridgeProperty::updateOrCreate', '->save()'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, basename($path) . ' must not write MLS rows');
            }
        }
    }

    /** §14-M. @test */
    public function no_raw_provider_record_reaches_the_browser(): void
    {
        $this->provider->records = [
            $this->providerRecord(['listing_key' => 'RAW-1'], [
                'PrivateRemarks'      => 'PROHIBITED-private-remarks',
                'STELLAR_TenantName'  => 'PROHIBITED-occupant',
                'LockBoxSerialNumber' => 'PROHIBITED-lockbox',
            ]),
        ];

        $body = $this->getJson('/api/explore/listings?bbox=' . $this->bboxAroundDefault())
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('RAW-1', $body, 'precondition: it was published');

        foreach (['PROHIBITED-private-remarks', 'PROHIBITED-occupant', 'PROHIBITED-lockbox',
                  'PrivateRemarks', 'STELLAR_TenantName', 'LockBoxSerialNumber'] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }
    }

    /**
     * The transaction classification is unchanged by discovery — it is still
     * decided from PropertyType by the same exact-match allow-list.
     *
     * @test
     */
    public function discovery_does_not_change_the_transaction_classification(): void
    {
        $this->assertSame(
            ExploreTransactionType::RENT,
            ExploreTransactionType::fromPropertyType('Residential Lease')
        );
        $this->assertSame(
            ExploreTransactionType::SALE,
            ExploreTransactionType::fromPropertyType('Commercial Sale')
        );
    }
}
