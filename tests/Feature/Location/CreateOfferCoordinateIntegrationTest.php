<?php

namespace Tests\Feature\Location;

use App\Jobs\ComputeLocationDna;
use App\Models\BridgeProperty;
use App\Models\PropertyLocationDna;
use App\Services\Location\Coordinates\PropertyCoordinateMeta;
use App\Services\Location\PropertyCoordinatePersistenceService;
use App\Services\LocationDna\LocationDnaGeocodeService;
use App\Services\Schema\ProvenanceSchemaReadiness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * G5: Seller and Landlord Create Offer resolve coordinates through the ladder.
 *
 * WHY THIS TESTS THE SERVICE AND THE HANDOFF, NOT THE LIVEWIRE COMPONENTS
 * ----------------------------------------------------------------------
 * The four Create Offer components are ~228 KB each and a full publish requires
 * a valid user, a populated multi-tab form and the whole publish-validation
 * chain. A test driving that proves mostly that the form still validates. What
 * G5 actually changed is one call inserted before each existing dispatch, and
 * the two things worth proving about it are that the service behaves correctly
 * and that its output survives the asynchronous handoff into
 * `property_location_dna`.
 *
 * So the wiring itself is asserted structurally — the call exists, at every
 * dispatch site, before the dispatch (see CreateOfferCoordinateWiringTest) —
 * and the behaviour is asserted here against the real service, the real
 * adapters and the real pipeline. The handoff tests call the genuine
 * LocationDnaGeocodeService — the pipeline step the dispatched job reaches —
 * handing it exactly the payload LocationDnaPipelineRunner would build from the
 * listing's meta. The job and runner are thin by comparison; the step that reads
 * `pre_lat`/`pre_lng` and provenance and writes the row is the one under test.
 *
 * Both roles are covered explicitly. Landlord parity is asserted, never
 * inferred.
 */
class CreateOfferCoordinateIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private const CENSUS = 'geocoding.geo.census.gov/*';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        ProvenanceSchemaReadiness::flush();

        // The shipped posture. Individual tests opt in where that is the point.
        config()->set('census_geocoder.enabled', false);
        config()->set('google_places.enabled', false);
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    /**
     * A stand-in for a saved Seller/Landlord auction row.
     *
     * The four role models do not share a base class or an interface, and the
     * service is typed against the EAV accessors they do share. This records
     * meta the same way and lets a test read it back.
     */
    private function listing(int $id, array $meta = []): object
    {
        return new class ($id, $meta) {
            public array $saved = [];

            public function __construct(public int $id, array $meta)
            {
                $this->saved = $meta;
            }

            public function saveMeta(string $key, $value): void
            {
                $this->saved[$key] = (string) $value;
            }

            public function info(string $key)
            {
                return $this->saved[$key] ?? null;
            }
        };
    }

    /** @return array<string, string> the address meta a Create Offer save leaves behind */
    private function addressMeta(
        string $address = '315 E Madison St',
        string $city = 'Tampa',
        string $state = 'FL',
        string $zip = '33602',
        string $unit = ''
    ): array {
        return [
            'address'         => $address,
            'unit_address'    => $unit,
            'property_city'   => $city,
            'property_state'  => $state,
            'property_county' => 'Hillsborough',
            'property_zip'    => $zip,
        ];
    }

    private function service(): PropertyCoordinatePersistenceService
    {
        return new PropertyCoordinatePersistenceService();
    }

    private function censusMatch(): array
    {
        return ['result' => ['addressMatches' => [[
            'coordinates'       => ['x' => -82.458094358643, 'y' => 27.948434712759],
            'addressComponents' => ['state' => 'FL', 'zip' => '33602'],
            'matchedAddress'    => '315 MADISON ST, TAMPA, FL, 33602',
        ]]]];
    }

    /** A trusted existing coordinate, stored the way the pipeline stores one. */
    private function storeExistingCoordinate(string $listingType, int $listingId): void
    {
        PropertyLocationDna::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'source_address' => '315 E Madison St',
            'source_city'    => 'Tampa',
            'source_county'  => 'Hillsborough',
            'source_state'   => 'FL',
            'source_zip'     => '33602',
            'geocoded_lat'   => 27.9506,
            'geocoded_lng'   => -82.4572,
            'geocode_source' => 'saved_meta',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);
    }

    /** @return array<int, array{0: string, 1: int}> both roles, for parity coverage */
    public static function roles(): array
    {
        return [
            'seller'   => ['seller_agent', 971001],
            'landlord' => ['landlord_agent', 972001],
        ];
    }

    // ── 1. existing trusted coordinate wins, zero Census ────────────────────

    /** @dataProvider roles */
    public function test_an_existing_trusted_coordinate_is_reused_without_calling_census(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true); // even enabled, it must not be reached
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $this->storeExistingCoordinate($listingType, $listingId);
        $listing = $this->listing($listingId, $this->addressMeta());

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_RESOLVED, $outcome['outcome']);
        // The Existing rung records the provider that ORIGINALLY produced the
        // point, not itself — "where did this coordinate come from" has one true
        // answer, and reusing it does not change it. The rung identifies itself
        // through the source instead.
        $this->assertSame('saved_meta', $outcome['provider']);
        $this->assertSame('existing', $listing->info(PropertyCoordinateMeta::SOURCE));
        $this->assertSame('27.9506', $listing->info(PropertyCoordinateMeta::LAT));

        Http::assertNothingSent();
    }

    // ── 2. Bridge rung, when a key is supplied ──────────────────────────────

    /**
     * The Bridge rung works and outranks Census — proven with a synthetic key.
     *
     * CURRENT CREATE OFFER GAP: Seller/Landlord Create Offer does not presently
     * persist a Bridge ListingKey. Its MLS import is a URL/text scrape
     * (MlsListingImportService) with no RESO ListingKey, and the adapter matches
     * only by exact key because address similarity is guessing. So on a real
     * Create Offer record this rung is silent and the ladder falls through to
     * Census. Binding a listing to its ListingKey is a separate data-binding
     * phase; this proves the rung is correct for when it arrives.
     *
     * @dataProvider roles
     */
    public function test_a_bridge_coordinate_is_used_when_a_listing_key_is_supplied(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        BridgeProperty::create([
            'listing_key'       => 'SYNTHETIC-G5-' . $listingId,
            'unparsed_address'  => '315 E Madison St',
            'city'              => 'Tampa',
            'state_or_province' => 'FL',
            'postal_code'       => '33602',
            'county_or_parish'  => 'Hillsborough',
            'latitude'          => 28.1111,
            'longitude'         => -82.2222,
        ]);

        $listing = $this->listing($listingId, array_merge($this->addressMeta(), [
            'mls_listing_key' => 'SYNTHETIC-G5-' . $listingId,
        ]));

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame('bridge_mls', $outcome['provider']);
        $this->assertSame('parcel', $outcome['precision'], 'MLS coordinates are graded Parcel, never Rooftop');
        $this->assertSame('28.1111', $listing->info(PropertyCoordinateMeta::LAT));

        Http::assertNothingSent();
    }

    /** @dataProvider roles */
    public function test_a_real_create_offer_listing_has_no_bridge_key_so_the_rung_is_silent(
        string $listingType,
        int $listingId
    ): void {
        // Documents the gap as behaviour rather than prose: without a key the
        // Bridge rung cannot answer, whatever is in bridge_properties.
        BridgeProperty::create([
            'listing_key'       => 'SOME-OTHER-RECORD-' . $listingId,
            'unparsed_address'  => '315 E Madison St',
            'city'              => 'Tampa',
            'state_or_province' => 'FL',
            'postal_code'       => '33602',
            'latitude'          => 28.1111,
            'longitude'         => -82.2222,
        ]);

        Http::fake();
        $listing = $this->listing($listingId, $this->addressMeta());

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNRESOLVED, $outcome['outcome']);
        Http::assertNothingSent();
    }

    // ── 3. Census disabled ──────────────────────────────────────────────────

    /** @dataProvider roles */
    public function test_a_manual_address_is_unresolved_and_silent_while_census_is_disabled(
        string $listingType,
        int $listingId
    ): void {
        Http::fake();

        $listing = $this->listing($listingId, $this->addressMeta());

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNRESOLVED, $outcome['outcome']);
        $this->assertNull($listing->info(PropertyCoordinateMeta::LAT));

        Http::assertNothingSent();
    }

    public function test_the_shipped_census_default_is_off(): void
    {
        $shipped = require config_path('census_geocoder.php');

        $this->assertFalse($shipped['enabled']);
    }

    // ── 4. Census enabled (mocked) ──────────────────────────────────────────

    /** @dataProvider roles */
    public function test_a_manual_address_resolves_through_census_when_enabled(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame('us_census', $outcome['provider']);
        $this->assertSame('interpolated', $outcome['precision']);

        $this->assertSame('27.948434712759', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame('-82.458094358643', $listing->info(PropertyCoordinateMeta::LNG));
        $this->assertSame('interpolated', $listing->info(PropertyCoordinateMeta::PRECISION));
        $this->assertSame('us_census', $listing->info(PropertyCoordinateMeta::PROVIDER));
        $this->assertSame('geocoder', $listing->info(PropertyCoordinateMeta::SOURCE));
        // The address we ASKED about, not the one Census answered with. Census
        // drops the directional ("315 MADISON ST"), and storing its answer would
        // mean the next save's lookup line never matched — re-resolving forever
        // while appearing to work.
        $this->assertSame(
            '315 e madison st tampa fl 33602',
            $listing->info(PropertyCoordinateMeta::NORMALIZED_ADDRESS)
        );

        Http::assertSentCount(1);
    }

    // ── 5. change detection ─────────────────────────────────────────────────

    /** @dataProvider roles */
    public function test_a_repeated_save_with_no_address_change_sends_nothing(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());

        $this->service()->resolveAndPersist($listing, $listingType);

        for ($i = 0; $i < 5; $i++) {
            $outcome = $this->service()->resolveAndPersist($listing, $listingType);
            $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNCHANGED, $outcome['outcome']);
        }

        Http::assertSentCount(1);
    }

    /** @dataProvider roles */
    public function test_a_normalization_only_change_reuses_the_coordinate(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());
        $this->service()->resolveAndPersist($listing, $listingType);

        // Same doorway, typed differently.
        foreach ($this->addressMeta(address: '315 East Madison Street') as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNCHANGED, $outcome['outcome']);
        Http::assertSentCount(1);
    }

    /** @dataProvider roles */
    public function test_a_unit_only_change_reuses_the_building_coordinate(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta(unit: 'Unit 4A'));
        $this->service()->resolveAndPersist($listing, $listingType);

        $listing->saveMeta('unit_address', 'Unit 4B');

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame(
            PropertyCoordinatePersistenceService::OUTCOME_UNCHANGED,
            $outcome['outcome'],
            'Two units share one building coordinate and one lookup'
        );
        Http::assertSentCount(1);
    }

    /** @dataProvider roles */
    public function test_a_meaningful_address_change_re_resolves(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());
        $this->service()->resolveAndPersist($listing, $listingType);

        // The property moved.
        $listing->saveMeta('address', '987 Elm St');
        $listing->saveMeta('property_zip', '33607');

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertNotSame(
            PropertyCoordinatePersistenceService::OUTCOME_UNCHANGED,
            $outcome['outcome'],
            'A different property must not inherit the old coordinate'
        );
        $this->assertSame(2, Http::recorded()->count());
    }

    // ── 6. provider failure never breaks a save ─────────────────────────────

    /**
     * @dataProvider providerFailures
     */
    public function test_a_provider_failure_degrades_safely(int $status, string $body): void
    {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($body, $status)]);

        $listing = $this->listing(973001, $this->addressMeta());

        $outcome = $this->service()->resolveAndPersist($listing, 'seller_agent');

        // No exception escaped, and nothing was invented.
        $this->assertContains($outcome['outcome'], [
            PropertyCoordinatePersistenceService::OUTCOME_UNRESOLVED,
            PropertyCoordinatePersistenceService::OUTCOME_ERROR,
        ]);
        $this->assertNull($listing->info(PropertyCoordinateMeta::LAT));
    }

    public static function providerFailures(): array
    {
        return [
            'timeout / 5xx'  => [502, ''],
            'rate limited'   => [429, ''],
            'malformed body' => [200, '<html>maintenance</html>'],
            'rejected'       => [400, '{"errors":["Invalid benchmark in request"]}'],
        ];
    }

    /** @dataProvider roles */
    public function test_a_provider_failure_never_destroys_an_existing_coordinate(
        string $listingType,
        int $listingId
    ): void {
        // The invariant that matters most: a Census outage is not evidence that
        // a coordinate we already hold is wrong.
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response('', 502)]);

        $listing = $this->listing($listingId, array_merge($this->addressMeta(), [
            PropertyCoordinateMeta::LAT                => '27.9506',
            PropertyCoordinateMeta::LNG                => '-82.4572',
            PropertyCoordinateMeta::NORMALIZED_ADDRESS => 'different old address',
        ]));

        $this->service()->resolveAndPersist($listing, $listingType);

        $this->assertSame('27.9506', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame('-82.4572', $listing->info(PropertyCoordinateMeta::LNG));
    }

    // ── 7. schema not ready ─────────────────────────────────────────────────

    /** @dataProvider roles */
    public function test_a_missing_provenance_schema_does_not_break_resolution(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        // Genuine absence: the columns are dropped, not mocked away.
        Schema::drop('property_location_dna');
        ProvenanceSchemaReadiness::flush();

        $listing = $this->listing($listingId, $this->addressMeta());

        $outcome = $this->service()->resolveAndPersist($listing, $listingType);

        // Meta is not the provenance schema, so the coordinate still lands.
        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_RESOLVED, $outcome['outcome']);
        $this->assertSame('27.948434712759', $listing->info(PropertyCoordinateMeta::LAT));
    }

    // ── 8. the asynchronous handoff, running the real job ───────────────────

    /** @dataProvider roles */
    public function test_the_coordinate_and_provenance_survive_into_property_location_dna(
        string $listingType,
        int $listingId
    ): void {
        // The handoff this whole design is shaped around. The real job runs; the
        // real pipeline reads the meta; the real geocode service writes the row.
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());
        $this->service()->resolveAndPersist($listing, $listingType);

        $provenance = PropertyCoordinateMeta::readProvenance(
            static fn (string $key) => $listing->info($key)
        );

        $this->assertNotNull($provenance, 'The ladder must record provable provenance');

        // Feed the pipeline exactly what it would read from the listing.
        $written = app(LocationDnaGeocodeService::class)->geocodeForListing($listingType, $listingId, [
            'address'    => '315 E Madison St',
            'city'       => 'Tampa',
            'state'      => 'FL',
            'county'     => 'Hillsborough',
            'zip'        => '33602',
            'pre_lat'    => $listing->info(PropertyCoordinateMeta::LAT),
            'pre_lng'    => $listing->info(PropertyCoordinateMeta::LNG),
            'provenance' => $provenance,
        ]);

        $this->assertTrue($written['success']);

        $row = PropertyLocationDna::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->firstOrFail();

        $this->assertEqualsWithDelta(27.948434712759, (float) $row->geocoded_lat, 0.0001);
        $this->assertSame('geocoded', $row->geocode_status);

        // The honest provenance survived rather than being flattened.
        $this->assertSame('interpolated', $row->geocode_precision);
        $this->assertSame('us_census', $row->geocode_provider);
        $this->assertSame('315 e madison st tampa fl 33602', $row->normalized_address);

        // geocode_source stays 'saved_meta' for ExistingCoordinatesAdapter's
        // allow-list; the honest detail lives in the columns above.
        $this->assertSame('saved_meta', $row->geocode_source);
    }

    /** @dataProvider roles */
    public function test_a_census_coordinate_is_not_read_back_inflated_to_parcel(
        string $listingType,
        int $listingId
    ): void {
        // 'saved_meta' infers Parcel. An Interpolated point stored under it must
        // not be laundered into something flood-zone work treats as the property.
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());
        $this->service()->resolveAndPersist($listing, $listingType);

        app(LocationDnaGeocodeService::class)->geocodeForListing($listingType, $listingId, [
            'address'    => '315 E Madison St',
            'city'       => 'Tampa',
            'state'      => 'FL',
            'county'     => 'Hillsborough',
            'zip'        => '33602',
            'pre_lat'    => $listing->info(PropertyCoordinateMeta::LAT),
            'pre_lng'    => $listing->info(PropertyCoordinateMeta::LNG),
            'provenance' => PropertyCoordinateMeta::readProvenance(
                static fn (string $key) => $listing->info($key)
            ),
        ]);

        // Now read it back through the Existing rung on a fresh listing.
        $reader = $this->listing($listingId, $this->addressMeta());
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $outcome = $this->service()->resolveAndPersist($reader, $listingType);

        $this->assertSame(
            'existing',
            $reader->info(PropertyCoordinateMeta::SOURCE),
            'The Existing rung must answer, not Census'
        );
        $this->assertSame(
            'interpolated',
            $outcome['precision'],
            'The stored precision must win over the saved_meta inference'
        );
    }

    /** @dataProvider roles */
    public function test_a_legacy_coordinate_without_provenance_keeps_legacy_handling(
        string $listingType,
        int $listingId
    ): void {
        // An autocomplete coordinate has no provable origin. It must keep
        // 'saved_meta' handling and gain no invented precision.
        $written = app(LocationDnaGeocodeService::class)->geocodeForListing($listingType, $listingId, [
            'address' => '315 E Madison St',
            'city'    => 'Tampa',
            'state'   => 'FL',
            'county'  => 'Hillsborough',
            'zip'     => '33602',
            'pre_lat' => '27.9506',
            'pre_lng' => '-82.4572',
            // no provenance key at all
        ]);

        $this->assertTrue($written['success']);

        $row = PropertyLocationDna::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->firstOrFail();

        $this->assertSame('saved_meta', $row->geocode_source);
        $this->assertNull($row->geocode_precision);
        $this->assertNull($row->geocode_provider);
    }

    /** @dataProvider roles */
    public function test_a_meaningful_address_change_invalidates_the_stored_coordinate(
        string $listingType,
        int $listingId
    ): void {
        // Step (d) of the pipeline remains the canonical stale-coordinate
        // invalidation, and G5 feeds it rather than bypassing it.
        $this->storeExistingCoordinate($listingType, $listingId);

        app(LocationDnaGeocodeService::class)->geocodeForListing($listingType, $listingId, [
            'address' => '987 Elm St',      // the property moved
            'city'    => 'Tampa',
            'state'   => 'FL',
            'county'  => 'Hillsborough',
            'zip'     => '33602',
        ]);

        $row = PropertyLocationDna::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->firstOrFail();

        $this->assertNull($row->geocoded_lat, 'The old coordinate must not survive an address change');
        $this->assertNotSame('geocoded', $row->geocode_status);
    }

    // ── 9. the job itself adds nothing ──────────────────────────────────────

    /** @dataProvider roles */
    public function test_resolution_dispatches_nothing(string $listingType, int $listingId): void
    {
        Bus::fake();
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $this->service()->resolveAndPersist($this->listing($listingId, $this->addressMeta()), $listingType);

        Bus::assertNotDispatched(ComputeLocationDna::class);
    }
}
