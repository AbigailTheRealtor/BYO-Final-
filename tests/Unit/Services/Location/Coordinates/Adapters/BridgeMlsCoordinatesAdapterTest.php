<?php

namespace Tests\Unit\Services\Location\Coordinates\Adapters;

use App\Models\BridgeProperty;
use App\Services\Location\Coordinates\Adapters\BridgeMlsCoordinatesAdapter;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The claim under test: an MLS coordinate is reachable only through the MLS
 * record key, and is reported for what it is.
 *
 * The key-only rule is the point of most of this file. A coordinate attached to
 * the wrong property is not a smaller version of having no coordinate — it is a
 * confident wrong answer, and address similarity is the usual way to produce
 * one.
 */
class BridgeMlsCoordinatesAdapterTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_KEY = 'STELLAR-MFR-4471102';

    private const LAT = 27.9659;
    private const LNG = -82.8001;

    private BridgeMlsCoordinatesAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new BridgeMlsCoordinatesAdapter();
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    private function bridgeRecord(array $overrides = []): BridgeProperty
    {
        return BridgeProperty::create(array_merge([
            'listing_key'       => self::LISTING_KEY,
            'listing_id'        => 'MFR4471102',
            'standard_status'   => 'Active',
            'unparsed_address'  => '123 Main St',
            'city'              => 'Tampa',
            'state_or_province' => 'FL',
            'postal_code'       => '33602',
            'county_or_parish'  => 'Hillsborough',
            'latitude'          => self::LAT,
            'longitude'         => self::LNG,
        ], $overrides));
    }

    private function addressWithKey(string $key = self::LISTING_KEY): PropertyAddress
    {
        return new PropertyAddress(
            address:       '123 Main St',
            unitAddress:   '',
            city:          'Tampa',
            county:        'Hillsborough',
            state:         'FL',
            zip:           '33602',
            listingType:   '',
            listingId:     null,
            mlsListingKey: $key,
        );
    }

    // ── the hit ─────────────────────────────────────────────────────────────

    public function test_an_exact_listing_key_resolves_the_feed_coordinate(): void
    {
        $this->bridgeRecord();

        $result = $this->adapter->resolve($this->addressWithKey());

        $this->assertTrue($result->isResolved());
        $this->assertSame(self::LAT, $result->latitude);
        $this->assertSame(self::LNG, $result->longitude);
    }

    public function test_the_result_is_labelled_as_coming_from_the_mls(): void
    {
        $this->bridgeRecord();

        $result = $this->adapter->resolve($this->addressWithKey());

        $this->assertSame(CoordinateSource::Mls, $result->source);
        $this->assertSame('bridge_mls', $result->provider);
    }

    public function test_the_feed_records_own_address_is_reported_with_the_coordinate(): void
    {
        $this->bridgeRecord(['unparsed_address' => '900 Bayshore Boulevard']);

        $result = $this->adapter->resolve($this->addressWithKey());

        $this->assertSame(
            '900 bayshore blvd tampa fl 33602',
            $result->normalizedAddress,
            'The coordinate describes the MLS record, and the result should say which one'
        );
    }

    public function test_no_confidence_is_invented_for_a_feed_that_reports_none(): void
    {
        $this->bridgeRecord();

        $this->assertNull($this->adapter->resolve($this->addressWithKey())->confidence);
    }

    // ── precision semantics ─────────────────────────────────────────────────

    public function test_an_mls_coordinate_is_graded_parcel(): void
    {
        $this->bridgeRecord();

        $this->assertSame(
            CoordinatePrecision::Parcel,
            $this->adapter->resolve($this->addressWithKey())->precision
        );
    }

    public function test_an_mls_coordinate_is_never_claimed_as_rooftop(): void
    {
        $this->bridgeRecord();

        $this->assertNotSame(
            CoordinatePrecision::Rooftop,
            $this->adapter->resolve($this->addressWithKey())->precision,
            'The feed publishes no precision field, so a roof cannot be claimed'
        );
    }

    public function test_parcel_keeps_the_coordinate_usable_for_location_dna(): void
    {
        $this->bridgeRecord();

        $result = $this->adapter->resolve($this->addressWithKey());

        $this->assertTrue($result->isUsableForLocationDna());
        $this->assertSame(['lat' => self::LAT, 'lng' => self::LNG], $result->exactCoordinates());
    }

    // ── identity: key only, never similarity ────────────────────────────────

    public function test_without_a_listing_key_nothing_is_looked_up(): void
    {
        $this->bridgeRecord();

        $result = $this->adapter->resolve(new PropertyAddress(
            address: '123 Main St', city: 'Tampa', state: 'FL', zip: '33602'
        ));

        $this->assertFalse($result->isResolved());
        $this->assertSame('no_mls_listing_key', $result->reason);
    }

    public function test_a_matching_address_alone_never_yields_a_coordinate(): void
    {
        // The record's address is identical to the one being asked about. It is
        // still not returned, because an address is not an identity.
        $this->bridgeRecord();

        $result = $this->adapter->resolve(new PropertyAddress(
            address: '123 Main St', city: 'Tampa', county: 'Hillsborough', state: 'FL', zip: '33602'
        ));

        $this->assertNull($result->latitude);
    }

    public function test_an_unknown_listing_key_is_a_clean_miss(): void
    {
        $this->bridgeRecord();

        $result = $this->adapter->resolve($this->addressWithKey('NOT-A-REAL-KEY'));

        $this->assertFalse($result->isResolved());
        $this->assertSame('mls_record_not_found', $result->reason);
    }

    public function test_a_blank_listing_key_is_treated_as_absent(): void
    {
        $this->bridgeRecord();

        $result = $this->adapter->resolve($this->addressWithKey('   '));

        $this->assertSame('no_mls_listing_key', $result->reason);
    }

    public function test_the_listing_key_match_is_exact(): void
    {
        $this->bridgeRecord();

        // A prefix of the real key must not match it.
        $result = $this->adapter->resolve($this->addressWithKey('STELLAR-MFR-447'));

        $this->assertSame('mls_record_not_found', $result->reason);
    }

    // ── malformed feed coordinates ──────────────────────────────────────────

    public function test_a_record_with_no_coordinates_is_reported_as_absent(): void
    {
        $this->bridgeRecord(['latitude' => null, 'longitude' => null]);

        $result = $this->adapter->resolve($this->addressWithKey());

        $this->assertFalse($result->isResolved());
        $this->assertSame('mls_coordinates_absent', $result->reason);
    }

    public function test_a_half_populated_pair_is_refused(): void
    {
        $this->bridgeRecord(['longitude' => null]);

        $this->assertSame(
            'mls_coordinates_absent',
            $this->adapter->resolve($this->addressWithKey())->reason
        );
    }

    public function test_zero_coordinates_are_refused_as_null_island(): void
    {
        $this->bridgeRecord(['latitude' => 0, 'longitude' => 0]);

        $result = $this->adapter->resolve($this->addressWithKey());

        $this->assertFalse($result->isResolved());
        $this->assertSame('mls_coordinates_invalid', $result->reason);
        $this->assertNull($result->exactCoordinates());
    }

    /**
     * @dataProvider outOfRangePairs
     */
    public function test_an_out_of_range_pair_is_refused(float $lat, float $lng): void
    {
        $this->bridgeRecord(['latitude' => $lat, 'longitude' => $lng]);

        $result = $this->adapter->resolve($this->addressWithKey());

        $this->assertFalse($result->isResolved());
        $this->assertSame('mls_coordinates_invalid', $result->reason);
    }

    /** @return array<string, array{float, float}> */
    public static function outOfRangePairs(): array
    {
        return [
            'latitude too high'  => [90.5,    -82.8001],
            'latitude too low'   => [-90.5,   -82.8001],
            'longitude too high' => [27.9659,  180.5],
            'longitude too low'  => [27.9659, -180.5],
        ];
    }

    // ── zero network ────────────────────────────────────────────────────────

    public function test_the_adapter_declares_that_it_needs_no_network(): void
    {
        $this->assertFalse($this->adapter->requiresNetwork());
    }

    public function test_a_hit_sends_nothing(): void
    {
        Http::fake();
        $this->bridgeRecord();

        $this->assertTrue($this->adapter->resolve($this->addressWithKey())->isResolved());

        Http::assertNothingSent();
    }

    public function test_a_miss_sends_nothing(): void
    {
        Http::fake();

        $this->adapter->resolve($this->addressWithKey('NOT-A-REAL-KEY'));

        Http::assertNothingSent();
    }

    public function test_an_invalid_coordinate_rejection_sends_nothing(): void
    {
        Http::fake();
        $this->bridgeRecord(['latitude' => 0, 'longitude' => 0]);

        $this->adapter->resolve($this->addressWithKey());

        Http::assertNothingSent();
    }

    public function test_the_adapter_never_reaches_the_bridge_api(): void
    {
        Http::fake();
        $this->bridgeRecord();

        $this->adapter->resolve($this->addressWithKey());

        Http::assertNothingSent();

        $source = file_get_contents(base_path(
            'app/Services/Location/Coordinates/Adapters/BridgeMlsCoordinatesAdapter.php'
        ));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('BridgeApiService', $source);
    }
}
