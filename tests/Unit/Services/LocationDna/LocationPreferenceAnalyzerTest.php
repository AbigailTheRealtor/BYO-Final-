<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationPreferenceAnalyzer;
use Tests\TestCase;

/**
 * LocationPreferenceAnalyzerTest — Phase 5B (updated from Phase 5A)
 *
 * Covers all required scenarios:
 *   (1)  Flexible location → flexibility line
 *   (2)  Many cities (≥5) → Phase 5B rule (c): broad suppressed, submarket present
 *   (3)  Many ZIP codes (≥6) → broad breadth line
 *   (4)  Many polygons (≥3) → broad breadth line
 *   (5)  Few ZIPs (1–2, no cities/polygons/radii) → highly targeted line
 *   (6)  Single city (no ZIPs/polygons/radii) → highly targeted line
 *   (7)  Single polygon → "Focused on a specifically defined target area."
 *   (8)  Multiple polygons → "Searching across several custom-defined target areas."
 *   (9)  Radius search present → radius insight line
 *   (10) Two or more cities → submarket-framing line
 *   (11) Cities-only preference type line
 *   (12) ZIP-only preference type line
 *   (13) Neighborhoods present → neighborhood preference type line
 *   (14) Mixed cities + ZIP codes → mixed preference type line
 *   (15) Empty preferences → empty summary_lines, no exception
 *   (16) Deterministic line ordering (flexibility → breadth → polygon/radius)
 *         Updated for Phase 5B: uses ZIPs for breadth so no combining rules fire.
 *   (17) Governance — no DB, Eloquent, or OpenAI imports in the service file
 *   (18) Phase 5B — combined flexibility + city list sentence (rule a)
 *   (19) Phase 5B — combined flexibility + radius sentence (rule b)
 *   (20) Phase 5B — broad + submarket deduplication: broad absent, submarket present (rule c)
 *   (21) Phase 5B — determinism: analyze() called twice with same input returns identical array
 *
 * No database, no factories, no HTTP calls — purely in-memory fixture arrays.
 */
class LocationPreferenceAnalyzerTest extends TestCase
{
    private function makeAnalyzer(): LocationPreferenceAnalyzer
    {
        return new LocationPreferenceAnalyzer();
    }

    private function assertSummaryShape(array $result): void
    {
        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary_lines', $result);
        $this->assertIsArray($result['summary_lines']);
    }

    // =========================================================================
    // (1) Flexible location → flexibility line
    // =========================================================================

    /** @test */
    public function it_generates_flexibility_line_when_flexible_location_is_true(): void
    {
        $preferences = ['flexible_location' => true];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains(
            'Open to multiple areas and willing to prioritize overall fit over a specific neighborhood.',
            $result['summary_lines'],
        );
    }

    /** @test */
    public function it_does_not_generate_flexibility_line_when_flexible_location_is_false(): void
    {
        $preferences = ['flexible_location' => false, 'cities' => ['Tampa']];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertNotContains(
            'Open to multiple areas and willing to prioritize overall fit over a specific neighborhood.',
            $result['summary_lines'],
        );
    }

    // =========================================================================
    // (2) Many cities (≥5) → Phase 5B rule (c): broad suppressed, submarket present
    // =========================================================================

