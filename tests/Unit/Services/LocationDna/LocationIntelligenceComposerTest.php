<?php

namespace Tests\Unit\Services\LocationDna;

use App\Services\LocationDna\LocationDnaEnrichmentRunner;
use App\Services\LocationDna\LocationIntelligenceComposer;
use App\Services\LocationDna\LocationIntelligenceSummaryService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * LocationIntelligenceComposerTest
 *
 * 7-case unit test for LocationIntelligenceComposer::compose().
 *
 *   (1) Full success — returns both enrichment and summary keys with data
 *   (2) Enrichment runner throws — empty enrichment + empty summary_lines
 *   (3) Summary service throws — enrichment present + empty summary_lines
 *   (4) Return array has exactly 'enrichment' and 'summary' keys and no others
 *   (5) No exception escapes to caller even when both services throw
 *   (6) Log::warning emitted on enrichment failure
 *   (7) Log::warning emitted on summary failure
 */
class LocationIntelligenceComposerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    private function boundaryData(): array
    {
        return ['geojson_polygons' => [], 'fallback' => false];
    }

    private function preferences(): array
    {
        return ['radius_searches' => []];
    }

    private function enrichmentPayload(): array
    {
        return [
            'floodZones'      => [['zone' => 'AE']],
            'schoolDistricts' => [['name' => 'Hillsborough County Schools']],
            'pois'            => [['label' => 'Park', 'name' => 'City Park']],
            'commuteTimes'    => [['destination' => 'Downtown', 'minutes' => 20]],
        ];
    }

    private function summaryPayload(): array
    {
        return [
            'summary_lines' => [
                'Flood Zone: AE',
                'School District: Hillsborough County Schools',
                'Nearby Park: City Park',
                'Downtown: 20 minutes',
            ],
        ];
    }

    private const EMPTY_ENRICHMENT = [
        'floodZones'      => [],
        'schoolDistricts' => [],
        'pois'            => [],
        'commuteTimes'    => [],
    ];

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function mockRunner(): MockInterface
    {
        return Mockery::mock(LocationDnaEnrichmentRunner::class);
    }

    private function mockSummary(): MockInterface
    {
        return Mockery::mock(LocationIntelligenceSummaryService::class);
    }

    private function makeComposer(MockInterface $runner, MockInterface $summary): LocationIntelligenceComposer
    {
        return new LocationIntelligenceComposer($runner, $summary);
    }

    // -------------------------------------------------------------------------
    // (1) Full success — both enrichment and summary keys contain data
    // -------------------------------------------------------------------------

    public function test_full_success_returns_enrichment_and_summary(): void
    {
        $runner  = $this->mockRunner();
        $summary = $this->mockSummary();

        $runner->shouldReceive('run')->once()
            ->with($this->boundaryData(), $this->preferences())
            ->andReturn($this->enrichmentPayload());

        $summary->shouldReceive('summarize')->once()
            ->with($this->enrichmentPayload())
            ->andReturn($this->summaryPayload());

        $result = $this->makeComposer($runner, $summary)
            ->compose($this->boundaryData(), $this->preferences());

        $this->assertSame($this->enrichmentPayload(), $result['enrichment']);
        $this->assertSame($this->summaryPayload(), $result['summary']);
        $this->assertNotEmpty($result['summary']['summary_lines']);
    }

    // -------------------------------------------------------------------------
    // (2) Enrichment runner throws → empty enrichment + empty summary_lines
    // -------------------------------------------------------------------------

    public function test_enrichment_failure_returns_empty_enrichment_and_empty_summary_lines(): void
    {
        Log::shouldReceive('warning')->once()->with(
            Mockery::pattern('/enrichment runner/'),
            Mockery::type('array')
        );

        $runner  = $this->mockRunner();
        $summary = $this->mockSummary();

        $runner->shouldReceive('run')->once()
            ->andThrow(new RuntimeException('runner exploded'));

        $summary->shouldReceive('summarize')->never();

        $result = $this->makeComposer($runner, $summary)
            ->compose($this->boundaryData(), $this->preferences());

        $this->assertSame(self::EMPTY_ENRICHMENT, $result['enrichment']);
        $this->assertSame(['summary_lines' => []], $result['summary']);
    }

    // -------------------------------------------------------------------------
    // (3) Summary service throws → enrichment present + empty summary_lines
    // -------------------------------------------------------------------------

    public function test_summary_failure_returns_enrichment_with_empty_summary_lines(): void
    {
        Log::shouldReceive('warning')->once()->with(
            Mockery::pattern('/summary service/'),
            Mockery::type('array')
        );

        $runner  = $this->mockRunner();
        $summary = $this->mockSummary();

        $runner->shouldReceive('run')->once()
            ->andReturn($this->enrichmentPayload());

        $summary->shouldReceive('summarize')->once()
            ->andThrow(new RuntimeException('summary exploded'));

        $result = $this->makeComposer($runner, $summary)
            ->compose($this->boundaryData(), $this->preferences());

        $this->assertSame($this->enrichmentPayload(), $result['enrichment']);
        $this->assertSame(['summary_lines' => []], $result['summary']);
    }

    // -------------------------------------------------------------------------
    // (4) Return array has exactly 'enrichment' and 'summary' keys and no others
    // -------------------------------------------------------------------------

    public function test_return_array_has_exactly_enrichment_and_summary_keys(): void
    {
        $runner  = $this->mockRunner();
        $summary = $this->mockSummary();

        $runner->shouldReceive('run')->once()->andReturn($this->enrichmentPayload());
        $summary->shouldReceive('summarize')->once()->andReturn($this->summaryPayload());

        $result = $this->makeComposer($runner, $summary)
            ->compose($this->boundaryData(), $this->preferences());

        $this->assertCount(2, $result, 'Result must contain exactly 2 keys');
        $this->assertArrayHasKey('enrichment', $result);
        $this->assertArrayHasKey('summary', $result);
    }

    // -------------------------------------------------------------------------
    // (5) No exception escapes even when both services throw
    // -------------------------------------------------------------------------

    public function test_no_exception_escapes_when_both_services_throw(): void
    {
        Log::shouldReceive('warning')->atLeast()->once();

        $runner  = $this->mockRunner();
        $summary = $this->mockSummary();

        $runner->shouldReceive('run')->once()
            ->andThrow(new RuntimeException('runner down'));

        $summary->shouldReceive('summarize')->never();

        $threw = false;
        try {
            $result = $this->makeComposer($runner, $summary)
                ->compose($this->boundaryData(), $this->preferences());
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertFalse($threw, 'No exception should escape compose()');
    }

    // -------------------------------------------------------------------------
    // (6) Log::warning emitted on enrichment failure
    // -------------------------------------------------------------------------

    public function test_warning_logged_on_enrichment_failure(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(
                Mockery::pattern('/enrichment runner/'),
                Mockery::type('array')
            );

        $runner  = $this->mockRunner();
        $summary = $this->mockSummary();

        $runner->shouldReceive('run')->once()
            ->andThrow(new RuntimeException('enrichment error'));

        $summary->shouldReceive('summarize')->never();

        $this->makeComposer($runner, $summary)
            ->compose($this->boundaryData(), $this->preferences());

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // (7) Log::warning emitted on summary failure
    // -------------------------------------------------------------------------

    public function test_warning_logged_on_summary_failure(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(
                Mockery::pattern('/summary service/'),
                Mockery::type('array')
            );

        $runner  = $this->mockRunner();
        $summary = $this->mockSummary();

        $runner->shouldReceive('run')->once()
            ->andReturn($this->enrichmentPayload());

        $summary->shouldReceive('summarize')->once()
            ->andThrow(new RuntimeException('summary error'));

        $this->makeComposer($runner, $summary)
            ->compose($this->boundaryData(), $this->preferences());

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Teardown
    // -------------------------------------------------------------------------

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
