<?php

namespace Tests\Feature\ListingImport;

use Tests\TestCase;
use App\Services\ListingImport\MlsListingImportService;

/**
 * Permanent regression tests using fixture files derived from real Stellar MLS listings.
 *
 * Fixture files:
 *  - tests/fixtures/mls/seller_regression.txt  — 8288 9TH AVENUE N, Seminole FL (A4601234)
 *  - tests/fixtures/mls/landlord_regression.txt — 8535 BLIND PASS DRIVE, Treasure Island FL (R4701987)
 *
 * Each test class is named after the failure mode it pins.  The inline text
 * patterns match what the fixture files contain so the two sets of assertions
 * remain in sync.
 *
 * Confirmed bug → fix mapping:
 *  BUG-1  City ← "SEMINOLE Pinellas County Unified"   FIX: County\b added to sectionHeaderStop
 *  BUG-2  Carport ← "No Tax"                           FIX: Tax\s+Legal\b added to labelStop
 *  BUG-3  Waterfront ← "Feet: 0"                       FIX: Waterfront regex requires `:` colon
 *  BUG-4  Water View ← "Intracoastal Water Assessment…" FIX: Tax\s+Assessment\b added to labelStop
 *  NEW-1  water_frontage parsed from "Water Frontage:"
 *  NEW-2  waterfront_feet parsed from "Waterfront Feet:"
 *  OK-1   Public Remarks → description works (was never broken, pinned here)
 *  OK-2   Rent Includes stops before Water Frontage (was never broken, pinned here)
 */