    /** @test */
    public function it_suppresses_broad_line_and_shows_submarket_for_five_or_more_cities(): void
    {
        $preferences = [
            'cities' => ['Tampa', 'St. Petersburg', 'Clearwater', 'Brandon', 'Wesley Chapel'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        // Phase 5B rule (c): broad is suppressed when submarket fires simultaneously.
        $this->assertNotContains('Broad geographic search area.', $result['summary_lines']);
        // Submarket line is present (it is more informative).
        $submarketLine = $this->findLineContaining($result['summary_lines'], 'submarkets');
        $this->assertNotNull($submarketLine, 'Submarket line must be present for 5+ cities');
        $this->assertStringContainsString('Tampa', $submarketLine);
    }

    /** @test */
    public function it_does_not_generate_broad_line_for_fewer_than_five_cities(): void
    {
        $preferences = [
            'cities' => ['Tampa', 'St. Petersburg', 'Clearwater', 'Brandon'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertNotContains('Broad geographic search area.', $result['summary_lines']);
    }

    // =========================================================================
    // (3) Many ZIP codes (≥6) → broad breadth line
    // =========================================================================

    /** @test */
    public function it_generates_broad_line_for_six_or_more_zip_codes(): void
    {
        $preferences = [
            'zip_codes' => ['33601', '33602', '33603', '33604', '33605', '33606'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains('Broad geographic search area.', $result['summary_lines']);
    }

    /** @test */
    public function it_does_not_generate_broad_line_for_fewer_than_six_zip_codes(): void
    {
        $preferences = [
            'zip_codes' => ['33601', '33602', '33603', '33604', '33605'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertNotContains('Broad geographic search area.', $result['summary_lines']);
    }

    // =========================================================================
    // (4) Many polygons (≥3) → broad breadth line
    // =========================================================================

    /** @test */
    public function it_generates_broad_line_for_three_or_more_polygons(): void
    {
        $makePolygon = fn () => ['path' => [
            ['lat' => 27.9, 'lng' => -82.5],
            ['lat' => 27.8, 'lng' => -82.5],
            ['lat' => 27.8, 'lng' => -82.4],
        ]];

        $preferences = [
            'polygons' => [$makePolygon(), $makePolygon(), $makePolygon()],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains('Broad geographic search area.', $result['summary_lines']);
    }

    /** @test */
    public function it_does_not_generate_broad_line_for_fewer_than_three_polygons(): void
    {
        $makePolygon = fn () => ['path' => [
            ['lat' => 27.9, 'lng' => -82.5],
            ['lat' => 27.8, 'lng' => -82.5],
            ['lat' => 27.8, 'lng' => -82.4],
        ]];

        $preferences = [
            'polygons' => [$makePolygon(), $makePolygon()],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertNotContains('Broad geographic search area.', $result['summary_lines']);
    }

    // =========================================================================
    // (5) Few ZIPs (1–2, no cities/polygons/radii) → highly targeted line
    // =========================================================================

    /** @test */
    public function it_generates_highly_targeted_line_for_one_zip_code_only(): void
    {
        $preferences = ['zip_codes' => ['33601']];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains('Highly targeted location preferences.', $result['summary_lines']);
    }

    /** @test */
    public function it_generates_highly_targeted_line_for_two_zip_codes_only(): void
    {
        $preferences = ['zip_codes' => ['33601', '33602']];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains('Highly targeted location preferences.', $result['summary_lines']);
    }

    /** @test */
    public function it_does_not_generate_highly_targeted_line_when_polygons_also_present(): void
    {
        $preferences = [
            'zip_codes' => ['33601'],
            'polygons'  => [['path' => [
                ['lat' => 27.9, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.4],
            ]]],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertNotContains('Highly targeted location preferences.', $result['summary_lines']);
    }

    // =========================================================================
    // (6) Single city (no ZIPs/polygons/radii) → highly targeted line
    // =========================================================================

    /** @test */
    public function it_generates_highly_targeted_line_for_one_city_only(): void
    {
        $preferences = ['cities' => ['Tampa']];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains('Highly targeted location preferences.', $result['summary_lines']);
    }

    /** @test */
    public function it_does_not_generate_highly_targeted_line_when_radii_also_present(): void
    {
        $preferences = [
            'cities'         => ['Tampa'],
            'radius_searches' => [['center' => ['lat' => 27.9, 'lng' => -82.5], 'radius_miles' => 5]],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertNotContains('Highly targeted location preferences.', $result['summary_lines']);
    }

    // =========================================================================
    // (7) Single polygon → "Focused on a specifically defined target area."
    // =========================================================================

    /** @test */
    public function it_generates_focused_line_for_single_polygon(): void
    {
        $preferences = [
            'polygons' => [['path' => [
                ['lat' => 27.9, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.4],
            ]]],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains('Focused on a specifically defined target area.', $result['summary_lines']);
        $this->assertNotContains('Searching across several custom-defined target areas.', $result['summary_lines']);
    }

    // =========================================================================
    // (8) Multiple polygons → "Searching across several custom-defined target areas."
    // =========================================================================

    /** @test */
    public function it_generates_several_areas_line_for_multiple_polygons(): void
    {
        $makePolygon = fn () => ['path' => [
            ['lat' => 27.9, 'lng' => -82.5],
            ['lat' => 27.8, 'lng' => -82.5],
            ['lat' => 27.8, 'lng' => -82.4],
        ]];

        $preferences = [
            'polygons' => [$makePolygon(), $makePolygon()],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains('Searching across several custom-defined target areas.', $result['summary_lines']);
        $this->assertNotContains('Focused on a specifically defined target area.', $result['summary_lines']);
    }

    // =========================================================================
    // (9) Radius search present → radius insight line
    // =========================================================================

    /** @test */
    public function it_generates_radius_line_when_radius_searches_are_present(): void
    {
        $preferences = [
            'radius_searches' => [
                ['center' => ['lat' => 27.9, 'lng' => -82.5], 'radius_miles' => 5],
            ],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains(
            'Searching within a defined radius from a preferred location.',
            $result['summary_lines'],
        );
    }

    /** @test */
    public function it_does_not_generate_radius_line_when_radius_searches_is_empty(): void
    {
        $preferences = ['radius_searches' => []];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertNotContains(
            'Searching within a defined radius from a preferred location.',
            $result['summary_lines'],
        );
    }

    // =========================================================================
    // (10) Two or more cities → submarket-framing line
    // =========================================================================

    /** @test */
    public function it_generates_submarket_framing_line_for_two_cities(): void
    {
        $preferences = ['cities' => ['Tampa', 'St. Petersburg']];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $submarketLine = $this->findLineContaining($result['summary_lines'], 'submarkets');
        $this->assertNotNull($submarketLine, 'Expected a submarket-framing line');
        $this->assertStringContainsString('Tampa', $submarketLine);
        $this->assertStringContainsString('St. Petersburg', $submarketLine);
    }

    /** @test */
    public function it_generates_submarket_framing_line_for_three_cities_with_correct_format(): void
    {
        $preferences = ['cities' => ['Tampa', 'St. Petersburg', 'Clearwater']];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $submarketLine = $this->findLineContaining($result['summary_lines'], 'submarkets');
        $this->assertNotNull($submarketLine);
        $this->assertStringContainsString('Tampa, St. Petersburg and Clearwater', $submarketLine);
    }

    /** @test */
    public function it_does_not_generate_submarket_framing_line_for_single_city(): void
    {
        $preferences = ['cities' => ['Tampa']];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $submarketLine = $this->findLineContaining($result['summary_lines'], 'submarkets');
        $this->assertNull($submarketLine, 'Should not produce submarket line for a single city');
    }

    // =========================================================================
    // (11) Cities-only preference type line
    // =========================================================================

    /** @test */
    public function it_generates_cities_only_preference_type_line(): void
    {
        $preferences = ['cities' => ['Tampa', 'St. Petersburg']];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains('Preferences defined by city or municipality.', $result['summary_lines']);
    }

    /** @test */
    public function it_does_not_generate_cities_only_line_when_zips_also_present(): void
    {
        $preferences = ['cities' => ['Tampa'], 'zip_codes' => ['33601']];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertNotContains('Preferences defined by city or municipality.', $result['summary_lines']);
    }

    // =========================================================================
    // (12) ZIP-only preference type line
    // =========================================================================

    /** @test */
    public function it_generates_zip_only_preference_type_line(): void
    {
        $preferences = ['zip_codes' => ['33601', '33602', '33603']];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains('Preferences defined by ZIP code.', $result['summary_lines']);
    }

    /** @test */
    public function it_does_not_generate_zip_only_line_when_cities_also_present(): void
    {
        $preferences = ['cities' => ['Tampa'], 'zip_codes' => ['33601']];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertNotContains('Preferences defined by ZIP code.', $result['summary_lines']);
    }

    // =========================================================================
    // (13) Neighborhoods present → neighborhood preference type line
    // =========================================================================

    /** @test */
    public function it_generates_neighborhoods_line_when_neighborhoods_are_present(): void
    {
        $preferences = [
            'cities'        => ['Tampa'],
            'neighborhoods' => ['Hyde Park', 'Davis Islands'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains(
            'Preferences include specific neighborhoods or subdivisions.',
            $result['summary_lines'],
        );
    }

    /** @test */
    public function it_does_not_generate_neighborhoods_line_when_neighborhoods_is_empty(): void
    {
        $preferences = ['cities' => ['Tampa'], 'neighborhoods' => []];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertNotContains(
            'Preferences include specific neighborhoods or subdivisions.',
            $result['summary_lines'],
        );
    }

    // =========================================================================
    // (14) Mixed cities + ZIP codes → mixed preference type line
    // =========================================================================

    /** @test */
    public function it_generates_mixed_preference_type_line_when_both_cities_and_zips_present(): void
    {
        $preferences = [
            'cities'    => ['Tampa', 'Brandon'],
            'zip_codes' => ['33601', '33602'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains(
            'Mixed location preferences combining cities and ZIP codes.',
            $result['summary_lines'],
        );
    }

    // =========================================================================
    // (15) Empty preferences → empty summary_lines, no exception
    // =========================================================================

    /** @test */
    public function it_returns_empty_summary_lines_for_empty_preferences(): void
    {
        $result = $this->makeAnalyzer()->analyze([]);

        $this->assertSummaryShape($result);
        $this->assertEmpty($result['summary_lines']);
    }

    /** @test */
    public function it_returns_empty_summary_lines_when_all_arrays_are_empty(): void
    {
        $preferences = [
            'cities'          => [],
            'zip_codes'       => [],
            'polygons'        => [],
            'radius_searches' => [],
            'neighborhoods'   => [],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertEmpty($result['summary_lines']);
    }

    // =========================================================================
    // (16) Deterministic line ordering
    //       Updated for Phase 5B: uses ZIP codes for breadth and a polygon so that
    //       no combining rules fire (no cities, no radius alongside flexibility).
    //       Verified order: flexibility → breadth → polygon/radius
    // =========================================================================

    /** @test */
    public function it_outputs_lines_in_standardized_order(): void
    {
        // Use ZIPs for breadth and a single polygon.
        // flexible_location=true with no cities and no radius → standalone flex line fires,
        // no Phase-5B combining rules apply, and broad fires via ZIP threshold.
        $preferences = [
            'flexible_location' => true,
            'zip_codes'         => ['33601', '33602', '33603', '33604', '33605', '33606'],
            'polygons'          => [['path' => [
                ['lat' => 27.9, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.4],
            ]]],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);
        $lines  = $result['summary_lines'];

        $this->assertSummaryShape($result);
        $this->assertNotEmpty($lines);

        $flexIdx    = $this->indexOfLineContaining($lines, 'overall fit');
        $breadthIdx = $this->indexOfLineContaining($lines, 'Broad geographic');
        $polyIdx    = $this->indexOfLineContaining($lines, 'target area');

        $this->assertNotNull($flexIdx, 'Flexibility line must be present');
        $this->assertNotNull($breadthIdx, 'Breadth line must be present');
        $this->assertNotNull($polyIdx, 'Polygon line must be present');

        $this->assertTrue($flexIdx < $breadthIdx, 'Flexibility must come before breadth');
        $this->assertTrue($breadthIdx < $polyIdx, 'Breadth must come before polygon/radius');
    }

    /** @test */
    public function it_places_specificity_line_after_all_other_groups(): void
    {
        $preferences = ['zip_codes' => ['33601']];

        $result = $this->makeAnalyzer()->analyze($preferences);
        $lines  = $result['summary_lines'];

        $this->assertSummaryShape($result);

        $targetedIdx = $this->indexOfLineContaining($lines, 'Highly targeted');
        $prefTypeIdx = $this->indexOfLineContaining($lines, 'ZIP code');

        // Both density signal ("Highly targeted") and preference-type label ("ZIP code")
        // appear together — they express different dimensions of the same preference set.
        $this->assertNotNull($targetedIdx, 'Highly targeted density line must be present');
        $this->assertNotNull($prefTypeIdx, 'Preference-type label line must be present');

        // Density signal precedes the preference-type label within the specificity group.
        $this->assertTrue($targetedIdx < $prefTypeIdx, 'Density signal must come before preference-type label');

        // Both specificity lines are last in the overall output (no enrichment lines present).
        $this->assertSame(count($lines) - 1, $prefTypeIdx, 'Preference-type label must be the very last line');
    }

    /** @test */
    public function it_places_combined_submarket_line_before_polygon_radius_after_phase5b_merge(): void
    {
        // Rule (a) fires: flex + 3 cities → combined submarket+flexibility sentence.
        // The combined line absorbs both the flexibility and geographic-targeting slots,
        // so it must still appear BEFORE any polygon/radius lines in the output.
        $preferences = [
            'flexible_location' => true,
            'cities'            => ['Tampa', 'St. Petersburg', 'Clearwater'],
            'polygons'          => [['path' => [
                ['lat' => 27.9, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.4],
            ]]],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);
        $lines  = $result['summary_lines'];

        $this->assertSummaryShape($result);
        $this->assertNotEmpty($lines);

        $geoIdx  = $this->indexOfLineContaining($lines, 'submarkets');
        $polyIdx = $this->indexOfLineContaining($lines, 'target area');

        $this->assertNotNull($geoIdx, 'Combined submarket line must be present');
        $this->assertNotNull($polyIdx, 'Polygon line must be present');

        $this->assertTrue(
            $geoIdx < $polyIdx,
            'Geographic targeting (combined) must come before polygon/radius lines',
        );
    }

    /** @test */
    public function it_places_standalone_submarket_line_before_polygon_radius_without_combining(): void
    {
        // No flex → rule (a) does not fire; standalone submarket line emitted.
        // Must still appear before any polygon/radius lines.
        $preferences = [
            'cities'  => ['Tampa', 'St. Petersburg'],
            'polygons' => [['path' => [
                ['lat' => 27.9, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.4],
            ]]],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);
        $lines  = $result['summary_lines'];

        $this->assertSummaryShape($result);
        $this->assertNotEmpty($lines);

        $geoIdx  = $this->indexOfLineContaining($lines, 'submarkets');
        $polyIdx = $this->indexOfLineContaining($lines, 'target area');

        $this->assertNotNull($geoIdx, 'Standalone submarket line must be present');
        $this->assertNotNull($polyIdx, 'Polygon line must be present');

        $this->assertTrue(
            $geoIdx < $polyIdx,
            'Geographic targeting (standalone) must come before polygon/radius lines',
        );
    }

    /** @test */
    public function it_places_submarket_line_before_radius_line(): void
    {
        // Standalone submarket (no flex) + radius → submarket before radius.
        $preferences = [
            'cities'          => ['Tampa', 'St. Petersburg'],
            'radius_searches' => [
                ['center' => ['lat' => 27.9, 'lng' => -82.5], 'radius_miles' => 5],
            ],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);
        $lines  = $result['summary_lines'];

        $this->assertSummaryShape($result);

        $geoIdx    = $this->indexOfLineContaining($lines, 'submarkets');
        $radiusIdx = $this->indexOfLineContaining($lines, 'defined radius');

        $this->assertNotNull($geoIdx, 'Submarket line must be present');
        $this->assertNotNull($radiusIdx, 'Radius line must be present');

        $this->assertTrue(
            $geoIdx < $radiusIdx,
            'Geographic targeting must come before polygon/radius lines',
        );
    }

    // =========================================================================
    // (17) Governance — no DB, Eloquent, or OpenAI imports in the service file
    // =========================================================================

    /** @test */
    public function service_file_contains_no_db_eloquent_or_openai_imports(): void
    {
        $serviceFile = file_get_contents(
            base_path('app/Services/LocationDna/LocationPreferenceAnalyzer.php'),
        );

        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*[Oo]pen[Aa][Ii]/m',
            $serviceFile,
            'Must not import OpenAI classes',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*LifestyleScore/m',
            $serviceFile,
            'Must not import scoring classes',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+.*MarketingContext/m',
            $serviceFile,
            'Must not import marketing report classes',
        );
        $this->assertStringNotContainsString(
            'DB::',
            $serviceFile,
            'Must not make DB calls',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+Illuminate\\\\Database\\\\Eloquent/m',
            $serviceFile,
            'Must not import Eloquent',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^use\s+Illuminate\\\\Support\\\\Facades\\\\DB/m',
            $serviceFile,
            'Must not import DB facade',
        );
    }

    // =========================================================================
    // (18) Phase 5B — combined flexibility + city list sentence (rule a)
    // =========================================================================

    /** @test */
    public function it_combines_flexibility_and_multi_city_into_single_submarket_sentence(): void
    {
        $preferences = [
            'flexible_location' => true,
            'cities'            => ['Tampa', 'St. Petersburg', 'Clearwater'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);
        $lines  = $result['summary_lines'];

        $this->assertSummaryShape($result);

        // Combined sentence must be present and contain all three cities.
        $combined = $this->findLineContaining($lines, 'submarkets');
        $this->assertNotNull($combined, 'Combined submarket+flexibility line must be present');
        $this->assertStringContainsString('Tampa, St. Petersburg and Clearwater', $combined);
        $this->assertStringContainsString('overall fit', $combined);

        // Standalone flexibility line must NOT appear.
        $this->assertNotContains(
            'Open to multiple areas and willing to prioritize overall fit over a specific neighborhood.',
            $lines,
        );

        // Standalone submarket line must NOT appear.
        $this->assertNotContains(
            'Seeking opportunities across multiple submarkets including Tampa, St. Petersburg and Clearwater.',
            $lines,
        );
    }

    /** @test */
    public function it_produces_exactly_one_submarket_related_line_when_flex_and_multi_city_combine(): void
    {
        $preferences = [
            'flexible_location' => true,
            'cities'            => ['Tampa', 'St. Petersburg'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);
        $lines  = $result['summary_lines'];

        $this->assertSummaryShape($result);

        $submarketLines = array_filter($lines, fn ($l) => str_contains($l, 'submarkets'));
        $this->assertCount(1, $submarketLines, 'Exactly one submarket-related line must appear');
    }

    // =========================================================================
    // (19) Phase 5B — combined flexibility + radius sentence (rule b)
    // =========================================================================

    /** @test */
    public function it_combines_flexibility_and_radius_into_single_commute_distance_sentence(): void
    {
        $preferences = [
            'flexible_location' => true,
            'radius_searches'   => [
                ['center' => ['lat' => 27.9, 'lng' => -82.5], 'radius_miles' => 10],
            ],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);
        $lines  = $result['summary_lines'];

        $this->assertSummaryShape($result);

        // Combined sentence must be present.
        $this->assertContains(
            'Open to opportunities within commuting distance of preferred areas while remaining flexible on exact location.',
            $lines,
        );

        // Standalone flexibility line must NOT appear.
        $this->assertNotContains(
            'Open to multiple areas and willing to prioritize overall fit over a specific neighborhood.',
            $lines,
        );

        // Standalone radius line must NOT appear.
        $this->assertNotContains(
            'Searching within a defined radius from a preferred location.',
            $lines,
        );
    }

    /** @test */
    public function it_applies_rule_a_not_rule_b_when_both_cities_and_radius_accompany_flexibility(): void
    {
        // When flex + multi-city + radius all appear, rule (a) fires first and
        // consumes flexibility. Rule (b) must NOT fire because flex is already consumed.
        // The radius line appears separately (not consumed by rule b).
        $preferences = [
            'flexible_location' => true,
            'cities'            => ['Tampa', 'St. Petersburg'],
            'radius_searches'   => [
                ['center' => ['lat' => 27.9, 'lng' => -82.5], 'radius_miles' => 10],
            ],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);
        $lines  = $result['summary_lines'];

        $this->assertSummaryShape($result);

        // Rule (a) combined line is present.
        $combined = $this->findLineContaining($lines, 'submarkets');
        $this->assertNotNull($combined, 'Rule (a) combined line must be present');
        $this->assertStringContainsString('overall fit', $combined);

        // Rule (b) combined line must NOT appear (rule a consumed flexibility first).
        $this->assertNotContains(
            'Open to opportunities within commuting distance of preferred areas while remaining flexible on exact location.',
            $lines,
        );

        // Radius line appears independently (not consumed by rule b since rule b didn't fire).
        $this->assertContains(
            'Searching within a defined radius from a preferred location.',
            $lines,
        );
    }

    // =========================================================================
    // (20) Phase 5B — broad + submarket deduplication: broad absent, submarket present (rule c)
    // =========================================================================

    /** @test */
    public function it_suppresses_broad_line_when_submarket_fires_alongside_it(): void
    {
        // 5 cities triggers both broad (≥5) and submarket (≥2) simultaneously.
        $preferences = [
            'cities' => ['Tampa', 'St. Petersburg', 'Clearwater', 'Brandon', 'Wesley Chapel'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);
        $lines  = $result['summary_lines'];

        $this->assertSummaryShape($result);

        // Broad line must be absent (suppressed by rule c).
        $this->assertNotContains('Broad geographic search area.', $lines);

        // Submarket line must be present (it communicates the same concept and more).
        $submarketLine = $this->findLineContaining($lines, 'submarkets');
        $this->assertNotNull($submarketLine, 'Submarket line must be present');
        $this->assertStringContainsString('Tampa', $submarketLine);
        $this->assertStringContainsString('Wesley Chapel', $submarketLine);
    }

    /** @test */
    public function it_emits_broad_line_when_broadness_is_from_zips_not_cities(): void
    {
        // Broad via ZIPs only — no multi-city, so submarket never fires.
        // Rule (c) must NOT suppress the broad line in this case.
        $preferences = [
            'zip_codes' => ['33601', '33602', '33603', '33604', '33605', '33606'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains('Broad geographic search area.', $result['summary_lines']);
    }

    /** @test */
    public function it_suppresses_broad_line_even_when_submarket_is_consumed_by_combining_rule(): void
    {
        // flex + 5 cities → rule (a) fires: combined flex+submarket sentence absorbs
        // both signals. Even though the standalone submarket line never appears,
        // the combined sentence already communicates "multiple submarkets," so broad
        // remains redundant and must still be suppressed by rule (c).
        $preferences = [
            'flexible_location' => true,
            'cities'            => ['Tampa', 'St. Petersburg', 'Clearwater', 'Brandon', 'Wesley Chapel'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);
        $lines  = $result['summary_lines'];

        $this->assertSummaryShape($result);

        // Combined submarket+flexibility line is present.
        $combined = $this->findLineContaining($lines, 'submarkets');
        $this->assertNotNull($combined, 'Combined submarket line must be present');
        $this->assertStringContainsString('overall fit', $combined);

        // Broad must NOT appear — the combined sentence already communicates the same breadth.
        $this->assertNotContains('Broad geographic search area.', $lines);
    }

    // =========================================================================
    // (21) Phase 5B — determinism: analyze() returns identical output on repeated calls
    // =========================================================================

    /** @test */
    public function it_produces_identical_output_on_repeated_calls_with_the_same_input(): void
    {
        $preferences = [
            'flexible_location' => true,
            'cities'            => ['Tampa', 'St. Petersburg', 'Clearwater'],
            'zip_codes'         => ['33601', '33602'],
            'neighborhoods'     => [],
            'polygons'          => [['path' => [
                ['lat' => 27.9, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.5],
                ['lat' => 27.8, 'lng' => -82.4],
            ]]],
            'radius_searches'   => [],
        ];

        $analyzer = $this->makeAnalyzer();

        $first  = $analyzer->analyze($preferences);
        $second = $analyzer->analyze($preferences);

        $this->assertSame($first, $second, 'analyze() must be deterministic: identical input must yield identical output');
    }

    /** @test */
    public function it_produces_identical_output_for_empty_preferences_on_repeated_calls(): void
    {
        $analyzer = $this->makeAnalyzer();

        $first  = $analyzer->analyze([]);
        $second = $analyzer->analyze([]);

        $this->assertSame($first, $second);
        $this->assertEmpty($first['summary_lines']);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function findLineContaining(array $lines, string $needle): ?string
    {
        foreach ($lines as $line) {
            if (str_contains($line, $needle)) {
                return $line;
            }
        }

        return null;
    }

    private function indexOfLineContaining(array $lines, string $needle): ?int
    {
        foreach ($lines as $index => $line) {
            if (str_contains($line, $needle)) {
                return $index;
            }
        }

        return null;
    }
}
