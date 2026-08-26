<?php

namespace Tests\Feature\Location;

use App\Http\Livewire\OfferListing\Concerns\RecordsSelectedPropertyAddress;
use App\Models\BridgeProperty;
use App\Models\PropertyLocationDna;
use App\Services\Location\Coordinates\Adapters\BridgeMlsCoordinatesAdapter;
use App\Services\Location\Coordinates\Adapters\CensusGeocoderAdapter;
use App\Services\Location\Coordinates\Adapters\ExistingCoordinatesAdapter;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProvenanceStatus;
use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateMeta;
use App\Services\Location\Coordinates\PropertyCoordinateResolver;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use App\Services\Location\PropertyCoordinatePersistenceService;
use App\Services\LocationDna\LocationDnaGeocodeService;
use App\Services\Schema\ProvenanceSchemaReadiness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Create Offer address pick no longer dictates the property's coordinate.
 *
 * WHAT CHANGED, IN ONE SENTENCE
 * -----------------------------
 * The four Seller/Landlord Create Offer components used to end their address
 * block by writing `$this->property_lat` — browser state, filled by whatever the
 * autocomplete widget attached to the row somebody clicked — straight into the
 * `property_lat` meta the whole coordinate architecture treats as authoritative.
 * They now write only the address, and the coordinate is produced from that
 * address by the ladder at the same save.
 *
 * WHY THAT WAS WORTH A PHASE
 * --------------------------
 * The raw write was not merely unprovenanced, it was self-laundering.
 * `property_lat` is forwarded by {@see \App\Services\LocationDna\LocationDnaPipelineRunner}
 * as `pre_lat`, stored by {@see LocationDnaGeocodeService} in
 * `property_location_dna` as `geocode_source = 'saved_meta'`, and read back by
 * {@see ExistingCoordinatesAdapter} as rung 1 of the ladder — graded Parcel —
 * on the next save. Two saves after a dropdown click, a suggestion was
 * indistinguishable from a coordinate the platform had vouched for, and it
 * outranked every rung that could actually vouch for one.
 *
 * WHAT THIS FILE ASSERTS AND WHAT IT DOES NOT
 * -------------------------------------------
 * The seam, behaviourally: a browser pick carrying a coordinate goes through the
 * real service, the real adapters and the real pipeline, and the coordinate that
 * comes out the other end is the ladder's, never the browser's. Plus the
 * structural half — that the four components no longer contain the write at all
 * — because a behavioural test cannot observe a line that was deleted from a
 * 228 KB component, and a careless edit could put it back.
 *
 * Ladder PRECEDENCE is not this file's subject; {@see CreateOfferCoordinateIntegrationTest}
 * owns that. What is asserted here for each rung is the reroute's claim: whoever
 * wins, the browser does not.
 */
class CreateOfferCoordinateRerouteTest extends TestCase
{
    use DatabaseTransactions;

    private const CENSUS = 'geocoding.geo.census.gov/*';

    /** The point the autocomplete widget hands over. Never a final coordinate. */
    private const BROWSER_LAT = '27.111111';
    private const BROWSER_LNG = '-82.111111';

