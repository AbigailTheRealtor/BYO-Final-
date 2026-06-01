<?php

namespace Tests\Feature;

use App\Exceptions\Dna\MarketingReadinessException;
use App\Models\PropertyDnaProfile;
use App\Models\User;
use App\Services\Dna\AiMarketingReportOrchestratorService;
use App\Services\Dna\AiMarketingReportPersistenceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the admin marketing report generation trigger (Phase XQ).
 *
 * Covers:
 *  §1 — Admin with a ready profile: three DB rows created and redirect to the show page.
 *  §2 — Non-admin user: redirected away by adminAuth middleware.
 *  §3 — Not-ready profile: no DB rows written, redirected back to brief preview.
 *  §4 — Persistence gate failure (blocked): no DB rows written, redirected back to brief preview.
 *
 * §1 uses the real AiMarketingReportPersistenceService so DB row creation can be verified.
 * §1 mocks only the AiMarketingReportOrchestratorService to avoid real OpenAI calls.
 * §3 and §4 mock the orchestrator to trigger each error path without any DB writes.
 */
class AiMarketingReportGenerateAdminTest extends TestCase
{
    use DatabaseTransactions;

    private const ROUTE_NAME = 'admin.property-dna.marketing-reports.generate';

    private const REQUIRED_SECTION_KEYS = [
        'property_feature_narrative',
        'transaction_terms_summary',
        'marketing_asset_statement',
        'missing_information_note',
        'listing_preparation_summary',
    ];

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAdmin(): User
    {
        return User::factory()->create(['user_type' => 'admin']);
    }

    private function makeNonAdmin(): User
    {
        return User::factory()->create(['user_type' => 'seller']);
    }

    private function makeProfile(): array
    {
        $profileId = DB::table('property_dna_profiles')->insertGetId([
            'listing_type'              => 'seller',
            'listing_id'                => 99,
            'version'                   => 1,
            'source_listing_updated_at' => now()->toDateTimeString(),
            'computed_at'               => now()->toDateTimeString(),
            'created_at'                => now()->toDateTimeString(),
            'updated_at'                => now()->toDateTimeString(),
        ]);

        return ['id' => $profileId];
    }

    private function makeValidOrchestrationResult(string $reportId, int $profileId): array
    {
        $section = [
            'draft_text'         => 'Sample section text for marketing report.',
            'source_attribution' => ['source-ref-1'],
            'status'             => 'pending_review',
        ];

        return [
            'orchestration_status' => 'ready_for_agent_review',
            'review'               => ['passed' => true],
            'generation'           => [
                'report' => [
                    'report_id'           => $reportId,
                    'listing_context'     => ['listing_id' => 99],
                    'generated_at'        => '2026-06-01T12:00:00+00:00',
                    'generation_metadata' => [
                        'ai_model'                  => 'gpt-4o',
                        'prompt_template_version'   => 'phase-xd-v1',
                        'phase_r_brief_version'     => 'phase-r-v1',
                        'phase_u_readiness_version' => 'phase-u-v1',
                        'phase_r_brief_snapshot'    => [],
                    ],
                    'readiness_snapshot'  => ['is_marketing_ready' => true],
                    'sections'            => [
                        'property_feature_narrative'  => $section,
                        'transaction_terms_summary'   => $section,
                        'marketing_asset_statement'   => $section,
                        'missing_information_note'    => $section,
                        'listing_preparation_summary' => $section,
                    ],
                    'attribution_verified' => true,
                ],
                'readiness' => ['is_marketing_ready' => true],
            ],
            'generated_at' => '2026-06-01T12:00:00+00:00',
        ];
    }

    private function assertNoMarketingRowsForReport(string $reportId): void
    {
        $this->assertSame(
            0,
            DB::table('marketing_reports')->where('id', $reportId)->count(),
            "marketing_reports must have 0 rows for report_id={$reportId}"
        );
        $this->assertSame(
            0,
            DB::table('marketing_report_versions')->where('marketing_report_id', $reportId)->count(),
            "marketing_report_versions must have 0 rows for marketing_report_id={$reportId}"
        );
        $this->assertSame(
            0,
            DB::table('marketing_report_audits')->where('report_id', $reportId)->count(),
            "marketing_report_audits must have 0 rows for report_id={$reportId}"
        );
    }

    // -------------------------------------------------------------------------
    // §1 — Admin generates report for a ready profile
    //
    // Uses a mock orchestrator (no OpenAI call) and the real persistence service.
    // Asserts all three DB tables receive one row each, then redirects to show page.
    // -------------------------------------------------------------------------

