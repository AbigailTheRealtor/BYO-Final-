<?php

namespace Tests\Unit\Services\Location\Coordinates;

use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use PHPUnit\Framework\TestCase;

/**
 * PropertyCoordinateResult — the exact/coarse boundary.
 *
 * This suite exists to pin one rule: a coarse coordinate must never qualify for
 * Location DNA distance work. A ZIP centroid is the middle of a postal area
 * that can span miles, and "how far to the nearest school" computed from one is
 * a confidently wrong number shown to a consumer.
 */
class PropertyCoordinateResultTest extends TestCase
{
    private function resultAt(CoordinatePrecision $precision): PropertyCoordinateResult
    {
        return PropertyCoordinateResult::resolved(
            27.7676, -82.6403, $precision, CoordinateSource::Geocoder, 'us_census'
        );
    }

    /** @return array<string, array{CoordinatePrecision}> */
    public static function exactTiers(): array
    {
        return [
            'rooftop'      => [CoordinatePrecision::Rooftop],
            'parcel'       => [CoordinatePrecision::Parcel],
            'entrance'     => [CoordinatePrecision::Entrance],
            'interpolated' => [CoordinatePrecision::Interpolated],
        ];
    }

    /** @return array<string, array{CoordinatePrecision}> */
    public static function coarseTiers(): array
    {
        return [
            'street'        => [CoordinatePrecision::Street],
            'zip_centroid'  => [CoordinatePrecision::ZipCentroid],
            'city_centroid' => [CoordinatePrecision::CityCentroid],
            'unknown'       => [CoordinatePrecision::Unknown],
        ];
    }

    /** @dataProvider exactTiers */
    public function test_exact_tiers_are_usable_for_location_dna(CoordinatePrecision $precision): void
    {
        $result = $this->resultAt($precision);

        $this->assertTrue($result->isUsableForLocationDna(), $precision->value . ' must drive Location DNA');
        $this->assertFalse($result->isCoarseDisplayOnly());
        $this->assertNotNull($result->exactCoordinates());
    }

    /** @dataProvider coarseTiers */
    public function test_coarse_tiers_are_never_usable_for_location_dna(CoordinatePrecision $precision): void
    {
        $result = $this->resultAt($precision);

        $this->assertFalse($result->isUsableForLocationDna(), $precision->value . ' must NOT drive Location DNA');
        $this->assertTrue($result->isCoarseDisplayOnly());
        $this->assertNull(
            $result->exactCoordinates(),
            'exactCoordinates() is the gate — a coarse point must not escape through it'
        );
    }

    /** @dataProvider coarseTiers */
    public function test_coarse_tiers_still_provide_display_coordinates(CoordinatePrecision $precision): void
    {
        $this->assertSame(
            ['lat' => 27.7676, 'lng' => -82.6403],
            $this->resultAt($precision)->displayCoordinates(),
            'A coarse point may still frame a map — it just may not be measured from'
        );
    }

    public function test_street_precision_is_coarse_by_product_decision(): void
    {
        $this->assertFalse(
            CoordinatePrecision::Street->isExact(),
            'street means the house number was NOT matched; flood-zone polygons are parcel-scale'
        );
    }

    // ── unresolved ──────────────────────────────────────────────────────────

    public function test_an_unresolved_result_is_not_usable_and_yields_no_coordinates(): void
    {
        $result = PropertyCoordinateResult::unresolved('non_google_geocoder_unavailable', '123 main st tampa fl 33602');

        $this->assertFalse($result->isResolved());
        $this->assertFalse($result->isUsableForLocationDna());
        $this->assertFalse($result->isCoarseDisplayOnly());
        $this->assertNull($result->exactCoordinates());
        $this->assertNull($result->displayCoordinates());
        $this->assertSame('non_google_geocoder_unavailable', $result->reason);
    }

    public function test_unresolved_retains_the_normalized_address_for_diagnostics(): void
    {
        $result = PropertyCoordinateResult::unresolved('insufficient_address', '123 main st');

        $this->assertSame('123 main st', $result->normalizedAddress);
    }

    // ── building-level coordinates ──────────────────────────────────────────

    public function test_a_building_coordinate_is_capped_at_parcel_not_rooftop(): void
    {
        $result = PropertyCoordinateResult::forBuilding(
            27.7676, -82.6403, CoordinateSource::Geocoder, 'us_census'
        );

        $this->assertSame(
            CoordinatePrecision::Parcel,
            $result->precision,
            'A unit-stripped lookup located the building, not the unit — calling it rooftop would overclaim'
        );
        $this->assertTrue($result->isUsableForLocationDna());
    }

    // ── metadata ────────────────────────────────────────────────────────────

    public function test_provider_and_source_metadata_survive(): void
    {
        $result = PropertyCoordinateResult::resolved(
            27.7676, -82.6403,
            CoordinatePrecision::Rooftop,
            CoordinateSource::Mls,
            'bridge_mls',
            '123 main st tampa fl 33602',
            0.97,
        );

        $this->assertSame(CoordinateSource::Mls, $result->source);
        $this->assertSame('bridge_mls', $result->provider);
        $this->assertSame('123 main st tampa fl 33602', $result->normalizedAddress);
        $this->assertSame(0.97, $result->confidence);
        $this->assertNotNull($result->resolvedAt);
    }

    public function test_confidence_is_optional(): void
    {
        $this->assertNull($this->resultAt(CoordinatePrecision::Rooftop)->confidence);
    }

    public function test_to_array_exposes_the_dna_gate(): void
    {
        $exact  = $this->resultAt(CoordinatePrecision::Rooftop)->toArray();
        $coarse = $this->resultAt(CoordinatePrecision::ZipCentroid)->toArray();

        $this->assertTrue($exact['usable_for_dna']);
        $this->assertFalse($coarse['usable_for_dna']);
        $this->assertSame('zip_centroid', $coarse['precision']);
    }

    // ── source semantics ────────────────────────────────────────────────────

    public function test_only_the_geocoder_source_requires_the_network(): void
    {
        $this->assertTrue(CoordinateSource::Existing->isLocal());
        $this->assertTrue(CoordinateSource::Mls->isLocal());
        $this->assertTrue(CoordinateSource::Centroid->isLocal());
        $this->assertTrue(CoordinateSource::Manual->isLocal());
        $this->assertFalse(CoordinateSource::Geocoder->isLocal());
    }
}
