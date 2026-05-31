<?php

namespace Tests\Unit\Services\Dna;

use App\Exceptions\Dna\MarketingReadinessException;
use App\Models\PropertyDnaProfile;
use App\Services\Ai\OpenAiClientService;
use App\Services\Dna\AiMarketingReportGeneratorService;
use App\Services\Dna\PropertyMarketingBriefService;
use App\Services\Dna\PropertyMarketingContextService;
use App\Services\Dna\PropertyMarketingReadinessService;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * AiMarketingReportGeneratorServiceTest
 *
 * Verifies the Phase XD AiMarketingReportGeneratorService in memory only.
 * No database connection is required — all dependencies are PHPUnit mock objects.
 * No real OpenAI API call is made at any point during this test run.
 *
 * Test coverage:
 *   (1) Readiness gate     — MarketingReadinessException thrown, send() never called.
 *   (2) Successful gen     — four-key result, attribution_verified true, generated_at set.
 *   (3) Missing top-level  — Exception thrown referencing the absent key name.
 *   (4) Missing section    — Exception thrown when a required section key is absent.
 *   (5) Attribution fail   — verifyAttribution() returns false; report not modified.
 *   (6) Empty draft        — verifyAttribution() returns true when draft_text is empty.
 *   (7) Payload shape      — send() receives exactly the five approved keys, no prohibited ones.
 */
class AiMarketingReportGeneratorServiceTest extends TestCase
{
    private PropertyMarketingContextService   $contextService;
    private PropertyMarketingBriefService     $briefService;
    private PropertyMarketingReadinessService $readinessService;
    private OpenAiClientService               $openAiClientService;
    private PropertyDnaProfile                $profile;
    private AiMarketingReportGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->instance('config', new ConfigRepository([
            'ai' => [
                'prompt_version' => 'test-v1',
                'api_key'        => 'test-key',
                'model'          => 'gpt-4',
                'max_retries'    => 3,
                'timeout_seconds'=> 30,
            ],
        ]));
        Container::setInstance($container);

        $this->contextService      = $this->createMock(PropertyMarketingContextService::class);
        $this->briefService        = $this->createMock(PropertyMarketingBriefService::class);
        $this->readinessService    = $this->createMock(PropertyMarketingReadinessService::class);
        $this->openAiClientService = $this->createMock(OpenAiClientService::class);
        $this->profile             = $this->createMock(PropertyDnaProfile::class);

        $this->service = new AiMarketingReportGeneratorService(
            $this->contextService,
            $this->briefService,
            $this->readinessService,
            $this->openAiClientService,
        );
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeReadyReadiness(): array
    {
        return [
            'is_marketing_ready' => true,
            'present_groups'     => ['Property Attributes', 'Transaction Details', 'Quantitative Data'],
            'missing_groups'     => [],
            'review_items'       => [],
            'summary'            => ['present_group_count' => 3, 'missing_group_count' => 0],
        ];
    }

    private function makeValidReport(): array
    {
        $section = ['draft_text' => 'Sample text.', 'source_attribution' => ['source-1']];

        return [
            'report_id'            => 'test-report-001',
            'generated_at'         => '2024-01-01T00:00:00+00:00',
            'listing_context'      => [],
            'readiness_snapshot'   => [],
            'sections'             => [
                'property_feature_narrative'  => $section,
                'transaction_terms_summary'   => $section,
                'marketing_asset_statement'   => $section,
                'missing_information_note'    => $section,
                'listing_preparation_summary' => $section,
            ],
            'generation_metadata'  => [],
            'attribution_verified' => true,
        ];
    }

    private function makeAiResult(array $report): array
    {
        return [
            'data'           => $report,
            'model'          => 'gpt-4',
            'prompt_version' => 'test-v1',
            'attempt_count'  => 1,
            'requested_at'   => '2024-01-01T00:00:00+00:00',
            'completed_at'   => '2024-01-01T00:00:01+00:00',
        ];
    }

    // =========================================================================
    // (1) Readiness gate
    // =========================================================================

    /** @test */
    public function it_throws_marketing_readiness_exception_when_profile_is_not_ready(): void
    {
        $this->readinessService
            ->expects($this->once())
            ->method('build')
            ->with($this->profile)
            ->willReturn([
                'is_marketing_ready' => false,
                'missing_groups'     => ['Property Attributes'],
            ]);

        $this->openAiClientService
            ->expects($this->never())
            ->method('send');

        $this->expectException(MarketingReadinessException::class);

        $this->service->generate($this->profile);
    }

    // =========================================================================
    // (2) Successful generation
    // =========================================================================

