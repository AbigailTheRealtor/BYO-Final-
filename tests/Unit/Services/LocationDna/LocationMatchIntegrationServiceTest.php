<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationMatchEngine;
use App\Services\LocationDna\LocationMatchInsightService;
use App\Services\LocationDna\LocationMatchIntegrationService;
use PHPUnit\Framework\TestCase;

class LocationMatchIntegrationServiceTest extends TestCase
{
    private function makeService(
        ?LocationMatchEngine        $engine = null,
        ?LocationMatchInsightService $insightService = null,
    ): LocationMatchIntegrationService {
        return new LocationMatchIntegrationService(
            $engine        ?? $this->createMock(LocationMatchEngine::class),
            $insightService ?? $this->createMock(LocationMatchInsightService::class),
        );
    }

    private function samplePreferences(): array
    {
        return [
            'cities'          => ['Orlando'],
            'zip_codes'       => ['32801'],
            'neighborhoods'   => ['Lake Eola Heights'],
            'polygons'        => [],
            'radius_searches' => [],
        ];
    }

    private function samplePropertyData(): array
    {
        return [
            'city'         => 'Orlando',
            'zip'          => '32801',
            'neighborhood' => 'Lake Eola Heights',
            'lat'          => 28.5495,
            'lng'          => -81.3774,
        ];
    }

    private function sampleEngineResult(): array
    {
        return [
            'matched_cities'        => ['Orlando'],
            'city_match'            => true,
            'matched_zips'          => ['32801'],
            'zip_match'             => true,
            'matched_neighborhoods' => ['Lake Eola Heights'],
            'polygon_match'         => false,
            'matched_polygon_count' => 0,
            'radius_match'          => false,
            'matched_radius_count'  => 0,
            'overlap_signals'       => ['city', 'zip', 'neighborhood'],
        ];
    }

    // -------------------------------------------------------------------------
    // Successful full-data integration
    // -------------------------------------------------------------------------

    public function test_build_returns_match_results_and_insights_keys(): void
    {
        $engine = $this->createMock(LocationMatchEngine::class);
        $engine->method('match')->willReturn($this->sampleEngineResult());

        $insightService = $this->createMock(LocationMatchInsightService::class);
        $insightService->method('buildInsights')->willReturn([
            'insights' => ['Strong location match.', 'Property aligns with a preferred city.'],
        ]);

        $service = $this->makeService($engine, $insightService);
        $result  = $service->build($this->samplePreferences(), $this->samplePropertyData());

        $this->assertArrayHasKey('match_results', $result);
        $this->assertArrayHasKey('insights', $result);
    }

    public function test_build_calls_engine_with_provided_preferences_and_property_data(): void
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

    public function test_build_calls_insight_service_with_engine_output(): void
    {
        $engineResult = $this->sampleEngineResult();

        $engine = $this->createMock(LocationMatchEngine::class);
        $engine->method('match')->willReturn($engineResult);

        $insightService = $this->createMock(LocationMatchInsightService::class);
        $insightService->expects($this->once())
            ->method('buildInsights')
            ->with($engineResult)
            ->willReturn(['insights' => []]);

        $this->makeService($engine, $insightService)->build($this->samplePreferences(), $this->samplePropertyData());
    }

    // -------------------------------------------------------------------------
    // Engine output passthrough
    // -------------------------------------------------------------------------

    public function test_engine_output_appears_under_match_results_key_intact(): void
    {
        $engineResult = $this->sampleEngineResult();

        $engine = $this->createMock(LocationMatchEngine::class);
        $engine->method('match')->willReturn($engineResult);

        $insightService = $this->createMock(LocationMatchInsightService::class);
        $insightService->method('buildInsights')->willReturn(['insights' => []]);

        $result = $this->makeService($engine, $insightService)->build($this->samplePreferences(), $this->samplePropertyData());

        $this->assertSame($engineResult, $result['match_results']);
    }