class MlsRealListingRegressionTest extends TestCase
{
    private MlsListingImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MlsListingImportService();
    }

    // =========================================================================
    // Seller fixture — 8288 9TH AVE N, Seminole FL
    // =========================================================================

    /**
     * BUG-1: City captured "SEMINOLE Pinellas County Unified" when the school
     * district block appeared inline after the city value without a "County:"
     * label separator.  Fix: County\b added to sectionHeaderStop so the bare
     * word "County" terminates the city capture even without a trailing colon.
     */
    public function test_seller_fixture_city_stops_before_county_unified(): void
    {
        $data = $this->parseFixture('seller_regression.txt');

        $this->assertArrayHasKey('city', $data);
        $this->assertStringNotContainsStringIgnoringCase('County', $data['city'],
            'City must not bleed into "County Unified" school-district text.');
        $this->assertStringNotContainsStringIgnoringCase('Pinellas', $data['city'],
            'City must not bleed into the county name.');
        $this->assertStringNotContainsStringIgnoringCase('Unified', $data['city'],
            'City must not capture school-district qualifier "Unified".');
        $this->assertEquals('SEMINOLE', trim($data['city']),
            'City must be exactly SEMINOLE.');
    }

    /**
     * BUG-2: Carport captured "No Tax" because "Tax Legal Desc:" is a compound
     * label not previously in labelStop, so the boundary fired late at "Legal Desc:"
     * and included the "Tax " prefix in the captured value.
     * Fix: Tax\s+Legal\b added to labelStop ahead of Legal\s+Desc.
     */
    public function test_seller_fixture_carport_stops_before_tax_legal_desc(): void
    {
        $data = $this->parseFixture('seller_regression.txt');

        $this->assertArrayHasKey('carport', $data);
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['carport'],
            'Carport must not bleed into the "Tax Legal Desc" compound label.');
        $this->assertStringNotContainsStringIgnoringCase('Legal', $data['carport'],
            'Carport must not bleed into legal description text.');
        $this->assertStringNotContainsStringIgnoringCase('Desc', $data['carport'],
            'Carport must not contain "Desc" from the Tax Legal Desc label.');
        $this->assertEquals('no', strtolower(trim($data['carport'])),
            'Carport boolean must be "no".');
    }

    /** OK-1 (pinned): Public Remarks maps to description via the additional_details chain. */
    public function test_seller_fixture_public_remarks_maps_to_description(): void
    {
        $data = $this->parseFixture('seller_regression.txt');

        $this->assertArrayHasKey('description', $data,
            'description key must be present after parsing Public Remarks.');
        $this->assertNotEmpty($data['description'],
            'description must not be empty when Public Remarks is present.');
        $this->assertStringContainsStringIgnoringCase('Seminole', $data['description'],
            'description must contain text from the Public Remarks block.');
    }

    /** Seller fixture: interior_features must not bleed into appliances. */
    public function test_seller_fixture_interior_features_do_not_bleed_into_appliances(): void
    {
        $data = $this->parseFixture('seller_regression.txt');

        if (!isset($data['interior_features'])) {
            $this->markTestSkipped('interior_features not parsed — check fixture format.');
        }

        $interiorStr = is_array($data['interior_features'])
            ? implode(',', $data['interior_features'])
            : (string) $data['interior_features'];

        $this->assertStringNotContainsStringIgnoringCase('Dishwasher', $interiorStr,
            'interior_features must not capture the Appliances block.');
        $this->assertStringNotContainsStringIgnoringCase('Refrigerator', $interiorStr,
            'interior_features must not capture appliance names.');
    }

    /** Seller fixture: waterfront must parse as the boolean "no" value. */
    public function test_seller_fixture_waterfront_is_no(): void
    {
        $data = $this->parseFixture('seller_regression.txt');

        $this->assertArrayHasKey('waterfront', $data);
        $this->assertEquals('no', strtolower(trim($data['waterfront'])),
            'Seller waterfront must normalize to "no".');
    }

    // =========================================================================
    // Landlord fixture — 8535 BLIND PASS DRIVE, Treasure Island FL
    // =========================================================================

    /**
     * BUG-3: Waterfront captured "Feet: 0" because the old Waterfront regex
     * used [\s:]+ which matched a space and consumed "Waterfront Feet:" as the
     * label prefix, capturing the feet value instead of the boolean.
     * Fix: Waterfront regex now requires a colon directly after "Waterfront"
     * (pattern: Waterfront\s*:), so "Waterfront Feet:" no longer matches.
     */
    public function test_landlord_fixture_waterfront_stops_at_colon_not_space(): void
    {
        $data = $this->parseFixture('landlord_regression.txt');

        $this->assertArrayHasKey('waterfront', $data);
        $this->assertStringNotContainsStringIgnoringCase('Feet', $data['waterfront'],
            'Waterfront must not capture "Feet: 0" from the Waterfront Feet label.');
        $this->assertEquals('no', strtolower(trim($data['waterfront'])),
            'Waterfront boolean must be "no" (property is not waterfront).');
    }

    /**
     * BUG-4: Water View captured "Intracoastal Water Assessment & Tax Assessment: $2400"
     * because neither "Assessment" nor "Tax Assessment" was in labelStop, allowing
     * the boundary to miss the combined "Assessment & Tax Assessment:" MLS label.
     * Fix: Tax\s+Assessment\b added to labelStop.
     */
    public function test_landlord_fixture_water_view_stops_before_assessment_label(): void
    {
        $data = $this->parseFixture('landlord_regression.txt');

        $this->assertArrayHasKey('water_view', $data);
        $waterView = is_array($data['water_view'])
            ? implode(',', $data['water_view'])
            : (string) $data['water_view'];

        $this->assertStringNotContainsStringIgnoringCase('Assessment', $waterView,
            'Water View must not bleed into "Assessment & Tax Assessment" label text.');
        $this->assertStringNotContainsStringIgnoringCase('Tax', $waterView,
            'Water View must not capture any Tax label text.');
        $this->assertStringContainsStringIgnoringCase('Intracoastal', $waterView,
            'Water View must contain the actual view value "Intracoastal".');
    }

    /**
     * NEW-1: Water Frontage parser (new) — extracts the type of water body from
     * "Water Frontage: <description>", stored as canonical key water_frontage.
     * No Livewire property exists yet; value is in $data only (not applied to form).
     */
    public function test_landlord_fixture_water_frontage_is_parsed(): void
    {
        $data = $this->parseFixture('landlord_regression.txt');

        $this->assertArrayHasKey('water_frontage', $data,
            'water_frontage must be present in parsed $data when "Water Frontage:" label exists in fixture.');
        $this->assertNotEmpty($data['water_frontage'],
            'water_frontage must not be empty.');
        $this->assertStringContainsStringIgnoringCase('Intracoastal', $data['water_frontage'],
            'water_frontage must contain the Intracoastal Waterway value from the fixture.');
    }

    /**
     * NEW-2: Waterfront Feet parser (new) — extracts the numeric footage from
     * "Waterfront Feet: <N>", stored as canonical key waterfront_feet.
     * No Livewire property exists yet; value is in $data only (not applied to form).
     */
    public function test_landlord_fixture_waterfront_feet_is_parsed(): void
    {
        $data = $this->parseFixture('landlord_regression.txt');

        $this->assertArrayHasKey('waterfront_feet', $data,
            'waterfront_feet must be present in parsed $data when "Waterfront Feet:" label exists in fixture.');
        $this->assertEquals('0', (string) $data['waterfront_feet'],
            'waterfront_feet must be "0" as specified in the fixture.');
    }

    /**
     * OK-2 (pinned): Rent Includes must stop at the Water Frontage label and must
     * not capture "Intracoastal Waterway" or waterfront boolean text.
     */
    public function test_landlord_fixture_rent_includes_stops_before_water_frontage(): void
    {
        $data = $this->parseFixture('landlord_regression.txt');

        $this->assertArrayHasKey('rent_includes', $data,
            'rent_includes must be parsed from the fixture.');

        $rentStr = is_array($data['rent_includes'])
            ? implode(',', $data['rent_includes'])
            : (string) $data['rent_includes'];

        $this->assertStringNotContainsStringIgnoringCase('Intracoastal', $rentStr,
            'rent_includes must stop at "Water Frontage:" and not capture waterway text.');
        $this->assertStringNotContainsStringIgnoringCase('Waterfront', $rentStr,
            'rent_includes must not capture Waterfront boolean text.');
        $this->assertStringContainsStringIgnoringCase('Cable TV', $rentStr,
            'rent_includes must contain the actual Cable TV value from the fixture.');
        $this->assertStringContainsStringIgnoringCase('Internet', $rentStr,
            'rent_includes must contain the actual Internet value from the fixture.');
    }

    /** OK-1 (pinned): Public Remarks maps to description for landlord role too. */
    public function test_landlord_fixture_public_remarks_maps_to_description(): void
    {
        $data = $this->parseFixture('landlord_regression.txt');

        $this->assertArrayHasKey('description', $data,
            'description key must be present after parsing Public Remarks.');
        $this->assertNotEmpty($data['description'],
            'description must not be empty when Public Remarks is present.');
        $this->assertStringContainsStringIgnoringCase('Blind Pass', $data['description'],
            'description must contain text from the Public Remarks block.');
    }

    // =========================================================================
    // Inline regression — same failure patterns as inline snippets
    // (provides narrower failure messages if the fixture format changes)
    // =========================================================================

    /** BUG-1 inline: "County Unified" after city value with no County: colon — sectionHeaderStop fires. */
    public function test_inline_city_stops_at_bare_county_word(): void
    {
        // The reproducing pattern: school-district text "County Unified" follows the city
        // value without its own labeled prefix ("School District:"), causing city to bleed.
        // "Pinellas" is NOT between SEMINOLE and County here — the county name only appears
        // after the "County:" labeled field later in the line.
        $data = $this->parse('City: SEMINOLE County Unified State: FL Zip Code: 33777 County: Pinellas List Price: $349900');

        $this->assertEquals('SEMINOLE', trim($data['city'] ?? ''),
            'City must stop at bare "County" word (sectionHeaderStop).');
    }

    /** BUG-2 inline: Compound "Tax Legal Desc:" label — carport must be "no". */
    public function test_inline_carport_stops_at_tax_legal_desc_compound_label(): void
    {
        $data = $this->parse('Carport: No Tax Legal Desc: NINE MILE ESTATES LOT 7 BLK B Bedrooms: 3');

        $this->assertEquals('no', strtolower(trim($data['carport'] ?? '')),
            'Carport must stop at "Tax Legal Desc:" compound label (Tax\s+Legal\b in labelStop).');
    }

    /** BUG-3 inline: Waterfront Feet before Waterfront — waterfront must be "no". */
    public function test_inline_waterfront_colon_required_not_space(): void
    {
        $data = $this->parse('Waterfront Feet: 0 Waterfront: No Water View: Intracoastal Water Special Assessment Y/N: No');

        $wf = strtolower(trim($data['waterfront'] ?? ''));
        $this->assertEquals('no', $wf,
            'Waterfront must require colon separator to not match "Waterfront Feet:" prefix.');
        $this->assertStringNotContainsString('feet', strtolower($data['waterfront'] ?? ''),
            'Waterfront must not capture "Feet: 0" from the Waterfront Feet label.');
    }

    /** BUG-4 inline: Water View stops at "Tax Assessment:" label. */
    public function test_inline_water_view_stops_at_tax_assessment_label(): void
    {
        // The reproducing pattern: "Tax Assessment:" follows directly after the water_view
        // value without a blank line, causing the greedy capture to bleed into the tax field.
        $data = $this->parse('Water View: Intracoastal Water Tax Assessment: $2400 Tax Year: 2024');

        $wv = $data['water_view'] ?? '';
        if (is_array($wv)) {
            $wv = implode(',', $wv);
        }
        $this->assertStringNotContainsStringIgnoringCase('Assessment', $wv,
            'Water View must stop at "Tax Assessment" label (Tax\s+Assessment\b in labelStop).');
        $this->assertStringContainsStringIgnoringCase('Intracoastal', $wv,
            'Water View must retain its actual value.');
    }

    /** NEW-1 inline: Water Frontage parser produces correct value. */
    public function test_inline_water_frontage_is_parsed(): void
    {
        $data = $this->parse('Water Frontage: Intracoastal Waterway Waterfront Feet: 0 Waterfront: No');

        $this->assertArrayHasKey('water_frontage', $data,
            'water_frontage canonical key must be present when "Water Frontage:" appears in text.');
        $this->assertStringContainsStringIgnoringCase('Intracoastal', $data['water_frontage'],
            'water_frontage must capture the waterway description.');
    }

    /** NEW-2 inline: Waterfront Feet parser produces numeric value. */
    public function test_inline_waterfront_feet_is_parsed(): void
    {
        $data = $this->parse('Waterfront Feet: 150 Waterfront: Yes Water View: Bay/Harbor');

        $this->assertArrayHasKey('waterfront_feet', $data,
            'waterfront_feet canonical key must be present when "Waterfront Feet:" appears.');
        $this->assertEquals('150', (string) $data['waterfront_feet'],
            'waterfront_feet must capture the numeric footage value.');
    }

    /** NEW: Water Frontage parser stops at the Waterfront boundary (no bleed). */
    public function test_inline_water_frontage_stops_at_waterfront_label(): void
    {
        $data = $this->parse('Water Frontage: Bay/Harbor Canal Waterfront: Yes Waterfront Feet: 200');

        $wf = $data['water_frontage'] ?? '';
        $this->assertStringNotContainsStringIgnoringCase('Waterfront', $wf,
            'water_frontage must stop before the "Waterfront:" label.');
        $this->assertStringNotContainsStringIgnoringCase('Yes', $wf,
            'water_frontage must not capture the Waterfront boolean value.');
    }

    // =========================================================================
    // NEW inline regressions — four browser-confirmed bugs fixed in this batch
    // =========================================================================

    /**
     * NEW-A: Carport bleeds into "Tax: $3,908" when some Stellar MLS exports emit
     * the annual tax as a bare "Tax:" label (not "Tax Year:" or "Tax Amount:").
     * Fix: Tax\b(?=\s*:) added to labelStop — colon lookahead prevents firing on
     * mid-word "Tax" inside parcel IDs like "1410Tax Year:".
     */
    public function test_inline_carport_stops_at_bare_tax_colon_label(): void
    {
        $data = $this->parse('Carport: No Tax: $3,908 Assessments: $0 Homestead: Yes');

        $carport = strtolower(trim($data['carport'] ?? ''));
        $this->assertEquals('no', $carport,
            'Carport must be "no" — must not bleed into "Tax: $3,908" bare-Tax label.');
        $this->assertStringNotContainsStringIgnoringCase('Tax', $data['carport'] ?? '',
            'Carport must not contain any "Tax" text from the adjacent Tax: label.');
        $this->assertStringNotContainsStringIgnoringCase('$3', $data['carport'] ?? '',
            'Carport must not capture the dollar amount from the Tax: label.');
    }

    /**
     * NEW-B: Interior Features bleeds into Appliances when the MLS uses the label
     * "Appliances Included:" (two-word variant) instead of the shorter "Appliances:".
     * The old labelStop had only "Appliances?\b", which required the colon to follow
     * immediately after the final "s".  Fix: Appliances?\s+Included\b added BEFORE
     * Appliances?\b so the longer two-word form is caught first.
     */
    public function test_inline_interior_features_stop_at_appliances_included_variant(): void
    {
        $data = $this->parse(
            'Interior Features: Ceiling Fans(s),Crown Molding,Walk-In Closet(s) ' .
            'Appliances Included: Dishwasher,Range,Refrigerator ' .
            'Exterior Construction: Block'
        );

        $interior = is_array($data['interior_features'] ?? null)
            ? implode(',', $data['interior_features'])
            : (string) ($data['interior_features'] ?? '');

        $this->assertStringNotContainsStringIgnoringCase('Dishwasher', $interior,
            'interior_features must stop at "Appliances Included:" and not capture appliance names.');
        $this->assertStringNotContainsStringIgnoringCase('Range', $interior,
            'interior_features must not bleed into the Appliances Included list.');
        $this->assertStringContainsStringIgnoringCase('Crown Molding', $interior,
            'interior_features must contain its own actual value.');
    }

    /**
     * NEW-B2: Appliances parses correctly from the "Appliances Included:" label variant.
     * Confirms the longer label is recognised by the appliances parser (not just blocked
     * from bleeding into interior_features).
     */
    public function test_inline_appliances_parsed_from_appliances_included_label(): void
    {
        $data = $this->parse(
            'Interior Features: Ceiling Fans(s) ' .
            'Appliances Included: Dishwasher,Range,Refrigerator ' .
            'Exterior Construction: Block'
        );

        $appliances = is_array($data['appliances'] ?? null)
            ? implode(',', $data['appliances'])
            : (string) ($data['appliances'] ?? '');

        $this->assertNotEmpty($appliances,
            'appliances must be parsed from the "Appliances Included:" label variant.');
        $this->assertStringContainsStringIgnoringCase('Dishwasher', $appliances,
            'appliances must contain Dishwasher from the "Appliances Included:" label.');
        $this->assertStringNotContainsStringIgnoringCase('Block', $appliances,
            'appliances must stop before "Exterior Construction:".');
    }

    /**
     * NEW-C: "Water Frontage Y/N: No" — some MLS exports emit a boolean Y/N variant
     * of the Water Frontage field.  The old text branch captured "Y/N: No" as if it
     * were a water body name.
     * Fix: a dedicated boolean branch parses "Water Frontage Y/N:" first, and a
     * negative lookahead (?!\s+Y\/N) in the text branch prevents re-matching.
     */
    public function test_inline_water_frontage_yn_not_captured_as_body_name(): void
    {
        $data = $this->parse(
            'Rent Includes: Cable TV,Internet ' .
            'Water Frontage Y/N: No ' .
            'Waterfront Feet: 0 Waterfront: No'
        );

        $wf = (string) ($data['water_frontage'] ?? '');
        $this->assertStringNotContainsStringIgnoringCase('Y/N', $wf,
            'water_frontage must not capture "Y/N" from the boolean Water Frontage Y/N label.');
        $this->assertStringNotContainsStringIgnoringCase('No', $wf,
            'water_frontage must not capture the boolean "No" value as a water body name.');
    }

    /**
     * NEW-C2: "Water Frontage Y/N: No" is stored under waterfront_yn (separate key),
     * and the boolean is normalized correctly.
     */
    public function test_inline_water_frontage_yn_stored_as_separate_key(): void
    {
        $data = $this->parse('Water Frontage Y/N: No Waterfront Feet: 0 Waterfront: No');

        $this->assertArrayHasKey('waterfront_yn', $data,
            'waterfront_yn key must be present when "Water Frontage Y/N:" appears in MLS text.');
        $this->assertEquals('no', strtolower(trim($data['waterfront_yn'])),
            'waterfront_yn must normalize to "no".');
    }

    /**
     * NEW-D: Water View bleeds into bare "Assessment" text when the MLS emits the
     * assessment section without the "Tax" prefix (i.e. "Assessment:" not "Tax Assessment:").
     * Fix: (?:Tax\s+)?Assessment\b added to sectionHeaderStop so the bare word
     * "Assessment" terminates captures even without a preceding "Tax " prefix.
     */
    public function test_inline_water_view_stops_at_bare_assessment_word(): void
    {
        $data = $this->parse('Water View: Intracoastal Waterway Assessment: $2,400 Tax Year: 2024');

        $wv = $data['water_view'] ?? '';
        if (is_array($wv)) {
            $wv = implode(',', $wv);
        }
        $this->assertStringNotContainsStringIgnoringCase('Assessment', $wv,
            'Water View must stop at bare "Assessment" even without a "Tax " prefix.');
        $this->assertStringContainsStringIgnoringCase('Intracoastal', $wv,
            'Water View must retain its actual Intracoastal Waterway value.');
    }

    /**
     * NEW-D2: Combined "Assessment & Tax Assessment:" compound label — Water View stops
     * at the first recognised word ("Assessment"), not at the full compound form.
     */
    public function test_inline_water_view_stops_at_compound_assessment_tax_assessment(): void
    {
        $data = $this->parse(
            'Water View: Intracoastal Water Assessment & Tax Assessment: $2,400 Tax Year: 2024'
        );

        $wv = $data['water_view'] ?? '';
        if (is_array($wv)) {
            $wv = implode(',', $wv);
        }
        $this->assertStringNotContainsStringIgnoringCase('Assessment', $wv,
            'Water View must stop at "Assessment" in the compound "Assessment & Tax Assessment:" label.');
        $this->assertStringContainsStringIgnoringCase('Intracoastal', $wv,
            'Water View must retain its actual value.');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Parse raw MLS text and return the data array. */
    private function parse(string $raw): array
    {
        $result = $this->service->import('', $raw);
        return $result['data'] ?? [];
    }

    /** Load a fixture file from tests/fixtures/mls/ and parse it. */
    private function parseFixture(string $filename): array
    {
        $path = base_path("tests/fixtures/mls/{$filename}");
        $this->assertFileExists($path, "Fixture file tests/fixtures/mls/{$filename} must exist.");
        $raw = file_get_contents($path);
        return $this->parse($raw);
    }
}
