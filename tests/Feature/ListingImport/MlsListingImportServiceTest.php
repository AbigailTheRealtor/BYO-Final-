<?php

namespace Tests\Feature\ListingImport;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\ListingImport\MlsListingImportService;
use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\MlsNormalizer;
use App\Services\ListingImport\MlsCoverageReporter;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;

class MlsListingImportServiceTest extends TestCase
{
    private MlsListingImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MlsListingImportService();
    }

    // ─── URL validation ───────────────────────────────────────────────────────

    public function test_empty_url_and_no_raw_text_returns_failure(): void
    {
        $result = $this->service->import('');

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);
        $this->assertIsString($result['error']);
    }

    public function test_invalid_url_returns_friendly_error(): void
    {
        $result = $this->service->import('not-a-url');

        $this->assertFalse($result['success']);
        $this->assertStringContainsStringIgnoringCase('url', $result['error']);
    }

    public function test_unreachable_url_returns_friendly_error(): void
    {
        Http::fake([
            '*' => Http::response('', 503),
        ]);

        $result = $this->service->import('https://example.invalid/listing/12345');

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);
    }

    public function test_connection_exception_returns_friendly_error(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $result = $this->service->import('https://example.invalid/listing/12345');

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);
    }

    // ─── Sale listing parsing ─────────────────────────────────────────────────

    public function test_valid_matrix_url_returns_parsed_sale_fields(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/ListingImport/matrix_sample.html'));

        Http::fake([
            '*' => Http::response($html, 200),
        ]);

        $result = $this->service->import('https://www.stellarmls.com/matrix/listing/12345');

        $this->assertTrue($result['success'], 'Expected success but got error: ' . ($result['error'] ?? ''));
        $this->assertEmpty($result['error']);

        $data = $result['data'];

        $this->assertArrayHasKey('mls_number', $data);
        $this->assertEquals('T3456789', $data['mls_number']);

        $this->assertArrayHasKey('price', $data);
        $this->assertEquals('450000', $data['price']);

        $this->assertArrayHasKey('bedrooms', $data);
        $this->assertEquals('4', $data['bedrooms']);

        $this->assertArrayHasKey('bathrooms', $data);
        $this->assertEquals('2.5', $data['bathrooms']);

        $this->assertArrayHasKey('heated_sqft', $data);
        $this->assertEquals('2150', $data['heated_sqft']);

        $this->assertArrayHasKey('year_built', $data);
        $this->assertEquals('2003', $data['year_built']);

        $this->assertArrayHasKey('lot_dimensions', $data);
        $this->assertStringContainsString('75', $data['lot_dimensions']);

        $this->assertArrayHasKey('pool', $data);
        $this->assertArrayHasKey('garage', $data);
        $this->assertArrayHasKey('appliances', $data);
        $this->assertArrayHasKey('air_conditioning', $data);
        $this->assertArrayHasKey('description', $data);
    }

    public function test_no_rental_signals_produces_sale_hint(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/ListingImport/matrix_sample.html'));

        Http::fake(['*' => Http::response($html, 200)]);

        $result = $this->service->import('https://www.stellarmls.com/matrix/listing/12345');

        $this->assertTrue($result['success']);
        $this->assertEquals('sale', $result['data']['listing_type_hint']);
    }

    // ─── Tax / Legal / Flood Zone / Zoning parsing (Residential) ─────────────

    public function test_sale_fixture_parses_tax_and_legal_fields(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/ListingImport/matrix_sample.html'));

        Http::fake(['*' => Http::response($html, 200)]);

        $result = $this->service->import('https://www.stellarmls.com/matrix/listing/12345');

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('tax_id', $data);
        $this->assertEquals('12-34-56-78901', $data['tax_id']);

        $this->assertArrayHasKey('tax_year', $data);
        $this->assertEquals('2023', $data['tax_year']);

        $this->assertArrayHasKey('annual_taxes', $data);
        $this->assertEquals('4812', $data['annual_taxes']);

        $this->assertArrayHasKey('legal_description', $data);
        $this->assertStringContainsString('LOT 15', $data['legal_description']);

        $this->assertArrayHasKey('flood_zone_code', $data);
        $this->assertEquals('X', $data['flood_zone_code']); // normalized to uppercase

        $this->assertArrayHasKey('flood_zone_date', $data);
        $this->assertStringContainsString('2009', $data['flood_zone_date']);

        $this->assertArrayHasKey('flood_zone_panel', $data);
        $this->assertStringContainsString('12057C', $data['flood_zone_panel']);

        $this->assertArrayHasKey('zoning', $data);
        $this->assertStringContainsString('RSF', $data['zoning']);

        $this->assertArrayHasKey('additional_parcels', $data);
        $this->assertEquals('no', $data['additional_parcels']); // normalized to lowercase

        $this->assertArrayHasKey('total_parcel_count', $data);
        $this->assertEquals('1', $data['total_parcel_count']);
    }

    // ─── Rental listing parsing ───────────────────────────────────────────────

    public function test_rental_rate_type_produces_rental_hint(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/ListingImport/matrix_rental_sample.html'));

        Http::fake(['*' => Http::response($html, 200)]);

        $result = $this->service->import('https://www.stellarmls.com/matrix/listing/99999');

        $this->assertTrue($result['success']);
        $this->assertEquals('rental', $result['data']['listing_type_hint']);
    }

    public function test_raw_text_with_monthly_rent_produces_rental_hint(): void
    {
        $rawText = "Bedrooms: 2  Bathrooms: 1  Monthly Rent: \$1,800  Year Built: 2010";

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertEquals('rental', $result['data']['listing_type_hint']);
    }

    public function test_raw_text_sale_price_without_rental_signals_sale_hint(): void
    {
        $rawText = "MLS #: A1234567  List Price: \$350,000  Bedrooms: 3  Bathrooms: 2  Year Built: 1999";

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertEquals('sale', $result['data']['listing_type_hint']);
        $this->assertEquals('350000', $result['data']['price']);
    }

    public function test_rental_fixture_parses_lease_term_fields(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/ListingImport/matrix_rental_sample.html'));

        Http::fake(['*' => Http::response($html, 200)]);

        $result = $this->service->import('https://www.stellarmls.com/matrix/listing/99999');

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('lease_amount_frequency', $data);
        $this->assertEquals('monthly', $data['lease_amount_frequency']); // normalized

        $this->assertArrayHasKey('minimum_security_deposit', $data);
        $this->assertEquals('2400', $data['minimum_security_deposit']);

        $this->assertArrayHasKey('application_fee', $data);
        $this->assertEquals('75', $data['application_fee']);

        $this->assertArrayHasKey('tenant_pays', $data);
        $this->assertStringContainsString('Electric', $data['tenant_pays']);

        $this->assertArrayHasKey('terms_of_lease', $data);
        $this->assertStringContainsString('12 Months', $data['terms_of_lease']);

        $this->assertArrayHasKey('rent_includes', $data);
        $this->assertStringContainsString('Water', $data['rent_includes']);
    }

    public function test_rental_fixture_parses_tax_fields(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/ListingImport/matrix_rental_sample.html'));

        Http::fake(['*' => Http::response($html, 200)]);

        $result = $this->service->import('https://www.stellarmls.com/matrix/listing/99999');

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('tax_id', $data);
        $this->assertEquals('23-45-67-89012', $data['tax_id']);

        $this->assertArrayHasKey('tax_year', $data);
        $this->assertEquals('2023', $data['tax_year']);

        $this->assertArrayHasKey('annual_taxes', $data);
        $this->assertEquals('2150', $data['annual_taxes']);

        $this->assertArrayHasKey('flood_zone_code', $data);
        $this->assertEquals('AE', $data['flood_zone_code']); // normalized to uppercase
    }

    // ─── Raw text parsing ─────────────────────────────────────────────────────

    public function test_raw_text_parses_basic_fields(): void
    {
        $rawText = "Bedrooms: 3  Bathrooms: 2  Heated Sq Ft: 1,800  Year Built: 2015  Pool: Yes  Garage: 1 Car";

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertEquals('3', $result['data']['bedrooms']);
        $this->assertEquals('2', $result['data']['bathrooms']);
        $this->assertEquals('1800', $result['data']['heated_sqft']);
        $this->assertEquals('2015', $result['data']['year_built']);
    }

    public function test_raw_text_parses_tax_legal_fields(): void
    {
        $rawText = implode('  ', [
            'Tax ID: 99-88-77-66',
            'Tax Year: 2022',
            'Taxes (Annual Amount): $3,500',
            'Legal Description: LOT 1 BLOCK A SUNRIDGE SUB PB 22 PG 4',
            'Flood Zone Code: X',
            'Zoning: R2',
            'Additional Parcels: No',
            'Total Number of Parcels: 1',
        ]);

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertEquals('99-88-77-66', $data['tax_id']);
        $this->assertEquals('2022', $data['tax_year']);
        $this->assertEquals('3500', $data['annual_taxes']);
        $this->assertStringContainsString('LOT 1', $data['legal_description']);
        $this->assertEquals('X', $data['flood_zone_code']);
        $this->assertStringContainsString('R2', $data['zoning']);
        $this->assertEquals('no', $data['additional_parcels']); // normalized
        $this->assertEquals('1', $data['total_parcel_count']);
    }

    public function test_raw_text_parses_rental_lease_fields(): void
    {
        $rawText = implode('  ', [
            'Lease Amount Frequency: Monthly',
            'Minimum Security Deposit: $1,800',
            'Terms of Lease: 12 Months, Month to Month',
            'Tenant Pays: Electric, Internet',
        ]);

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertEquals('monthly', $data['lease_amount_frequency']); // normalized
        $this->assertEquals('1800', $data['minimum_security_deposit']);
        $this->assertStringContainsString('12 Months', $data['terms_of_lease']);
        $this->assertStringContainsString('Electric', $data['tenant_pays']);
    }

    // ─── Parser boundary protection ───────────────────────────────────────────

    public function test_pool_value_does_not_bleed_into_garage_label(): void
    {
        // No separator between Pool and Garage — stop-pattern must cut off at "Garage:"
        $rawText = "Pool: Yes Garage: 2 Car Carport: No Appliances: Dishwasher";

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('pool', $data);
        $this->assertStringNotContainsStringIgnoringCase('Garage', $data['pool']);
    }

    public function test_carport_value_does_not_bleed_into_appliances_label(): void
    {
        $rawText = "Carport: No Appliances: Dishwasher, Refrigerator Interior Features: Walk-in Closet";

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('carport', $data);
        $this->assertStringNotContainsStringIgnoringCase('Appliances', $data['carport']);
    }

    public function test_air_conditioning_value_does_not_bleed_into_heating_label(): void
    {
        $rawText = "Air Conditioning: Central Air Heating: Electric Year Built: 2005";

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('air_conditioning', $data);
        $this->assertStringNotContainsStringIgnoringCase('Heating', $data['air_conditioning']);
    }

    // ─── HOA / CDD parsing (sale fixture) ────────────────────────────────────

    public function test_sale_fixture_parses_hoa_fields(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/ListingImport/matrix_sample.html'));
        Http::fake(['*' => Http::response($html, 200)]);

        $result = $this->service->import('https://www.stellarmls.com/matrix/listing/12345');

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('has_hoa', $data);
        $this->assertEquals('yes', $data['has_hoa']); // normalized

        $this->assertArrayHasKey('association_name', $data);
        $this->assertStringContainsString('Ocean Breeze', $data['association_name']);

        $this->assertArrayHasKey('association_fee_amount', $data);
        $this->assertEquals('185', $data['association_fee_amount']);

        $this->assertArrayHasKey('association_fee_frequency', $data);
        $this->assertEquals('monthly', $data['association_fee_frequency']); // normalized

        $this->assertArrayHasKey('has_cdd', $data);
        $this->assertEquals('no', $data['has_cdd']); // normalized
    }

    public function test_rental_fixture_parses_hoa_and_cdd_fields(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/ListingImport/matrix_rental_sample.html'));
        Http::fake(['*' => Http::response($html, 200)]);

        $result = $this->service->import('https://www.stellarmls.com/matrix/listing/99999');

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('has_hoa', $data);
        $this->assertEquals('no', $data['has_hoa']); // normalized

        $this->assertArrayHasKey('has_cdd', $data);
        $this->assertEquals('yes', $data['has_cdd']); // normalized

        $this->assertArrayHasKey('annual_cdd_fee', $data);
        $this->assertEquals('960', $data['annual_cdd_fee']);
    }

    public function test_raw_text_parses_hoa_fields(): void
    {
        $rawText = implode('  ', [
            'Association Y/N: Yes',
            'Association Name: Lakewood Owners Association',
            'Association Fee: $250',
            'Association Fee Freq: Quarterly',
            'CDD Y/N: No',
        ]);

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertEquals('yes', $data['has_hoa']);
        $this->assertStringContainsString('Lakewood', $data['association_name']);
        $this->assertEquals('250', $data['association_fee_amount']);
        $this->assertEquals('quarterly', $data['association_fee_frequency']); // normalized
        $this->assertEquals('no', $data['has_cdd']);
    }

    public function test_raw_text_parses_cdd_annual_fee(): void
    {
        $rawText = 'CDD Y/N: Yes  CDD Annual Amount: $1,450  Association Y/N: No';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertEquals('yes', $result['data']['has_cdd']);
        $this->assertEquals('1450', $result['data']['annual_cdd_fee']);
        $this->assertEquals('no', $result['data']['has_hoa']);
    }

    // ─── MlsNormalizer ────────────────────────────────────────────────────────

    /**
     * @dataProvider booleanNormalizerProvider
     */
    public function test_normalizer_boolean_coercion(string $field, string $input, string $expected): void
    {
        $this->assertEquals($expected, MlsNormalizer::normalize($field, $input));
    }

    public static function booleanNormalizerProvider(): array
    {
        return [
            'pool Yes'             => ['pool', 'Yes', 'yes'],
            'pool Y'               => ['pool', 'Y', 'yes'],
            'pool TRUE'            => ['pool', 'TRUE', 'yes'],
            'pool 1'               => ['pool', '1', 'yes'],
            'pool No'              => ['pool', 'No', 'no'],
            'pool N'               => ['pool', 'N', 'no'],
            'pool NO'              => ['pool', 'NO', 'no'],
            'pool 0'               => ['pool', '0', 'no'],
            'garage Yes'           => ['garage', 'Yes', 'yes'],
            'carport No'           => ['carport', 'No', 'no'],
            'waterfront Yes'       => ['waterfront', 'Yes', 'yes'],
            'additional_parcels Y' => ['additional_parcels', 'Y', 'yes'],
            'pool In Ground'       => ['pool', 'In Ground', 'In Ground'],
        ];
    }

    public function test_normalizer_furnishing(): void
    {
        $this->assertEquals('furnished', MlsNormalizer::normalize('furnished', 'Furnished'));
        $this->assertEquals('unfurnished', MlsNormalizer::normalize('furnished', 'Unfurnished'));
        $this->assertEquals('negotiable', MlsNormalizer::normalize('furnished', 'Negotiable'));
        $this->assertEquals('turnkey', MlsNormalizer::normalize('furnished', 'Turnkey'));
        $this->assertEquals('partial', MlsNormalizer::normalize('furnished', 'Partial'));
    }

    public function test_normalizer_flood_zone(): void
    {
        $this->assertEquals('X', MlsNormalizer::normalize('flood_zone_code', 'X'));
        $this->assertEquals('AE', MlsNormalizer::normalize('flood_zone_code', 'ae'));
        $this->assertEquals('VE', MlsNormalizer::normalize('flood_zone_code', 've'));
        $this->assertEquals('yes', MlsNormalizer::normalize('flood_zone_code', 'Flood Insurance Required'));
        $this->assertEquals('yes', MlsNormalizer::normalize('flood_zone_code', 'Insurance Required'));
    }

    public function test_normalizer_hoa_fee_frequency(): void
    {
        $this->assertEquals('monthly',      MlsNormalizer::normalize('association_fee_frequency', 'Monthly'));
        $this->assertEquals('quarterly',    MlsNormalizer::normalize('association_fee_frequency', 'Quarterly'));
        $this->assertEquals('annually',     MlsNormalizer::normalize('association_fee_frequency', 'Annually'));
        $this->assertEquals('annually',     MlsNormalizer::normalize('association_fee_frequency', 'annual'));
        $this->assertEquals('semi_annually',MlsNormalizer::normalize('association_fee_frequency', 'Semi-Annually'));
        $this->assertEquals('one_time',     MlsNormalizer::normalize('association_fee_frequency', 'One-Time'));
    }

    public function test_normalizer_has_hoa_and_has_cdd(): void
    {
        $this->assertEquals('yes', MlsNormalizer::normalize('has_hoa', 'Yes'));
        $this->assertEquals('no',  MlsNormalizer::normalize('has_hoa', 'No'));
        $this->assertEquals('yes', MlsNormalizer::normalize('has_cdd', 'Y'));
        $this->assertEquals('no',  MlsNormalizer::normalize('has_cdd', 'N'));
        $this->assertEquals('yes', MlsNormalizer::normalize('has_cdd', 'TRUE'));
        $this->assertEquals('no',  MlsNormalizer::normalize('has_hoa', '0'));
    }

    public function test_normalizer_lease_frequency(): void
    {
        $this->assertEquals('monthly', MlsNormalizer::normalize('lease_amount_frequency', 'Monthly'));
        $this->assertEquals('annually', MlsNormalizer::normalize('lease_amount_frequency', 'Annually'));
        $this->assertEquals('annually', MlsNormalizer::normalize('lease_amount_frequency', 'annual'));
        $this->assertEquals('weekly', MlsNormalizer::normalize('lease_amount_frequency', 'Weekly'));
        $this->assertEquals('month_to_month', MlsNormalizer::normalize('lease_amount_frequency', 'Month to Month'));
        $this->assertEquals('12_months', MlsNormalizer::normalize('lease_amount_frequency', '12 Months'));
        $this->assertEquals('seasonal', MlsNormalizer::normalize('lease_amount_frequency', 'Seasonal'));
    }

    public function test_normalizer_unknown_field_passes_value_through(): void
    {
        $this->assertEquals('SomeValue', MlsNormalizer::normalize('description', 'SomeValue'));
        $this->assertEquals('RSF-4', MlsNormalizer::normalize('zoning', 'RSF-4'));
    }

    // ─── Field map correctness ────────────────────────────────────────────────

    public function test_field_map_returns_arrays_for_all_four_roles(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $map = MlsFieldMap::forRole($role);
            $this->assertIsArray($map);
            $this->assertNotEmpty($map, "Field map for role '{$role}' should not be empty");
        }
    }

    public function test_seller_map_tax_id_maps_to_parcel_id(): void
    {
        $map = MlsFieldMap::forRole('seller');

        $this->assertArrayHasKey('tax_id', $map, 'seller map must have tax_id canonical key');
        $this->assertEquals('parcel_id', $map['tax_id'], 'tax_id must map to parcel_id, not tax_id');
    }

    public function test_seller_map_does_not_contain_invalid_mls_number_mapping(): void
    {
        $map = MlsFieldMap::forRole('seller');

        // mls_number has no Livewire property on SellerOfferListing — must be absent
        $this->assertArrayNotHasKey('mls_number', $map);
    }

    public function test_landlord_map_does_not_contain_application_fee(): void
    {
        $map = MlsFieldMap::forRole('landlord');

        // application_fee property does not exist on LandlordOfferListing
        $this->assertArrayNotHasKey('application_fee', $map);
    }

    public function test_landlord_map_does_not_contain_mls_number_mapping(): void
    {
        $map = MlsFieldMap::forRole('landlord');

        $this->assertArrayNotHasKey('mls_number', $map);
    }

    public function test_landlord_map_includes_rental_fields(): void
    {
        $map = MlsFieldMap::forRole('landlord');

        // Existing rental fields still present
        $this->assertArrayHasKey('available_date', $map);
        $this->assertArrayHasKey('rent_includes', $map);

        // New rental fields added in this audit
        $this->assertArrayHasKey('minimum_security_deposit', $map);
        $this->assertArrayHasKey('lease_amount_frequency', $map);
        $this->assertArrayHasKey('terms_of_lease', $map);
        $this->assertArrayHasKey('tenant_pays', $map);
    }

    public function test_seller_and_landlord_maps_include_hoa_fields(): void
    {
        foreach (['seller', 'landlord'] as $role) {
            $map = MlsFieldMap::forRole($role);
            $this->assertArrayHasKey('has_hoa',                 $map, "Role '{$role}' must map has_hoa");
            $this->assertArrayHasKey('association_name',        $map, "Role '{$role}' must map association_name");
            $this->assertArrayHasKey('association_fee_amount',  $map, "Role '{$role}' must map association_fee_amount");
            $this->assertArrayHasKey('association_fee_frequency', $map, "Role '{$role}' must map association_fee_frequency");
            $this->assertArrayHasKey('has_cdd',                 $map, "Role '{$role}' must map has_cdd");
            $this->assertArrayHasKey('annual_cdd_fee',          $map, "Role '{$role}' must map annual_cdd_fee");
        }
    }

    public function test_hoa_fields_map_to_correct_livewire_properties_on_seller(): void
    {
        $map = MlsFieldMap::forRole('seller');
        $this->assertEquals('has_hoa',                 $map['has_hoa']);
        $this->assertEquals('association_name',        $map['association_name']);
        $this->assertEquals('association_fee_amount',  $map['association_fee_amount']);
        $this->assertEquals('association_fee_frequency', $map['association_fee_frequency']);
        $this->assertEquals('has_cdd',                 $map['has_cdd']);
        $this->assertEquals('annual_cdd_fee',          $map['annual_cdd_fee']);
    }

    public function test_buyer_and_tenant_maps_omit_hoa_fields(): void
    {
        foreach (['buyer', 'tenant'] as $role) {
            $map = MlsFieldMap::forRole($role);
            $this->assertArrayNotHasKey('has_hoa',                 $map, "Role '{$role}' must not map has_hoa");
            $this->assertArrayNotHasKey('association_fee_amount',  $map, "Role '{$role}' must not map association_fee_amount");
            $this->assertArrayNotHasKey('has_cdd',                 $map, "Role '{$role}' must not map has_cdd");
        }
    }

    public function test_landlord_map_includes_tax_legal_flood_zone_fields(): void
    {
        $map = MlsFieldMap::forRole('landlord');

        $this->assertArrayHasKey('tax_id', $map);
        $this->assertEquals('parcel_id', $map['tax_id']);
        $this->assertArrayHasKey('tax_year', $map);
        $this->assertArrayHasKey('annual_taxes', $map);
        $this->assertArrayHasKey('legal_description', $map);
        $this->assertArrayHasKey('flood_zone_code', $map);
    }

    public function test_seller_map_includes_tax_legal_flood_zone_fields(): void
    {
        $map = MlsFieldMap::forRole('seller');

        $this->assertArrayHasKey('tax_id', $map);
        $this->assertArrayHasKey('tax_year', $map);
        $this->assertArrayHasKey('annual_taxes', $map);
        $this->assertArrayHasKey('legal_description', $map);
        $this->assertArrayHasKey('flood_zone_code', $map);
        $this->assertArrayHasKey('additional_parcels', $map);
        $this->assertArrayHasKey('total_parcel_count', $map);
        $this->assertArrayHasKey('zoning', $map);
    }

    public function test_buyer_and_tenant_maps_omit_rental_only_fields(): void
    {
        $buyerMap  = MlsFieldMap::forRole('buyer');
        $tenantMap = MlsFieldMap::forRole('tenant');

        $this->assertArrayNotHasKey('application_fee', $buyerMap);
        $this->assertArrayNotHasKey('application_fee', $tenantMap);
    }

    public function test_buyer_map_omits_year_built(): void
    {
        $map = MlsFieldMap::forRole('buyer');

        // year_built property does not exist on BuyerOfferListing
        $this->assertArrayNotHasKey('year_built', $map);
    }

    public function test_tenant_map_omits_price_mapping(): void
    {
        $map = MlsFieldMap::forRole('tenant');

        // MLS listing price must not auto-fill Tenant's desired rental amount
        $this->assertArrayNotHasKey('price', $map);
    }

    public function test_buyer_and_tenant_maps_omit_owner_disclosure_fields(): void
    {
        foreach (['buyer', 'tenant'] as $role) {
            $map = MlsFieldMap::forRole($role);
            $this->assertArrayNotHasKey('tax_id', $map,           "Role '{$role}' must not map tax_id");
            $this->assertArrayNotHasKey('tax_year', $map,         "Role '{$role}' must not map tax_year");
            $this->assertArrayNotHasKey('annual_taxes', $map,     "Role '{$role}' must not map annual_taxes");
            $this->assertArrayNotHasKey('legal_description', $map, "Role '{$role}' must not map legal_description");
            $this->assertArrayNotHasKey('flood_zone_code', $map,  "Role '{$role}' must not map flood_zone_code");
        }
    }

    public function test_all_mapped_seller_properties_exist_on_component(): void
    {
        $map = MlsFieldMap::forRole('seller');

        foreach ($map as $canonicalKey => $propName) {
            $propName = ltrim($propName, '*');
            $this->assertTrue(
                property_exists(SellerOfferListing::class, $propName),
                "Seller map '{$canonicalKey}' → '{$propName}': property does not exist on SellerOfferListing"
            );
        }
    }

    public function test_all_mapped_landlord_properties_exist_on_component(): void
    {
        $map = MlsFieldMap::forRole('landlord');

        foreach ($map as $canonicalKey => $propName) {
            $propName = ltrim($propName, '*');
            $this->assertTrue(
                property_exists(LandlordOfferListing::class, $propName),
                "Landlord map '{$canonicalKey}' → '{$propName}': property does not exist on LandlordOfferListing"
            );
        }
    }

    // ─── applyImportedFields overwrite guard ──────────────────────────────────

    public function test_apply_does_not_overwrite_filled_property_unless_in_override_list(): void
    {
        $this->actingAs(\App\Models\User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->bedrooms = '3';

        $component->importPreviewData = [
            [
                'canonical_key'      => 'bedrooms',
                'prop_name'          => 'bedrooms',
                'label'              => 'Bedrooms',
                'value'              => '5',
                'is_array_prop'      => false,
                'has_existing_value' => true,
                'checked'            => true,
            ],
        ];

        $component->applyImportedFields(['bedrooms'], []);

        $this->assertEquals('3', $component->bedrooms, 'bedrooms should not be overwritten without override confirmation');
    }

    public function test_apply_overwrites_filled_property_when_in_override_list(): void
    {
        $this->actingAs(\App\Models\User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->bedrooms = '3';

        $component->importPreviewData = [
            [
                'canonical_key'      => 'bedrooms',
                'prop_name'          => 'bedrooms',
                'label'              => 'Bedrooms',
                'value'              => '5',
                'is_array_prop'      => false,
                'has_existing_value' => true,
                'checked'            => true,
            ],
        ];

        $component->applyImportedFields(['bedrooms'], ['bedrooms']);

        $this->assertEquals('5', $component->bedrooms, 'bedrooms should be overwritten when in override list');
    }

    public function test_apply_fills_empty_property_without_override(): void
    {
        $this->actingAs(\App\Models\User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->bedrooms = '';

        $component->importPreviewData = [
            [
                'canonical_key'      => 'bedrooms',
                'prop_name'          => 'bedrooms',
                'label'              => 'Bedrooms',
                'value'              => '4',
                'is_array_prop'      => false,
                'has_existing_value' => false,
                'checked'            => true,
            ],
        ];

        $component->applyImportedFields(['bedrooms'], []);

        $this->assertEquals('4', $component->bedrooms);
    }

    // ─── Missing-property guard: mls_number not imported ─────────────────────

    public function test_mls_number_is_parsed_but_not_mapped_to_any_role(): void
    {
        $rawText = "MLS #: T9876543  List Price: \$299,000  Bedrooms: 3  Bathrooms: 2";

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        // mls_number IS parsed (present in raw data)
        $this->assertArrayHasKey('mls_number', $result['data']);
        $this->assertEquals('T9876543', $result['data']['mls_number']);

        // But mls_number must NOT appear in any field map (no component property exists)
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $map = MlsFieldMap::forRole($role);
            $this->assertArrayNotHasKey('mls_number', $map,
                "mls_number must not appear in '{$role}' map — no Livewire property exists"
            );
        }
    }

    // ─── SSRF protection ──────────────────────────────────────────────────────

    /**
     * @dataProvider ssrfBlockedUrlProvider
     */
    public function test_ssrf_blocked_urls_return_friendly_error(string $url): void
    {
        $result = $this->service->import($url);

        $this->assertFalse($result['success'], "Expected SSRF block for URL: {$url}");
        $this->assertNotEmpty($result['error']);
        $this->assertStringNotContainsStringIgnoringCase('exception', $result['error']);
        $this->assertStringNotContainsStringIgnoringCase('stack', $result['error']);
    }

    public static function ssrfBlockedUrlProvider(): array
    {
        return [
            'loopback IPv4'               => ['http://127.0.0.1/admin'],
            'loopback IPv4 alt'           => ['http://127.1.2.3/secret'],
            'AWS metadata endpoint'       => ['http://169.254.169.254/latest/meta-data/'],
            'link-local'                  => ['http://169.254.0.1/'],
            'private RFC-1918 class A'    => ['http://10.0.0.1/'],
            'private RFC-1918 class B'    => ['http://172.16.0.1/'],
            'private RFC-1918 class B hi' => ['http://172.31.255.255/'],
            'private RFC-1918 class C'    => ['http://192.168.1.1/router'],
            'IPv6 loopback bracketed'     => ['http://[::1]/'],
        ];
    }

    // ─── Phase 2: boundary-fix tests ─────────────────────────────────────────

    public function test_city_does_not_bleed_into_county_label(): void
    {
        $rawText = 'City: SEMINOLE County: Pinellas State: FL';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('city', $data);
        $this->assertStringNotContainsStringIgnoringCase('County', $data['city']);
        $this->assertStringContainsStringIgnoringCase('SEMINOLE', $data['city']);
    }

    public function test_county_does_not_bleed_into_list_price_label(): void
    {
        // "PinellasList" is a known bleed — "List" (from "List Price") must be a stop token
        $rawText = 'County: PinellasList Price: $450,000';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('county', $data);
        $this->assertStringNotContainsStringIgnoringCase('List', $data['county']);
        $this->assertEquals('Pinellas', trim($data['county']));
    }

    public function test_lot_dimensions_does_not_bleed_into_lot_size_label(): void
    {
        $rawText = 'Lot Dimensions: 50x127 Lot Size Acres: 0.14';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('lot_dimensions', $data);
        $this->assertStringNotContainsStringIgnoringCase('Lot Size', $data['lot_dimensions']);
        $this->assertStringContainsString('50x127', $data['lot_dimensions']);
    }

    public function test_garage_does_not_bleed_when_carport_immediately_follows(): void
    {
        // No separator: "1 SpacesCarport:No" — Carport must stop the Garage capture
        $rawText = 'Garage: 1 SpacesCarport: No Appliances: Dishwasher';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('garage', $data);
        $this->assertStringNotContainsStringIgnoringCase('Carport', $data['garage']);
    }

    public function test_ac_does_not_bleed_when_floor_covering_immediately_follows(): void
    {
        // No separator: "Central AirFloor Covering: Tile"
        $rawText = 'Air Conditioning: Central AirFloor Covering: Tile Heating: Electric';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('air_conditioning', $data);
        $this->assertStringNotContainsStringIgnoringCase('Floor', $data['air_conditioning']);
        $this->assertStringContainsStringIgnoringCase('Central Air', $data['air_conditioning']);
    }

    public function test_appliances_does_not_bleed_into_room_names(): void
    {
        $rawText = 'Appliances: Dishwasher, Refrigerator Kitchen: 12x10 Living Room: 15x14';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('appliances', $data);
        $this->assertStringNotContainsStringIgnoringCase('Kitchen', $data['appliances']);
        $this->assertStringNotContainsStringIgnoringCase('Living Room', $data['appliances']);
        $this->assertStringContainsStringIgnoringCase('Dishwasher', $data['appliances']);
    }

    public function test_appliances_does_not_bleed_into_exterior_construction(): void
    {
        $rawText = 'Appliances: Range, Microwave Exterior Construction: Block Roof: Shingle';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('appliances', $data);
        $this->assertStringNotContainsStringIgnoringCase('Exterior Construction', $data['appliances']);
        $this->assertStringContainsStringIgnoringCase('Range', $data['appliances']);
    }

    // ─── Phase 2: Public Remarks (English Only) → description ─────────────────

    public function test_public_remarks_english_only_maps_to_description(): void
    {
        $rawText = 'Public Remarks (English Only): Stunning waterfront home with panoramic views. Directions: Take I-4 west.';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('description', $data);
        $this->assertStringContainsString('Stunning waterfront home', $data['description']);
        $this->assertStringNotContainsStringIgnoringCase('Directions', $data['description']);
    }

    public function test_description_canonical_key_maps_to_additional_details_on_seller(): void
    {
        $map = MlsFieldMap::forRole('seller');
        $this->assertArrayHasKey('description', $map);
        $this->assertEquals('additional_details', $map['description']);
    }

    public function test_description_canonical_key_maps_to_additional_details_on_landlord(): void
    {
        $map = MlsFieldMap::forRole('landlord');
        $this->assertArrayHasKey('description', $map);
        $this->assertEquals('additional_details', $map['description']);
    }

    // ─── Phase 2: new property characteristic fields ──────────────────────────

    public function test_raw_text_parses_roof_type(): void
    {
        $rawText = 'Roof Type: Shingle Exterior Construction: Block Foundation: Slab';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('roof_type', $data);
        $this->assertStringContainsStringIgnoringCase('Shingle', $data['roof_type']);
        $this->assertStringNotContainsStringIgnoringCase('Exterior', $data['roof_type']);
    }

    public function test_raw_text_parses_exterior_construction(): void
    {
        $rawText = 'Exterior Construction: Block, Stucco Foundation: Slab Year Built: 2001';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('exterior_construction', $data);
        $this->assertStringContainsStringIgnoringCase('Block', $data['exterior_construction']);
        $this->assertStringNotContainsStringIgnoringCase('Foundation', $data['exterior_construction']);
    }

    public function test_raw_text_parses_foundation(): void
    {
        $rawText = 'Foundation: Slab Year Built: 2005';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('foundation', $data);
        $this->assertStringContainsStringIgnoringCase('Slab', $data['foundation']);
    }

    public function test_raw_text_parses_heating_and_fuel(): void
    {
        $rawText = 'Heating and Fuel: Central, Electric Air Conditioning: Central Air';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('heating_fuel', $data);
        $this->assertStringContainsStringIgnoringCase('Electric', $data['heating_fuel']);
        $this->assertStringNotContainsStringIgnoringCase('Air Conditioning', $data['heating_fuel']);
    }

    public function test_raw_text_parses_heating_ampersand_fuel(): void
    {
        $rawText = 'Heating & Fuel: Electric, Gas Sewer: Public';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('heating_fuel', $data);
        $this->assertStringContainsStringIgnoringCase('Electric', $data['heating_fuel']);
    }

    public function test_raw_text_parses_water(): void
    {
        $rawText = 'Water: Public Sewer: Public Utilities: Cable Available';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('water', $data);
        $this->assertStringContainsStringIgnoringCase('Public', $data['water']);
        $this->assertStringNotContainsStringIgnoringCase('Sewer', $data['water']);
    }

    public function test_raw_text_parses_sewer(): void
    {
        $rawText = 'Sewer: Septic Tank Utilities: None';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('sewer', $data);
        $this->assertStringContainsStringIgnoringCase('Septic', $data['sewer']);
    }

    public function test_raw_text_parses_utilities(): void
    {
        $rawText = 'Utilities: Cable Available, Electricity Connected Year Built: 2008';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('utilities', $data);
        $this->assertStringContainsStringIgnoringCase('Cable', $data['utilities']);
    }

    public function test_raw_text_parses_sqft_heated_source(): void
    {
        $rawText = 'Sq Ft Heated Source: Public Records Bedrooms: 3';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('sqft_heated_source', $data);
        $this->assertStringContainsStringIgnoringCase('Public Records', $data['sqft_heated_source']);
    }

    public function test_raw_text_parses_flood_insurance_required(): void
    {
        $rawText = 'Flood Insurance Required: Yes Flood Zone Code: AE';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('flood_insurance_required', $data);
        $this->assertEquals('yes', $data['flood_insurance_required']);
    }

    public function test_raw_text_parses_flood_insurance_not_required(): void
    {
        $rawText = 'Flood Insurance Required: No Flood Zone Code: X';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('flood_insurance_required', $data);
        $this->assertEquals('no', $data['flood_insurance_required']);
    }

    // ─── Phase 2: field map includes new property characteristic fields ────────

    // ─── Phase 2: Special Assessments parsing ────────────────────────────────

    public function test_raw_text_parses_special_assessments_yes_no(): void
    {
        $rawText = 'Special Assessments Y/N: Yes CDD Y/N: No';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('has_special_assessments', $data);
        $this->assertEquals('yes', $data['has_special_assessments']);
    }

    public function test_raw_text_parses_special_assessments_bare_label(): void
    {
        $rawText = 'Special Assessments: No HOA: Yes';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('has_special_assessments', $data);
        $this->assertEquals('no', $data['has_special_assessments']);
    }

    public function test_raw_text_parses_special_assessment_amount(): void
    {
        $rawText = 'Special Assessments: Yes Special Assessment Amount: $1,200 CDD Y/N: No';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('special_assessment_amount', $data);
        $this->assertEquals('1200', $data['special_assessment_amount']);
    }

    public function test_raw_text_parses_special_assessment_description(): void
    {
        $rawText = 'Special Assessments: Yes Special Assessment Description: Road repaving district Year Built: 2005';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('special_assessment_description', $data);
        $this->assertStringContainsStringIgnoringCase('Road repaving', $data['special_assessment_description']);
    }

    // ─── Phase 2: missing alias variants ─────────────────────────────────────

    public function test_homeowners_association_label_populates_has_hoa(): void
    {
        foreach ([
            'Homeowners Association: Yes CDD Y/N: No',
            'Homeowner Assoc: Yes CDD Y/N: No',
        ] as $rawText) {
            $result = $this->service->import('', $rawText);
            $this->assertTrue($result['success']);
            $this->assertArrayHasKey('has_hoa', $result['data'], "has_hoa missing for: $rawText");
            $this->assertEquals('yes', $result['data']['has_hoa'], "has_hoa incorrect for: $rawText");
        }
    }

    public function test_legal_desc_abbreviation_populates_legal_description(): void
    {
        $rawText = 'Legal Desc: Lot 4 Block 12 Pine Ridge Sub Flood Zone Code: X';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('legal_description', $result['data']);
        $this->assertStringContainsString('Lot 4', $result['data']['legal_description']);
    }

    public function test_bare_freq_label_populates_association_fee_frequency(): void
    {
        $rawText = 'Association: Yes Association Fee: $250 Freq: Monthly CDD Y/N: No';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('association_fee_frequency', $result['data']);
        $this->assertStringContainsStringIgnoringCase('month', $result['data']['association_fee_frequency']);
    }

    public function test_fema_panel_label_populates_flood_zone_panel(): void
    {
        foreach ([
            'FEMA Panel: 12099C0350H Flood Zone Code: AE',
            'FEMA Flood Zone Panel: 12099C0350H Flood Zone Code: AE',
            'Panel: 12099C0350H Flood Zone Code: AE',
        ] as $rawText) {
            $result = $this->service->import('', $rawText);
            $this->assertTrue($result['success']);
            $this->assertArrayHasKey('flood_zone_panel', $result['data'], "flood_zone_panel missing for: $rawText");
            $this->assertStringContainsString('12099C0350H', $result['data']['flood_zone_panel'], "value mismatch for: $rawText");
        }
    }

    public function test_seller_and_landlord_maps_include_special_assessment_fields(): void
    {
        foreach (['seller', 'landlord'] as $role) {
            $map = MlsFieldMap::forRole($role);
            $this->assertArrayHasKey('has_special_assessments',        $map, "Role '{$role}' must map has_special_assessments");
            $this->assertEquals('has_special_assessments',             $map['has_special_assessments']);
            $this->assertArrayHasKey('special_assessment_amount',      $map, "Role '{$role}' must map special_assessment_amount");
            $this->assertEquals('special_assessment_amount',           $map['special_assessment_amount']);
            $this->assertArrayHasKey('special_assessment_description', $map, "Role '{$role}' must map special_assessment_description");
            $this->assertEquals('special_assessment_description',      $map['special_assessment_description']);
        }
    }

    public function test_seller_map_includes_new_property_characteristic_fields(): void
    {
        $map = MlsFieldMap::forRole('seller');

        $this->assertArrayHasKey('heating_fuel',          $map, 'seller map must have heating_fuel');
        $this->assertEquals('*heating_and_fuel',          $map['heating_fuel']);

        $this->assertArrayHasKey('roof_type',             $map, 'seller map must have roof_type');
        $this->assertEquals('*roof_type',                 $map['roof_type']);

        $this->assertArrayHasKey('exterior_construction', $map, 'seller map must have exterior_construction');
        $this->assertEquals('*exterior_construction',     $map['exterior_construction']);

        $this->assertArrayHasKey('foundation',            $map, 'seller map must have foundation');
        $this->assertEquals('*foundation',                $map['foundation']);

        $this->assertArrayHasKey('water',                 $map, 'seller map must have water');
        $this->assertEquals('*water',                     $map['water']);

        $this->assertArrayHasKey('sewer',                 $map, 'seller map must have sewer');
        $this->assertEquals('*sewer',                     $map['sewer']);

        $this->assertArrayHasKey('utilities',             $map, 'seller map must have utilities');
        $this->assertEquals('*utilities',                 $map['utilities']);

        $this->assertArrayHasKey('sqft_heated_source',   $map, 'seller map must have sqft_heated_source');
        $this->assertEquals('sqft_heated_source',         $map['sqft_heated_source']);

        $this->assertArrayHasKey('flood_insurance_required', $map, 'seller map must have flood_insurance_required');
        $this->assertEquals('flood_insurance_required',   $map['flood_insurance_required']);

        $this->assertArrayHasKey('flood_zone_panel',      $map, 'seller map must have flood_zone_panel');
        $this->assertEquals('flood_zone_panel',           $map['flood_zone_panel']);
    }

    public function test_landlord_map_includes_new_property_characteristic_fields(): void
    {
        $map = MlsFieldMap::forRole('landlord');

        $this->assertArrayHasKey('heating_fuel',          $map, 'landlord map must have heating_fuel');
        $this->assertEquals('*heating_fuel',              $map['heating_fuel']);

        $this->assertArrayHasKey('water',                 $map, 'landlord map must have water');
        $this->assertEquals('*water',                     $map['water']);

        $this->assertArrayHasKey('sewer',                 $map, 'landlord map must have sewer');
        $this->assertEquals('*sewer',                     $map['sewer']);

        $this->assertArrayHasKey('utilities',             $map, 'landlord map must have utilities');
        // Phase B fix: landlord utilities must target the array property, not the legacy string property
        $this->assertEquals('*property_utilities',        $map['utilities']);

        $this->assertArrayHasKey('sqft_heated_source',   $map, 'landlord map must have sqft_heated_source');
        $this->assertEquals('sqft_heated_source',         $map['sqft_heated_source']);

        $this->assertArrayHasKey('flood_insurance_required', $map, 'landlord map must have flood_insurance_required');
        $this->assertEquals('flood_insurance_required',   $map['flood_insurance_required']);

        $this->assertArrayHasKey('flood_zone_panel',      $map, 'landlord map must have flood_zone_panel');
        $this->assertEquals('flood_zone_panel',           $map['flood_zone_panel']);
    }

    public function test_public_url_is_not_blocked_by_ssrf_guard(): void
    {
        Http::fake([
            '*' => Http::response('<p>Bedrooms: 3 Bathrooms: 2</p>', 200),
        ]);

        $result = $this->service->import('https://8.8.8.8/listing');

        $this->assertStringNotContainsStringIgnoringCase('not permitted', $result['error'] ?? '');
    }

    // ─── Coverage report generation ───────────────────────────────────────────

    public function test_coverage_report_generates_file_with_expected_columns(): void
    {
        $tmpPath = sys_get_temp_dir() . '/mls_coverage_test_' . uniqid() . '.md';

        $writtenPath = MlsCoverageReporter::generate($tmpPath);

        $this->assertFileExists($writtenPath, 'Coverage report file should be written');

        $content = file_get_contents($writtenPath);

        // ── Required exact column headers (from specification contract) ───────
        $this->assertStringContainsString('MLS Form',                       $content, 'Column: MLS Form');
        $this->assertStringContainsString('MLS Section',                    $content, 'Column: MLS Section');
        $this->assertStringContainsString('MLS Field Label',                $content, 'Column: MLS Field Label');
        $this->assertStringContainsString('Canonical Import Key',           $content, 'Column: Canonical Import Key');
        $this->assertStringContainsString('Current Mapping',                $content, 'Column: Current Mapping');
        $this->assertStringContainsString('Target Property',                $content, 'Column: Target Property');
        $this->assertStringContainsString('Property Exists (Y/N)',          $content, 'Column: Property Exists (Y/N)');
        $this->assertStringContainsString('Form Field Exists (Y/N)',        $content, 'Column: Form Field Exists (Y/N)');
        $this->assertStringContainsString('Safe To Import (Y/N)',           $content, 'Column: Safe To Import (Y/N)');
        $this->assertStringContainsString('Normalization Required (Y/N)',   $content, 'Column: Normalization Required (Y/N)');
        $this->assertStringContainsString('Notes',                          $content, 'Column: Notes');

        // ── All 7 Stellar MLS form names must appear ─────────────────────────
        $this->assertStringContainsString('Residential',         $content, 'Form: Residential');
        $this->assertStringContainsString('Rental',              $content, 'Form: Rental');
        $this->assertStringContainsString('Vacant Land',         $content, 'Form: Vacant Land');
        $this->assertStringContainsString('Income',              $content, 'Form: Income');
        $this->assertStringContainsString('Commercial Sale',     $content, 'Form: Commercial Sale');
        $this->assertStringContainsString('Commercial Lease',    $content, 'Form: Commercial Lease');
        $this->assertStringContainsString('Business Opportunity',$content, 'Form: Business Opportunity');

        // ── Form Field Exists column is derived from actual blade files ───────
        // The column must contain role-prefixed Y/N values (e.g. "S:Y" or "L:N")
        $this->assertMatchesRegularExpression('/Form Field Exists/i', $content);
        $this->assertMatchesRegularExpression('/[SLB T]:Y|[SLB T]:N/', $content, 'Form Field Exists column contains role-prefixed Y/N values');

        // ── Rejected Mapping Candidates section ───────────────────────────────
        $this->assertStringContainsString('Rejected Mapping Candidates', $content);
        $this->assertStringContainsString('mls_number',     $content, 'Rejected: mls_number documented');
        $this->assertStringContainsString('parcel_id',      $content, 'Rejected: parcel_id documented');
        $this->assertStringContainsString('application_fee',$content, 'Rejected: application_fee documented');

        // ── HOA / CDD fields appear in report ─────────────────────────────────
        $this->assertStringContainsString('has_hoa',                   $content, 'HOA field: has_hoa');
        $this->assertStringContainsString('association_fee_amount',    $content, 'HOA field: association_fee_amount');
        $this->assertStringContainsString('has_cdd',                   $content, 'HOA field: has_cdd');
        $this->assertStringContainsString('annual_cdd_fee',            $content, 'HOA field: annual_cdd_fee');

        // ── Commercial / Income / Vacant Land / Business fields appear ────────
        // These have NO canonical key — they must appear as unmapped rows (N)
        // to surface the coverage gap. If they are missing, the audit is incomplete.
        $this->assertStringContainsString('Building Size',             $content, 'Commercial Sale: Building Size row');
        $this->assertStringContainsString('Number of Bays',           $content, 'Commercial Sale: Number of Bays row');
        $this->assertStringContainsString('Cap Rate',                  $content, 'Income/Commercial: Cap Rate row');
        $this->assertStringContainsString('Net Operating Income',      $content, 'Income/Commercial: NOI row');
        $this->assertStringContainsString('Number of Units',          $content, 'Income: Number of Units row');
        $this->assertStringContainsString('Lot Features',             $content, 'Vacant Land: Lot Features row');
        $this->assertStringContainsString('Business Type',            $content, 'Business Opportunity: Business Type row');
        $this->assertStringContainsString('Annual Revenue',           $content, 'Business Opportunity: Annual Revenue row');
        $this->assertStringContainsString('Lease Rate Type',          $content, 'Commercial Lease: Lease Rate Type row');

        // All unmapped rows must show Safe To Import = N
        // Verify the report contains N entries (unmapped rows from commercial forms)
        $unmappedCount = substr_count($content, '| N |');
        $this->assertGreaterThan(10, $unmappedCount, 'Report must have >10 unmapped (N) rows for commercial/land/income/business fields');

        @unlink($tmpPath);
    }

    // ─── Phase 3: boundary-fix verification tests ─────────────────────────────

    public function test_parcel_id_does_not_bleed_into_tax_label(): void
    {
        // Real Stellar MLS pattern: parcel ID immediately followed by "Tax" label
        $rawText = 'Parcel ID: 19-30-17-45612-000-1410Tax Year: 2024 Annual Taxes: $3,200';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('tax_id', $data);
        $this->assertEquals('19-30-17-45612-000-1410', $data['tax_id'],
            'Parcel ID must not include "Tax" from the following label');
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['tax_id']);
    }

    public function test_parcel_id_boundary_with_tax_id_label(): void
    {
        $rawText = 'Tax ID: 12-34-56-78901Tax Year: 2023 Flood Zone Code: X';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('tax_id', $data);
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['tax_id'],
            'tax_id must not bleed into following Tax Year label');
        $this->assertStringContainsString('12-34-56-78901', $data['tax_id']);
    }

    public function test_heating_fuel_does_not_bleed_into_fireplace_label(): void
    {
        $rawText = 'Heating & Fuel: Central, Electric Fireplace Y/N: No Heated Area: 1850';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('heating_fuel', $data);
        $this->assertStringNotContainsStringIgnoringCase('Fireplace', $data['heating_fuel'],
            'Heating & Fuel must not absorb Fireplace Y/N label');
        $this->assertStringContainsStringIgnoringCase('Electric', $data['heating_fuel']);
    }

    public function test_heating_fuel_does_not_bleed_into_heated_area_label(): void
    {
        $rawText = 'Heating and Fuel: Natural Gas Heated Area: 2050 Heated Area Meters: 190';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('heating_fuel', $data);
        $this->assertStringNotContainsStringIgnoringCase('Heated Area', $data['heating_fuel'],
            'Heating & Fuel must not absorb Heated Area label');
        $this->assertStringContainsStringIgnoringCase('Natural Gas', $data['heating_fuel']);
    }

    public function test_sqft_heated_source_does_not_bleed_into_cdom(): void
    {
        // Real Stellar MLS pattern: CDOM immediately follows the source value
        $rawText = 'Sq Ft Heated Source: Public RecordsCDOM: 136 Year Built: 2005';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('sqft_heated_source', $data);
        $this->assertStringNotContainsStringIgnoringCase('CDOM', $data['sqft_heated_source'],
            'Sq Ft Heated Source must not absorb CDOM label');
        $this->assertStringContainsStringIgnoringCase('Public Records', $data['sqft_heated_source']);
    }

    public function test_appliances_does_not_bleed_into_rooms_section(): void
    {
        $rawText = 'Appliances: Dishwasher, Refrigerator, MicrowaveRooms Kitchen: 12x10 Living Room: 15x14 Primary Bedroom: 14x12';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('appliances', $data);
        $this->assertStringNotContainsStringIgnoringCase('Rooms', $data['appliances'],
            'Appliances must not absorb Rooms section header');
        $this->assertStringContainsStringIgnoringCase('Dishwasher', $data['appliances']);
    }

    public function test_appliances_does_not_bleed_into_exterior_information(): void
    {
        $rawText = 'Appliances: Range, Microwave, DishwasherExterior Information Pool: Yes Garage: 2';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('appliances', $data);
        $this->assertStringNotContainsStringIgnoringCase('Exterior Information', $data['appliances'],
            'Appliances must not absorb Exterior Information section header');
        $this->assertStringContainsStringIgnoringCase('Range', $data['appliances']);
    }

    public function test_additional_parcels_yn_colon_prefix_is_normalized(): void
    {
        // Stellar MLS exports "Y/N:No" for additional parcels boolean field
        $rawText = 'Additional Parcels: Y/N:No Total Number of Parcels: 1';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('additional_parcels', $data);
        $this->assertEquals('no', $data['additional_parcels'],
            'additional_parcels Y/N:No should normalize to "no"');
    }

    public function test_additional_parcels_yn_colon_yes_prefix_is_normalized(): void
    {
        $rawText = 'Additional Parcels: Y/N:Yes Total Number of Parcels: 2';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('additional_parcels', $data);
        $this->assertEquals('yes', $data['additional_parcels'],
            'additional_parcels Y/N:Yes should normalize to "yes"');
    }

    public function test_normalizer_boolean_strips_yn_colon_prefix(): void
    {
        $this->assertEquals('no',  MlsNormalizer::normalizeBoolean('Y/N:No'));
        $this->assertEquals('yes', MlsNormalizer::normalizeBoolean('Y/N:Yes'));
        $this->assertEquals('no',  MlsNormalizer::normalizeBoolean('Y/N: No'));
        $this->assertEquals('yes', MlsNormalizer::normalizeBoolean('Y/N: Yes'));
        // Normal values still work
        $this->assertEquals('no',  MlsNormalizer::normalizeBoolean('No'));
        $this->assertEquals('yes', MlsNormalizer::normalizeBoolean('Yes'));
    }

    // ─── Phase 4: field-map correctness tests ─────────────────────────────────

    /**
     * Seller list price must map to 'maximum_budget' (the "Desired Sale Price" wire:model
     * on the Sale Terms tab), NOT 'purchase_price' (which is inside the Seller Financing
     * sub-section and hidden by default).
     */
    public function test_seller_price_maps_to_maximum_budget_not_purchase_price(): void
    {
        $sellerMap = \App\Services\ListingImport\MlsFieldMap::forRole('seller');

        $this->assertArrayHasKey('price', $sellerMap,
            'Seller field map must include a price entry');
        $this->assertEquals('maximum_budget', $sellerMap['price'],
            'Seller price must map to maximum_budget (the Desired Sale Price field on Sale Terms tab)');
        $this->assertNotEquals('purchase_price', $sellerMap['price'],
            'purchase_price is the Seller Financing sub-field, not the primary list price');
    }

    /** Buyer price must continue to map to maximum_budget (buyer budget cap). */
    public function test_buyer_price_maps_to_maximum_budget(): void
    {
        $buyerMap = \App\Services\ListingImport\MlsFieldMap::forRole('buyer');

        $this->assertArrayHasKey('price', $buyerMap);
        $this->assertEquals('maximum_budget', $buyerMap['price']);
    }

    /** Landlord list price must map to desired_rental_amount. */
    public function test_landlord_price_maps_to_desired_rental_amount(): void
    {
        $landlordMap = \App\Services\ListingImport\MlsFieldMap::forRole('landlord');

        $this->assertArrayHasKey('price', $landlordMap);
        $this->assertEquals('desired_rental_amount', $landlordMap['price']);
    }

    /**
     * End-to-end: parse a raw MLS snippet containing a list price and verify
     * the parsed 'price' canonical key is present with the correct numeric value.
     */
    public function test_list_price_parses_correctly_from_raw_mls_text(): void
    {
        $rawText = 'List Price: $450,000 Bedrooms: 4 Bathrooms: 3';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('price', $data,
            'Parser must extract a price canonical key from "List Price:" label');
        $this->assertEquals('450000', $data['price'],
            'List price must be normalised to a plain integer string (no commas, no $ sign)');
    }

    // ─── Phase B regression tests ─────────────────────────────────────────────

    // ── Fix #1: Landlord utilities → *property_utilities ──────────────────────

    public function test_landlord_map_utilities_targets_array_property(): void
    {
        $map = MlsFieldMap::forRole('landlord');

        $this->assertArrayHasKey('utilities', $map);
        $this->assertEquals('*property_utilities', $map['utilities'],
            'Landlord utilities must map to *property_utilities (the array multi-select), not the legacy string $utilities');
    }

    // ── Fix #2: Heating simple label merges into heating_fuel key ─────────────

    public function test_plain_heating_label_maps_to_heating_fuel_key(): void
    {
        $rawText = 'Heating: Electric Year Built: 2010';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('heating_fuel', $data,
            'Plain "Heating:" label must produce heating_fuel canonical key');
        $this->assertStringContainsStringIgnoringCase('Electric', $data['heating_fuel']);
        $this->assertArrayNotHasKey('heating', $data,
            'Separate heating key must not exist — it should be merged into heating_fuel');
    }

    public function test_heating_and_fuel_label_still_maps_to_heating_fuel_key(): void
    {
        $rawText = 'Heating and Fuel: Central, Gas Sewer: Public';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('heating_fuel', $data);
        $this->assertStringContainsStringIgnoringCase('Gas', $data['heating_fuel']);
    }

    // ── Fix #3: Landlord lot_size_acres → total_acreage ──────────────────────

    public function test_landlord_map_includes_lot_size_acres(): void
    {
        $map = MlsFieldMap::forRole('landlord');

        $this->assertArrayHasKey('lot_size_acres', $map,
            'Landlord map must include lot_size_acres (LandlordOfferListing has $total_acreage)');
        $this->assertEquals('total_acreage', $map['lot_size_acres']);
    }

    // ── Fix #4: Tenant address fields added ───────────────────────────────────

    public function test_tenant_map_includes_all_address_fields(): void
    {
        $map = MlsFieldMap::forRole('tenant');

        $this->assertArrayHasKey('address', $map,       'tenant map must include address');
        $this->assertEquals('address',        $map['address']);

        $this->assertArrayHasKey('city', $map,          'tenant map must include city');
        $this->assertEquals('property_city',  $map['city']);

        $this->assertArrayHasKey('state', $map,         'tenant map must include state');
        $this->assertEquals('property_state', $map['state']);

        $this->assertArrayHasKey('zip', $map,           'tenant map must include zip');
        $this->assertEquals('property_zip',   $map['zip']);

        $this->assertArrayHasKey('county', $map,        'tenant map must include county');
        $this->assertEquals('property_county',$map['county']);
    }

    // ── Fix #5: Tenant sqft_heated_source added ───────────────────────────────

    public function test_tenant_map_includes_sqft_heated_source(): void
    {
        $map = MlsFieldMap::forRole('tenant');

        $this->assertArrayHasKey('sqft_heated_source', $map,
            'Tenant map must include sqft_heated_source (TenantOfferListing has the property)');
        $this->assertEquals('sqft_heated_source', $map['sqft_heated_source']);
    }

    // ── Fix #6: Buyer address fields intentionally excluded ───────────────────

    public function test_buyer_map_omits_address_fields(): void
    {
        $map = MlsFieldMap::forRole('buyer');

        // BuyerOfferListing uses a preference-based multi-city/county model.
        // No wire:model bindings exist for these fields in any buyer blade tab.
        $this->assertArrayNotHasKey('address', $map, 'buyer must not map address — no blade binding');
        $this->assertArrayNotHasKey('city',    $map, 'buyer must not map city — no blade binding');
        $this->assertArrayNotHasKey('state',   $map, 'buyer must not map state — no blade binding');
        $this->assertArrayNotHasKey('zip',     $map, 'buyer must not map zip — no blade binding');
        $this->assertArrayNotHasKey('county',  $map, 'buyer must not map county — no blade binding');
    }

    // ── Fix #7: flood_insurance_required normalizer uses named case ───────────

    public function test_normalizer_flood_insurance_required_named_case(): void
    {
        $this->assertEquals('yes', MlsNormalizer::normalize('flood_insurance_required', 'Yes'));
        $this->assertEquals('no',  MlsNormalizer::normalize('flood_insurance_required', 'No'));
        $this->assertEquals('yes', MlsNormalizer::normalize('flood_insurance_required', 'Y'));
        $this->assertEquals('no',  MlsNormalizer::normalize('flood_insurance_required', 'N'));
    }

    // ── Fix #8: has_special_assessments normalizer uses named case ────────────

    public function test_normalizer_has_special_assessments_named_case(): void
    {
        $this->assertEquals('yes', MlsNormalizer::normalize('has_special_assessments', 'Yes'));
        $this->assertEquals('no',  MlsNormalizer::normalize('has_special_assessments', 'No'));
        $this->assertEquals('yes', MlsNormalizer::normalize('has_special_assessments', 'Y'));
        $this->assertEquals('no',  MlsNormalizer::normalize('has_special_assessments', 'N'));
    }

    // ── Coverage: address + beds/baths + pool/garage/carport + appliances + remarks ──

    public function test_address_import_from_raw_text(): void
    {
        $rawText = 'Address: 123 Main Street City: Tampa State: FL Zip: 33601 County: Hillsborough';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('address', $data);
        $this->assertStringContainsString('123 Main Street', $data['address']);
        $this->assertArrayHasKey('city', $data);
        $this->assertStringContainsStringIgnoringCase('Tampa', $data['city']);
        $this->assertArrayHasKey('state', $data);
        $this->assertEquals('FL', $data['state']);
        $this->assertArrayHasKey('zip', $data);
        $this->assertEquals('33601', $data['zip']);
        $this->assertArrayHasKey('county', $data);
        $this->assertStringContainsStringIgnoringCase('Hillsborough', $data['county']);
    }

    public function test_bedrooms_and_bathrooms_parse_from_raw_text(): void
    {
        $rawText = 'Bedrooms: 4 Bathrooms: 2.5 Heated Sq Ft: 2,100';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertEquals('4',   $result['data']['bedrooms']);
        $this->assertEquals('2.5', $result['data']['bathrooms']);
        $this->assertEquals('2100',$result['data']['heated_sqft']);
    }

    public function test_annual_property_taxes_parse_from_raw_text(): void
    {
        $rawText = 'Annual Property Taxes: $5,200 Tax Year: 2023 Tax ID: 11-22-33-44555';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('annual_taxes', $data);
        $this->assertEquals('5200', $data['annual_taxes']);
        $this->assertArrayHasKey('tax_year', $data);
        $this->assertEquals('2023', $data['tax_year']);
        $this->assertArrayHasKey('tax_id', $data);
        $this->assertEquals('11-22-33-44555', $data['tax_id']);
    }

    public function test_tax_year_and_parcel_id_parsed_as_separate_fields(): void
    {
        $rawText = 'Tax ID: 55-66-77-88999 Tax Year: 2024 Annual Property Taxes: $3,100';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('tax_id', $data, 'tax_id must be its own key');
        $this->assertArrayHasKey('tax_year', $data, 'tax_year must be its own key');
        $this->assertNotEquals($data['tax_id'], $data['tax_year'], 'tax_id and tax_year must be different values');
        $this->assertEquals('55-66-77-88999', $data['tax_id']);
        $this->assertEquals('2024', $data['tax_year']);
    }

    public function test_pool_garage_carport_parse_from_raw_text(): void
    {
        $rawText = 'Pool: Yes Garage: 2 Car Carport: No Year Built: 2005';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('pool', $data);
        $this->assertEquals('yes', $data['pool']);
        $this->assertArrayHasKey('garage', $data);
        $this->assertStringContainsString('2', $data['garage']);
        $this->assertArrayHasKey('carport', $data);
        $this->assertEquals('no', $data['carport']);
    }

    public function test_appliances_parse_from_raw_text(): void
    {
        $rawText = 'Appliances: Dishwasher, Range, Refrigerator, Microwave Interior Features: Walk-in Closet';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('appliances', $data);
        $this->assertStringContainsStringIgnoringCase('Dishwasher', $data['appliances']);
        $this->assertStringContainsStringIgnoringCase('Refrigerator', $data['appliances']);
        $this->assertStringNotContainsStringIgnoringCase('Interior Features', $data['appliances']);
    }

    public function test_public_remarks_parses_to_description_key(): void
    {
        $rawText = 'Public Remarks: Beautiful 3/2 home with updated kitchen. Directions: Head north on US-19.';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('description', $data);
        $this->assertStringContainsStringIgnoringCase('Beautiful 3/2', $data['description']);
        $this->assertStringNotContainsStringIgnoringCase('Directions', $data['description']);
    }

    public function test_description_maps_to_additional_details_for_all_roles(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $map = MlsFieldMap::forRole($role);
            $this->assertArrayHasKey('description', $map, "Role '{$role}' must map description");
            $this->assertEquals('additional_details', $map['description'],
                "Role '{$role}' description must target additional_details");
        }
    }
}
