<?php

namespace Tests\Unit\Services\Bridge;

use Tests\TestCase;
use App\Services\Bridge\OData\TenantCriteriaODataFilterBuilder;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

/**
 * Unit tests for TenantCriteriaODataFilterBuilder.
 *
 * All tests are pure unit tests — no database required.
 *
 * Tenant criteria notes:
 *   - PropertyType values are 'Residential' or 'Commercial Lease' (confirmed via
 *     live Bridge API import 2026-06-23; see TenantOfferListingCriteriaLoader docblock).
 *   - TenantOfferListingCriteriaLoader sets max_price=null to avoid misapplying a
 *     monthly budget against a sale list_price; the builder handles null gracefully.
 *   - Bounding-box logic is shared with BuyerCriteriaODataFilterBuilder via
 *     PolygonBoundingBox::fromPayload().
 */
class TenantCriteriaODataFilterBuilderTest extends TestCase
{
    private TenantCriteriaODataFilterBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new TenantCriteriaODataFilterBuilder();
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
            'max_price'      => 2500,
            'min_bedrooms'   => 2,
            'radius_searches' => [
                [
                    'lat'          => 27.9944,
                    'lng'          => -82.4451,
                    'radius_miles' => 5,
                ],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString("StandardStatus eq 'Active'", $filter);
        $this->assertStringContainsString("PropertyType eq 'Residential'", $filter);
        $this->assertStringContainsString('ListPrice le 2500', $filter);
        $this->assertStringContainsString('BedroomsTotal ge 2', $filter);
        $this->assertStringContainsString('Latitude ge ', $filter);
        $this->assertStringContainsString('Latitude le ', $filter);
        $this->assertStringContainsString('Longitude ge ', $filter);
        $this->assertStringContainsString('Longitude le ', $filter);
    }

    // =========================================================================
    // Sparse payload (only status + property type)
    // =========================================================================

    public function test_sparse_payload_emits_only_status_and_property_type(): void
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

    // =========================================================================
    // Null max_price (typical tenant case from TenantOfferListingCriteriaLoader)
    // =========================================================================

    public function test_null_max_price_omits_list_price_clause(): void
    {
        $payload = $this->makePayload(['max_price' => null]);

        $filter = $this->builder->build($payload);

        $this->assertStringNotContainsString('ListPrice', $filter);
    }

    public function test_when_max_price_populated_list_price_is_emitted(): void
    {
        $payload = $this->makePayload(['max_price' => 3000]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString('ListPrice le 3000', $filter);
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
    // Rental property types (Bridge API confirmed values)
    // =========================================================================

    public function test_residential_property_type_emitted_correctly(): void
    {
        $payload = $this->makePayload(['property_types' => ['Residential']]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString("PropertyType eq 'Residential'", $filter);
    }

    public function test_commercial_lease_property_type_emitted_correctly(): void
    {
        $payload = $this->makePayload(['property_types' => ['Commercial Lease']]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString("PropertyType eq 'Commercial Lease'", $filter);
    }

    public function test_multiple_rental_property_types_use_or_syntax(): void
    {
        $payload = $this->makePayload([
            'property_types' => ['Residential', 'Commercial Lease'],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString("PropertyType eq 'Residential'", $filter);
        $this->assertStringContainsString("PropertyType eq 'Commercial Lease'", $filter);
        $this->assertMatchesRegularExpression('/\(PropertyType eq .+ or PropertyType eq .+\)/', $filter);
    }

    // =========================================================================
    // Geographic filtering
    // =========================================================================

    public function test_radius_search_produces_bounding_box_clauses(): void
    {
        $payload = $this->makePayload([
            'radius_searches' => [
                [
                    'lat'          => 25.7617,
                    'lng'          => -80.1918,
                    'radius_miles' => 3,
                ],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString('Latitude ge ', $filter);
        $this->assertStringContainsString('Latitude le ', $filter);
        $this->assertStringContainsString('Longitude ge ', $filter);
        $this->assertStringContainsString('Longitude le ', $filter);
    }

    public function test_polygon_produces_bounding_box_clauses(): void
    {
        $payload = $this->makePayload([
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

        $this->assertStringContainsString('Latitude ge 25.7', $filter);
        $this->assertStringContainsString('Latitude le 25.8', $filter);
        $this->assertStringContainsString('Longitude ge -80.2', $filter);
        $this->assertStringContainsString('Longitude le -80.1', $filter);
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
        $this->assertStringContainsString('27.85', $filter);
        $this->assertStringContainsString('25.7', $filter);
    }

    public function test_multi_radius_areas_joined_with_or(): void
    {
        $payload = $this->makePayload([
            'radius_searches' => [
                ['lat' => 27.9944, 'lng' => -82.4451, 'radius_miles' => 5],
                ['lat' => 25.7617, 'lng' => -80.1918, 'radius_miles' => 3],
            ],
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString(' or ', $filter);
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
            'max_price'    => 2000,
            'min_bedrooms' => 1,
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString('ListPrice le 2000', $filter);
        $this->assertStringContainsString('BedroomsTotal ge 1', $filter);
        $this->assertStringNotContainsString("'2000'", $filter);
        $this->assertStringNotContainsString("'1'", $filter);
    }

    public function test_standard_status_always_present(): void
    {
        $payload = $this->makePayload();

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString("StandardStatus eq 'Active'", $filter);
    }

    public function test_clauses_joined_with_and(): void
    {
        $payload = $this->makePayload([
            'max_price'    => 2500,
            'min_bedrooms' => 2,
        ]);

        $filter = $this->builder->build($payload);

        $this->assertStringContainsString(' and ', $filter);
    }
}
