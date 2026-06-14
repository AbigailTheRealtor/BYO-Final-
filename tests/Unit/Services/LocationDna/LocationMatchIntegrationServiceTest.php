<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationMatchEngine;
use App\Services\LocationDna\LocationMatchInsightService;
use App\Services\LocationDna\LocationMatchIntegrationService;
use PHPUnit\Framework\TestCase;

/**
 * LocationMatchIntegrationServiceTest — Phase 6C
 *
 * Verifies that LocationMatchIntegrationService correctly sequences
 * LocationMatchEngine (6A) → LocationMatchInsightService (6B) and
 * returns both the raw match results and the insight strings.
 *
 * Null/empty input handling and presentation fallbacks are Phase 6D concerns
 * and are not tested here.
 *
 * Coverage:
 *   (1)  Delegation — calls engine once with the provided inputs
 *   (2)  Delegation — passes engine output to insight service
 *   (3)  Output shape — always returns both match_results and insights keys
 *   (4)  Pass-through — match_results contains the raw engine output intact
 *   (5)  Pass-through — insights contains the string[] from insight service
 *   (6)  Fixture A — strong match (city + zip + polygon): strong header + signals + footer
 *   (7)  Fixture B — multi-signal (city + zip): footer present, no strong header
 *   (8)  Fixture C — single signal (radius only): exactly one radius insight
 *   (9)  Fixture D — no match: no-overlap fallback only
 *   (10) Governance — source file contains no DB, Eloquent, or OpenAI imports
 */
class LocationMatchIntegrationServiceTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeService(
        ?LocationMatchEngine         $engine = null,
        ?LocationMatchInsightService $insightService = null,
    ): LocationMatchIntegrationService {
        return new LocationMatchIntegrationService(
            $engine         ?? $this->createMock(LocationMatchEngine::class),
            $insightService ?? $this->createMock(LocationMatchInsightService::class),
        );
    }

    private function realService(): LocationMatchIntegrationService
    {
        return new LocationMatchIntegrationService(
            new LocationMatchEngine(),
            new LocationMatchInsightService(),
        );
    }

    private function samplePreferences(): array
    {
        return [
            'cities'    => ['Tampa'],
            'zip_codes' => ['33601'],
        ];
    }

    private function samplePropertyData(): array
    {
        return [
            'city' => 'Tampa',
            'zip'  => '33601',
        ];
    }

    private function sampleEngineResult(): array
    {
        return [
            'matched_cities'        => ['Tampa'],
            'city_match'            => true,
            'matched_zips'          => ['33601'],
            'zip_match'             => true,
            'matched_neighborhoods' => [],
            'polygon_match'         => false,
            'matched_polygon_count' => 0,
            'radius_match'          => false,
            'matched_radius_count'  => 0,
            'overlap_signals'       => ['city', 'zip'],
        ];
    }

    // =========================================================================
    // (1) Delegation — engine receives correct inputs
    // =========================================================================

    /** @test */
    public function it_calls_engine_once_with_provided_preferences_and_property_data(): void
    {
        $preferences  = $this->samplePreferences();
        $propertyData = $this->samplePropertyData();

        $engine = $this->createMock(LocationMatchEngine::class);
        $engine->expects($this->once())
            ->method('match')
            ->with($preferences, $propertyData)
            ->willReturn($this->sampleEngineResult());

        $insightService = $this->createMock(LocationMatchInsightService::class);
        $insightService->method('buildInsights')->willReturn(['insights' => []]);

        $this->makeService($engine, $insightService)->build($preferences, $propertyData);
    }

    // =========================================================================
    // (2) Delegation — insight service receives engine output
    // =========================================================================

    /** @test */
    public function it_passes_engine_output_to_insight_service(): void
    {
        $engineResult = $this->sampleEngineResult();

        $engine = $this->createMock(LocationMatchEngine::class);
        $engine->method('match')->willReturn($engineResult);

        $insightService = $this->createMock(LocationMatchInsightService::class);
        $insightService->expects($this->once())
            ->method('buildInsights')
            ->with($engineResult)
            ->willReturn(['insights' => []]);

        $this->makeService($engine, $insightService)
            ->build($this->samplePreferences(), $this->samplePropertyData());
    }

    // =========================================================================
    // (3) Output shape — both keys always present
    // =========================================================================

    /** @test */
    public function output_always_has_match_results_and_insights_keys(): void
    {
        $result = $this->realService()->build(
            $this->samplePreferences(),
            $this->samplePropertyData(),
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('match_results', $result);
        $this->assertArrayHasKey('insights', $result);
        $this->assertIsArray($result['match_results']);
        $this->assertIsArray($result['insights']);
    }

    // =========================================================================
    // (4) Pass-through — match_results is the raw engine output
    // =========================================================================

    /** @test */
    public function match_results_contains_raw_engine_output_intact(): void
    {
        $engineResult = $this->sampleEngineResult();

        $engine = $this->createMock(LocationMatchEngine::class);
        $engine->method('match')->willReturn($engineResult);

        $insightService = $this->createMock(LocationMatchInsightService::class);
        $insightService->method('buildInsights')->willReturn(['insights' => []]);

        $result = $this->makeService($engine, $insightService)
            ->build($this->samplePreferences(), $this->samplePropertyData());

        $this->assertSame($engineResult, $result['match_results']);
    }

    // =========================================================================
    // (5) Pass-through — insights contains the string[] from insight service
    // =========================================================================

    /** @test */
    public function insights_contains_string_array_from_insight_service(): void
    {
        $expectedInsights = ['Strong location match.', 'Property aligns with a preferred city.'];

        $engine = $this->createMock(LocationMatchEngine::class);
        $engine->method('match')->willReturn($this->sampleEngineResult());

        $insightService = $this->createMock(LocationMatchInsightService::class);
        $insightService->method('buildInsights')->willReturn(['insights' => $expectedInsights]);

        $result = $this->makeService($engine, $insightService)
            ->build($this->samplePreferences(), $this->samplePropertyData());

        $this->assertSame($expectedInsights, $result['insights']);
    }

    // =========================================================================
    // (6) Fixture A — Strong match (city + zip + polygon, 3+ signals)
    // =========================================================================

    /** @test */
    public function fixture_a_strong_match_produces_strong_header_signals_and_footer(): void
    {
        $result = $this->realService()->build(
            [
                'cities'    => ['Tampa'],
                'zip_codes' => ['33601'],
                'polygons'  => [[
                    'path' => [
                        ['lat' => 27.9, 'lng' => -82.5],
                        ['lat' => 28.0, 'lng' => -82.5],
                        ['lat' => 28.0, 'lng' => -82.4],
                        ['lat' => 27.9, 'lng' => -82.4],
                    ],
                ]],
            ],
            ['city' => 'Tampa', 'zip' => '33601', 'lat' => 27.95, 'lng' => -82.45],
        );

        $insights = $result['insights'];
        $this->assertSame('Strong location match.', $insights[0]);
        $this->assertContains('Property aligns with a preferred city.', $insights);
        $this->assertContains('Property aligns with a preferred ZIP code.', $insights);
        $this->assertContains('Property falls within a preferred search area.', $insights);
        $this->assertSame('Multiple location preference signals align.', end($insights));
        $this->assertTrue($result['match_results']['city_match']);
        $this->assertTrue($result['match_results']['zip_match']);
        $this->assertTrue($result['match_results']['polygon_match']);
    }

    // =========================================================================
    // (7) Fixture B — Multi-signal (city + zip, exactly 2 signals)
    // =========================================================================

    /** @test */
    public function fixture_b_two_signals_has_footer_but_no_strong_header(): void
    {
        $result = $this->realService()->build(
            ['cities' => ['Orlando'], 'zip_codes' => ['32801']],
            ['city' => 'Orlando', 'zip' => '32801'],
        );

        $insights = $result['insights'];
        $this->assertContains('Property aligns with a preferred city.', $insights);
        $this->assertContains('Property aligns with a preferred ZIP code.', $insights);
        $this->assertContains('Multiple location preference signals align.', $insights);
        $this->assertNotContains('Strong location match.', $insights);
        $this->assertSame(['city', 'zip'], $result['match_results']['overlap_signals']);
    }

    // =========================================================================
    // (8) Fixture C — Single signal (radius only)
    // =========================================================================

    /** @test */
    public function fixture_c_single_radius_signal_produces_exactly_one_insight(): void
    {
        $result = $this->realService()->build(
            ['radius_searches' => [[
                'center'       => ['lat' => 27.9506, 'lng' => -82.4572],
                'radius_miles' => 5.0,
            ]]],
            ['lat' => 27.9506, 'lng' => -82.4572],
        );

        $this->assertCount(1, $result['insights']);
        $this->assertContains('Property falls within a preferred search radius.', $result['insights']);
        $this->assertTrue($result['match_results']['radius_match']);
    }

    // =========================================================================
    // (9) Fixture D — No match
    // =========================================================================

    /** @test */
    public function fixture_d_no_match_produces_no_overlap_fallback(): void
    {
        $result = $this->realService()->build(
            ['cities' => ['Tampa'], 'zip_codes' => ['33601']],
            ['city' => 'Miami', 'zip' => '33101'],
        );

        $this->assertSame(['No direct location preference overlap detected.'], $result['insights']);
        $this->assertSame([], $result['match_results']['overlap_signals']);
    }

    // =========================================================================
    // (10) Governance
    // =========================================================================

    /** @test */
    public function governance_source_file_contains_no_db_eloquent_or_openai_imports(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../../app/Services/LocationDna/LocationMatchIntegrationService.php'
        );

        foreach ([
            'use Illuminate\\Database\\Eloquent' => 'Eloquent import',
            'use Illuminate\\Support\\Facades\\DB' => 'DB facade import',
            'use OpenAI\\'                         => 'OpenAI import',
            'DB::'                                 => 'DB facade call',
            'Http::'                               => 'Http facade call',
        ] as $pattern => $label) {
            $this->assertStringNotContainsString($pattern, $source,
                "Service must not contain: {$label}");
        }
    }
}
