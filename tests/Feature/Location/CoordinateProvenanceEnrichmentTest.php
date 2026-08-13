<?php

namespace Tests\Feature\Location;

use App\Models\BridgeProperty;
use App\Services\Location\Coordinates\Adapters\AddressPointCoordinateAdapter;
use App\Services\Location\Coordinates\Adapters\BridgeMlsCoordinatesAdapter;
use App\Services\Location\Coordinates\Adapters\CensusGeocoderAdapter;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProvenanceStatus;
use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateMeta;
use App\Services\Location\Coordinates\PropertyCoordinateResolver;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use App\Services\Location\PropertyCoordinatePersistenceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Enriched coordinate provenance: timestamp, source ref, actor, reason.
 *
 * WHAT THIS PHASE IS FOR
 * ----------------------
 * The four original provenance keys record what produced a coordinate and how
 * precise it is. They cannot answer three questions a manual override and an
 * authoritative corpus both need: when was this established, which upstream
 * record said so, and — if a person decided it — who and why.
 *
 * Everything here is storage contract. No override UI exists, no route produces
 * one, and nothing in this suite creates a feature. What it proves is that the
 * shape is recordable, that automatic sources do not acquire a fabricated
 * author, and that coordinates written before any of this remain readable and
 * are classified honestly rather than flattered.
 *
 * WHY THE ADDRESS-POINT RUNG IS STUBBED AND THE OTHER TWO ARE NOT
 * --------------------------------------------------------------
 * Bridge answers from a mirror table and Census from a fakeable HTTP call, so
 * both run for real here. The corpus lives on a PostGIS connection and is read
 * with ST_X/ST_Y, which the SQLite suite cannot execute — a corpus row cannot be
 * inserted in this environment at all. Rather than pretend otherwise, the rung
 * is stubbed for the persistence assertions and its own forwarding of
 * `source_ref` is checked against the source it actually runs.
 */
class CoordinateProvenanceEnrichmentTest extends TestCase
{
    use DatabaseTransactions;