    /** @test */
    public function it_returns_four_key_result_with_attribution_and_timestamp_on_success(): void
    {
        $readiness   = $this->makeReadyReadiness();
        $validReport = $this->makeValidReport();

        $this->readinessService->method('build')->willReturn($readiness);
        $this->contextService->method('build')->willReturn([]);
        $this->briefService->method('build')->willReturn([]);
        $this->openAiClientService->method('send')->willReturn($this->makeAiResult($validReport));

        $result = $this->service->generate($this->profile);

        $this->assertArrayHasKey('report',               $result);
        $this->assertArrayHasKey('readiness',            $result);
        $this->assertArrayHasKey('attribution_verified', $result);
        $this->assertArrayHasKey('generated_at',         $result);

        $this->assertTrue($result['attribution_verified']);
        $this->assertIsString($result['generated_at']);
        $this->assertNotEmpty($result['generated_at']);
    }

    // =========================================================================
    // (3) Contract failure — missing top-level key
    // =========================================================================

    /** @test */
    public function it_throws_exception_referencing_missing_top_level_key(): void
    {
        $report = $this->makeValidReport();
        unset($report['report_id']);

        $this->readinessService->method('build')->willReturn($this->makeReadyReadiness());
        $this->contextService->method('build')->willReturn([]);
        $this->briefService->method('build')->willReturn([]);
        $this->openAiClientService->method('send')->willReturn($this->makeAiResult($report));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/report_id/');

        $this->service->generate($this->profile);
    }

    // =========================================================================
    // (4) Contract failure — missing section key
    // =========================================================================

    /** @test */
    public function it_throws_exception_when_a_required_section_key_is_absent(): void
    {
        $report = $this->makeValidReport();
        unset($report['sections']['property_feature_narrative']);

        $this->readinessService->method('build')->willReturn($this->makeReadyReadiness());
        $this->contextService->method('build')->willReturn([]);
        $this->briefService->method('build')->willReturn([]);
        $this->openAiClientService->method('send')->willReturn($this->makeAiResult($report));

        $this->expectException(\Exception::class);

        $this->service->generate($this->profile);
    }

    // =========================================================================
    // (5) Attribution failure — non-empty draft_text, empty source_attribution
    // =========================================================================

    /** @test */
    public function verify_attribution_returns_false_when_section_has_content_but_no_attribution(): void
    {
        $report = $this->makeValidReport();
        $report['sections']['property_feature_narrative'] = [
            'draft_text'         => 'This section has content.',
            'source_attribution' => [],
        ];

        $snapshot = $report;

        $result = $this->service->verifyAttribution($report);

        $this->assertFalse($result);
        $this->assertSame($snapshot, $report, 'verifyAttribution() must not modify the report array');
    }

    // =========================================================================
    // (6) Empty draft attribution — all draft_text values are empty
    // =========================================================================

    /** @test */
    public function verify_attribution_returns_true_when_all_sections_have_empty_draft_text(): void
    {
        $empty = ['draft_text' => '', 'source_attribution' => []];

        $report = [
            'report_id'            => 'test-report-002',
            'generated_at'         => '2024-01-01T00:00:00+00:00',
            'listing_context'      => [],
            'readiness_snapshot'   => [],
            'sections'             => [
                'property_feature_narrative'  => $empty,
                'transaction_terms_summary'   => $empty,
                'marketing_asset_statement'   => $empty,
                'missing_information_note'    => $empty,
                'listing_preparation_summary' => $empty,
            ],
            'generation_metadata'  => [],
            'attribution_verified' => false,
        ];

        $result = $this->service->verifyAttribution($report);

        $this->assertTrue($result);
    }

    // =========================================================================
    // (7) Payload shape — exactly the five approved keys, no prohibited keys
    // =========================================================================

    /** @test */
    public function it_passes_exactly_the_five_approved_keys_to_send_with_no_prohibited_keys(): void
    {
        $approvedKeys   = ['phase_p', 'phase_r', 'phase_u', 'required_contract', 'prompt_version'];
        $prohibitedKeys = ['demographic', 'race', 'religion', 'ethnicity', 'disability',
                           'family_status', 'income_tier', 'school_rating', 'credit_score',
                           'buyer_identity', 'tenant_identity'];

        $this->readinessService->method('build')->willReturn($this->makeReadyReadiness());
        $this->contextService->method('build')->willReturn(['attribute_context' => []]);
        $this->briefService->method('build')->willReturn(['property_attribute_context' => []]);

        $this->openAiClientService
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (array $payload) use ($approvedKeys, $prohibitedKeys) {
                foreach ($approvedKeys as $key) {
                    if (!array_key_exists($key, $payload)) {
                        return false;
                    }
                }
                foreach ($prohibitedKeys as $key) {
                    if (array_key_exists($key, $payload)) {
                        return false;
                    }
                }
                return count($payload) === count($approvedKeys);
            }))
            ->willReturn($this->makeAiResult($this->makeValidReport()));

        $this->service->generate($this->profile);
    }
}
