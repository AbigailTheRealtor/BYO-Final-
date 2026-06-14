<?php

namespace Tests\Feature\ListingImport;

use App\Services\ListingImport\MlsListingImportService;
use App\Http\Livewire\OfferListing\Concerns\HasMlsImport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for P1 and P2 MLS parser gap-fixes.
 *
 * Each test verifies that a previously-missing or underspecified parser branch
 * now fires correctly.  Tests are named with the gap ID (P1-1, P1-2, …) so
 * that the audit matrix can reference them directly.
 *
 * Gap IDs match docs/audits/MLS_FULL_PIPELINE_MATRIX_ALL_7_FORMS.md §Gap fixes.
 *
 * ─── P1 — Label-variant parser gaps (P1-1 through P1-3) ─────────────────────
 *   P1-1  Security Deposit bare 2-word label ("Security Deposit:")
 *   P1-2  HOA Dues label ("HOA Dues:") for association_fee_amount
 *   P1-3  Folio Number / Folio # label for tax_id
 *
 * ─── P2 — Minor / edge-case gaps (P2-1 through P2-4) ────────────────────────
 *   P2-1  Terms of Lease reversed-word-order label ("Lease Terms:")
 *   P2-2  Zoning multi-word codes (e.g. "R-1 Single Family")
 *   P2-3  Flood Zone Date text-format dates ("January 15, 2020")
 *   P2-4  water_frontage / waterfront_feet stale-comment coverage check
 */
class MlsGapFixesRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private MlsListingImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MlsListingImportService::class);
    }

    // =========================================================================
    // P1-1  Security Deposit — bare 2-word label
    // =========================================================================

    /** Bare "Security Deposit: $1,500" must populate minimum_security_deposit. */
    public function test_P1_1_security_deposit_bare_label_emits_canonical_key(): void
    {
        $raw = 'Monthly Rent: $2,200 Security Deposit: $1,500 Tenant Pays: Electricity';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('minimum_security_deposit', $result['data'],
            'P1-1: bare "Security Deposit:" label must emit minimum_security_deposit');
        $this->assertSame('1500', $result['data']['minimum_security_deposit']);
    }

    /** "Security Deposit: $2,000.00" must strip currency symbols and formatting. */
    public function test_P1_1_security_deposit_bare_label_strips_formatting(): void
    {
        $raw = 'Security Deposit: $2,000.00 Tenant Pays: Cable';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('minimum_security_deposit', $result['data'],
            'P1-1: bare Security Deposit must parse regardless of formatting');
        $this->assertSame('2000.00', $result['data']['minimum_security_deposit']);
    }

    /** "Minimum Security Deposit:" (3-word form) must still fire correctly. */
    public function test_P1_1_minimum_security_deposit_3word_still_works(): void
    {
        $raw = 'Minimum Security Deposit: $3,000 Available Date: 01/01/2025';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertSame('3000', $result['data']['minimum_security_deposit'],
            'P1-1: 3-word "Minimum Security Deposit" must remain functional');
    }

    /** Parser must not assign arbitrary text as a deposit value. */
    public function test_P1_1_security_deposit_does_not_match_non_numeric(): void
    {
        // Non-numeric value after "Security Deposit:" — parser requires \$?[\d,.]+ format
        $raw = 'Security Deposit: Required - Contact Agent | Tenant Pays: Water';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('minimum_security_deposit', $result['data'],
            'P1-1: non-numeric security deposit must not populate the field');
    }

    // =========================================================================
    // P1-2  HOA Dues — label form for association_fee_amount
    // =========================================================================

    /** "HOA Dues: $350" must populate association_fee_amount. */
    public function test_P1_2_hoa_dues_label_emits_association_fee_amount(): void
    {
        $raw = 'HOA Y/N: Yes HOA Dues: $350 Association Name: Oak Ridge HOA';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('association_fee_amount', $result['data'],
            'P1-2: "HOA Dues:" must emit association_fee_amount');
        $this->assertSame('350', $result['data']['association_fee_amount']);
    }

    /** "HOA Dues: $1,200.00" must strip commas and keep decimals. */
    public function test_P1_2_hoa_dues_strips_currency_formatting(): void
    {
        $raw = 'HOA Dues: $1,200.00 CDD Y/N: No';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertSame('1200.00', $result['data']['association_fee_amount'],
            'P1-2: HOA Dues must strip commas and retain decimal precision');
    }

    /** "HOA Fee:" (existing form) must still work after the change. */
    public function test_P1_2_hoa_fee_label_still_works(): void
    {
        $raw = 'HOA Fee: $450 Association Fee Freq: Monthly';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertSame('450', $result['data']['association_fee_amount'],
            'P1-2: existing HOA Fee: label must remain functional');
    }

    /** "Association Fee:" (existing form) must still work after the change. */
    public function test_P1_2_association_fee_label_still_works(): void
    {
        $raw = 'Association Fee: $275 Association Fee Freq: Quarterly';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertSame('275', $result['data']['association_fee_amount'],
            'P1-2: existing Association Fee: label must remain functional');
    }

    // =========================================================================
    // P1-3  Folio Number / Folio # — Miami-Dade / Broward County label form
    // =========================================================================

    /** "Folio Number: 30-1234-567-0010" must populate tax_id. */
    public function test_P1_3_folio_number_emits_tax_id(): void
    {
        $raw = 'Folio Number: 30-1234-567-0010 Tax Year: 2024';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('tax_id', $result['data'],
            'P1-3: "Folio Number:" must emit tax_id canonical key');
        $this->assertSame('30-1234-567-0010', $result['data']['tax_id']);
    }

    /** "Folio #: 12-3456-789-0001" alternate short form must also populate tax_id. */
    public function test_P1_3_folio_hash_form_emits_tax_id(): void
    {
        $raw = 'Folio #: 12-3456-789-0001 Tax Year: 2023';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('tax_id', $result['data'],
            'P1-3: "Folio #:" short form must emit tax_id');
        $this->assertSame('12-3456-789-0001', $result['data']['tax_id']);
    }

    /** "Tax ID:" (existing form) must still fire after adding the Folio branches. */
    public function test_P1_3_tax_id_label_still_works(): void
    {
        $raw = 'Tax ID: 08-1234-56-78901 Tax Year: 2024';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertSame('08-1234-56-78901', $result['data']['tax_id'],
            'P1-3: existing Tax ID: label must remain functional');
    }

    /** "Parcel Number:" (existing form) must still fire after adding the Folio branches. */
    public function test_P1_3_parcel_number_label_still_works(): void
    {
        $raw = 'Parcel Number: 08-44-23-01-0012 Tax Year: 2023';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertSame('08-44-23-01-0012', $result['data']['tax_id'],
            'P1-3: existing Parcel Number: label must remain functional');
    }

    // =========================================================================
    // P2-1  Terms of Lease — reversed-label variant "Lease Terms:"
    // =========================================================================

    /** "Lease Terms: Month-to-Month, 1 Year" must populate terms_of_lease. */
    public function test_P2_1_lease_terms_reversed_label_emits_terms_of_lease(): void
    {
        $raw = 'Monthly Rent: $2,500 Lease Terms: Month-to-Month, 1 Year Tenant Pays: Electricity';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('terms_of_lease', $result['data'],
            'P2-1: reversed "Lease Terms:" label must emit terms_of_lease');
        $this->assertStringContainsString('Month-to-Month', $result['data']['terms_of_lease']);
    }

    /** "Terms of Lease:" (standard form) must still fire after adding the alternate. */
    public function test_P2_1_terms_of_lease_standard_label_still_works(): void
    {
        $raw = 'Monthly Rent: $1,800 Terms of Lease: 1 Year Tenant Pays: Water';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('terms_of_lease', $result['data'],
            'P2-1: standard Terms of Lease: label must remain functional');
        $this->assertStringContainsString('1 Year', $result['data']['terms_of_lease']);
    }

    /**
     * "Lease Terms:" must not bleed into the next label.
     *
     * Use "Tenant Pays:" as the following field (definitely in labelStop) with a
     * pipe separator to match real Stellar MLS export format.  This verifies that
     * boundary=true fires correctly and strips everything after "1 Year, Annual".
     */
    public function test_P2_1_lease_terms_does_not_bleed_into_next_field(): void
    {
        $raw = 'Lease Terms: 1 Year, Annual | Tenant Pays: Electricity, Water';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('terms_of_lease', $result['data']);
        $this->assertStringNotContainsStringIgnoringCase(
            'Tenant Pays',
            $result['data']['terms_of_lease'],
            'P2-1: Lease Terms capture must stop before Tenant Pays label'
        );
        $this->assertStringContainsString('1 Year', $result['data']['terms_of_lease'],
            'P2-1: The actual lease term value must be present');
    }

    // =========================================================================
    // P2-2  Zoning — multi-word codes
    // =========================================================================

    /**
     * Single-word zoning codes must still be captured correctly.
     *
     * Use a pipe separator to match real MLS data; the char class stops at "|"
     * so the capture is exactly the code token without the next label.
     */
    public function test_P2_2_zoning_single_word_code(): void
    {
        $raw = 'Zoning: R-1 | Lot Size: 0.25 acres';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('zoning', $result['data'],
            'P2-2: single-word Zoning code must still be captured');
        $this->assertSame('R-1', $result['data']['zoning']);
    }

    /**
     * "Zoning: R-1 Single Family" must capture the full multi-word code.
     *
     * Pipe-separated format: the space-allowing char class captures the whole
     * multi-word code and the "|" terminates the capture cleanly.
     */
    public function test_P2_2_zoning_multiword_code_captured(): void
    {
        $raw = 'Zoning: R-1 Single Family | Lot Size: 0.50 acres';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('zoning', $result['data'],
            'P2-2: multi-word Zoning code must be captured');
        $this->assertSame('R-1 Single Family', $result['data']['zoning']);
    }

    /** "Zoning: B-3 General Business" multi-word commercial code must be captured. */
    public function test_P2_2_zoning_commercial_multiword_code(): void
    {
        $raw = 'Zoning: B-3 General Business | Year Built: 1990';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertSame('B-3 General Business', $result['data']['zoning'],
            'P2-2: commercial multi-word zoning code must be fully captured');
    }

    /**
     * Multi-word zoning must not bleed into the next label.
     *
     * Pipe separator is the ground truth; "R-3 Multi Family" is the code,
     * everything after "|" belongs to the next field.
     */
    public function test_P2_2_zoning_multiword_does_not_bleed(): void
    {
        $raw = 'Zoning: R-3 Multi Family | Lot Size: 0.75 acres | Year Built: 2000';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertStringNotContainsStringIgnoringCase(
            'Lot',
            $result['data']['zoning'] ?? '',
            'P2-2: zoning must stop at pipe before Lot Size label'
        );
        $this->assertSame('R-3 Multi Family', $result['data']['zoning'],
            'P2-2: full multi-word code must be captured');
    }

    // =========================================================================
    // P2-3  Flood Zone Date — text-format dates
    // =========================================================================

    /** Numeric "MM/DD/YYYY" format must still work. */
    public function test_P2_3_flood_zone_date_numeric_slash_format(): void
    {
        $raw = 'Flood Zone Code: AE Flood Zone Date: 05/22/2023 Flood Zone Panel: 12099C';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('flood_zone_date', $result['data'],
            'P2-3: numeric MM/DD/YYYY Flood Zone Date must still be captured');
        $this->assertSame('05/22/2023', $result['data']['flood_zone_date']);
    }

    /** ISO "YYYY-MM-DD" format must still work. */
    public function test_P2_3_flood_zone_date_iso_format(): void
    {
        $raw = 'Flood Zone Date: 2023-05-22 Flood Zone Panel: 12099C0312F';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertSame('2023-05-22', $result['data']['flood_zone_date'],
            'P2-3: ISO YYYY-MM-DD Flood Zone Date must still be captured');
    }

    /** "Flood Zone Date: January 15, 2020" (full text month + comma) must be captured. */
    public function test_P2_3_flood_zone_date_text_month_with_comma(): void
    {
        $raw = 'Flood Zone Date: January 15, 2020 Flood Zone Panel: 12086C0294F';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('flood_zone_date', $result['data'],
            'P2-3: text-month Flood Zone Date must now be captured');
        $this->assertSame('January 15, 2020', $result['data']['flood_zone_date']);
    }

    /** "Flood Zone Date: Mar 8 2019" (abbreviated month, no comma) must be captured. */
    public function test_P2_3_flood_zone_date_abbreviated_month_no_comma(): void
    {
        $raw = 'Flood Zone Date: Mar 8 2019 Flood Zone Panel: 12099C0215G';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('flood_zone_date', $result['data'],
            'P2-3: abbreviated-month Flood Zone Date must be captured');
        $this->assertSame('Mar 8 2019', $result['data']['flood_zone_date']);
    }

    // =========================================================================
    // P2-4  water_frontage / waterfront_feet — confirm parser fires (stale comment fix)
    // =========================================================================

    /** "Water Frontage: Intracoastal Waterway" must populate water_frontage. */
    public function test_P2_4_water_frontage_text_is_captured(): void
    {
        $raw = 'Waterfront: Yes Water Frontage: Intracoastal Waterway Waterfront Feet: 75';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('water_frontage', $result['data'],
            'P2-4: water_frontage must be emitted by parser');
        $this->assertStringContainsString('Intracoastal', $result['data']['water_frontage']);
    }

    /** "Waterfront Feet: 120" must populate waterfront_feet as a numeric string. */
    public function test_P2_4_waterfront_feet_is_captured(): void
    {
        $raw = 'Waterfront: Yes Waterfront Feet: 120 Water Access: Lake';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('waterfront_feet', $result['data'],
            'P2-4: waterfront_feet must be emitted by parser');
        $this->assertSame('120', $result['data']['waterfront_feet']);
    }

    /** Edge case: "Waterfront Feet: 0" must not be silently dropped (PHP falsy guard). */
    public function test_P2_4_waterfront_feet_zero_value_not_dropped(): void
    {
        $raw = 'Waterfront: No Waterfront Feet: 0 Water Access: None';

        $result = $this->service->import('', $raw);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('waterfront_feet', $result['data'],
            'P2-4: waterfront_feet = 0 must not be silently dropped (falsy guard)');
        $this->assertSame('0', $result['data']['waterfront_feet']);
    }

    // =========================================================================
    // Stage 5.5 smoke — mlsApplied browser event (PHP side verified)
    // =========================================================================

    /**
     * Stage 5.5 parser smoke — sewer not bleeding into Water: label.
     *
     * Verifies that parseFields() correctly captures "Public Sewer" and stops
     * before the immediately-following "Water:" label.  The "Water\b(?=\s*:)"
     * labelStop entry (added in T#2729) is what enables this boundary.
     *
     * For the full apply → saveDraft → loadDraft persistence test that covers the
     * server-side prerequisite for Select2 rehydration, see
     * MlsLiveImportAuditTest::test_stage5_5_sewer_apply_save_reload_end_to_end().
     */
    public function test_stage5_5_sewer_parser_does_not_bleed_into_water_label(): void
    {
        $raw = 'City: Tampa | County: Hillsborough | State: FL | Sewer: Public Sewer | Water: Public';

        $importResult = $this->service->import('', $raw);

        $this->assertTrue($importResult['success']);
        $this->assertArrayHasKey('sewer', $importResult['data'],
            'Stage 5.5 parser smoke: sewer canonical key must be present');
        $this->assertSame('Public Sewer', $importResult['data']['sewer'],
            'Stage 5.5 parser smoke: sewer must not bleed into the following Water: label');
        $this->assertArrayHasKey('water', $importResult['data'],
            'Stage 5.5 parser smoke: Water: label must still be captured as a separate field');
    }

    // ─── Description address-strip regression tests ──────────────────────────
    // These verify that the header-strip regex handles all separator variants
    // between the ZIP code and the narrative remarks body.

    /**
     * Original separator: whitespace only.  This was always handled.
     */
    public function test_description_address_strip_space_separator(): void
    {
        $raw = 'Public Remarks: 123 MAIN ST TAMPA FL 33601 Beautiful home with pool and spa.';
        $result = $this->service->import('', $raw);
        $desc = $result['data']['description'] ?? '';
        $this->assertStringStartsWith('Beautiful home', $desc,
            'Address block before state+ZIP followed by space must be stripped');
        $this->assertStringNotContainsString('TAMPA', $desc,
            'City token must not remain in stripped description');
    }

    /**
     * Period separator after ZIP — the fix extends [\s+] to [\s.,;:!?\-–—]+
     * so a period immediately after the ZIP code does not block stripping.
     */
    public function test_description_address_strip_period_separator(): void
    {
        $raw = 'Public Remarks: 123 MAGNOLIA BLVD SARASOTA FL 34232. Welcome to paradise, this stunning home features...';
        $result = $this->service->import('', $raw);
        $desc = $result['data']['description'] ?? '';
        $this->assertStringStartsWith('Welcome to paradise', $desc,
            'Address block before state+ZIP followed by period must be stripped');
        $this->assertStringNotContainsString('SARASOTA', $desc,
            'City token must not remain in stripped description');
    }

    /**
     * Em-dash / hyphen separator after ZIP.
     */
    public function test_description_address_strip_dash_separator(): void
    {
        $raw = 'Public Remarks: 456 OAK AVE ORLANDO FL 32801 - Charming 3-bed 2-bath retreat near the lake.';
        $result = $this->service->import('', $raw);
        $desc = $result['data']['description'] ?? '';
        $this->assertStringStartsWith('Charming 3-bed', $desc,
            'Address block before state+ZIP followed by dash must be stripped');
    }

    /**
     * No address prefix — should pass through untouched.
     */
    public function test_description_no_address_prefix_passes_through(): void
    {
        $raw = 'Public Remarks: Stunning waterfront home with open floor plan and breathtaking views.';
        $result = $this->service->import('', $raw);
        $desc = $result['data']['description'] ?? '';
        $this->assertStringStartsWith('Stunning waterfront', $desc,
            'Description without address prefix must not be modified by the strip regex');
    }

    /**
     * Mixed-case address — strip regex requires all-caps block before state+ZIP;
     * a mixed-case address (e.g. "123 Main St") must NOT be stripped.
     */
    public function test_description_mixed_case_address_is_not_stripped(): void
    {
        $raw = 'Public Remarks: 123 Main St, Tampa FL 33601. This home has a beautiful kitchen.';
        $result = $this->service->import('', $raw);
        $desc = $result['data']['description'] ?? '';
        // Mixed-case prefix contains lowercase letters → strip does not fire
        $this->assertStringContainsString('123 Main St', $desc,
            'Mixed-case address prefix must not be stripped (strip only fires on all-caps blocks)');
    }

    // ─── Property Type normalization (apply-time, role-specific) ─────────────

    /**
     * Helper: invoke the private static normalizePropertyTypeForRole method via Reflection.
     */
    private function normalizePropertyType(string $value, string $role): string
    {
        $ref = new \ReflectionMethod(HasMlsImport::class, 'normalizePropertyTypeForRole');
        $ref->setAccessible(true);
        return $ref->invoke(null, $value, $role);
    }

    /** Seller: "Residential Property" (MLS verbose form) → 'Residential' */
    public function test_property_type_seller_residential_property_normalizes(): void
    {
        $this->assertSame('Residential', $this->normalizePropertyType('Residential Property', 'seller'));
    }

    /** Seller: "Commercial Property" → 'Commercial' */
    public function test_property_type_seller_commercial_property_normalizes(): void
    {
        $this->assertSame('Commercial', $this->normalizePropertyType('Commercial Property', 'seller'));
    }

    /** Seller: "Business Opportunity" → 'Business' */
    public function test_property_type_seller_business_opportunity_normalizes(): void
    {
        $this->assertSame('Business', $this->normalizePropertyType('Business Opportunity', 'seller'));
    }

    /** Seller: "Income/Multifamily" → 'Income' */
    public function test_property_type_seller_income_multifamily_normalizes(): void
    {
        $this->assertSame('Income', $this->normalizePropertyType('Income/Multifamily', 'seller'));
    }

    /** Seller: "Vacant Land Sale" → 'Vacant Land' */
    public function test_property_type_seller_vacant_land_sale_normalizes(): void
    {
        $this->assertSame('Vacant Land', $this->normalizePropertyType('Vacant Land Sale', 'seller'));
    }

    /** Seller: "Single Family Residence" → 'Residential' */
    public function test_property_type_seller_single_family_residence_normalizes(): void
    {
        $this->assertSame('Residential', $this->normalizePropertyType('Single Family Residence', 'seller'));
    }

    /** Seller: already-short 'Residential' passes through unchanged */
    public function test_property_type_seller_already_short_passes_through(): void
    {
        $this->assertSame('Residential', $this->normalizePropertyType('Residential', 'seller'));
    }

    /** Landlord: "Residential Property" stays as 'Residential Property' (form option match) */
    public function test_property_type_landlord_residential_property_unchanged(): void
    {
        $this->assertSame('Residential Property', $this->normalizePropertyType('Residential Property', 'landlord'));
    }

    /** Landlord: "Commercial Property" stays as 'Commercial Property' (form option match) */
    public function test_property_type_landlord_commercial_property_unchanged(): void
    {
        $this->assertSame('Commercial Property', $this->normalizePropertyType('Commercial Property', 'landlord'));
    }

    /** Landlord: short "Residential" (edge case) normalises UP to 'Residential Property' */
    public function test_property_type_landlord_short_residential_normalizes_up(): void
    {
        $this->assertSame('Residential Property', $this->normalizePropertyType('Residential', 'landlord'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // P3 — Apply-time routing fixes
    //
    // P3-1  Landlord residential terms_of_lease → desired_lease_length
    //       Root cause: MLS "Lease Terms: Month-to-Month" parses to canonical key
    //       'terms_of_lease', but that prop holds COMMERCIAL lease types (Gross Lease,
    //       Net Lease) for the landlord form.  Residential duration values belong in
    //       'desired_lease_length' (blade uses $residential_lease_term_options).
    //       Fix: HasMlsImport::applyImportedFields() re-routes 'terms_of_lease' →
    //       'desired_lease_length' when role=landlord AND property_type='Residential Property'.
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * P3-1: landlord residential — terms_of_lease canonical key is routed to
     * desired_lease_length at apply time, leaving terms_of_lease empty.
     *
     * Uses an anonymous class that wires in the HasMlsImport trait with just
     * enough properties for applyImportedFields() to run:
     *   - user_type='landlord' → resolveImportRole() returns 'landlord'
     *   - property_type='Residential Property' → triggers the re-route
     *   - importPreviewData preset with a terms_of_lease row
     */
    public function test_p3_1_landlord_residential_terms_of_lease_routes_to_desired_lease_length(): void
    {
        $component = new class {
            use HasMlsImport;

            public string $user_type       = 'landlord';
            public string $property_type   = 'Residential Property';
            public array  $desired_lease_length = [];
            public array  $terms_of_lease       = [];

            public function dispatchBrowserEvent(string $event, array $data = []): void {}
        };

        // Simulate the preview row exactly as applyImportedFields() expects.
        // prop_name is the field-map value minus the '*' prefix.
        $component->importPreviewData = [
            [
                'canonical_key' => 'terms_of_lease',
                'prop_name'     => 'terms_of_lease',
                'is_array_prop' => true,
                'value'         => 'Month-to-Month',
                'label'         => 'Lease Terms',
                'raw_value'     => 'Month-to-Month',
                'normalized'    => 'Month-to-Month',
            ],
        ];

        $component->applyImportedFields(['terms_of_lease']);

        $this->assertSame(
            ['Month-to-Month'],
            $component->desired_lease_length,
            'Landlord residential: terms_of_lease value must be routed to desired_lease_length.'
        );

        $this->assertEmpty(
            $component->terms_of_lease,
            'Landlord residential: terms_of_lease prop must remain empty after routing to desired_lease_length.'
        );
    }

    /**
     * P3-1b: landlord commercial — terms_of_lease canonical key stays on
     * terms_of_lease prop (no re-route for Commercial Property).
     */
    public function test_p3_1b_landlord_commercial_terms_of_lease_stays_on_terms_of_lease(): void
    {
        $component = new class {
            use HasMlsImport;

            public string $user_type       = 'landlord';
            public string $property_type   = 'Commercial Property';
            public array  $desired_lease_length = [];
            public array  $terms_of_lease       = [];

            public function dispatchBrowserEvent(string $event, array $data = []): void {}
        };

        $component->importPreviewData = [
            [
                'canonical_key' => 'terms_of_lease',
                'prop_name'     => 'terms_of_lease',
                'is_array_prop' => true,
                'value'         => 'Gross Lease',
                'label'         => 'Lease Terms',
                'raw_value'     => 'Gross Lease',
                'normalized'    => 'Gross Lease',
            ],
        ];

        $component->applyImportedFields(['terms_of_lease']);

        $this->assertSame(
            ['Gross Lease'],
            $component->terms_of_lease,
            'Landlord commercial: terms_of_lease value must stay on terms_of_lease prop.'
        );

        $this->assertEmpty(
            $component->desired_lease_length,
            'Landlord commercial: desired_lease_length must remain empty for commercial listings.'
        );
    }

    /**
     * P3-1c: seller — terms_of_lease is NOT re-routed (routing only fires for landlord).
     * The seller form doesn't have desired_lease_length; the prop_name assignment
     * means property_exists check would skip it if redirected, so verify it stays
     * on the terms_of_lease prop for a seller component.
     */
    public function test_p3_1c_seller_terms_of_lease_not_rerouted(): void
    {
        $component = new class {
            use HasMlsImport;

            public string $user_type       = 'seller';
            public string $property_type   = 'Residential';
            public array  $terms_of_lease  = [];

            public function dispatchBrowserEvent(string $event, array $data = []): void {}
        };

        $component->importPreviewData = [
            [
                'canonical_key' => 'terms_of_lease',
                'prop_name'     => 'terms_of_lease',
                'is_array_prop' => true,
                'value'         => 'Gross Lease',
                'label'         => 'Lease Terms',
                'raw_value'     => 'Gross Lease',
                'normalized'    => 'Gross Lease',
            ],
        ];

        $component->applyImportedFields(['terms_of_lease']);

        $this->assertSame(
            ['Gross Lease'],
            $component->terms_of_lease,
            'Seller: terms_of_lease routing must not fire; value stays on terms_of_lease.'
        );
    }
}