    // -------------------------------------------------------------------------
    // Insight output passthrough
    // -------------------------------------------------------------------------

    public function test_insight_strings_appear_under_insights_key(): void
    {
        $expectedInsights = [
            'Strong location match.',
            'Property aligns with a preferred city.',
            'Property aligns with a preferred ZIP code.',
            'Multiple location preference signals align.',
        ];

        $engine = $this->createMock(LocationMatchEngine::class);
        $engine->method('match')->willReturn($this->sampleEngineResult());

        $insightService = $this->createMock(LocationMatchInsightService::class);
        $insightService->method('buildInsights')->willReturn(['insights' => $expectedInsights]);

        $result = $this->makeService($engine, $insightService)->build($this->samplePreferences(), $this->samplePropertyData());

        $this->assertSame($expectedInsights, $result['insights']);
    }

    // -------------------------------------------------------------------------
    // Missing preferences — empty-state
    // -------------------------------------------------------------------------

    public function test_empty_preferences_returns_empty_state_without_calling_services(): void
    {
        $engine = $this->createMock(LocationMatchEngine::class);
        $engine->expects($this->never())->method('match');

        $insightService = $this->createMock(LocationMatchInsightService::class);
        $insightService->expects($this->never())->method('buildInsights');

        $result = $this->makeService($engine, $insightService)->build([], $this->samplePropertyData());

        $this->assertSame([], $result['match_results']);
        $this->assertSame([], $result['insights']);
    }

    // -------------------------------------------------------------------------
    // Missing property data — empty-state
    // -------------------------------------------------------------------------

    public function test_empty_property_data_returns_empty_state_without_calling_services(): void
    {
        $engine = $this->createMock(LocationMatchEngine::class);
        $engine->expects($this->never())->method('match');

        $insightService = $this->createMock(LocationMatchInsightService::class);
        $insightService->expects($this->never())->method('buildInsights');

        $result = $this->makeService($engine, $insightService)->build($this->samplePreferences(), []);

        $this->assertSame([], $result['match_results']);
        $this->assertSame([], $result['insights']);
    }

    public function test_both_empty_returns_empty_state(): void
    {
        $result = $this->makeService()->build([], []);

        $this->assertSame([], $result['match_results']);
        $this->assertSame([], $result['insights']);
    }

    // -------------------------------------------------------------------------
    // Governance — no direct DB or HTTP calls
    // -------------------------------------------------------------------------

    public function test_service_has_no_db_or_http_imports(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/app/Services/LocationDna/LocationMatchIntegrationService.php'
        );

        $this->assertStringNotContainsString('use Illuminate\\Support\\Facades\\DB', $source, 'Must not import DB facade');
        $this->assertStringNotContainsString('use Illuminate\\Support\\Facades\\Http', $source, 'Must not import Http facade');
        $this->assertStringNotContainsString('use Illuminate\\Database\\Eloquent', $source, 'Must not import Eloquent');
        $this->assertStringNotContainsString('use OpenAI\\', $source, 'Must not import OpenAI client');
        $this->assertDoesNotMatchRegularExpression('/(?<!\/\/)(?<!\*)\s+DB::/', $source, 'Must not call DB facade outside comments');
        $this->assertDoesNotMatchRegularExpression('/(?<!\/\/)(?<!\*)\s+Http::/', $source, 'Must not call Http facade outside comments');
        $this->assertStringNotContainsString('curl_init', $source, 'Must not make cURL calls');
        $this->assertStringNotContainsString('curl_exec', $source, 'Must not make cURL calls');
    }

    public function test_service_only_declares_two_constructor_collaborators(): void
    {
        $reflection  = new \ReflectionClass(LocationMatchIntegrationService::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();
        $this->assertCount(2, $params, 'Constructor must have exactly two parameters');

        $types = array_map(
            fn ($p) => (string) $p->getType(),
            $params
        );

        $this->assertContains(LocationMatchEngine::class, $types);
        $this->assertContains(LocationMatchInsightService::class, $types);
    }
}