    public function test_admin_can_generate_report_for_ready_profile(): void
    {
        $admin    = $this->makeAdmin();
        $profile  = $this->makeProfile();
        $reportId = Str::uuid()->toString();

        $orchestrationResult = $this->makeValidOrchestrationResult($reportId, $profile['id']);

        $mockOrchestrator = $this->mock(AiMarketingReportOrchestratorService::class);
        $mockOrchestrator->shouldReceive('run')
            ->once()
            ->andReturn($orchestrationResult);

        $response = $this->actingAs($admin)
            ->post(route(self::ROUTE_NAME, $profile['id']));

        $response->assertRedirect(route('admin.property-dna.marketing-reports.show', $reportId));

        $this->assertSame(
            1,
            DB::table('marketing_reports')->where('id', $reportId)->count(),
            'Expected one marketing_reports row to be created'
        );

        $this->assertSame(
            count(self::REQUIRED_SECTION_KEYS),
            DB::table('marketing_report_versions')->where('marketing_report_id', $reportId)->count(),
            'Expected five marketing_report_versions rows (one per section key)'
        );

        $this->assertSame(
            1,
            DB::table('marketing_report_audits')->where('report_id', $reportId)->count(),
            'Expected one marketing_report_audits row to be created'
        );
    }

    // -------------------------------------------------------------------------
    // §2 — Non-admin cannot access the generate route
    //
    // The adminAuth middleware redirects non-admin users to the dashboard.
    // -------------------------------------------------------------------------

    public function test_non_admin_cannot_access_generate_route(): void
    {
        $nonAdmin = $this->makeNonAdmin();
        $profile  = $this->makeProfile();

        $response = $this->actingAs($nonAdmin)
            ->post(route(self::ROUTE_NAME, $profile['id']));

        $response->assertRedirect(route('dashboard'));
    }

    // -------------------------------------------------------------------------
    // §3 — Not-ready profile: no DB rows created, redirected to brief preview
    //
    // Orchestrator throws MarketingReadinessException (profile fails readiness gate).
    // No DB rows are written. Admin is redirected back to the brief preview page.
    // -------------------------------------------------------------------------

    public function test_not_ready_profile_creates_no_db_rows_and_redirects_to_brief_preview(): void
    {
        $admin    = $this->makeAdmin();
        $profile  = $this->makeProfile();
        $reportId = Str::uuid()->toString();

        $readinessSnapshot = [
            'is_marketing_ready' => false,
            'missing_groups'     => ['Property Attributes', 'Quantitative Data'],
            'present_groups'     => ['Transaction Details'],
            'review_items'       => [],
            'summary'            => ['present_group_count' => 1, 'missing_group_count' => 2],
        ];

        $mockOrchestrator = $this->mock(AiMarketingReportOrchestratorService::class);
        $mockOrchestrator->shouldReceive('run')
            ->once()
            ->andThrow(new MarketingReadinessException(
                'Profile is not marketing-ready.',
                $readinessSnapshot
            ));

        $this->mock(AiMarketingReportPersistenceService::class)
            ->shouldNotReceive('persist');

        $response = $this->actingAs($admin)
            ->post(route(self::ROUTE_NAME, $profile['id']));

        $response->assertRedirect(
            route('admin.property-dna.marketing-brief-preview', $profile['id'])
        );

        $this->assertNoMarketingRowsForReport($reportId);
    }

    // -------------------------------------------------------------------------
    // §4 — Persistence gate failure: no DB rows created, redirected to brief preview
    //
    // Orchestrator returns a 'blocked' status. The real AiMarketingReportPersistenceService
    // gate check throws an Exception. No rows are created. Admin is redirected back.
    // -------------------------------------------------------------------------

    public function test_persistence_gate_failure_creates_no_db_rows_and_redirects_to_brief_preview(): void
    {
        $admin    = $this->makeAdmin();
        $profile  = $this->makeProfile();
        $reportId = Str::uuid()->toString();

        $blockedResult = [
            'orchestration_status' => 'blocked',
            'review'               => ['passed' => false],
            'generation'           => [
                'report'    => [
                    'report_id'           => $reportId,
                    'listing_context'     => ['listing_id' => 99],
                    'generated_at'        => '2026-06-01T12:00:00+00:00',
                    'generation_metadata' => [],
                    'readiness_snapshot'  => [],
                    'sections'            => [],
                    'attribution_verified' => false,
                ],
                'readiness' => ['is_marketing_ready' => true],
            ],
            'generated_at' => '2026-06-01T12:00:00+00:00',
        ];

        $mockOrchestrator = $this->mock(AiMarketingReportOrchestratorService::class);
        $mockOrchestrator->shouldReceive('run')
            ->once()
            ->andReturn($blockedResult);

        $response = $this->actingAs($admin)
            ->post(route(self::ROUTE_NAME, $profile['id']));

        $response->assertRedirect(
            route('admin.property-dna.marketing-brief-preview', $profile['id'])
        );

        $this->assertNoMarketingRowsForReport($reportId);
    }
}