    /** The four components whose raw writes this phase removed. */
    private const COMPONENTS = [
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php',
        'app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php',
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php',
        'app/Http/Livewire/OfferListing/Landlord/LandlordOfferListingEdit.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        ProvenanceSchemaReadiness::flush();

        // The shipped posture. Tests opt in where that is the point.
        config()->set('census_geocoder.enabled', false);
        config()->set('google_places.enabled', false);
        config()->set('address_point_corpus.enabled', false);
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    /** A stand-in for a saved Seller/Landlord auction row (EAV accessors). */
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

    /**
     * A stand-in for a Create Offer component mid-save: the address fields the
     * autocomplete filled, including the coordinate it supplied.
     *
     * Uses the real trait, so what is exercised is the production write seam and
     * not a paraphrase of it.
     */
    private function componentAfterBrowserPick(
        string $lat = self::BROWSER_LAT,
        string $lng = self::BROWSER_LNG,
        string $placeId = 'ChIJ-browser-pick'
    ): object {
        return new class ($lat, $lng, $placeId) {
            use RecordsSelectedPropertyAddress {
                saveSelectedPropertyAddressMeta as public;
            }

            public function __construct(
                public string $property_lat,
                public string $property_lng,
                public string $google_place_id
            ) {
            }
        };
    }

    /** @return array<string, string> the address meta a Create Offer save leaves behind */
    private function addressMeta(
        string $address = '315 E Madison St',
        string $city = 'Tampa',
        string $state = 'FL',
        string $zip = '33602'
    ): array {
        return [
            'address'         => $address,
            'unit_address'    => '',
            'property_city'   => $city,
            'property_county' => 'Hillsborough',
            'property_state'  => $state,
            'property_zip'    => $zip,
        ];
    }

    /**
     * Run the save the way the reroute wires it: the component records its
     * address selection, then the listing is resolved through the ladder.
     *
     * @return array{outcome: string, reason: string|null, provider: string|null, precision: string|null}
     */
    private function save(
        object $listing,
        string $listingType,
        ?PropertyCoordinateResolver $resolver = null
    ): array {
        $this->componentAfterBrowserPick()->saveSelectedPropertyAddressMeta($listing);

        return (new PropertyCoordinatePersistenceService($resolver))
            ->resolveAndPersist($listing, $listingType);
    }

    private function censusMatch(): array
    {
        return ['result' => ['addressMatches' => [[
            'coordinates'       => ['x' => -82.458094358643, 'y' => 27.948434712759],
            'addressComponents' => ['state' => 'FL', 'zip' => '33602'],
            'matchedAddress'    => '315 MADISON ST, TAMPA, FL, 33602',
        ]]]];
    }

    /**
     * A stand-in for the AddressPoint rung.
     *
     * The real one reads the `addresses` corpus over PostGIS (`ST_Y(geom)`), and
     * the corpus is deliberately inert — no data imported,
     * `ADDRESS_POINT_CORPUS_ENABLED` off, and the test database is SQLite. Its
     * own behaviour is covered by {@see AddressPointCoordinateAdapterTest}
     * against the real query. What this file needs from that rung is only that a
     * corpus answer, when there is one, becomes the listing's coordinate instead
     * of the browser's.
     */
    private function addressPointRung(float $lat, float $lng): CoordinateProviderAdapterInterface
    {
        return new class ($lat, $lng) implements CoordinateProviderAdapterInterface {
            public function __construct(private float $lat, private float $lng)
            {
            }

            public function providerId(): string
            {
                return 'address_point';
            }

            public function source(): CoordinateSource
            {
                return CoordinateSource::AddressPoint;
            }

            public function requiresNetwork(): bool
            {
                return false;
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function resolve(PropertyAddress $address): PropertyCoordinateResult
            {
                return PropertyCoordinateResult::resolved(
                    latitude:          $this->lat,
                    longitude:         $this->lng,
                    precision:         CoordinatePrecision::Rooftop,
                    source:            CoordinateSource::AddressPoint,
                    provider:          'address_point',
                    normalizedAddress: $address->coordinateLookupLine(),
                    sourceRef:         'NENA-TEST-0001',
                );
            }
        };
    }

    /** The standard ladder with the corpus rung stood in for. */
    private function ladderWithAddressPoint(float $lat, float $lng): PropertyCoordinateResolver
    {
        return new PropertyCoordinateResolver([
            new ExistingCoordinatesAdapter(),
            new BridgeMlsCoordinatesAdapter(),
            $this->addressPointRung($lat, $lng),
            new CensusGeocoderAdapter(),
        ]);
    }

    /**
     * An existing coordinate, stored the way the pipeline stores one.
     *
     * The default row is the legacy shape: `geocode_source = 'saved_meta'` and
     * nothing else. That is what the pipeline wrote for years, and — as this
     * file's own header describes — it is exactly what a laundered browser pick
     * looks like two saves later. It carries no `geocode_provider`, so it cannot
     * say which rung produced it, and no `geocode_precision`, so it cannot say
     * how good it is.
     *
     * Pass `$provenance` to store the other kind: a row the ladder wrote, which
     * records both. See {@see CoordinateSourcePrecedenceTest} for the full
     * matrix of what each provenance shape is and is not allowed to do.
     */
    private function storeExistingCoordinate(
        string $listingType,
        int $listingId,
        string $sourceAddress = '315 E Madison St',
        array $provenance = []
    ): void {
        PropertyLocationDna::create(array_merge([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'source_address' => $sourceAddress,
            'source_city'    => 'Tampa',
            'source_county'  => 'Hillsborough',
            'source_state'   => 'FL',
            'source_zip'     => '33602',
            'geocoded_lat'   => 27.9506,
            'geocoded_lng'   => -82.4572,
            'geocode_source' => 'saved_meta',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ], $provenance));
    }

    private function assertNotTheBrowserCoordinate(object $listing): void
    {
        $this->assertNotSame(
            self::BROWSER_LAT,
            (string) $listing->info(PropertyCoordinateMeta::LAT),
            'the browser-supplied latitude must never become the final coordinate'
        );
        $this->assertNotSame(
            self::BROWSER_LNG,
            (string) $listing->info(PropertyCoordinateMeta::LNG),
            'the browser-supplied longitude must never become the final coordinate'
        );
    }

    /** @return array<string, array{0: string, 1: int}> both roles, for parity coverage */
    public static function roles(): array
    {
        return [
            'seller'   => ['seller_agent', 981001],
            'landlord' => ['landlord_agent', 982001],
        ];
    }

    // ── CASE A: Bridge available ────────────────────────────────────────────

    /** @dataProvider roles */
    public function test_case_a_a_bridge_coordinate_wins_over_the_browser_pick(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true); // must not be reached
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        BridgeProperty::create([
            'listing_key'       => 'REROUTE-A-' . $listingId,
            'unparsed_address'  => '315 E Madison St',
            'city'              => 'Tampa',
            'state_or_province' => 'FL',
            'postal_code'       => '33602',
            'county_or_parish'  => 'Hillsborough',
            'latitude'          => 28.1111,
            'longitude'         => -82.2222,
        ]);

        $listing = $this->listing($listingId, array_merge($this->addressMeta(), [
            'mls_listing_key' => 'REROUTE-A-' . $listingId,
        ]));

        $outcome = $this->save($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_RESOLVED, $outcome['outcome']);
        $this->assertSame('bridge_mls', $outcome['provider']);
        $this->assertSame('28.1111', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertNotTheBrowserCoordinate($listing);

        Http::assertNothingSent();
    }

    // ── CASE B: AddressPoint available ──────────────────────────────────────

    /** @dataProvider roles */
    public function test_case_b_an_address_point_coordinate_wins_over_the_browser_pick(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true); // outranked, never called
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());

        $outcome = $this->save(
            $listing,
            $listingType,
            $this->ladderWithAddressPoint(27.9481234, -82.4581234)
        );

        $this->assertSame('address_point', $outcome['provider']);
        $this->assertSame('rooftop', $outcome['precision']);
        $this->assertSame('27.9481234', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame('NENA-TEST-0001', $listing->info(PropertyCoordinateMeta::SOURCE_REF));
        $this->assertNotTheBrowserCoordinate($listing);

        Http::assertNothingSent();
    }

    // ── CASE C: only Census resolves ────────────────────────────────────────

    /** @dataProvider roles */
    public function test_case_c_a_census_coordinate_wins_over_the_browser_pick(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());

        $outcome = $this->save($listing, $listingType);

        $this->assertSame('us_census', $outcome['provider']);
        $this->assertSame('interpolated', $outcome['precision']);
        $this->assertSame('27.948434712759', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertNotTheBrowserCoordinate($listing);
    }

    // ── CASE D: nothing resolves ────────────────────────────────────────────

    /** @dataProvider roles */
    public function test_case_d_nothing_resolving_leaves_no_coordinate_rather_than_the_browser_one(
        string $listingType,
        int $listingId
    ): void {
        // Every rung silent: no DNA row, no MLS key, corpus off, Census off.
        Http::fake();

        $listing = $this->listing($listingId, $this->addressMeta());

        $outcome = $this->save($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNRESOLVED, $outcome['outcome']);

        // The whole point of the phase: an unresolvable address ends with NO
        // coordinate, not with the widget's as a consolation prize.
        $this->assertEmpty(
            (string) ($listing->info(PropertyCoordinateMeta::LAT) ?? ''),
            'an unresolved address must not fall back to the browser coordinate'
        );
        $this->assertEmpty((string) ($listing->info(PropertyCoordinateMeta::LNG) ?? ''));

        // …and the address the user selected is still stored, so a later rung
        // (corpus, Bridge binding) can resolve it without them retyping it.
        $this->assertSame('315 E Madison St', $listing->info('address'));
        $this->assertSame('Tampa', $listing->info('property_city'));
        $this->assertSame('33602', $listing->info('property_zip'));

        Http::assertNothingSent();
    }

    // ── CASE E: an existing coordinate, trusted and not ─────────────────────

    /** @dataProvider roles */
    public function test_case_e_an_existing_coordinate_with_no_provenance_does_not_outrank_the_ladder(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true); // now genuinely reached
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        // The legacy row: 'saved_meta' and nothing else. Under the semantics this
        // file's header describes as the defect, that name alone was graded
        // Parcel and ended the ladder at rung 1.
        $this->storeExistingCoordinate($listingType, $listingId);
        $listing = $this->listing($listingId, $this->addressMeta());

        $outcome = $this->save($listing, $listingType);

        // It no longer does. A source name is not provenance: the row cannot say
        // which rung produced it, so rung 1 declines and a rung that can vouch
        // for its answer resolves instead.
        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_RESOLVED, $outcome['outcome']);
        $this->assertSame('us_census', $outcome['provider']);
        $this->assertSame('27.948434712759', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertNotSame(
            '27.9506',
            $listing->info(PropertyCoordinateMeta::LAT),
            'an unprovenanced stored coordinate must not win merely by being stored'
        );

        // The reroute's own claim, which this case exists to make: whoever wins,
        // it is still never the browser.
        $this->assertNotTheBrowserCoordinate($listing);
    }