    private const CENSUS       = 'geocoding.geo.census.gov/*';
    private const LISTING_TYPE = 'seller_agent';
    private const LISTING_ID   = 994001;
    private const LISTING_KEY  = 'STELLAR-MFR-4410099';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('census_geocoder.enabled', false);
        config()->set('google_places.enabled', false);
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    /** A stand-in for a saved Seller/Landlord auction row (EAV meta accessors). */
    private function listing(array $meta = []): object
    {
        return new class (self::LISTING_ID, $meta) {
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

    /** @return array<string, string> */
    private function addressMeta(array $extra = []): array
    {
        return array_merge([
            'address'         => '315 E Madison St',
            'unit_address'    => '',
            'property_city'   => 'Tampa',
            'property_state'  => 'FL',
            'property_county' => 'Hillsborough',
            'property_zip'    => '33602',
        ], $extra);
    }

    private function address(string $street = '315 E Madison St'): PropertyAddress
    {
        return new PropertyAddress(
            address:     $street,
            unitAddress: '',
            city:        'Tampa',
            county:      'Hillsborough',
            state:       'FL',
            zip:         '33602',
            listingType: self::LISTING_TYPE,
            listingId:   self::LISTING_ID,
        );
    }

    private function censusMatch(): array
    {
        return ['result' => ['addressMatches' => [[
            'coordinates'       => ['x' => -82.458094358643, 'y' => 27.948434712759],
            'addressComponents' => ['state' => 'FL', 'zip' => '33602'],
            'matchedAddress'    => '315 MADISON ST, TAMPA, FL, 33602',
        ]]]];
    }

    /** @param list<CoordinateProviderAdapterInterface> $rungs */
    private function persistWith(object $listing, array $rungs): array
    {
        return (new PropertyCoordinatePersistenceService(new PropertyCoordinateResolver($rungs)))
            ->resolveAndPersist($listing, self::LISTING_TYPE);
    }

    /** A rung standing in for the corpus, which cannot be inserted into in SQLite. */
    private function addressPointRung(?string $sourceRef): CoordinateProviderAdapterInterface
    {
        return new class ($sourceRef) implements CoordinateProviderAdapterInterface {
            public function __construct(private readonly ?string $ref)
            {
            }

            public function providerId(): string { return 'address_point'; }
            public function source(): CoordinateSource { return CoordinateSource::AddressPoint; }
            public function requiresNetwork(): bool { return false; }
            public function isAvailable(): bool { return true; }

            public function resolve(PropertyAddress $address): PropertyCoordinateResult
            {
                return PropertyCoordinateResult::resolved(
                    latitude:  27.9506,
                    longitude: -82.4572,
                    precision: CoordinatePrecision::Rooftop,
                    source:    CoordinateSource::AddressPoint,
                    provider:  'address_point',
                    normalizedAddress: $address->coordinateLookupLine(),
                    sourceRef: $this->ref,
                );
            }
        };
    }

    // ── A. Bridge ───────────────────────────────────────────────────────────

    public function test_a_bridge_coordinate_stores_full_automatic_provenance(): void
    {
        BridgeProperty::create([
            'listing_key'       => self::LISTING_KEY,
            'unparsed_address'  => '315 E Madison St',
            'city'              => 'Tampa',
            'state_or_province' => 'FL',
            'postal_code'       => '33602',
            'county_or_parish'  => 'Hillsborough',
            'latitude'          => 28.1111,
            'longitude'         => -82.2222,
        ]);

        $listing = $this->listing($this->addressMeta(['mls_listing_key' => self::LISTING_KEY]));

        $outcome = $this->persistWith($listing, [new BridgeMlsCoordinatesAdapter()]);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_RESOLVED, $outcome['outcome']);
        $this->assertSame('mls', $listing->info(PropertyCoordinateMeta::SOURCE));
        $this->assertSame('bridge_mls', $listing->info(PropertyCoordinateMeta::PROVIDER));
        $this->assertSame('parcel', $listing->info(PropertyCoordinateMeta::PRECISION));
        $this->assertNotSame('', $listing->info(PropertyCoordinateMeta::RECORDED_AT));
        $this->assertSame(
            CoordinateProvenanceStatus::Automatic,
            PropertyCoordinateMeta::classify(fn (string $k) => $listing->info($k))
        );
    }

    // ── D. source_ref ───────────────────────────────────────────────────────

    public function test_the_bridge_listing_key_is_retained_as_the_source_ref(): void
    {
        BridgeProperty::create([
            'listing_key'       => self::LISTING_KEY,
            'unparsed_address'  => '315 E Madison St',
            'city'              => 'Tampa',
            'state_or_province' => 'FL',
            'postal_code'       => '33602',
            'county_or_parish'  => 'Hillsborough',
            'latitude'          => 28.1111,
            'longitude'         => -82.2222,
        ]);

        $listing = $this->listing($this->addressMeta(['mls_listing_key' => self::LISTING_KEY]));

        $this->persistWith($listing, [new BridgeMlsCoordinatesAdapter()]);

        $this->assertSame(self::LISTING_KEY, $listing->info(PropertyCoordinateMeta::SOURCE_REF));
    }

    // ── B. AddressPoint ─────────────────────────────────────────────────────

    public function test_an_address_point_coordinate_stores_full_automatic_provenance(): void
    {
        $listing = $this->listing($this->addressMeta());

        $outcome = $this->persistWith($listing, [$this->addressPointRung('NENA-SITEADDID-77123')]);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_RESOLVED, $outcome['outcome']);
        $this->assertSame('address_point', $listing->info(PropertyCoordinateMeta::SOURCE));
        $this->assertSame('address_point', $listing->info(PropertyCoordinateMeta::PROVIDER));
        $this->assertSame('rooftop', $listing->info(PropertyCoordinateMeta::PRECISION));
        $this->assertSame('NENA-SITEADDID-77123', $listing->info(PropertyCoordinateMeta::SOURCE_REF));
        $this->assertNotSame('', $listing->info(PropertyCoordinateMeta::RECORDED_AT));
        $this->assertSame(
            CoordinateProvenanceStatus::Automatic,
            PropertyCoordinateMeta::classify(fn (string $k) => $listing->info($k))
        );
    }

    public function test_a_source_that_publishes_no_reference_records_an_empty_one(): void
    {
        // Empty is the record that this source publishes no identifier. It is not
        // the same as the key being absent, which is what a pre-enrichment row
        // looks like — see the legacy test below.
        $listing = $this->listing($this->addressMeta());

        $this->persistWith($listing, [$this->addressPointRung(null)]);

        $this->assertSame('', $listing->info(PropertyCoordinateMeta::SOURCE_REF));
        $this->assertSame(
            CoordinateProvenanceStatus::Automatic,
            PropertyCoordinateMeta::classify(fn (string $k) => $listing->info($k))
        );
    }

    public function test_the_corpus_rung_forwards_its_source_ref(): void
    {
        // The corpus needs PostGIS, so this is asserted against the code the rung
        // actually runs: it selects source_ref and passes it into the result.
        $source = file_get_contents(
            (new \ReflectionClass(AddressPointCoordinateAdapter::class))->getFileName()
        );

        $this->assertStringContainsString('source_ref', $source);
        $this->assertStringContainsString("sourceRef: \$point['source_ref']", $source);
    }

