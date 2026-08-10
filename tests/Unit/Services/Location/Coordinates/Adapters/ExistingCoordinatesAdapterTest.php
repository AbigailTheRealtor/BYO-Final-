<?php

namespace Tests\Unit\Services\Location\Coordinates\Adapters;

use App\Models\PropertyLocationDna;
use App\Services\Location\Coordinates\Adapters\ExistingCoordinatesAdapter;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The claim under test: a stored coordinate is reused when — and only when — it
 * can be shown to still describe the address being asked about.
 *
 * Both halves matter. A reuse that is too eager attaches an old address's point
 * to a new address, which is worse than having no coordinate at all because it
 * looks like an answer. A reuse that is too shy re-geocodes an address that
 * never moved, which is what this rung exists to avoid.
 */
class ExistingCoordinatesAdapterTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 8801;

    private const LAT = 27.9506;
    private const LNG = -82.4572;

    private ExistingCoordinatesAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new ExistingCoordinatesAdapter();
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    private function storedRow(array $overrides = []): PropertyLocationDna
    {
        return PropertyLocationDna::create(array_merge([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_county'  => 'Hillsborough',
            'source_state'   => 'FL',
            'source_zip'     => '33602',
            'geocoded_lat'   => self::LAT,
            'geocoded_lng'   => self::LNG,
            'geocode_source' => 'saved_meta',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ], $overrides));
    }

    /** The address as the listing currently states it, with its listing handle. */
    private function currentAddress(
        string $street = '123 Main St',
        string $unit = '',
        string $city = 'Tampa',
        string $state = 'FL',
        string $zip = '33602',
    ): PropertyAddress {
        return new PropertyAddress(
            address:     $street,
            unitAddress: $unit,
            city:        $city,
            county:      'Hillsborough',
            state:       $state,
            zip:         $zip,
            listingType: self::LISTING_TYPE,
            listingId:   self::LISTING_ID,
        );
    }

    // ── the hit ─────────────────────────────────────────────────────────────

    public function test_a_saved_coordinate_for_the_same_address_is_reused(): void
    {
        $this->storedRow();

        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertTrue($result->isResolved());
        $this->assertSame(self::LAT, $result->latitude);
        $this->assertSame(self::LNG, $result->longitude);
        $this->assertSame(CoordinateSource::Existing, $result->source);
    }

    public function test_the_reused_coordinate_is_usable_for_location_dna(): void
    {
        $this->storedRow();

        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertTrue($result->isUsableForLocationDna());
        $this->assertSame(
            ['lat' => self::LAT, 'lng' => self::LNG],
            $result->exactCoordinates()
        );
    }

    public function test_the_originating_provider_is_preserved_not_overwritten(): void
    {
        $this->storedRow(['geocode_source' => 'google']);

        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertSame(
            'google',
            $result->provider,
            'Reusing a coordinate does not change where it came from'
        );
    }

    public function test_the_original_resolution_time_is_preserved(): void
    {
        $this->storedRow(['geocoded_at' => '2026-01-02 03:04:05']);

        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertSame('2026-01-02', $result->resolvedAt?->format('Y-m-d'));
    }

    // ── precision is never inflated ─────────────────────────────────────────

    public function test_a_reused_coordinate_is_never_promoted_to_rooftop(): void
    {
        foreach (['saved_meta', 'google'] as $geocodeSource) {
            PropertyLocationDna::query()->delete();
            $this->storedRow(['geocode_source' => $geocodeSource]);

            $result = $this->adapter->resolve($this->currentAddress());

            $this->assertSame(
                CoordinatePrecision::Parcel,
                $result->precision,
                "{$geocodeSource} proves a building, never a roof"
            );
        }
    }

    public function test_an_unrecognised_geocode_source_is_refused_rather_than_graded(): void
    {
        $this->storedRow(['geocode_source' => 'some_future_provider']);

        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertFalse($result->isResolved());
        $this->assertSame('existing_precision_unprovable', $result->reason);
    }

    public function test_a_coarse_stored_source_is_never_promoted_into_an_exact_coordinate(): void
    {
        // The failure this guards against: a centroid sitting in the table
        // being handed back as if it identified the property.
        foreach (['zip_centroid', 'city_centroid', 'street'] as $coarse) {
            PropertyLocationDna::query()->delete();
            $this->storedRow(['geocode_source' => $coarse]);

            $result = $this->adapter->resolve($this->currentAddress());

            $this->assertFalse($result->isUsableForLocationDna(), $coarse);
            $this->assertNull($result->exactCoordinates(), $coarse);
        }
    }

    public function test_a_missing_geocode_source_is_not_guessed_at(): void
    {
        $this->storedRow(['geocode_source' => null]);

        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertFalse($result->isResolved());
        $this->assertSame('existing_precision_unprovable', $result->reason);
    }

    // ── address-change invalidation ─────────────────────────────────────────

    public function test_a_different_street_number_rejects_the_stored_coordinate(): void
    {
        $this->storedRow();

        $result = $this->adapter->resolve($this->currentAddress(street: '456 Main St'));

        $this->assertFalse($result->isResolved());
        $this->assertSame('existing_address_changed', $result->reason);
        $this->assertNull($result->exactCoordinates());
    }

    public function test_a_different_city_rejects_the_stored_coordinate(): void
    {
        $this->storedRow();

        $result = $this->adapter->resolve($this->currentAddress(city: 'Orlando', zip: '32801'));

        $this->assertSame('existing_address_changed', $result->reason);
    }

    public function test_a_different_zip_rejects_the_stored_coordinate(): void
    {
        $this->storedRow();

        $result = $this->adapter->resolve($this->currentAddress(zip: '33605'));

        $this->assertSame('existing_address_changed', $result->reason);
    }

    public function test_a_different_state_rejects_the_stored_coordinate(): void
    {
        $this->storedRow();

        $result = $this->adapter->resolve($this->currentAddress(state: 'GA'));

        $this->assertSame('existing_address_changed', $result->reason);
    }

    // ── normalization-only differences must NOT invalidate ──────────────────

    /**
     * @dataProvider equivalentSpellings
     */
    public function test_a_normalization_only_difference_still_reuses_the_coordinate(
        string $street,
        string $city,
        string $state,
        string $zip,
    ): void {
        $this->storedRow();

        $result = $this->adapter->resolve($this->currentAddress(
            street: $street,
            city:   $city,
            state:  $state,
            zip:    $zip,
        ));

        $this->assertTrue(
            $result->isResolved(),
            "'{$street}, {$city}, {$state} {$zip}' is the same doorway and must not force a re-geocode"
        );
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function equivalentSpellings(): array
    {
        return [
            'casing'            => ['123 MAIN ST',     'TAMPA', 'fl',      '33602'],
            'punctuation'       => ['123 Main St.',    'Tampa', 'FL',      '33602'],
            'extra whitespace'  => ['123   Main  St',  ' Tampa', 'FL',     '33602'],
            'suffix spelled out'=> ['123 Main Street', 'Tampa', 'FL',      '33602'],
            'state spelled out' => ['123 Main St',     'Tampa', 'Florida', '33602'],
            'zip plus four'     => ['123 Main St',     'Tampa', 'FL',      '33602-1234'],
        ];
    }

    public function test_a_directional_abbreviation_does_not_invalidate(): void
    {
        $this->storedRow(['source_address' => '456 North Oak Avenue']);

        $result = $this->adapter->resolve($this->currentAddress(street: '456 N Oak Ave'));

        $this->assertTrue($result->isResolved());
    }

    // ── unit semantics ──────────────────────────────────────────────────────

    public function test_adding_a_unit_does_not_invalidate_the_building_coordinate(): void
    {
        $this->storedRow();

        $result = $this->adapter->resolve($this->currentAddress(unit: 'Unit 4A'));

        $this->assertTrue(
            $result->isResolved(),
            'A unit is not a different place on the earth; the building did not move'
        );
    }

    public function test_changing_the_unit_does_not_force_a_new_lookup(): void
    {
        $this->storedRow();

        $a = $this->adapter->resolve($this->currentAddress(unit: 'Unit 4A'));
        $b = $this->adapter->resolve($this->currentAddress(unit: '#4B'));

        $this->assertTrue($a->isResolved());
        $this->assertTrue($b->isResolved());
        $this->assertSame($a->latitude, $b->latitude);
        $this->assertSame($a->longitude, $b->longitude);
    }

    public function test_units_share_a_coordinate_but_remain_distinct_properties(): void
    {
        $unitA = $this->currentAddress(unit: 'Unit 4A');
        $unitB = $this->currentAddress(unit: 'Unit 4B');

        $this->assertSame(
            $unitA->coordinateLookupLine(),
            $unitB->coordinateLookupLine(),
            'One building, one lookup'
        );

        $this->assertNotSame(
            $unitA->propertyIdentityLine(),
            $unitB->propertyIdentityLine(),
            'Two properties, two identities'
        );
    }

    // ── malformed coordinates ───────────────────────────────────────────────

    /**
     * @dataProvider malformedPairs
     */
    public function test_a_malformed_coordinate_pair_is_refused(mixed $lat, mixed $lng): void
    {
        $this->storedRow(['geocoded_lat' => $lat, 'geocoded_lng' => $lng]);

        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertFalse($result->isResolved());
        $this->assertNull($result->exactCoordinates());
    }

    /** @return array<string, array{mixed, mixed}> */
    public static function malformedPairs(): array
    {
        return [
            'latitude above range'  => [91.0,   -82.4572],
            'latitude below range'  => [-90.5,  -82.4572],
            'longitude above range' => [27.9506, 181.0],
            'longitude below range' => [27.9506, -180.5],
            'null island'           => [0,       0],
            'latitude missing'      => [null,   -82.4572],
            'longitude missing'     => [27.9506, null],
            'both missing'          => [null,    null],
            'empty strings'         => ['',      ''],
        ];
    }

    public function test_an_out_of_range_latitude_reports_it_as_invalid_not_as_a_miss(): void
    {
        $this->storedRow(['geocoded_lat' => 91.0]);

        $this->assertSame(
            'existing_coordinates_invalid',
            $this->adapter->resolve($this->currentAddress())->reason
        );
    }

    public function test_an_out_of_range_longitude_reports_it_as_invalid_not_as_a_miss(): void
    {
        $this->storedRow(['geocoded_lng' => -180.5]);

        $this->assertSame(
            'existing_coordinates_invalid',
            $this->adapter->resolve($this->currentAddress())->reason
        );
    }

    public function test_an_absent_coordinate_is_distinguished_from_an_invalid_one(): void
    {
        $this->storedRow(['geocoded_lat' => null, 'geocoded_lng' => null]);

        $this->assertSame(
            'existing_coordinates_absent',
            $this->adapter->resolve($this->currentAddress())->reason
        );
    }

    // ── rows that must not be trusted ───────────────────────────────────────

    /**
     * @dataProvider nonGeocodedStatuses
     */
    public function test_only_a_geocoded_row_is_trusted(string $status): void
    {
        $this->storedRow(['geocode_status' => $status]);

        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertFalse($result->isResolved());
        $this->assertSame('existing_not_geocoded', $result->reason);
    }

    /** @return array<string, array{string}> */
    public static function nonGeocodedStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'failed'  => ['failed'],
            'skipped' => ['skipped'],
        ];
    }

    public function test_a_row_with_a_blank_stored_address_is_not_treated_as_a_match(): void
    {
        // A blank snapshot normalizes to an empty string. So does a blank
        // incoming address — which must not be allowed to satisfy the equality.
        $this->storedRow(['source_address' => '', 'source_city' => '', 'source_state' => '', 'source_zip' => '']);

        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertFalse($result->isResolved());
        $this->assertSame('existing_address_changed', $result->reason);
    }

    // ── absent inputs ───────────────────────────────────────────────────────

    public function test_no_listing_handle_means_no_lookup_is_attempted(): void
    {
        $this->storedRow();

        $result = $this->adapter->resolve(new PropertyAddress(
            address: '123 Main St', city: 'Tampa', state: 'FL', zip: '33602'
        ));

        $this->assertFalse($result->isResolved());
        $this->assertSame('no_listing_handle', $result->reason);
    }

    public function test_a_listing_with_no_stored_row_is_a_clean_miss(): void
    {
        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertFalse($result->isResolved());
        $this->assertSame('no_existing_record', $result->reason);
    }

    public function test_another_listings_row_is_never_borrowed(): void
    {
        $this->storedRow(['listing_id' => 99999]);

        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertSame('no_existing_record', $result->reason);
    }

    public function test_a_row_for_a_different_listing_type_is_never_borrowed(): void
    {
        $this->storedRow(['listing_type' => 'landlord_agent_auction']);

        $result = $this->adapter->resolve($this->currentAddress());

        $this->assertSame('no_existing_record', $result->reason);
    }

    // ── zero network ────────────────────────────────────────────────────────

    public function test_the_adapter_declares_that_it_needs_no_network(): void
    {
        $this->assertFalse($this->adapter->requiresNetwork());
    }

    public function test_a_hit_sends_nothing(): void
    {
        Http::fake();
        $this->storedRow();

        $this->assertTrue($this->adapter->resolve($this->currentAddress())->isResolved());

        Http::assertNothingSent();
    }

    public function test_a_miss_sends_nothing(): void
    {
        Http::fake();

        $this->adapter->resolve($this->currentAddress());

        Http::assertNothingSent();
    }

    public function test_a_stale_rejection_sends_nothing(): void
    {
        Http::fake();
        $this->storedRow();

        $this->adapter->resolve($this->currentAddress(street: '456 Main St'));

        Http::assertNothingSent();
    }

    public function test_an_invalid_coordinate_rejection_sends_nothing(): void
    {
        Http::fake();
        $this->storedRow(['geocoded_lat' => 0, 'geocoded_lng' => 0]);

        $this->adapter->resolve($this->currentAddress());

        Http::assertNothingSent();
    }
}