    /** @dataProvider roles */
    public function test_case_e_an_existing_trusted_coordinate_is_reused_without_spending_a_request(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true); // must not be reached
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        // The same point, this time stored by the ladder: it records the rung
        // that produced it and how precise that rung was. Reuse is earned by
        // provenance, not assumed from a source name.
        $this->storeExistingCoordinate($listingType, $listingId, '315 E Madison St', [
            'geocode_provider'  => 'address_point',
            'geocode_precision' => CoordinatePrecision::Rooftop->value,
        ]);
        $listing = $this->listing($listingId, $this->addressMeta());

        $outcome = $this->save($listing, $listingType);

        // Rung 1 answers, and the provider recorded is the rung that originally
        // produced the point — not this adapter, and not the legacy source name.
        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_RESOLVED, $outcome['outcome']);
        $this->assertSame('address_point', $outcome['provider']);
        $this->assertSame('existing', $listing->info(PropertyCoordinateMeta::SOURCE));
        $this->assertSame('27.9506', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertNotTheBrowserCoordinate($listing);

        Http::assertNothingSent();
    }

    /** @dataProvider roles */
    public function test_case_e_a_second_save_of_the_same_address_re_resolves_nothing(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());
        $this->save($listing, $listingType);

        $second = $this->save($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNCHANGED, $second['outcome']);
        $this->assertSame('27.948434712759', $listing->info(PropertyCoordinateMeta::LAT));
        Http::assertSentCount(1); // the repeat save spent no provider budget
    }

    // ── CASE F: the address changes ─────────────────────────────────────────

    /** @dataProvider roles */
    public function test_case_f_a_coordinate_recorded_for_the_old_address_is_not_reused_for_the_new_one(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        // Address A resolves and is recorded with its provenance.
        $listing = $this->listing($listingId, $this->addressMeta());
        $this->save($listing, $listingType);

        $this->assertSame('27.948434712759', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame(
            '315 e madison st tampa fl 33602',
            $listing->info(PropertyCoordinateMeta::NORMALIZED_ADDRESS)
        );

        // The user moves the listing to address B, and nothing can resolve it.
        config()->set('census_geocoder.enabled', false);

        foreach ($this->addressMeta('900 W Kennedy Blvd', 'Tampa', 'FL', '33606') as $key => $value) {
            $listing->saveMeta($key, $value);
        }

        $outcome = $this->save($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNRESOLVED, $outcome['outcome']);

        // A's point must not survive as B's. Leaving it would not merely leave
        // stale data: the pipeline stamps pre_lat with whatever address it is
        // handed, so it would be RECORDED as B's coordinate.
        $this->assertSame('', (string) $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame('', (string) $listing->info(PropertyCoordinateMeta::LNG));

        // Its provenance goes with it — a provenance whose point is gone is not
        // a weaker claim, it is an unattached one.
        foreach (PropertyCoordinateMeta::provenanceKeys() as $key) {
            $this->assertSame('', (string) $listing->info($key), "{$key} must be discarded with the coordinate");
        }

        $this->assertSame(
            CoordinateProvenanceStatus::Incomplete,
            PropertyCoordinateMeta::classify(static fn (string $k) => $listing->info($k))
        );
    }

    /** @dataProvider roles */
    public function test_case_f_the_existing_rung_refuses_a_point_stored_for_a_different_address(
        string $listingType,
        int $listingId
    ): void {
        Http::fake();

        // The stored DNA row belongs to the OLD address.
        $this->storeExistingCoordinate($listingType, $listingId, '900 W Kennedy Blvd');

        $listing = $this->listing($listingId, $this->addressMeta());

        $outcome = $this->save($listing, $listingType);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNRESOLVED, $outcome['outcome']);
        $this->assertEmpty((string) ($listing->info(PropertyCoordinateMeta::LAT) ?? ''));
        Http::assertNothingSent();
    }

    /** @dataProvider roles */
    public function test_case_f_a_legacy_coordinate_with_no_recorded_address_is_not_deleted(
        string $listingType,
        int $listingId
    ): void {
        // The other half of the rule. Nothing records which address produced a
        // legacy value, so it can be shown neither to belong to this address nor
        // not to — and a delete on suspicion would destroy what this release
        // cannot replace. It keeps its legacy handling.
        Http::fake();

        $listing = $this->listing($listingId, array_merge($this->addressMeta(), [
            PropertyCoordinateMeta::LAT => '27.5',
            PropertyCoordinateMeta::LNG => '-82.5',
        ]));

        $this->save($listing, $listingType);

        $this->assertSame('27.5', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame('-82.5', $listing->info(PropertyCoordinateMeta::LNG));
    }

    /** @dataProvider roles */
    public function test_case_f_a_partial_provenance_is_not_read_as_proof_of_staleness(
        string $listingType,
        int $listingId
    ): void {
        // Half a provenance is an unverifiable statement, not a weaker one. This
        // class refuses to assemble a claim out of fragments everywhere else, and
        // it does not get to make an exception when the claim would authorise a
        // delete. Same fixture shape a provider-outage regression uses.
        Http::fake();

        $listing = $this->listing($listingId, array_merge($this->addressMeta(), [
            PropertyCoordinateMeta::LAT                => '27.9506',
            PropertyCoordinateMeta::LNG                => '-82.4572',
            PropertyCoordinateMeta::NORMALIZED_ADDRESS => 'some other address entirely',
            // No precision, no provider — readProvenance() reads this as absent.
        ]));

        $this->save($listing, $listingType);

        $this->assertSame('27.9506', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame('-82.4572', $listing->info(PropertyCoordinateMeta::LNG));
    }

    /** @dataProvider roles */
    public function test_case_f_a_manual_override_is_not_deleted_by_an_address_edit(
        string $listingType,
        int $listingId
    ): void {
        // A person is accountable for this one and stated a reason. Surfacing the
        // mismatch to them is a later decision; deleting it silently is not one a
        // machine gets to make. (Nothing writes manual provenance today — the
        // override path is not wired to any UI.)
        Http::fake();

        $manual = PropertyCoordinateResult::manual(
            latitude:       27.9506,
            longitude:      -82.4572,
            precision:      CoordinatePrecision::Rooftop,
            actorId:        4242,
            overrideReason: 'surveyed on site',
            normalizedAddress: 'some other address entirely',
        );

        $listing = $this->listing($listingId, array_merge(
            $this->addressMeta(),
            [
                PropertyCoordinateMeta::LAT => '27.9506',
                PropertyCoordinateMeta::LNG => '-82.4572',
            ],
            PropertyCoordinateMeta::provenanceFor($manual)
        ));

        $this->assertSame(
            CoordinateProvenanceStatus::Manual,
            PropertyCoordinateMeta::classify(static fn (string $k) => $listing->info($k))
        );

        $this->save($listing, $listingType);

        $this->assertSame('27.9506', $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame('4242', $listing->info(PropertyCoordinateMeta::ACTOR_ID));
        $this->assertSame('surveyed on site', $listing->info(PropertyCoordinateMeta::REASON));
    }

    // ── CASE G: provenance on an automatic coordinate ───────────────────────

    /** @dataProvider roles */
    public function test_case_g_an_automatically_resolved_coordinate_carries_full_provenance(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());
        $this->save($listing, $listingType);

        // The four original fields.
        $this->assertSame('interpolated', $listing->info(PropertyCoordinateMeta::PRECISION));
        $this->assertSame('us_census', $listing->info(PropertyCoordinateMeta::PROVIDER));
        $this->assertSame('geocoder', $listing->info(PropertyCoordinateMeta::SOURCE));
        $this->assertSame(
            '315 e madison st tampa fl 33602',
            $listing->info(PropertyCoordinateMeta::NORMALIZED_ADDRESS)
        );

        // PR #80's enrichment: a timestamp is always established for an
        // automatic resolution…
        $this->assertNotSame('', (string) $listing->info(PropertyCoordinateMeta::RECORDED_AT));
        $this->assertNotFalse(
            strtotime((string) $listing->info(PropertyCoordinateMeta::RECORDED_AT)),
            'recorded_at must be a parseable timestamp'
        );

        // …and no person is invented for one.
        $this->assertSame('', (string) $listing->info(PropertyCoordinateMeta::ACTOR_ID));
        $this->assertSame('', (string) $listing->info(PropertyCoordinateMeta::REASON));

        $provenance = PropertyCoordinateMeta::readProvenance(
            static fn (string $key) => $listing->info($key)
        );

        $this->assertNotNull($provenance, 'a rerouted coordinate must read back as provable');
    }

    /** @dataProvider roles */
    public function test_case_g_a_source_ref_is_stored_when_the_source_publishes_one(
        string $listingType,
        int $listingId
    ): void {
        Http::fake();

        $listing = $this->listing($listingId, $this->addressMeta());
        $this->save($listing, $listingType, $this->ladderWithAddressPoint(27.9481234, -82.4581234));

        $this->assertSame('NENA-TEST-0001', $listing->info(PropertyCoordinateMeta::SOURCE_REF));
    }

    /** @dataProvider roles */
    public function test_case_g_a_source_ref_is_empty_rather_than_invented_when_there_is_none(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());
        $this->save($listing, $listingType);

        // Census publishes no per-record identifier. Present-and-empty says
        // "this source has none"; it is not the same fact as absent.
        $this->assertSame('', (string) $listing->info(PropertyCoordinateMeta::SOURCE_REF));
    }

    // ── CASE H: never Manual ────────────────────────────────────────────────

    /** @dataProvider roles */
    public function test_case_h_no_rerouted_coordinate_is_ever_represented_as_manual(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        foreach ([null, $this->ladderWithAddressPoint(27.9481234, -82.4581234)] as $resolver) {
            $listing = $this->listing($listingId, $this->addressMeta());
            $this->save($listing, $listingType, $resolver);

            $reader = static fn (string $key) => $listing->info($key);

            $this->assertSame(
                CoordinateProvenanceStatus::Automatic,
                PropertyCoordinateMeta::classify($reader),
                'a ladder result is automatic provenance, never a manual override'
            );
            $this->assertNotSame(
                CoordinateSource::Manual->value,
                $listing->info(PropertyCoordinateMeta::SOURCE)
            );
        }
    }

    public function test_case_h_a_manual_representation_still_requires_an_accountable_person(): void
    {
        // The path a browser pick must never be able to take. Unchanged by this
        // phase and asserted here because the phase's whole claim is that an
        // automatic value cannot dress itself as a manual one.
        $this->expectException(\InvalidArgumentException::class);

        PropertyCoordinateResult::manual(
            latitude:       27.9,
            longitude:      -82.4,
            precision:      CoordinatePrecision::Rooftop,
            actorId:        0,
            overrideReason: 'browser said so',
        );
    }

    // ── CASE I: Location DNA observes the ladder's coordinate ───────────────

    /** @dataProvider roles */
    public function test_case_i_location_dna_receives_the_ladder_coordinate_and_not_the_discarded_one(
        string $listingType,
        int $listingId
    ): void {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($listingId, $this->addressMeta());
        $this->save($listing, $listingType);

        // Exactly what LocationDnaPipelineRunner would build from this listing's
        // meta, handed to the real geocode service — the step the dispatched job
        // reaches. Resolution happens before the dispatch precisely so this
        // payload carries the ladder's answer.
        $written = app(LocationDnaGeocodeService::class)->geocodeForListing($listingType, $listingId, [
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

        $this->assertTrue($written['success']);

        $row = PropertyLocationDna::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->firstOrFail();

        $this->assertEqualsWithDelta(27.948434712759, (float) $row->geocoded_lat, 0.0000001);
        $this->assertEqualsWithDelta(-82.458094358643, (float) $row->geocoded_lng, 0.0000001);

        // The failure this phase exists to remove: Location DNA computed against
        // the browser's point while the ladder's sat unused beside it.
        $this->assertNotEqualsWithDelta(
            (float) self::BROWSER_LAT,
            (float) $row->geocoded_lat,
            0.0001,
            'Location DNA must not be computed from the discarded browser coordinate'
        );

        $this->assertSame('interpolated', $row->geocode_precision);
        $this->assertSame('us_census', $row->geocode_provider);
    }

    /** @dataProvider roles */
    public function test_case_i_an_unresolved_address_hands_location_dna_no_coordinate_at_all(
        string $listingType,
        int $listingId
    ): void {
        Http::fake();

        $listing = $this->listing($listingId, $this->addressMeta());
        $this->save($listing, $listingType);

        $written = app(LocationDnaGeocodeService::class)->geocodeForListing($listingType, $listingId, [
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

        // Not an error — an unknown coordinate. The approved geocoder for this
        // address does not exist yet, and Google is not it.
        $this->assertFalse($written['success']);

        $row = PropertyLocationDna::where('listing_type', $listingType)
            ->where('listing_id', $listingId)
            ->firstOrFail();

        $this->assertNull($row->geocoded_lat);
        $this->assertSame('skipped', $row->geocode_status);
        $this->assertSame('non_google_geocoder_unavailable', $row->geocode_error);

        Http::assertNothingSent();
    }

    // ── CASE J: the seam itself ─────────────────────────────────────────────

    public function test_case_j_the_write_seam_records_the_address_selection_and_no_coordinate(): void
    {
        $listing   = $this->listing(983001, $this->addressMeta());
        $component = $this->componentAfterBrowserPick();

        $component->saveSelectedPropertyAddressMeta($listing);

        // The legacy address-selection metadata still round-trips…
        $this->assertSame('ChIJ-browser-pick', $listing->info('google_place_id'));

        // …and the coordinate it arrived with does not reach the meta at all.
        $this->assertNull($listing->info(PropertyCoordinateMeta::LAT));
        $this->assertNull($listing->info(PropertyCoordinateMeta::LNG));
    }

    public function test_case_j_no_create_offer_component_writes_a_raw_property_coordinate(): void
    {
        foreach (self::COMPONENTS as $path) {
            $source = file_get_contents(base_path($path));

            $this->assertIsString($source, "{$path} must be readable");

            foreach ([
                "saveMeta('property_lat'",
                'saveMeta("property_lat"',
                "saveMeta('property_lng'",
                'saveMeta("property_lng"',
            ] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $source,
                    basename($path) . " must not write {$forbidden} — the ladder is the only writer"
                );
            }
        }
    }

    public function test_case_j_every_create_offer_component_uses_the_shared_write_seam(): void
    {
        foreach (self::COMPONENTS as $path) {
            $source = file_get_contents(base_path($path));

            $this->assertStringContainsString(
                'use RecordsSelectedPropertyAddress;',
                $source,
                basename($path) . ' must use the shared write seam'
            );
            $this->assertStringContainsString(
                '$this->saveSelectedPropertyAddressMeta($auction);',
                $source,
                basename($path) . ' must call the shared write seam'
            );
        }
    }

    public function test_case_j_the_seam_is_used_by_exactly_the_four_create_offer_components(): void
    {
        $users = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Http/Livewire'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), 'use RecordsSelectedPropertyAddress;')) {
                $users[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        sort($users);
        $expected = self::COMPONENTS;
        sort($expected);

        // Hire Agent never declared the geo fields, so it never had the write to
        // remove; Buyer and Tenant carry search areas rather than a property.
        $this->assertSame($expected, $users);
    }

    public function test_case_j_the_seam_writes_no_coordinate_and_reaches_no_provider(): void
    {
        $source = file_get_contents(
            base_path('app/Http/Livewire/OfferListing/Concerns/RecordsSelectedPropertyAddress.php')
        );

        $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) $source);

        foreach ([
            'property_lat',
            'property_lng',
            'googleapis',
            'GooglePlaces',
            'Geocoder',
            'ComputeLocationDna',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                (string) $code,
                "the write seam must not reference {$forbidden}"
            );
        }
    }

    // ── the resolution path actually reads the address that was just saved ──

    public function test_resolution_reads_meta_written_after_the_relation_was_loaded(): void
    {
        // The defect that made the reroute a no-op on a real component, and that
        // the removed raw write had been masking. `saveMeta()` writes through the
        // relation QUERY; `info()` reads the loaded relation COLLECTION; Eloquent
        // never invalidates the second when the first runs. A save boundary does
        // both in that order, so without the refresh the service reads an empty
        // address and declines — silently, because an insufficient address is an
        // ordinary answer rather than an error.
        $user = \App\Models\User::factory()->create(['user_type' => 'seller']);

        $auction = new \App\Models\SellerAgentAuction();
        $auction->user_id  = $user->id;
        $auction->title    = 'Reroute relation-cache regression';
        $auction->address  = '315 E Madison St';
        $auction->is_draft = true;
        $auction->save();

        // Whatever the component did first that loaded the relation. On a new
        // listing it loads empty, and stays empty for the rest of the request.
        $auction->meta;
        $this->assertFalse($auction->info('address'), 'precondition: the relation is loaded and empty');

        foreach ($this->addressMeta() as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        $this->assertFalse(
            $auction->info('address'),
            'precondition: saveMeta() does not refresh the loaded relation'
        );

        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $resolver = new class {
            use \App\Http\Livewire\OfferListing\Concerns\ResolvesPropertyCoordinates {
                resolvePropertyCoordinates as public;
            }
        };

        $outcome = $resolver->resolvePropertyCoordinates($auction, 'seller_agent');

        $this->assertSame(
            PropertyCoordinatePersistenceService::OUTCOME_RESOLVED,
            $outcome['outcome'],
            'the address saved a moment earlier must be visible to the ladder'
        );
        $this->assertSame('us_census', $outcome['provider']);

        $auction->unsetRelation('meta');
        $this->assertSame('27.948434712759', $auction->info(PropertyCoordinateMeta::LAT));
    }

    public function test_case_j_the_autocomplete_provider_is_unchanged_by_this_phase(): void
    {
        // The address pick may still come from the existing widget. Decoupling
        // it from the coordinate is this phase; replacing it is the next one.
        $blade = (string) file_get_contents(
            base_path('resources/views/components/byo-address-autocomplete.blade.php')
        );

        $this->assertStringContainsString('fillFromResolvedAddress', $blade);
        $this->assertFalse(config('google_places.enabled'), 'the legacy Google path stays fail-closed');
        $this->assertFalse((bool) config('address_point_corpus.enabled'), 'the corpus stays inert');
    }
}