    // ── C. Census ───────────────────────────────────────────────────────────

    public function test_a_census_coordinate_stores_its_lower_quality_identity(): void
    {
        config()->set('census_geocoder.enabled', true);
        Http::fake([self::CENSUS => Http::response($this->censusMatch())]);

        $listing = $this->listing($this->addressMeta());

        $outcome = $this->persistWith($listing, [new CensusGeocoderAdapter()]);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_RESOLVED, $outcome['outcome']);
        $this->assertSame('geocoder', $listing->info(PropertyCoordinateMeta::SOURCE));
        $this->assertSame('us_census', $listing->info(PropertyCoordinateMeta::PROVIDER));
        // Interpolated, not parcel: a house number placed along a street range.
        $this->assertSame('interpolated', $listing->info(PropertyCoordinateMeta::PRECISION));
        $this->assertNotSame('', $listing->info(PropertyCoordinateMeta::RECORDED_AT));
        $this->assertSame(
            CoordinateProvenanceStatus::Automatic,
            PropertyCoordinateMeta::classify(fn (string $k) => $listing->info($k))
        );
    }

    // ── E. automatic has a timestamp but no author ──────────────────────────

    public function test_automatic_resolution_records_a_timestamp_and_no_actor(): void
    {
        $listing = $this->listing($this->addressMeta());

        $this->persistWith($listing, [$this->addressPointRung('REF-1')]);

        $recordedAt = $listing->info(PropertyCoordinateMeta::RECORDED_AT);
        $this->assertNotSame('', $recordedAt);
        $this->assertNotFalse(
            strtotime($recordedAt),
            'The stored timestamp must be parseable, not a formatted-for-humans string'
        );

        // The whole point: nobody decided this, and the record says nobody did.
        $this->assertSame('', $listing->info(PropertyCoordinateMeta::ACTOR_ID));
        $this->assertSame('', $listing->info(PropertyCoordinateMeta::REASON));
    }

    public function test_the_recorded_timestamp_is_the_results_own_not_the_moment_of_writing(): void
    {
        // Load-bearing for auditing coordinate age: a rung reusing an older point
        // reports when that point was established, and the persisted value must
        // preserve it rather than reset to now.
        $established = new \DateTimeImmutable('2026-01-02T03:04:05+00:00');

        $rung = new class ($established) implements CoordinateProviderAdapterInterface {
            public function __construct(private readonly \DateTimeImmutable $at)
            {
            }

            public function providerId(): string { return 'address_point'; }
            public function source(): CoordinateSource { return CoordinateSource::AddressPoint; }
            public function requiresNetwork(): bool { return false; }
            public function isAvailable(): bool { return true; }

            public function resolve(PropertyAddress $address): PropertyCoordinateResult
            {
                return PropertyCoordinateResult::resolved(
                    latitude:  27.9506,
                    longitude: -82.4572,
                    precision: CoordinatePrecision::Rooftop,
                    source:    CoordinateSource::AddressPoint,
                    provider:  'address_point',
                    normalizedAddress: $address->coordinateLookupLine(),
                    resolvedAt: $this->at,
                );
            }
        };

        $listing = $this->listing($this->addressMeta());
        $this->persistWith($listing, [$rung]);

        $this->assertSame(
            '2026-01-02',
            (new \DateTimeImmutable($listing->info(PropertyCoordinateMeta::RECORDED_AT)))->format('Y-m-d')
        );
    }

    // ── F. manual provenance is representable ───────────────────────────────

    public function test_a_manual_override_can_be_represented_without_any_ui(): void
    {
        $result = PropertyCoordinateResult::manual(
            latitude:  27.9600,
            longitude: -82.4600,
            precision: CoordinatePrecision::Rooftop,
            actorId:   4242,
            overrideReason: 'Corpus point sits on the parcel entrance, not the dwelling',
            normalizedAddress: $this->address()->coordinateLookupLine(),
        );

        $stored = PropertyCoordinateMeta::provenanceFor($result);

        $this->assertSame('manual', $stored[PropertyCoordinateMeta::SOURCE]);
        $this->assertSame('manual_override', $stored[PropertyCoordinateMeta::PROVIDER]);
        $this->assertSame('rooftop', $stored[PropertyCoordinateMeta::PRECISION]);
        $this->assertSame('4242', $stored[PropertyCoordinateMeta::ACTOR_ID]);
        $this->assertSame(
            'Corpus point sits on the parcel entrance, not the dwelling',
            $stored[PropertyCoordinateMeta::REASON]
        );
        $this->assertNotSame('', $stored[PropertyCoordinateMeta::RECORDED_AT]);

        $this->assertSame(
            CoordinateProvenanceStatus::Manual,
            PropertyCoordinateMeta::classify(fn (string $k) => $stored[$k] ?? null)
        );
    }

    public function test_a_manual_override_cannot_be_created_without_a_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PropertyCoordinateResult::manual(
            latitude:  27.96,
            longitude: -82.46,
            precision: CoordinatePrecision::Rooftop,
            actorId:   7,
            overrideReason: '   ',
        );
    }

    public function test_a_manual_override_cannot_be_created_without_an_actor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PropertyCoordinateResult::manual(
            latitude:  27.96,
            longitude: -82.46,
            precision: CoordinatePrecision::Rooftop,
            actorId:   0,
            overrideReason: 'anything',
        );
    }

    public function test_no_ui_or_route_produces_a_manual_override_yet(): void
    {
        // Pins the phase boundary: the contract exists, the feature does not.
        //
        // Scanned for the CALL, not the name — the value object and the status
        // enum both reference `manual()` in their docblocks, and a doc reference
        // is not a feature. The directories are the ones a feature would have to
        // live in: a controller, a component, a route or a view.
        $callSites = [];

        foreach (['app/Http', 'routes', 'resources'] as $dir) {
            $path = base_path($dir);

            if (! is_dir($path)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                if (str_contains((string) file_get_contents($file->getPathname()), 'PropertyCoordinateResult::manual(')) {
                    $callSites[] = $file->getPathname();
                }
            }
        }

        $this->assertSame(
            [],
            $callSites,
            'Manual override is a contract this phase, not a feature: ' . implode(', ', $callSites)
        );
    }

    // ── G. legacy rows ──────────────────────────────────────────────────────

    public function test_a_legacy_row_without_the_new_keys_stays_readable_and_reads_as_incomplete(): void
    {
        // Exactly what a coordinate resolved before this phase looks like: the
        // original four keys, none of the new ones.
        $legacy = [
            PropertyCoordinateMeta::PRECISION          => 'parcel',
            PropertyCoordinateMeta::PROVIDER           => 'bridge_mls',
            PropertyCoordinateMeta::SOURCE             => 'mls',
            PropertyCoordinateMeta::NORMALIZED_ADDRESS => '315 e madison st tampa fl 33602',
        ];

        $reader = fn (string $k) => $legacy[$k] ?? null;

        $provenance = PropertyCoordinateMeta::readProvenance($reader);

        $this->assertNotNull($provenance, 'A legacy row must remain readable, not crash or vanish');
        $this->assertSame('parcel', $provenance['precision']);
        $this->assertSame('bridge_mls', $provenance['provider']);
        $this->assertSame('', $provenance['recorded_at'], 'The absent key reads empty, not invented');
        $this->assertSame('', $provenance['source_ref']);

        // Still classified Automatic — it is complete provenance by the rules
        // that existed when it was written, and the enriched keys are additive.
        $this->assertSame(CoordinateProvenanceStatus::Automatic, PropertyCoordinateMeta::classify($reader));
    }

    public function test_a_row_with_no_provenance_at_all_is_incomplete(): void
    {
        $reader = fn (string $k) => null;

        $this->assertNull(PropertyCoordinateMeta::readProvenance($reader));
        $this->assertSame(CoordinateProvenanceStatus::Incomplete, PropertyCoordinateMeta::classify($reader));
        $this->assertFalse(CoordinateProvenanceStatus::Incomplete->isComplete());
    }

    public function test_partial_provenance_is_incomplete_rather_than_partially_trusted(): void
    {
        $reader = fn (string $k) => $k === PropertyCoordinateMeta::PRECISION ? 'parcel' : null;

        $this->assertSame(CoordinateProvenanceStatus::Incomplete, PropertyCoordinateMeta::classify($reader));
    }

    // ── I. nothing is upgraded to Manual ────────────────────────────────────

    public function test_a_manual_source_without_an_actor_is_not_treated_as_manual(): void
    {
        // The forgery case. A raw writer stamping source=manual must not thereby
        // acquire the authority of a signed human decision.
        $forged = [
            PropertyCoordinateMeta::PRECISION          => 'rooftop',
            PropertyCoordinateMeta::PROVIDER           => 'manual_override',
            PropertyCoordinateMeta::SOURCE             => 'manual',
            PropertyCoordinateMeta::NORMALIZED_ADDRESS => '315 e madison st tampa fl 33602',
        ];

        $this->assertSame(
            CoordinateProvenanceStatus::Incomplete,
            PropertyCoordinateMeta::classify(fn (string $k) => $forged[$k] ?? null),
            'source=manual without an actor and a reason is not a manual override'
        );
    }

    public function test_a_manual_source_without_a_reason_is_not_treated_as_manual(): void
    {
        $forged = [
            PropertyCoordinateMeta::PRECISION          => 'rooftop',
            PropertyCoordinateMeta::PROVIDER           => 'manual_override',
            PropertyCoordinateMeta::SOURCE             => 'manual',
            PropertyCoordinateMeta::NORMALIZED_ADDRESS => '315 e madison st tampa fl 33602',
            PropertyCoordinateMeta::ACTOR_ID           => '99',
            PropertyCoordinateMeta::REASON             => '',
        ];

        $this->assertSame(
            CoordinateProvenanceStatus::Incomplete,
            PropertyCoordinateMeta::classify(fn (string $k) => $forged[$k] ?? null)
        );
    }

    public function test_an_automatic_source_carrying_an_actor_is_self_contradictory(): void
    {
        $contradictory = [
            PropertyCoordinateMeta::PRECISION          => 'parcel',
            PropertyCoordinateMeta::PROVIDER           => 'bridge_mls',
            PropertyCoordinateMeta::SOURCE             => 'mls',
            PropertyCoordinateMeta::NORMALIZED_ADDRESS => '315 e madison st tampa fl 33602',
            PropertyCoordinateMeta::ACTOR_ID           => '5',
        ];

        $this->assertSame(
            CoordinateProvenanceStatus::Incomplete,
            PropertyCoordinateMeta::classify(fn (string $k) => $contradictory[$k] ?? null),
            'An MLS feed record has no author; a row claiming one is not trustworthy'
        );
    }

    public function test_an_automatic_resolution_never_produces_a_manual_source(): void
    {
        $listing = $this->listing($this->addressMeta());

        $this->persistWith($listing, [$this->addressPointRung('REF-9')]);

        $this->assertNotSame('manual', $listing->info(PropertyCoordinateMeta::SOURCE));
        $this->assertSame('', $listing->info(PropertyCoordinateMeta::ACTOR_ID));
    }

    // ── H. address-change invalidation is unchanged ─────────────────────────

    public function test_an_address_change_still_forces_re_resolution(): void
    {
        $listing = $this->listing($this->addressMeta());
        $this->persistWith($listing, [$this->addressPointRung('REF-A')]);

        $firstNormalized = $listing->info(PropertyCoordinateMeta::NORMALIZED_ADDRESS);
        $this->assertNotSame('', $firstNormalized);

        // Same listing, different street. The stored normalized address no longer
        // matches, so the service must resolve again rather than report unchanged.
        $moved = $this->listing(array_merge($listing->saved, ['address' => '317 E Madison St']));

        $outcome = $this->persistWith($moved, [$this->addressPointRung('REF-B')]);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_RESOLVED, $outcome['outcome']);
        $this->assertNotSame($firstNormalized, $moved->info(PropertyCoordinateMeta::NORMALIZED_ADDRESS));
        $this->assertSame('REF-B', $moved->info(PropertyCoordinateMeta::SOURCE_REF));
    }

    public function test_an_unchanged_address_is_still_reported_unchanged(): void
    {
        $listing = $this->listing($this->addressMeta());
        $this->persistWith($listing, [$this->addressPointRung('REF-A')]);

        $again = $this->listing($listing->saved);
        $outcome = $this->persistWith($again, [$this->addressPointRung('REF-B')]);

        $this->assertSame(PropertyCoordinatePersistenceService::OUTCOME_UNCHANGED, $outcome['outcome']);
    }

    // ── J. no Google ────────────────────────────────────────────────────────

    public function test_this_phase_introduces_no_google_source(): void
    {
        foreach ([
            PropertyCoordinateMeta::class,
            PropertyCoordinateResult::class,
            CoordinateProvenanceStatus::class,
        ] as $class) {
            $source = file_get_contents((new \ReflectionClass($class))->getFileName());

            $this->assertStringNotContainsString('googleapis', $source, $class);
            $this->assertStringNotContainsStringIgnoringCase('google_places', $source, $class);
        }
    }

    public function test_no_outbound_request_is_made_while_persisting_a_local_coordinate(): void
    {
        Http::fake();

        $listing = $this->listing($this->addressMeta());
        $this->persistWith($listing, [$this->addressPointRung('REF-1')]);

        Http::assertNothingSent();
    }
}
