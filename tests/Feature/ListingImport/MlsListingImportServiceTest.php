<?php

namespace Tests\Feature\ListingImport;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\ListingImport\MlsListingImportService;
use App\Services\ListingImport\MlsFieldMap;
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

    // ─── applyImportedFields overwrite guard ──────────────────────────────────

    public function test_apply_does_not_overwrite_filled_property_unless_in_override_list(): void
    {
        $this->actingAs(\App\Models\User::factory()->make(['id' => 1]));

        $component = new SellerOfferListing();
        $component->bedrooms = '3';

        // Simulate having preview data
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

        // Call without override — should NOT overwrite
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

        // Call WITH override — should overwrite
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

    // ─── SSRF protection ──────────────────────────────────────────────────────

    /**
     * @dataProvider ssrfBlockedUrlProvider
     */
    public function test_ssrf_blocked_urls_return_friendly_error(string $url): void
    {
        // No Http::fake() — the guard must reject before any HTTP call is made.
        $result = $this->service->import($url);

        $this->assertFalse($result['success'], "Expected SSRF block for URL: {$url}");
        $this->assertNotEmpty($result['error']);
        // Error should not reveal internal network details
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

    public function test_public_url_is_not_blocked_by_ssrf_guard(): void
    {
        Http::fake([
            '*' => Http::response('<p>Bedrooms: 3 Bathrooms: 2</p>', 200),
        ]);

        // A real public IP should pass the guard and reach the HTTP layer
        $result = $this->service->import('https://8.8.8.8/listing');

        // The guard passed (no "not permitted" error); the HTTP layer may succeed or fail
        $this->assertStringNotContainsStringIgnoringCase('not permitted', $result['error'] ?? '');
    }

    // ─── Field map sanity ─────────────────────────────────────────────────────

    public function test_field_map_returns_arrays_for_all_four_roles(): void
    {
        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $map = MlsFieldMap::forRole($role);
            $this->assertIsArray($map);
            $this->assertNotEmpty($map, "Field map for role '{$role}' should not be empty");
        }
    }

    public function test_buyer_and_tenant_maps_omit_rental_only_fields(): void
    {
        $buyerMap  = MlsFieldMap::forRole('buyer');
        $tenantMap = MlsFieldMap::forRole('tenant');

        // These are landlord-only fields; buyer/tenant should not try to set them
        $this->assertArrayNotHasKey('application_fee', $buyerMap);
        $this->assertArrayNotHasKey('application_fee', $tenantMap);
    }

    public function test_landlord_map_includes_rental_fields(): void
    {
        $map = MlsFieldMap::forRole('landlord');

        $this->assertArrayHasKey('available_date', $map);
        $this->assertArrayHasKey('application_fee', $map);
        $this->assertArrayHasKey('rent_includes', $map);
    }
}
