<?php

namespace Tests\Feature\ListingImport;

use Tests\TestCase;
use App\Services\ListingImport\MlsListingImportService;

/**
 * Regression tests for MLS parser bleed.
 *
 * Every test injects a real-format inline MLS text snippet and asserts that a
 * specific field does NOT contain contaminating text from an adjacent label.
 *
 * These tests pin the exact failure modes confirmed in production:
 *  - City bleeding into County / School District / Neighborhood / Flood Zone
 *  - Carport bleeding into Rental Rate Type / Tax / Legal Subdivision
 *  - Interior Features bleeding into Appliances / Exterior Information / Interior Information
 *  - Waterfront bleeding into Interior Information
 *  - Rent Includes bleeding into Water Frontage / Waterfront values
 *  - Water View bleeding into Special Assessment / Tax values
 *
 * Fixture-based tests validate clean extraction from tests/fixtures/mls/residential.txt
 * and tests/fixtures/mls/rental.txt.
 */
class MlsParserBleedRegressionTest extends TestCase
{
    private MlsListingImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MlsListingImportService();
    }

    // ─── City bleed tests ─────────────────────────────────────────────────────

    /** City value must stop at the County label. */
    public function test_city_does_not_bleed_into_county_label(): void
    {
        $raw = 'City: SEMINOLE County: Pinellas State: FL Zip: 33772';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('city', $data);
        $this->assertStringNotContainsStringIgnoringCase('County', $data['city']);
        $this->assertStringNotContainsStringIgnoringCase('Pinellas', $data['city']);
        $this->assertEquals('SEMINOLE', trim($data['city']));
    }

    /** City value must stop at a bare Neighborhood section header (no colon). */
    public function test_city_does_not_bleed_into_bare_neighborhood_header(): void
    {
        $raw = 'City: SEMINOLE Neighborhood Sunridge Park County: Pinellas State: FL';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('city', $data);
        $this->assertStringNotContainsStringIgnoringCase('Neighborhood', $data['city']);
        $this->assertStringNotContainsStringIgnoringCase('Sunridge', $data['city']);
    }

    /** City value must stop at a School District label. */
    public function test_city_does_not_bleed_into_school_district(): void
    {
        $raw = 'City: SEMINOLE School District: Pinellas County: Pinellas State: FL';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('city', $data);
        $this->assertStringNotContainsStringIgnoringCase('School District', $data['city']);
        $this->assertStringNotContainsStringIgnoringCase('School', $data['city']);
    }

    /** City value must stop at a Flood Zone label. */
    public function test_city_does_not_bleed_into_flood_zone(): void
    {
        $raw = 'City: SEMINOLE Flood Zone Code: X County: Pinellas State: FL';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('city', $data);
        $this->assertStringNotContainsStringIgnoringCase('Flood', $data['city']);
        $this->assertStringNotContainsStringIgnoringCase('Zone', $data['city']);
    }

    // ─── Carport bleed tests ──────────────────────────────────────────────────

    /** Carport must stop before Rental Rate Type (Landlord-specific label). */
    public function test_carport_does_not_bleed_into_rental_rate_type(): void
    {
        $raw = 'Carport: Yes Rental Rate Type: Monthly Bedrooms: 3 Bathrooms: 2';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('carport', $data);
        $this->assertStringNotContainsStringIgnoringCase('Rental Rate Type', $data['carport']);
        $this->assertStringNotContainsStringIgnoringCase('Monthly', $data['carport']);
    }

    /** Carport must stop before Tax Year. */
    public function test_carport_does_not_bleed_into_tax_year(): void
    {
        $raw = 'Carport: No Tax Year: 2023 Tax ID: 12-34-56-789';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('carport', $data);
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['carport']);
        $this->assertStringNotContainsStringIgnoringCase('2023', $data['carport']);
    }

    /** Carport must stop before Legal Subdivision / Legal Desc labels. */
    public function test_carport_does_not_bleed_into_legal_description(): void
    {
        $raw = 'Carport: No Legal Description: SUNRIDGE ESTATES LOT 14 Year Built: 1998';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('carport', $data);
        $this->assertStringNotContainsStringIgnoringCase('Legal', $data['carport']);
        $this->assertStringNotContainsStringIgnoringCase('SUNRIDGE', $data['carport']);
    }

    /** Carport must stop before Subdivision label. */
    public function test_carport_does_not_bleed_into_subdivision(): void
    {
        $raw = 'Carport: Yes Subdivision: Bayshore Terrace Sub Bedrooms: 3';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('carport', $data);
        $this->assertStringNotContainsStringIgnoringCase('Subdivision', $data['carport']);
    }

    // ─── Interior Features bleed tests ───────────────────────────────────────

    /** Interior Features must stop before Appliances label. */
    public function test_interior_features_does_not_bleed_into_appliances(): void
    {
        $raw = 'Interior Features: Ceiling Fans(s),Crown Molding,High Ceilings Appliances: Dishwasher,Refrigerator Roof Type: Shingle';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('interior_features', $data);
        $this->assertStringNotContainsStringIgnoringCase('Appliances', $data['interior_features']);
        $this->assertStringNotContainsStringIgnoringCase('Dishwasher', $data['interior_features']);
    }

    /** Interior Features must stop at a bare "Exterior Information" section header (no colon). */
    public function test_interior_features_does_not_bleed_into_exterior_information_header(): void
    {
        $raw = 'Interior Features: Ceiling Fans(s),Walk-In Closet(s) Exterior Information Roof Type: Shingle Exterior Construction: Block';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('interior_features', $data);
        $this->assertStringNotContainsStringIgnoringCase('Exterior Information', $data['interior_features']);
        $this->assertStringNotContainsStringIgnoringCase('Shingle', $data['interior_features']);
    }

    /** Interior Features must stop at a bare "Interior Information" section header (no colon). */
    public function test_interior_features_does_not_bleed_into_interior_information_header(): void
    {
        $raw = 'Interior Features: Stone Counters,Vaulted Ceiling Interior Information Air Conditioning: Central Air';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('interior_features', $data);
        $this->assertStringNotContainsStringIgnoringCase('Interior Information', $data['interior_features']);
        $this->assertStringNotContainsStringIgnoringCase('Air Conditioning', $data['interior_features']);
    }

    /** Interior Features must stop at "Rooms" bare section header. */
    public function test_interior_features_does_not_bleed_into_rooms_header(): void
    {
        $raw = 'Interior Features: Stone Counters,Crown Molding Rooms Kitchen: 12x10 Living Room: 14x16';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('interior_features', $data);
        $this->assertStringNotContainsStringIgnoringCase('Rooms', $data['interior_features']);
        $this->assertStringNotContainsStringIgnoringCase('Kitchen', $data['interior_features']);
    }

    // ─── Waterfront bleed tests ───────────────────────────────────────────────

    /** Waterfront must stop before Interior Information section header (bare, no colon). */
    public function test_waterfront_does_not_bleed_into_interior_information_header(): void
    {
        $raw = 'Waterfront: No Interior Information Air Conditioning: Central Air Heating: Central';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('waterfront', $data);
        $this->assertStringNotContainsStringIgnoringCase('Interior Information', $data['waterfront']);
        $this->assertStringNotContainsStringIgnoringCase('Air Conditioning', $data['waterfront']);
    }

    /** Waterfront must stop at Interior Information with colon form. */
    public function test_waterfront_does_not_bleed_into_interior_information_with_colon(): void
    {
        $raw = 'Waterfront: Yes Interior Information: Air Conditioning: Central Air';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('waterfront', $data);
        $this->assertStringNotContainsStringIgnoringCase('Interior', $data['waterfront']);
    }

    /** Waterfront normalized value must be yes or no (not contaminated text). */
    public function test_waterfront_from_residential_fixture_is_clean(): void
    {
        $raw = file_get_contents(base_path('tests/fixtures/mls/residential.txt'));
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('waterfront', $data);
        $this->assertContains($data['waterfront'], ['yes', 'no'],
            'waterfront must be "yes" or "no", got: ' . $data['waterfront']);
    }

    /** Waterfront from rental fixture must be clean. */
    public function test_waterfront_from_rental_fixture_is_clean(): void
    {
        $raw = file_get_contents(base_path('tests/fixtures/mls/rental.txt'));
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('waterfront', $data);
        $this->assertContains($data['waterfront'], ['yes', 'no'],
            'waterfront must be "yes" or "no", got: ' . $data['waterfront']);
    }

    // ─── Rent Includes bleed tests ────────────────────────────────────────────

    /** Rent Includes must stop before Water Frontage label. */
    public function test_rent_includes_does_not_bleed_into_water_frontage(): void
    {
        $raw = 'Rent Includes: Lawn Care,Trash Collection Water Frontage: 50 Waterfront: No Bedrooms: 3';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('rent_includes', $data);
        $this->assertStringNotContainsStringIgnoringCase('Water Frontage', $data['rent_includes']);
        $this->assertStringNotContainsStringIgnoringCase('50', $data['rent_includes']);
    }

    /** Rent Includes must stop before Waterfront label. */
    public function test_rent_includes_does_not_bleed_into_waterfront_label(): void
    {
        $raw = 'Rent Includes: Lawn Care,Trash Collection Waterfront: No Water Access: Bay/Harbor';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('rent_includes', $data);
        $this->assertStringNotContainsStringIgnoringCase('Waterfront', $data['rent_includes']);
    }

    /** Rent Includes from rental fixture must contain only clean values. */
    public function test_rent_includes_from_rental_fixture_is_clean(): void
    {
        $raw = file_get_contents(base_path('tests/fixtures/mls/rental.txt'));
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('rent_includes', $data);
        $this->assertStringNotContainsStringIgnoringCase('Water Frontage', $data['rent_includes']);
        $this->assertStringNotContainsStringIgnoringCase('Waterfront', $data['rent_includes']);
        $this->assertStringNotContainsStringIgnoringCase('Flood', $data['rent_includes']);
    }

    // ─── Water View bleed tests ───────────────────────────────────────────────

    /** Water View must stop before Special Assessment label. */
    public function test_water_view_does_not_bleed_into_special_assessment(): void
    {
        $raw = 'Water View: Lake Special Assessment Y/N: No Tax Year: 2023 Bedrooms: 3';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('water_view', $data);
        $this->assertStringNotContainsStringIgnoringCase('Special Assessment', $data['water_view']);
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['water_view']);
    }

    /** Water View must stop before Tax Year label. */
    public function test_water_view_does_not_bleed_into_tax_year(): void
    {
        $raw = 'Water View: Bay/Harbor - Partial Tax Year: 2023 Annual CDD: $960';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('water_view', $data);
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['water_view']);
        $this->assertStringNotContainsStringIgnoringCase('2023', $data['water_view']);
    }

    /** Water View from residential fixture must be clean. */
    public function test_water_view_from_residential_fixture_is_clean(): void
    {
        $raw = file_get_contents(base_path('tests/fixtures/mls/residential.txt'));
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('water_view', $data);
        $this->assertStringNotContainsStringIgnoringCase('Assessment', $data['water_view']);
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['water_view']);
        $this->assertStringNotContainsStringIgnoringCase('Flood', $data['water_view']);
    }

    /** Water View from rental fixture must be clean. */
    public function test_water_view_from_rental_fixture_is_clean(): void
    {
        $raw = file_get_contents(base_path('tests/fixtures/mls/rental.txt'));
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('water_view', $data);
        $this->assertStringNotContainsStringIgnoringCase('Assessment', $data['water_view']);
        $this->assertStringNotContainsStringIgnoringCase('Flood', $data['water_view']);
    }

    // ─── Appliances bleed tests ───────────────────────────────────────────────

    /** Appliances must stop at "Exterior Information" bare header (former post-extraction hack). */
    public function test_appliances_does_not_bleed_into_exterior_information_header(): void
    {
        $raw = 'Appliances: Dishwasher,Microwave,Refrigerator Exterior Information Roof Type: Shingle';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('appliances', $data);
        $this->assertStringNotContainsStringIgnoringCase('Exterior Information', $data['appliances']);
        $this->assertStringNotContainsStringIgnoringCase('Shingle', $data['appliances']);
    }

    /** Appliances must stop at "Interior Information" bare header. */
    public function test_appliances_does_not_bleed_into_interior_information_header(): void
    {
        $raw = 'Appliances: Dishwasher,Range,Refrigerator Interior Information Air Conditioning: Central Air';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('appliances', $data);
        $this->assertStringNotContainsStringIgnoringCase('Interior Information', $data['appliances']);
        $this->assertStringNotContainsStringIgnoringCase('Air Conditioning', $data['appliances']);
    }

    /** Appliances must stop at "Rooms" bare header. */
    public function test_appliances_does_not_bleed_into_rooms_header(): void
    {
        $raw = 'Appliances: Dishwasher,Washer,Dryer Rooms Kitchen: 12x10 Dining Room: 10x10';
        $data = $this->parseRaw($raw);

        $this->assertArrayHasKey('appliances', $data);
        $this->assertStringNotContainsStringIgnoringCase('Rooms', $data['appliances']);
        $this->assertStringNotContainsStringIgnoringCase('Kitchen', $data['appliances']);
    }

    // ─── Seller fixture end-to-end validation ─────────────────────────────────

    /** Full residential fixture: all mapped Seller fields parse correctly with no contamination. */
    public function test_seller_residential_fixture_all_fields_clean(): void
    {
        $raw = file_get_contents(base_path('tests/fixtures/mls/residential.txt'));
        $data = $this->parseRaw($raw);

        // City clean
        $this->assertEquals('Tampa', $data['city'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('County', $data['city'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Hillsborough', $data['city'] ?? '');

        // County clean
        $this->assertEquals('Hillsborough', $data['county'] ?? '');

        // Core fields
        $this->assertEquals('4', $data['bedrooms'] ?? '');
        $this->assertEquals('2.5', $data['bathrooms'] ?? '');
        $this->assertEquals('2184', $data['heated_sqft'] ?? '');
        $this->assertEquals('1998', $data['year_built'] ?? '');
        $this->assertEquals('80x120', $data['lot_dimensions'] ?? '');
        $this->assertEquals('0.22', $data['lot_size_acres'] ?? '');

        // Pool, Garage, Carport — normalizeFormYesNo() returns Title Case "Yes"/"No"
        $this->assertEquals('Yes', $data['pool'] ?? '');
        $this->assertEquals('Yes', $data['garage'] ?? '');
        $this->assertEquals('No', $data['carport'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Appliances', $data['carport'] ?? '');

        // A/C and Heating clean
        $this->assertStringContainsStringIgnoringCase('Central', $data['air_conditioning'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Interior Features', $data['air_conditioning'] ?? '');
        $this->assertStringContainsStringIgnoringCase('Central', $data['heating_fuel'] ?? '');

        // Interior Features clean
        $this->assertStringContainsStringIgnoringCase('Ceiling Fans', $data['interior_features'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Appliances', $data['interior_features'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Exterior Information', $data['interior_features'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Interior Information', $data['interior_features'] ?? '');

        // Appliances clean
        $this->assertStringContainsStringIgnoringCase('Dishwasher', $data['appliances'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Roof Type', $data['appliances'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Exterior', $data['appliances'] ?? '');

        // Waterfront clean
        $this->assertEquals('no', $data['waterfront'] ?? '');

        // Water View clean
        $this->assertStringContainsString('Lake', $data['water_view'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['water_view'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Assessment', $data['water_view'] ?? '');

        // Tax / Legal / Flood clean
        $this->assertEquals('19-30-17-45612-000-1410', $data['tax_id'] ?? '');
        $this->assertEquals('2023', $data['tax_year'] ?? '');
        $this->assertEquals('X', $data['flood_zone_code'] ?? '');
        $this->assertStringContainsString('SUNRIDGE', strtoupper($data['legal_description'] ?? ''));

        // Structural clean
        $this->assertStringContainsStringIgnoringCase('Shingle', $data['roof_type'] ?? '');
        $this->assertStringContainsStringIgnoringCase('Block', $data['exterior_construction'] ?? '');
        $this->assertStringContainsStringIgnoringCase('Slab', $data['foundation'] ?? '');
        $this->assertStringContainsStringIgnoringCase('Public', $data['sewer'] ?? '');
    }

    // ─── Landlord fixture end-to-end validation ───────────────────────────────

    /** Full rental fixture: all mapped Landlord fields parse correctly with no contamination. */
    public function test_landlord_rental_fixture_all_fields_clean(): void
    {
        $raw = file_get_contents(base_path('tests/fixtures/mls/rental.txt'));
        $data = $this->parseRaw($raw);

        // City clean
        $this->assertStringContainsString('Petersburg', $data['city'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('County', $data['city'] ?? '');

        // County clean
        $this->assertEquals('Pinellas', $data['county'] ?? '');

        // Core fields
        $this->assertEquals('3', $data['bedrooms'] ?? '');
        $this->assertEquals('2', $data['bathrooms'] ?? '');
        $this->assertEquals('1650', $data['heated_sqft'] ?? '');
        $this->assertEquals('2002', $data['year_built'] ?? '');
        $this->assertEquals('65x110', $data['lot_dimensions'] ?? '');

        // Rental price
        $this->assertEquals('2800', $data['price'] ?? '');

        // Pool, Garage, Carport — normalizeFormYesNo() returns Title Case "Yes"/"No"
        $this->assertEquals('No', $data['pool'] ?? '');
        $this->assertEquals('Yes', $data['garage'] ?? '');
        $this->assertEquals('No', $data['carport'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Rental Rate', $data['carport'] ?? '');

        // A/C and Heating clean
        $this->assertStringContainsStringIgnoringCase('Central', $data['air_conditioning'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Interior', $data['air_conditioning'] ?? '');

        // Interior Features clean
        $this->assertStringContainsStringIgnoringCase('Ceiling Fans', $data['interior_features'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Appliances', $data['interior_features'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Exterior', $data['interior_features'] ?? '');

        // Appliances clean
        $this->assertStringContainsStringIgnoringCase('Dishwasher', $data['appliances'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Roof', $data['appliances'] ?? '');

        // Rent Includes clean
        $this->assertStringContainsStringIgnoringCase('Lawn Care', $data['rent_includes'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Waterfront', $data['rent_includes'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Water Frontage', $data['rent_includes'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Flood', $data['rent_includes'] ?? '');

        // Waterfront clean
        $this->assertEquals('no', $data['waterfront'] ?? '');

        // Water View clean
        $this->assertStringContainsString('Bay', $data['water_view'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Assessment', $data['water_view'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['water_view'] ?? '');

        // Tax / Flood clean
        $this->assertEquals('25-31-17-67890-001-0220', $data['tax_id'] ?? '');
        $this->assertEquals('2023', $data['tax_year'] ?? '');
        $this->assertEquals('AE', $data['flood_zone_code'] ?? '');

        // Structural clean
        $this->assertStringContainsStringIgnoringCase('Shingle', $data['roof_type'] ?? '');
        $this->assertStringContainsStringIgnoringCase('Block', $data['exterior_construction'] ?? '');
        $this->assertStringContainsStringIgnoringCase('Slab', $data['foundation'] ?? '');

        // Lease term fields clean
        $this->assertStringContainsStringIgnoringCase('12 Months', $data['terms_of_lease'] ?? '');
        $this->assertStringContainsStringIgnoringCase('Electricity', $data['tenant_pays'] ?? '');
    }

    // ─── Boundary interaction round-trip tests ────────────────────────────────

    /**
     * Appliances followed by Interior Features (both present in residential fixture):
     * Each should contain only its own values.
     */
    public function test_appliances_and_interior_features_do_not_cross_contaminate(): void
    {
        $raw = 'Interior Features: Ceiling Fans(s),Crown Molding,Walk-In Closet(s) Appliances: Dishwasher,Microwave,Refrigerator Roof Type: Shingle';
        $data = $this->parseRaw($raw);

        // interior_features must NOT include appliance values
        $this->assertStringNotContainsStringIgnoringCase('Dishwasher', $data['interior_features'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Refrigerator', $data['interior_features'] ?? '');

        // appliances must NOT include interior feature values
        $this->assertStringNotContainsStringIgnoringCase('Crown Molding', $data['appliances'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Walk-In Closet', $data['appliances'] ?? '');
    }

    /**
     * Water-related fields do not cross-contaminate each other.
     * Water Access, Water View, and Waterfront are each separate fields.
     */
    public function test_water_fields_do_not_cross_contaminate(): void
    {
        $raw = 'Waterfront: No Water Access: Lake,Pond Water View: Lake Flood Zone Code: X';
        $data = $this->parseRaw($raw);

        $this->assertEquals('no', $data['waterfront'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Water Access', $data['waterfront'] ?? '');

        $this->assertStringContainsString('Lake', $data['water_access'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Water View', $data['water_access'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Flood', $data['water_access'] ?? '');

        $this->assertStringContainsString('Lake', $data['water_view'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Flood', $data['water_view'] ?? '');
    }

    /**
     * Inline single-line MLS text (all fields on one line without newlines) must parse
     * Seller fields cleanly. This is the hardest case for boundary protection.
     */
    public function test_seller_inline_single_line_text_no_bleed(): void
    {
        $raw =
            'City: Tampa County: Hillsborough State: FL Zip: 33610 ' .
            'List Price: $485,000 Bedrooms: 4 Bathrooms: 2.5 ' .
            'Heated Sq. Ft.: 2,184 Year Built: 1998 ' .
            'Pool: Yes Garage: Yes Carport: No ' .
            'Air Conditioning: Central Air Heating and Fuel: Central,Electric ' .
            'Interior Features: Ceiling Fans(s),Crown Molding,Walk-In Closet(s) ' .
            'Appliances: Dishwasher,Disposal,Microwave Roof Type: Shingle ' .
            'Waterfront: No Water Access: Lake Water View: Lake ' .
            'Flood Zone Code: X Tax Year: 2023';

        $data = $this->parseRaw($raw);

        // City clean
        $this->assertArrayHasKey('city', $data);
        $this->assertStringNotContainsStringIgnoringCase('County', $data['city']);
        $this->assertStringNotContainsStringIgnoringCase('Hillsborough', $data['city']);

        // Carport clean (no Tax Year bleed)
        $this->assertArrayHasKey('carport', $data);
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['carport']);

        // Interior Features clean
        $this->assertArrayHasKey('interior_features', $data);
        $this->assertStringNotContainsStringIgnoringCase('Appliances', $data['interior_features']);
        $this->assertStringNotContainsStringIgnoringCase('Dishwasher', $data['interior_features']);

        // Appliances clean
        $this->assertArrayHasKey('appliances', $data);
        $this->assertStringNotContainsStringIgnoringCase('Roof', $data['appliances']);
        $this->assertStringNotContainsStringIgnoringCase('Shingle', $data['appliances']);

        // Waterfront clean
        $this->assertEquals('no', $data['waterfront'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Water Access', $data['waterfront'] ?? '');

        // Water View clean
        $this->assertStringNotContainsStringIgnoringCase('Flood', $data['water_view'] ?? '');
    }

    /**
     * Inline single-line Landlord/rental text must parse cleanly.
     */
    public function test_landlord_inline_single_line_text_no_bleed(): void
    {
        $raw =
            'City: St. Petersburg County: Pinellas State: FL Zip: 33705 ' .
            'Monthly Rent: $2,800 Bedrooms: 3 Bathrooms: 2 ' .
            'Heated Sq. Ft.: 1,650 Year Built: 2002 ' .
            'Carport: No Rental Rate Type: Monthly ' .
            'Air Conditioning: Central Air Heating and Fuel: Central,Gas ' .
            'Interior Features: Ceiling Fans(s),Stone Counters Appliances: Dishwasher,Range ' .
            'Rent Includes: Lawn Care,Trash Collection ' .
            'Water Frontage: 50 Waterfront: No Water View: Bay/Harbor - Partial ' .
            'Special Assessment Y/N: No Tax Year: 2023';

        $data = $this->parseRaw($raw);

        // Carport must not bleed into Rental Rate Type
        $this->assertArrayHasKey('carport', $data);
        $this->assertStringNotContainsStringIgnoringCase('Rental Rate', $data['carport']);
        $this->assertStringNotContainsStringIgnoringCase('Monthly', $data['carport']);

        // Rent Includes must not bleed into Water Frontage
        $this->assertArrayHasKey('rent_includes', $data);
        $this->assertStringNotContainsStringIgnoringCase('Water Frontage', $data['rent_includes']);
        $this->assertStringNotContainsStringIgnoringCase('Waterfront', $data['rent_includes']);

        // Waterfront must not bleed into Water View
        $this->assertEquals('no', $data['waterfront'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Water View', $data['waterfront'] ?? '');

        // Water View must not bleed into Special Assessment or Tax
        $this->assertStringNotContainsStringIgnoringCase('Special', $data['water_view'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['water_view'] ?? '');

        // Interior Features must not bleed into Appliances
        $this->assertStringNotContainsStringIgnoringCase('Appliances', $data['interior_features'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Dishwasher', $data['interior_features'] ?? '');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    /** Parse raw text directly (no URL fetch). */
    private function parseRaw(string $raw): array
    {
        $result = $this->service->import('', $raw);
        $this->assertTrue($result['success'], 'Parser returned failure: ' . ($result['error'] ?? ''));
        return $result['data'];
    }
}
