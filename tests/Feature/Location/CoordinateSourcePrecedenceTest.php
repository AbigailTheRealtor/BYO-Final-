<?php

namespace Tests\Feature\Location;

use App\Models\PropertyLocationDna;
use App\Services\Location\Coordinates\Adapters\AddressPointCoordinateAdapter;
use App\Services\Location\Coordinates\Adapters\BridgeMlsCoordinatesAdapter;
use App\Services\Location\Coordinates\Adapters\CensusGeocoderAdapter;
use App\Services\Location\Coordinates\Adapters\ExistingCoordinatesAdapter;
use App\Services\Location\Coordinates\Adapters\StandardCoordinateLadder;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateProviderAdapterInterface;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateResolver;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Which coordinate source wins, when more than one could answer.
 *
 * THE DEFECT THIS PINS
 * --------------------
 * {@see ExistingCoordinatesAdapter} is rung 1 and {@see PropertyCoordinateResolver}
 * stops at the first rung that resolves. So anything the Existing rung is willing
 * to vouch for becomes permanent for that address: no later rung is ever consulted
 * again. Before this suite, that rung graded a coordinate by the NAME of its
 * `geocode_source` — 'saved_meta' and 'google' were both read as Parcel — which
 * meant an unvalidated latitude/longitude posted by the browser outranked the MLS
 * feed and the address-point corpus, forever.
 *
 * The rule now: a stored coordinate is reused only when its recorded ladder
 * provenance shows it came from a rung no later rung can improve upon, and only
 * when its precision was actually recorded rather than guessed.
 *
 * WHY THE DOWNSTREAM RUNGS ARE STUBS
 * ----------------------------------
 * The rung under test is the Existing one. Bridge answers from a mirror table and
 * the corpus rung answers from a separate spatial connection that is flag-inert
 * and empty in every environment this suite runs in — standing them up would test
 * their fixtures, not precedence. Each stub declares the real provider id its rung
 * uses, so a rename that broke the trust list would fail here.
 *
 * Case E is the exception and is asserted against the real
 * {@see StandardCoordinateLadder}, because "Bridge outranks AddressPoint" is a
 * statement about the actual assembled order and must not be provable by a stub.
 */
class CoordinateSourcePrecedenceTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 8801;

    private const EXISTING_LAT = 27.9506;
    private const EXISTING_LNG = -82.4572;

    private const BRIDGE_LAT = 28.1111;
    private const BRIDGE_LNG = -82.2222;

    private const CORPUS_LAT = 29.3333;
    private const CORPUS_LNG = -82.7777;

    // ── fixtures ────────────────────────────────────────────────────────────

    private function address(string $street = '123 Main St'): PropertyAddress
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

    /**
     * A stored coordinate with whatever provenance the case under test needs.
     *
     * @param array<string, mixed> $provenance
     */
    private function storedCoordinate(array $provenance): void
    {
        PropertyLocationDna::create(array_merge([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_county'  => 'Hillsborough',
            'source_state'   => 'FL',
            'source_zip'     => '33602',
            'geocoded_lat'   => self::EXISTING_LAT,
            'geocoded_lng'   => self::EXISTING_LNG,
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ], $provenance));
    }

    /** A rung that always answers, declaring the real provider id of the rung it stands in for. */
    private function stubRung(
        string $providerId,
        CoordinateSource $source,
        CoordinatePrecision $precision,
        float $lat,
        float $lng,
    ): CoordinateProviderAdapterInterface {
        return new class ($providerId, $source, $precision, $lat, $lng) implements CoordinateProviderAdapterInterface {
            public function __construct(
                private readonly string $id,
                private readonly CoordinateSource $src,
                private readonly CoordinatePrecision $precision,
                private readonly float $lat,
                private readonly float $lng,
            ) {
            }

            public function providerId(): string { return $this->id; }
            public function source(): CoordinateSource { return $this->src; }
            public function requiresNetwork(): bool { return false; }
            public function isAvailable(): bool { return true; }

            public function resolve(PropertyAddress $address): PropertyCoordinateResult
            {
                return PropertyCoordinateResult::resolved(
                    latitude:  $this->lat,
                    longitude: $this->lng,
                    precision: $this->precision,
                    source:    $this->src,
                    provider:  $this->id,
                );
            }
        };
    }

    private function bridgeRung(): CoordinateProviderAdapterInterface
    {
        return $this->stubRung(
            'bridge_mls', CoordinateSource::Mls, CoordinatePrecision::Parcel,
            self::BRIDGE_LAT, self::BRIDGE_LNG
        );
    }

    private function addressPointRung(): CoordinateProviderAdapterInterface
    {
        return $this->stubRung(
            'address_point', CoordinateSource::AddressPoint, CoordinatePrecision::Rooftop,
            self::CORPUS_LAT, self::CORPUS_LNG
        );
    }

    /** A rung that fails the test if the ladder ever reaches it. */
    private function neverReachedRung(string $providerId): CoordinateProviderAdapterInterface
    {
        return new class ($providerId, $this) implements CoordinateProviderAdapterInterface {
            public function __construct(
                private readonly string $id,
                private readonly \PHPUnit\Framework\TestCase $test,
            ) {
            }

            public function providerId(): string { return $this->id; }
            public function source(): CoordinateSource { return CoordinateSource::Geocoder; }
            public function requiresNetwork(): bool { return false; }
            public function isAvailable(): bool { return true; }

            public function resolve(PropertyAddress $address): PropertyCoordinateResult
            {
                $this->test->fail("The ladder must not reach {$this->id}");
            }
        };
    }

    /** @param list<CoordinateProviderAdapterInterface> $downstream */
    private function resolveWith(array $downstream): PropertyCoordinateResult
    {
        return (new PropertyCoordinateResolver(
            array_merge([new ExistingCoordinatesAdapter()], $downstream)
        ))->resolve($this->address());
    }

    // ── CASE A ──────────────────────────────────────────────────────────────

    public function test_case_a_unprovenanced_saved_meta_does_not_block_bridge(): void
    {
        // The browser wrote a coordinate; Location DNA recorded it as 'saved_meta'
        // with no ladder provenance. It must not outrank the MLS feed.
        $this->storedCoordinate(['geocode_source' => 'saved_meta']);

        $result = $this->resolveWith([$this->bridgeRung()]);

        $this->assertTrue($result->isResolved());
        $this->assertSame(CoordinateSource::Mls, $result->source, 'Bridge must win');
        $this->assertSame(self::BRIDGE_LAT, $result->latitude);
        $this->assertNotSame(self::EXISTING_LAT, $result->latitude);
    }

    // ── CASE B ──────────────────────────────────────────────────────────────

    public function test_case_b_unprovenanced_saved_meta_does_not_block_address_point(): void
    {
        $this->storedCoordinate(['geocode_source' => 'saved_meta']);

        $result = $this->resolveWith([$this->addressPointRung()]);

        $this->assertTrue($result->isResolved());
        $this->assertSame(CoordinateSource::AddressPoint, $result->source, 'The corpus must win');
        $this->assertSame(self::CORPUS_LAT, $result->latitude);
    }

    // ── CASE C ──────────────────────────────────────────────────────────────

    public function test_case_c_legacy_google_does_not_block_address_point(): void
    {
        // A legacy Google row carries no ladder provenance either. The old
        // google -> Parcel inference is gone and is not reinstated for
        // compatibility: an authoritative corpus point is strictly better than a
        // Geocoding API answer whose location_type was never even stored.
        $this->storedCoordinate(['geocode_source' => 'google']);

        $result = $this->resolveWith([$this->addressPointRung()]);

        $this->assertTrue($result->isResolved());
        $this->assertSame(CoordinateSource::AddressPoint, $result->source);
        $this->assertSame(self::CORPUS_LAT, $result->latitude);
    }

    // ── CASE D ──────────────────────────────────────────────────────────────

    public function test_case_d_persisted_census_interpolation_does_not_block_address_point(): void
    {
        // The subtle one. This row has FULL, honest provenance — the ladder wrote
        // it — so no provenance check rejects it, and CoordinatePrecision grades
        // Interpolated as exact, so no precision check rejects it either. It is
        // refused because a house number interpolated along a street segment is
        // what the corpus exists to replace.
        $this->storedCoordinate([
            'geocode_source'    => 'saved_meta',
            'geocode_provider'  => 'us_census',
            'geocode_precision' => CoordinatePrecision::Interpolated->value,
        ]);

        $result = $this->resolveWith([$this->addressPointRung()]);

        $this->assertTrue($result->isResolved());
        $this->assertSame(CoordinateSource::AddressPoint, $result->source, 'The corpus must win');
        $this->assertSame(self::CORPUS_LAT, $result->latitude);
    }

    public function test_case_d_census_precision_alone_would_not_have_caught_this(): void
    {
        // Pins the reason Case D needs a provenance rule and not a precision
        // comparison: an isExact() test would have passed a Census point through.
        $this->assertTrue(
            CoordinatePrecision::Interpolated->isExact(),
            'If this becomes false, revisit whether the provider rule is still the right mechanism'
        );
    }

    // ── CASE E ──────────────────────────────────────────────────────────────

    public function test_case_e_bridge_still_outranks_address_point(): void
    {
        // Asserted against the REAL ladder: this precedence is deliberate and
        // this change must not have touched it.
        $providerIds = array_map(
            static fn (CoordinateProviderAdapterInterface $a): string => $a->providerId(),
            StandardCoordinateLadder::adapters()
        );

        $this->assertSame(
            ['existing_coordinates', 'bridge_mls', 'address_point', 'us_census'],
            $providerIds,
            'The assembled ladder order is unchanged'
        );

        $this->assertLessThan(
            array_search('address_point', $providerIds, true),
            array_search('bridge_mls', $providerIds, true),
            'Bridge must remain ahead of AddressPoint'
        );

        // And behaviourally: with both able to answer, Bridge wins and the corpus
        // rung is never reached.
        $result = (new PropertyCoordinateResolver([
            $this->bridgeRung(),
            $this->neverReachedRung('address_point'),
        ]))->resolve($this->address());

        $this->assertSame(CoordinateSource::Mls, $result->source);
        $this->assertSame(self::BRIDGE_LAT, $result->latitude);
    }

    public function test_address_point_still_outranks_census(): void
    {
        $providerIds = array_map(
            static fn (CoordinateProviderAdapterInterface $a): string => $a->providerId(),
            StandardCoordinateLadder::adapters()
        );

        $this->assertLessThan(
            array_search('us_census', $providerIds, true),
            array_search('address_point', $providerIds, true),
            'AddressPoint must remain ahead of Census'
        );

        // Census is still on the ladder — the fix does not remove the fallback.
        $this->assertContains('us_census', $providerIds);

        $result = (new PropertyCoordinateResolver([
            $this->addressPointRung(),
            $this->neverReachedRung('us_census'),
        ]))->resolve($this->address());

        $this->assertSame(CoordinateSource::AddressPoint, $result->source);
    }

    // ── CASE F ──────────────────────────────────────────────────────────────

    public function test_case_f_trusted_address_point_coordinate_is_reused_when_nothing_better_exists(): void
    {
        $this->storedCoordinate([
            'geocode_source'    => 'saved_meta',
            'geocode_provider'  => 'address_point',
            'geocode_precision' => CoordinatePrecision::Rooftop->value,
        ]);

        // No downstream rung at all: the stored answer is the answer.
        $result = $this->resolveWith([]);

        $this->assertTrue($result->isResolved(), 'A trusted corpus coordinate is still reusable');
        $this->assertSame(CoordinateSource::Existing, $result->source);
        $this->assertSame(self::EXISTING_LAT, $result->latitude);
        $this->assertSame('address_point', $result->provider);
        $this->assertSame(CoordinatePrecision::Rooftop, $result->precision);
    }

    // ── CASE G ──────────────────────────────────────────────────────────────

    public function test_case_g_trusted_bridge_coordinate_is_not_downgraded_by_address_point(): void
    {
        $this->storedCoordinate([
            'geocode_source'    => 'saved_meta',
            'geocode_provider'  => 'bridge_mls',
            'geocode_precision' => CoordinatePrecision::Parcel->value,
        ]);

        // The corpus rung must never be reached: Bridge already outranks it, and
        // a stored Bridge coordinate is a Bridge coordinate.
        $result = $this->resolveWith([$this->neverReachedRung('address_point')]);

        $this->assertTrue($result->isResolved());
        $this->assertSame(CoordinateSource::Existing, $result->source);
        $this->assertSame(self::EXISTING_LAT, $result->latitude);
        $this->assertSame('bridge_mls', $result->provider);
    }

    // ── CASE H ──────────────────────────────────────────────────────────────

    public function test_case_h_a_coordinate_for_a_different_address_is_rejected(): void
    {
        // Address-change invalidation predates this change and must survive it.
        $this->storedCoordinate([
            'geocode_source'    => 'saved_meta',
            'geocode_provider'  => 'address_point',
            'geocode_precision' => CoordinatePrecision::Rooftop->value,
        ]);

        $resolver = new PropertyCoordinateResolver([new ExistingCoordinatesAdapter()]);

        $result = $resolver->resolve($this->address('125 Main St'));

        $this->assertFalse(
            $result->isResolved(),
            'A coordinate for 123 Main St must never be served for 125 Main St'
        );
    }

    public function test_case_h_a_different_address_falls_through_to_the_next_rung(): void
    {
        $this->storedCoordinate([
            'geocode_source'    => 'saved_meta',
            'geocode_provider'  => 'address_point',
            'geocode_precision' => CoordinatePrecision::Rooftop->value,
        ]);

        $resolver = new PropertyCoordinateResolver([
            new ExistingCoordinatesAdapter(),
            $this->bridgeRung(),
        ]);

        $result = $resolver->resolve($this->address('125 Main St'));

        $this->assertSame(CoordinateSource::Mls, $result->source);
        $this->assertSame(self::BRIDGE_LAT, $result->latitude);
    }

    // ── CASE I ──────────────────────────────────────────────────────────────

    public function test_case_i_unprovable_provenance_declines_safely(): void
    {
        // Trusted rung, but the precision was never recorded.
        $this->storedCoordinate([
            'geocode_source'    => 'saved_meta',
            'geocode_provider'  => 'bridge_mls',
            'geocode_precision' => null,
        ]);

        // Asserted on the rung itself: the resolver reports its own
        // `no_adapter_resolved` once every rung has declined, so the specific
        // reason is only observable here.
        $rung = (new ExistingCoordinatesAdapter())->resolve($this->address());

        $this->assertFalse($rung->isResolved());
        $this->assertSame('existing_precision_unprovable', $rung->reason);

        $this->assertFalse($this->resolveWith([])->isResolved(), 'and the ladder ends unresolved');
    }

    public function test_case_i_absent_provenance_declines_safely(): void
    {
        $this->storedCoordinate(['geocode_source' => 'saved_meta']);

        $rung = (new ExistingCoordinatesAdapter())->resolve($this->address());

        $this->assertFalse($rung->isResolved());
        $this->assertSame('existing_provenance_absent', $rung->reason);
        $this->assertNull($rung->latitude, 'A declined rung returns no coordinate');

        $this->assertFalse($this->resolveWith([])->isResolved(), 'and the ladder ends unresolved');
    }

    public function test_a_declined_reuse_never_fabricates_a_coordinate(): void
    {
        // Fail closed: when nothing downstream can answer either, the ladder ends
        // unresolved rather than handing back the coordinate it just refused.
        $this->storedCoordinate(['geocode_source' => 'google']);

        $result = $this->resolveWith([]);

        $this->assertFalse($result->isResolved());
        $this->assertNull($result->latitude);
        $this->assertNull($result->longitude);
    }
}
