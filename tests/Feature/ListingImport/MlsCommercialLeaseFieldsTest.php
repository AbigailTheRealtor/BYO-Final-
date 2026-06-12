<?php

namespace Tests\Feature\ListingImport;

use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\MlsListingImportService;
use App\Services\ListingImport\MlsNormalizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Round-trip tests for the four new commercial-lease MLS fields:
 *   lease_rate_type, pets_allowed, minimum_lease_months, office_area_sqft
 *
 * Covers three layers of the pipeline:
 *   (1) Parser output  — parseFields() / import() emits the correct canonical keys
 *   (2) Normalization  — MlsNormalizer::normalize() produces expected canonical tokens
 *   (3) Field map      — MlsFieldMap::landlord() maps canonical keys to Livewire props
 */
class MlsCommercialLeaseFieldsTest extends TestCase
{
    use DatabaseTransactions;

    private MlsListingImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MlsListingImportService::class);
    }

    // =========================================================================
    // (1) Parser output — raw text
    // =========================================================================

    public function test_parser_emits_lease_rate_type_key(): void
    {
        $rawText = 'Monthly Rent: $5,200  Lease Rate Type: NNN  Tenant Pays: Electricity';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('lease_rate_type', $result['data'],
            'Parser must emit the lease_rate_type canonical key');
        $this->assertSame('nnn', $result['data']['lease_rate_type']);
    }

    public function test_parser_emits_pets_allowed_key_for_no(): void
    {
        $rawText = 'Monthly Rent: $5,200  Pets Allowed: No  Tenant Pays: Electricity';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('pets_allowed', $result['data'],
            'Parser must emit the pets_allowed canonical key');
        $this->assertSame('no', $result['data']['pets_allowed']);
    }

    public function test_parser_emits_pets_allowed_key_for_yes(): void
    {
        $rawText = 'Monthly Rent: $2,000  Pets Allowed: Yes  Bedrooms: 2';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('pets_allowed', $result['data'],
            'Parser must emit the pets_allowed canonical key');
        $this->assertSame('yes', $result['data']['pets_allowed']);
    }

    public function test_parser_emits_minimum_lease_months_key(): void
    {
        $rawText = 'Monthly Rent: $5,200  Minimum Lease: 12  Tenant Pays: Electricity';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('minimum_lease_months', $result['data'],
            'Parser must emit the minimum_lease_months canonical key');
        $this->assertSame('12', $result['data']['minimum_lease_months']);
    }

    public function test_parser_emits_minimum_lease_months_from_months_label(): void
    {
        $rawText = 'Monthly Rent: $2,200  Minimum Lease (Months): 6  Bedrooms: 2';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('minimum_lease_months', $result['data'],
            'Parser must handle "Minimum Lease (Months):" label form');
        $this->assertSame('6', $result['data']['minimum_lease_months']);
    }

    public function test_parser_emits_office_area_sqft_key(): void
    {
        $rawText = 'Monthly Rent: $5,200  Office Area (Sq Ft): 1,800  Tenant Pays: Electricity';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('office_area_sqft', $result['data'],
            'Parser must emit the office_area_sqft canonical key');
        $this->assertSame('1800', $result['data']['office_area_sqft'],
            'Parser must strip commas from office area value');
    }

    public function test_parser_strips_commas_from_office_area_sqft(): void
    {
        $rawText = 'Monthly Rent: $8,000  Office Area: 12,500  Tenant Pays: Insurance';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('office_area_sqft', $result['data']);
        $this->assertSame('12500', $result['data']['office_area_sqft']);
    }

    // =========================================================================
    // (2) Fixture round-trip — all four fields together
    // =========================================================================

    public function test_commercial_lease_fixture_contains_all_four_new_fields(): void
    {
        $fixturePath = base_path('tests/fixtures/mls/commercial_lease.txt');
        $this->assertFileExists($fixturePath, 'commercial_lease.txt fixture must exist');

        $rawText = file_get_contents($fixturePath);
        $result  = $this->service->import('', $rawText);

        $this->assertTrue($result['success'], 'Import must succeed for the fixture');
        $data = $result['data'];

        $this->assertArrayHasKey('lease_rate_type', $data,
            'Fixture must produce lease_rate_type key');
        $this->assertSame('nnn', $data['lease_rate_type'],
            'Fixture Lease Rate Type: NNN must normalize to "nnn"');

        $this->assertArrayHasKey('pets_allowed', $data,
            'Fixture must produce pets_allowed key');
        $this->assertSame('no', $data['pets_allowed'],
            'Fixture Pets Allowed: No must normalize to "no"');

        $this->assertArrayHasKey('minimum_lease_months', $data,
            'Fixture must produce minimum_lease_months key');
        $this->assertSame('12', $data['minimum_lease_months'],
            'Fixture Minimum Lease: 12 must be captured as "12"');

        $this->assertArrayHasKey('office_area_sqft', $data,
            'Fixture must produce office_area_sqft key');
        $this->assertSame('1800', $data['office_area_sqft'],
            'Fixture Office Area (Sq Ft): 1,800 must be captured as "1800"');
    }

    public function test_commercial_lease_fixture_listing_type_hint_is_rental(): void
    {
        $fixturePath = base_path('tests/fixtures/mls/commercial_lease.txt');
        $rawText     = file_get_contents($fixturePath);
        $result      = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertSame('rental', $result['data']['listing_type_hint'],
            'Presence of lease_rate_type must trigger the rental listing_type_hint');
    }

    // =========================================================================
    // (3) Normalizer
    // =========================================================================

    public function test_normalizer_lease_rate_type_nnn_variants(): void
    {
        $this->assertSame('nnn', MlsNormalizer::normalize('lease_rate_type', 'NNN'));
        $this->assertSame('nnn', MlsNormalizer::normalize('lease_rate_type', 'Triple Net'));
        $this->assertSame('nnn', MlsNormalizer::normalize('lease_rate_type', 'triple-net'));
        $this->assertSame('nnn', MlsNormalizer::normalize('lease_rate_type', 'Net Net Net'));
    }

    public function test_normalizer_lease_rate_type_gross_variants(): void
    {
        $this->assertSame('gross', MlsNormalizer::normalize('lease_rate_type', 'Gross'));
        $this->assertSame('gross', MlsNormalizer::normalize('lease_rate_type', 'Full Service Gross'));
        $this->assertSame('gross', MlsNormalizer::normalize('lease_rate_type', 'Full Service'));
    }

    public function test_normalizer_lease_rate_type_modified_gross_variants(): void
    {
        $this->assertSame('modified_gross', MlsNormalizer::normalize('lease_rate_type', 'Modified Gross'));
        $this->assertSame('modified_gross', MlsNormalizer::normalize('lease_rate_type', 'modified-gross'));
        $this->assertSame('modified_gross', MlsNormalizer::normalize('lease_rate_type', 'Mod. Gross'));
    }

    public function test_normalizer_lease_rate_type_unknown_value_lowercased(): void
    {
        $result = MlsNormalizer::normalize('lease_rate_type', 'Custom Lease Type');
        $this->assertSame('custom_lease_type', $result,
            'Unknown lease rate type values should be lowercased with underscores');
    }

    public function test_normalizer_pets_allowed_yes(): void
    {
        $this->assertSame('yes', MlsNormalizer::normalize('pets_allowed', 'Yes'));
        $this->assertSame('yes', MlsNormalizer::normalize('pets_allowed', 'Y'));
    }

    public function test_normalizer_pets_allowed_no(): void
    {
        $this->assertSame('no', MlsNormalizer::normalize('pets_allowed', 'No'));
        $this->assertSame('no', MlsNormalizer::normalize('pets_allowed', 'N'));
    }

    // =========================================================================
    // (4) Field map — landlord() entries
    // =========================================================================

    public function test_field_map_landlord_has_lease_rate_type(): void
    {
        $map = MlsFieldMap::forRole('landlord');
        $this->assertArrayHasKey('lease_rate_type', $map,
            'MlsFieldMap::forRole(landlord) must have lease_rate_type entry');
        $this->assertSame('commercial_lease_type', $map['lease_rate_type']);
    }

    public function test_field_map_landlord_has_pets_allowed(): void
    {
        $map = MlsFieldMap::forRole('landlord');
        $this->assertArrayHasKey('pets_allowed', $map,
            'MlsFieldMap::forRole(landlord) must have pets_allowed entry');
        $this->assertSame('pet_policy', $map['pets_allowed']);
    }

    public function test_field_map_landlord_has_minimum_lease_months(): void
    {
        $map = MlsFieldMap::forRole('landlord');
        $this->assertArrayHasKey('minimum_lease_months', $map,
            'MlsFieldMap::forRole(landlord) must have minimum_lease_months entry');
        $this->assertSame('min_lease_period', $map['minimum_lease_months']);
    }

    public function test_field_map_landlord_has_office_area_sqft(): void
    {
        $map = MlsFieldMap::forRole('landlord');
        $this->assertArrayHasKey('office_area_sqft', $map,
            'MlsFieldMap::forRole(landlord) must have office_area_sqft entry');
        $this->assertSame('office_retail_sqft', $map['office_area_sqft']);
    }
}
