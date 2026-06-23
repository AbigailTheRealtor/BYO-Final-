<?php

namespace Tests\Unit\Services\Bridge;

use Tests\TestCase;
use App\Services\Bridge\OData\BuyerCriteriaODataFilterBuilder;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

/**
 * Unit tests for BuyerCriteriaODataFilterBuilder.
 *
 * All tests are pure unit tests — no database required.
 * Verifies OData $filter string correctness against the Bridge Interactive
 * OData 4.0 specification:
 *   - String literals are single-quoted
 *   - 'and' joins clauses
 *   - Numeric comparisons use no quotes
 *   - Null payload fields are omitted
 */
class BuyerCriteriaODataFilterBuilderTest extends TestCase
{
    private BuyerCriteriaODataFilterBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new BuyerCriteriaODataFilterBuilder();
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function makePayload(array $overrides = []): BuyerCriteriaPayload
    {
        $defaults = [
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ];

        return new BuyerCriteriaPayload(array_merge($defaults, $overrides));
    }

    // =========================================================================
    // Fully populated payload
    // =========================================================================

    public function test_fully_populated_payload_produces_all_clauses(): void
    {
        $payload = $this->makePayload([
            'property_types' => ['Residential'],
            'max_price'      => 500000,
            'min_bedrooms'   => 3,
            'radius_searches' => [
                [
                    'lat'          => 27.9944,
                    'lng'          => -82.4451,
                    'radius_miles' => 10,
                ],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString("StandardStatus eq 'Active'", $filter);
        $this->assertStringContainsString("PropertyType eq 'Residential'", $filter);
        $this->assertStringContainsString('ListPrice le 500000', $filter);
        $this->assertStringContainsString('BedroomsTotal ge 3', $filter);
        $this->assertStringContainsString('Latitude ge ', $filter);
        $this->assertStringContainsString('Latitude le ', $filter);
        $this->assertStringContainsString('Longitude ge ', $filter);
        $this->assertStringContainsString('Longitude le ', $filter);
    }

    public function test_clauses_joined_with_and(): void
    {
        $payload = $this->makePayload([
            'max_price'    => 300000,
            'min_bedrooms' => 2,
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString(' and ', $filter);
    }

    // =========================================================================
    // Sparse payload (only status + property type)
    // =========================================================================

    public function test_sparse_payload_omits_optional_clauses(): void
    {
        $payload = $this->makePayload([
            'property_types' => ['Residential'],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString("StandardStatus eq 'Active'", $filter);
        $this->assertStringContainsString("PropertyType eq 'Residential'", $filter);
        $this->assertStringNotContainsString('ListPrice', $filter);
        $this->assertStringNotContainsString('BedroomsTotal', $filter);
        $this->assertStringNotContainsString('Latitude', $filter);
        $this->assertStringNotContainsString('Longitude', $filter);
    }

    public function test_sparse_payload_contains_exactly_status_and_type_joined_with_and(): void
    {
        $payload = $this->makePayload([
            'property_types' => ['Residential'],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertSame(
            "StandardStatus eq 'Active' and PropertyType eq 'Residential'",
            $filter
        );
    }

    // =========================================================================
    // Null fields are omitted
    // =========================================================================

    public function test_null_max_price_omits_list_price_clause(): void
    {
        $payload = $this->makePayload(['max_price' => null]);

        $filter = $this->builder->build($payload);

        $this->assertStringNotContainsString('ListPrice', $filter);
    }

    public function test_null_min_bedrooms_omits_bedrooms_clause(): void
    {
        $payload = $this->makePayload(['min_bedrooms' => null]);

        $filter = $this->builder->build($payload);

        $this->assertStringNotContainsString('BedroomsTotal', $filter);
    }

    // =========================================================================
    // No-location payload
    // =========================================================================

    public function test_no_location_omits_geo_clauses(): void
    {
        $payload = $this->makePayload([
            'radius_searches' => [],
            'polygons'        => [],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringNotContainsString('Latitude', $filter);
        $this->assertStringNotContainsString('Longitude', $filter);
    }

    // =========================================================================
    // Radius search bounding box
    // =========================================================================

    public function test_radius_search_produces_bounding_box_clauses(): void
    {
        $payload = $this->makePayload([
            'radius_searches' => [
                [
                    'lat'          => 27.9944,
                    'lng'          => -82.4451,
                    'radius_miles' => 5,
                ],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString('Latitude ge ', $filter);
        $this->assertStringContainsString('Latitude le ', $filter);
        $this->assertStringContainsString('Longitude ge ', $filter);
        $this->assertStringContainsString('Longitude le ', $filter);
    }

    public function test_radius_bounding_box_is_symmetric_around_center(): void
    {
        $centerLat   = 27.9944;
        $centerLng   = -82.4451;
        $radiusMiles = 10.0;

        $payload = $this->makePayload([
            'radius_searches' => [
                [
                    'lat'          => $centerLat,
                    'lng'          => $centerLng,
                    'radius_miles' => $radiusMiles,
                ],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $latDelta = $radiusMiles / 69.0;
        $expectedMinLat = $centerLat - $latDelta;
        $expectedMaxLat = $centerLat + $latDelta;

        // Verify the bounding box values are present with at least 3-decimal precision.
        // The output format uses up to 6 significant decimal places (no trailing zeros),
        // so we check the first 5 characters of the value (e.g. "27.84" inside "27.849472").
        $minLatPrefix = substr(number_format($expectedMinLat, 6, '.', ''), 0, 5);
        $maxLatPrefix = substr(number_format($expectedMaxLat, 6, '.', ''), 0, 5);

        $this->assertStringContainsString($minLatPrefix, $filter);
        $this->assertStringContainsString($maxLatPrefix, $filter);
    }

    public function test_legacy_center_format_radius_search(): void
    {
        $payload = $this->makePayload([
            'radius_searches' => [
                [
                    'center' => ['lat' => 25.7617, 'lng' => -80.1918],
                    'radius_miles' => 3,
                ],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString('Latitude ge ', $filter);
        $this->assertStringContainsString('Longitude ge ', $filter);
    }

    public function test_zero_radius_is_ignored(): void
    {
        $payload = $this->makePayload([
            'radius_searches' => [
                ['lat' => 25.0, 'lng' => -80.0, 'radius_miles' => 0],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringNotContainsString('Latitude', $filter);
    }

    public function test_radius_with_missing_coordinates_is_ignored(): void
    {
        $payload = $this->makePayload([
            'radius_searches' => [
                ['radius_miles' => 10],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringNotContainsString('Latitude', $filter, 'Malformed radius entry (no lat/lng) must not emit a bbox around (0,0)');
    }

    // =========================================================================
    // Polygon bounding box
    // =========================================================================

    public function test_polygon_produces_bounding_box_clauses(): void
    {
        $payload = $this->makePayload([
            'polygons' => [
                [
                    'path' => [
                        ['lat' => 27.90, 'lng' => -82.50],
                        ['lat' => 27.95, 'lng' => -82.40],
                        ['lat' => 27.85, 'lng' => -82.45],
                    ],
                ],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString('Latitude ge 27.85', $filter);
        $this->assertStringContainsString('Latitude le 27.95', $filter);
        $this->assertStringContainsString('Longitude ge -82.5', $filter);
        $this->assertStringContainsString('Longitude le -82.4', $filter);
    }

    // =========================================================================
    // Multi-search-area (polygon) payload
    // =========================================================================

    public function test_multi_polygon_clauses_joined_with_or(): void
    {
        $payload = $this->makePayload([
            'polygons' => [
                [
                    'path' => [
                        ['lat' => 27.90, 'lng' => -82.50],
                        ['lat' => 27.95, 'lng' => -82.40],
                        ['lat' => 27.85, 'lng' => -82.45],
                    ],
                ],
                [
                    'path' => [
                        ['lat' => 25.70, 'lng' => -80.20],
                        ['lat' => 25.80, 'lng' => -80.10],
                        ['lat' => 25.75, 'lng' => -80.15],
                    ],
                ],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString(' or ', $filter);
    }

    public function test_multi_polygon_contains_both_bounding_boxes(): void
    {
        $payload = $this->makePayload([
            'polygons' => [
                [
                    'path' => [
                        ['lat' => 27.90, 'lng' => -82.50],
                        ['lat' => 27.95, 'lng' => -82.40],
                        ['lat' => 27.85, 'lng' => -82.45],
                    ],
                ],
                [
                    'path' => [
                        ['lat' => 25.70, 'lng' => -80.20],
                        ['lat' => 25.80, 'lng' => -80.10],
                        ['lat' => 25.75, 'lng' => -80.15],
                    ],
                ],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString('27.85', $filter);
        $this->assertStringContainsString('27.95', $filter);
        $this->assertStringContainsString('25.7', $filter);
        $this->assertStringContainsString('25.8', $filter);
    }

    public function test_radius_and_polygon_both_produce_geo_clauses(): void
    {
        $payload = $this->makePayload([
            'radius_searches' => [
                ['lat' => 27.9944, 'lng' => -82.4451, 'radius_miles' => 5],
            ],
            'polygons' => [
                [
                    'path' => [
                        ['lat' => 25.70, 'lng' => -80.20],
                        ['lat' => 25.80, 'lng' => -80.10],
                        ['lat' => 25.75, 'lng' => -80.15],
                    ],
                ],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString(' or ', $filter);
        $this->assertStringContainsString('Latitude ge ', $filter);
    }

    // =========================================================================
    // Multiple property types
    // =========================================================================

    public function test_multiple_property_types_use_or_syntax(): void
    {
        $payload = $this->makePayload([
            'property_types' => ['Residential', 'Income'],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString("PropertyType eq 'Residential'", $filter);
        $this->assertStringContainsString("PropertyType eq 'Income'", $filter);
        $this->assertStringContainsString(' or ', $filter);
        $this->assertMatchesRegularExpression('/\(PropertyType eq .+ or PropertyType eq .+\)/', $filter);
    }

    // =========================================================================
    // OData spec compliance
    // =========================================================================

    public function test_string_values_are_single_quoted(): void
    {
        $payload = $this->makePayload(['property_types' => ['Residential']]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString("'Active'", $filter);
        $this->assertStringContainsString("'Residential'", $filter);
    }

    public function test_numeric_values_have_no_quotes(): void
    {
        $payload = $this->makePayload([
            'max_price'    => 450000,
            'min_bedrooms' => 4,
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString('ListPrice le 450000', $filter);
        $this->assertStringContainsString('BedroomsTotal ge 4', $filter);
        $this->assertStringNotContainsString("'450000'", $filter);
        $this->assertStringNotContainsString("'4'", $filter);
    }

    public function test_standard_status_always_present(): void
    {
        $payload = $this->makePayload();

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString("StandardStatus eq 'Active'", $filter);
    }

    public function test_polygon_with_fewer_than_3_vertices_is_ignored(): void
    {
        $payload = $this->makePayload([
            'polygons' => [
                [
                    'path' => [
                        ['lat' => 27.90, 'lng' => -82.50],
                        ['lat' => 27.95, 'lng' => -82.40],
                    ],
                ],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringNotContainsString('Latitude', $filter);
    }

    public function test_property_type_with_single_quote_is_escaped(): void
    {
        $payload = $this->makePayload(['property_types' => ["O'Brien Type"]]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString("PropertyType eq 'O''Brien Type'", $filter);
    }
}
