<?php

namespace Tests\Feature\ListingImport;

use App\Services\ListingImport\MlsListingImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * MlsIncomeFieldsImportTest
 *
 * MLS parser tests for the 7 income / multifamily field branches added in
 * Task 2530.  Uses the same inline-rawText import pattern as
 * MlsNewFieldsImportTest — no HTTP fixture needed.
 *
 * Coverage:
 *   1.  gross_annual_income  — "Gross Annual Income" label form
 *   2.  gross_annual_income  — "Annual Gross Income" alternate label
 *   3.  gross_annual_income  — comma stripping (e.g. $1,200,000 → "1200000")
 *   4.  annual_operating_expenses — standard label
 *   5.  annual_operating_expenses — short label ("Annual Expenses")
 *   6.  annual_operating_expenses — comma stripping
 *   7.  cap_rate             — percentage sign stripped
 *   8.  cap_rate             — decimal precision preserved
 *   9.  unit_types_raw       — "Unit Mix" label
 *  10.  unit_types_raw       — "Unit Type" label
 *  11.  net_operating_income_raw — "Net Operating Income" label
 *  12.  net_operating_income_raw — "NOI" abbreviation label
 *  13.  gross_annual_income does not bleed into annual_operating_expenses
 *  14.  cap_rate does not bleed into gross_annual_income
 *  15.  listing_type_hint is 'sale' when no rental signals present
 */
class MlsIncomeFieldsImportTest extends TestCase
{
    use DatabaseTransactions;

