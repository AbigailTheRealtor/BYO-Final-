<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationPreferenceAnalyzer;
use Tests\TestCase;

/**
 * LocationPreferenceAnalyzerTest — Phase 5A
 *
 * Covers all required scenarios:
 *   (1)  Flexible location → flexibility line
 *   (2)  Many cities (≥5) → broad breadth line
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
 *   (16) Deterministic line ordering (flexibility → breadth → geo targeting → polygon/radius → specificity)
 *   (17) Governance — no DB, Eloquent, or OpenAI imports in the service file
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
    // (2) Many cities (≥5) → broad breadth line
    // =========================================================================

    /** @test */
    public function it_generates_broad_line_for_five_or_more_cities(): void
    {
        $preferences = [
            'cities' => ['Tampa', 'St. Petersburg', 'Clearwater', 'Brandon', 'Wesley Chapel'],
        ];

        $result = $this->makeAnalyzer()->analyze($preferences);

        $this->assertSummaryShape($result);
        $this->assertContains('Broad geographic search area.', $result['summary_lines']);
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
    //       flexibility → breadth → geographic targeting → polygon/radius → specificity
    // =========================================================================

    /** @test */
    public function it_outputs_lines_in_standardized_order(): void
    {
        $preferences = [
            'flexible_location' => true,
            'cities'            => ['Tampa', 'St. Petersburg', 'Clearwater', 'Brandon', 'Wesley Chapel'],
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
        $geoIdx     = $this->indexOfLineContaining($lines, 'submarkets');
        $polyIdx    = $this->indexOfLineContaining($lines, 'target area');

        $this->assertNotNull($flexIdx, 'Flexibility line must be present');
        $this->assertNotNull($breadthIdx, 'Breadth line must be present');
        $this->assertNotNull($geoIdx, 'Geographic targeting line must be present');
        $this->assertNotNull($polyIdx, 'Polygon line must be present');

        $this->assertTrue($flexIdx < $breadthIdx, 'Flexibility must come before breadth');
        $this->assertTrue($breadthIdx < $geoIdx, 'Breadth must come before geographic targeting');
        $this->assertTrue($geoIdx < $polyIdx, 'Geographic targeting must come before polygon/radius');
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