    private MlsListingImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MlsListingImportService::class);
    }

    // =========================================================================
    // gross_annual_income
    // =========================================================================

    public function test_parser_emits_gross_annual_income_from_gross_annual_income_label(): void
    {
        $rawText = 'Gross Annual Income: $120,000  Bedrooms: 4';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('gross_annual_income', $result['data'],
            'Parser must emit gross_annual_income canonical key');
        $this->assertSame('120000', $result['data']['gross_annual_income']);
    }

    public function test_parser_emits_gross_annual_income_from_annual_gross_income_label(): void
    {
        $rawText = 'Annual Gross Income: $150,000  Year Built: 2001';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('gross_annual_income', $result['data'],
            'Parser must also match "Annual Gross Income" label variant');
        $this->assertSame('150000', $result['data']['gross_annual_income']);
    }

    public function test_parser_strips_commas_from_gross_annual_income(): void
    {
        $rawText = 'Gross Annual Income: $1,250,000  Bedrooms: 8';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('gross_annual_income', $result['data']);
        $this->assertSame('1250000', $result['data']['gross_annual_income'],
            'Comma separators must be stripped from gross_annual_income');
    }

    // =========================================================================
    // annual_operating_expenses
    // =========================================================================

    public function test_parser_emits_annual_operating_expenses_from_standard_label(): void
    {
        $rawText = 'Annual Operating Expenses: $45,000  Pool: Yes';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('annual_operating_expenses', $result['data'],
            'Parser must emit annual_operating_expenses canonical key');
        $this->assertSame('45000', $result['data']['annual_operating_expenses']);
    }

    public function test_parser_emits_annual_operating_expenses_from_short_label(): void
    {
        $rawText = 'Annual Expenses: $30,500  Garage: 2';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('annual_operating_expenses', $result['data'],
            'Parser must match "Annual Expenses" short-label variant');
        $this->assertSame('30500', $result['data']['annual_operating_expenses']);
    }

    public function test_parser_strips_commas_from_annual_operating_expenses(): void
    {
        $rawText = 'Annual Operating Expenses: $1,050,000  Bedrooms: 12';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('annual_operating_expenses', $result['data']);
        $this->assertSame('1050000', $result['data']['annual_operating_expenses'],
            'Comma separators must be stripped from annual_operating_expenses');
    }

    // =========================================================================
    // cap_rate
    // =========================================================================

    public function test_parser_emits_cap_rate_and_strips_percent_sign(): void
    {
        $rawText = 'Cap Rate: 6.5%  Bedrooms: 4';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('cap_rate', $result['data'],
            'Parser must emit cap_rate canonical key');
        $this->assertSame('6.5', $result['data']['cap_rate'],
            'Percent sign must be stripped from cap_rate');
    }

    public function test_parser_emits_cap_rate_without_percent_sign(): void
    {
        $rawText = 'Cap Rate: 7.25  Year Built: 1985';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('cap_rate', $result['data']);
        $this->assertSame('7.25', $result['data']['cap_rate'],
            'Cap rate without percent sign must still be captured correctly');
    }

    public function test_parser_preserves_cap_rate_decimal_precision(): void
    {
        $rawText = 'Cap Rate: 5.1250%  Garage: 1';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('cap_rate', $result['data']);
        $this->assertSame('5.1250', $result['data']['cap_rate'],
            'Full decimal precision (up to 4 places) must be preserved for cap_rate');
    }

    // =========================================================================
    // unit_types_raw
    // =========================================================================

    public function test_parser_emits_unit_types_raw_from_unit_mix_label(): void
    {
        $rawText = 'Unit Mix: 4x1BD/1BA, 2x2BD/2BA  Pool: Yes';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('unit_types_raw', $result['data'],
            'Parser must emit unit_types_raw from "Unit Mix" label');
        $this->assertStringContainsStringIgnoringCase('1BD', $result['data']['unit_types_raw']);
    }

    public function test_parser_emits_unit_types_raw_from_unit_type_label(): void
    {
        $rawText = 'Unit Type: Studio, 1BR  Bedrooms: 0';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('unit_types_raw', $result['data'],
            'Parser must emit unit_types_raw from "Unit Type" label variant');
        $this->assertStringContainsStringIgnoringCase('Studio', $result['data']['unit_types_raw']);
    }

    // =========================================================================
    // net_operating_income_raw
    // =========================================================================

    public function test_parser_emits_net_operating_income_raw_from_full_label(): void
    {
        $rawText = 'Net Operating Income: $75,000  Year Built: 2003';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('net_operating_income_raw', $result['data'],
            'Parser must emit net_operating_income_raw from "Net Operating Income" label');
        $this->assertStringContainsString('75', $result['data']['net_operating_income_raw']);
    }

    public function test_parser_emits_net_operating_income_raw_from_noi_abbreviation(): void
    {
        $rawText = 'NOI: $80,000  Pool: No';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('net_operating_income_raw', $result['data'],
            'Parser must emit net_operating_income_raw from "NOI" abbreviation');
        $this->assertStringContainsString('80', $result['data']['net_operating_income_raw']);
    }

    // =========================================================================
    // Bleed-prevention
    // =========================================================================

    public function test_gross_annual_income_does_not_bleed_into_annual_operating_expenses(): void
    {
        $rawText = 'Gross Annual Income: $200,000  Annual Operating Expenses: $60,000  Bedrooms: 6';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('gross_annual_income', $data);
        $this->assertArrayHasKey('annual_operating_expenses', $data);

        $this->assertSame('200000', $data['gross_annual_income'],
            'gross_annual_income must not bleed into the next label');
        $this->assertSame('60000', $data['annual_operating_expenses'],
            'annual_operating_expenses must capture its own value');

        $this->assertStringNotContainsString('60000', $data['gross_annual_income'],
            'gross_annual_income must not include the operating_expenses value');
    }

    public function test_cap_rate_does_not_bleed_into_gross_annual_income(): void
    {
        $rawText = 'Cap Rate: 6.0%  Gross Annual Income: $180,000  Bedrooms: 8';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $data = $result['data'];

        $this->assertArrayHasKey('cap_rate', $data);
        $this->assertArrayHasKey('gross_annual_income', $data);

        $this->assertSame('6.0', $data['cap_rate']);
        $this->assertSame('180000', $data['gross_annual_income']);
    }

    // =========================================================================
    // listing_type_hint remains 'sale' when no rental signals present
    // =========================================================================

    public function test_income_text_with_no_rental_signals_produces_sale_hint(): void
    {
        $rawText = 'Gross Annual Income: $100,000  Cap Rate: 5.5%  Annual Operating Expenses: $35,000';

        $result = $this->service->import('', $rawText);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('listing_type_hint', $result['data']);
        $this->assertSame('sale', $result['data']['listing_type_hint'],
            'Income-only text with no rental keywords must produce a sale hint');
    }
}
