<?php

namespace Tests\Unit\Services\Dna;

use App\Models\PropertyDnaProfile;
use App\Services\Dna\AiMarketingReportPersistenceService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AiMarketingReportPersistenceServiceTest — Phase XK
 *
 * Verifies AiMarketingReportPersistenceService using the project's standard
 * DatabaseTransactions test pattern.  Each test runs inside a transaction that
 * rolls back automatically — no data survives to the live schema.
 *
 * Report IDs are valid UUIDs throughout because marketing_reports.id is a
 * PostgreSQL uuid column that rejects non-UUID strings at the parameter level.
 *
 * No OpenAI calls, no HTTP requests, no Livewire, no routes touched.
 *
 * Test coverage:
 *   (1)  Gate — orchestration_status not 'ready_for_agent_review'
 *   (2)  Gate — review.passed absent or false
 *   (3)  Gate — generation.report absent or empty
 *   (4)  Gate — attribution_verified absent or false
 *   (5)  Gate — each required section key missing (one assertion per key)
 *   (6)  Duplicate report_id guard — throws before any insert
 *   (7)  Happy path — six records created with correct field values
 *   (8)  Transaction rollback — no rows survive a mid-transaction failure
 *   (9)  Audit append-only intent — documented; driver note included
 */
class AiMarketingReportPersistenceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const REQUIRED_SECTION_KEYS = [
        'property_feature_narrative',
        'transaction_terms_summary',
        'marketing_asset_statement',
        'missing_information_note',
        'listing_preparation_summary',
    ];

    private AiMarketingReportPersistenceService $service;
    private PropertyDnaProfile $profile;
    private int $profileId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AiMarketingReportPersistenceService();

        // Insert a real property_dna_profiles row so that FK constraints on
        // marketing_reports.profile_id and marketing_report_audits.profile_id
        // are satisfied.  DatabaseTransactions rolls this back after each test.
        $this->profileId = DB::table('property_dna_profiles')->insertGetId([
            'listing_type'              => 'seller',
            'listing_id'               => 1,
            'version'                  => 1,
            'source_listing_updated_at' => now()->toDateTimeString(),
            'computed_at'              => now()->toDateTimeString(),
            'created_at'               => now()->toDateTimeString(),
            'updated_at'               => now()->toDateTimeString(),
        ]);

        // Build a PropertyDnaProfile instance with the real DB id.
        // The service only reads $profile->id, so an unsaved model instance
        // with the attribute set is sufficient.
        $this->profile = new PropertyDnaProfile();
        $this->profile->setAttribute('id', $this->profileId);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Return a canonical valid orchestration result fixture.
     * Every gate condition passes; every required key is present.
     *
     * @param  string $reportId  Must be a valid UUID — marketing_reports.id is
     *                           a PostgreSQL uuid column.
     */
    private function makeValidOrchestrationResult(string $reportId): array
    {
        $section = [
            'draft_text'         => 'Sample section text for testing.',
            'source_attribution' => ['source-ref-1'],
            'status'             => 'pending_review',
        ];

        return [
            'orchestration_status' => 'ready_for_agent_review',
            'review'               => [
                'passed' => true,
            ],
            'generation'           => [
                'report'    => [
                    'report_id'            => $reportId,
                    'listing_context'      => ['listing_id' => 42],
                    'generated_at'         => '2026-05-31T12:00:00+00:00',
                    'generation_metadata'  => [
                        'ai_model'                  => 'gpt-4o',
                        'prompt_template_version'   => 'phase-xd-v1',
                        'phase_r_brief_version'     => 'phase-r-v1',
                        'phase_u_readiness_version' => 'phase-u-v1',
                        'phase_r_brief_snapshot'    => ['brief_key' => 'brief_value'],
                    ],
                    'readiness_snapshot'   => ['is_marketing_ready' => true],
                    'sections'             => [
                        'property_feature_narrative'  => $section,
                        'transaction_terms_summary'   => $section,
                        'marketing_asset_statement'   => $section,
                        'missing_information_note'    => $section,
                        'listing_preparation_summary' => $section,
                    ],
                    'attribution_verified' => true,
                ],
                'readiness' => [
                    'is_marketing_ready' => true,
                ],
            ],
        ];
    }

    /**
     * Assert that no rows exist in any of the three marketing tables for the
     * given report_id.  Used to confirm a gate or rollback prevented all writes.
     */
    private function assertNoMarketingRowsWritten(string $reportId): void
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

    /**
     * Install a driver-appropriate blocker that causes any INSERT into
     * marketing_report_audits with the given report_id to fail.
     *
     * PostgreSQL: adds a CHECK constraint scoped to the report_id.
     *             Transactional DDL — rolled back by DatabaseTransactions.
     *             The report_id is cast to ::uuid to satisfy the column type.
     * SQLite:     creates a TEMPORARY TRIGGER (connection-scoped).
     *
     * Returns the constraint/trigger name so the caller can drop it explicitly
     * before assertions (belt-and-suspenders).
     */
    private function installAuditInsertBlocker(string $reportId): string
    {
        $name = 'xk_rollback_test_block';

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE marketing_report_audits "
                . "ADD CONSTRAINT {$name} "
                . "CHECK (report_id IS DISTINCT FROM '{$reportId}'::uuid)"
            );
        } else {
            DB::statement("
                CREATE TEMPORARY TRIGGER {$name}
                BEFORE INSERT ON marketing_report_audits
                WHEN NEW.report_id = '{$reportId}'
                BEGIN
                    SELECT RAISE(ABORT, 'Simulated mid-transaction failure: audit insert blocked');
                END;
            ");
        }

        return $name;
    }

    /**
     * Remove the blocker installed by installAuditInsertBlocker().
     */
    private function removeAuditInsertBlocker(string $name): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE marketing_report_audits DROP CONSTRAINT IF EXISTS {$name}");
        } else {
            DB::statement("DROP TRIGGER IF EXISTS {$name}");
        }
    }

    /**
     * Insert a pre-existing marketing_reports row for the given report_id.
     * Used by the duplicate-guard tests to simulate an already-persisted report.
     */
    private function preInsertMarketingReport(string $reportId): void
    {
        DB::table('marketing_reports')->insert([
            'id'                        => $reportId,
            'listing_id'                => 42,
            'profile_id'                => $this->profileId,
            'generated_at'              => now()->toDateTimeString(),
            'ai_model'                  => 'gpt-4o',
            'prompt_template_version'   => 'v1',
            'report_contract_version'   => 'phase-w-v1',
            'phase_r_brief_version'     => 'r1',
            'phase_u_readiness_version' => 'u1',
            'readiness_snapshot'        => json_encode([]),
            'sections'                  => json_encode([]),
            'attribution_verified'      => true,
            'status'                    => 'pending_review',
        ]);
    }

    // =========================================================================
    // (1) Gate — orchestration_status
    // =========================================================================

    /** @test */
    public function it_throws_when_orchestration_status_is_not_ready_for_agent_review(): void
    {
        $reportId = Str::uuid()->toString();

        foreach (['blocked', 'pending', 'failed', ''] as $badStatus) {
            $result                         = $this->makeValidOrchestrationResult($reportId);
            $result['orchestration_status'] = $badStatus;

            try {
                $this->service->persist($this->profile, $result);
                $this->fail("Expected Exception for orchestration_status='{$badStatus}' was not thrown.");
            } catch (Exception $e) {
                $this->assertStringContainsString(
                    'orchestration_status',
                    $e->getMessage(),
                    "Exception message must reference 'orchestration_status' for value '{$badStatus}'"
                );
            }
        }

        $this->assertNoMarketingRowsWritten($reportId);
    }

    /** @test */
    public function it_throws_when_orchestration_status_key_is_absent(): void
    {
        $reportId = Str::uuid()->toString();
        $result   = $this->makeValidOrchestrationResult($reportId);
        unset($result['orchestration_status']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/orchestration_status/');

        $this->service->persist($this->profile, $result);
    }

    // =========================================================================
    // (2) Gate — review.passed
    // =========================================================================

    /** @test */
    public function it_throws_when_review_passed_is_false(): void
    {
        $reportId                   = Str::uuid()->toString();
        $result                     = $this->makeValidOrchestrationResult($reportId);
        $result['review']['passed'] = false;

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/review\.passed/');

        $this->service->persist($this->profile, $result);
    }

    /** @test */
    public function it_throws_when_review_passed_is_absent(): void
    {
        $reportId = Str::uuid()->toString();
        $result   = $this->makeValidOrchestrationResult($reportId);
        unset($result['review']['passed']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/review\.passed/');

        $this->service->persist($this->profile, $result);
    }

    /** @test */
    public function it_writes_nothing_when_review_passed_gate_fails(): void
    {
        $reportId                   = Str::uuid()->toString();
        $result                     = $this->makeValidOrchestrationResult($reportId);
        $result['review']['passed'] = false;

        try {
            $this->service->persist($this->profile, $result);
        } catch (Exception $e) {
        }

        $this->assertNoMarketingRowsWritten($reportId);
    }

    // =========================================================================
    // (3) Gate — generation.report absent or empty
    // =========================================================================

    /** @test */
    public function it_throws_when_generation_report_is_absent(): void
    {
        $reportId = Str::uuid()->toString();
        $result   = $this->makeValidOrchestrationResult($reportId);
        unset($result['generation']['report']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/generation\.report/');

        $this->service->persist($this->profile, $result);
    }

    /** @test */
    public function it_throws_when_generation_report_is_an_empty_array(): void
    {
        $reportId                       = Str::uuid()->toString();
        $result                         = $this->makeValidOrchestrationResult($reportId);
        $result['generation']['report'] = [];

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/generation\.report/');

        $this->service->persist($this->profile, $result);
    }

    /** @test */
    public function it_writes_nothing_when_generation_report_gate_fails(): void
    {
        $reportId = Str::uuid()->toString();
        $result   = $this->makeValidOrchestrationResult($reportId);
        unset($result['generation']['report']);

        try {
            $this->service->persist($this->profile, $result);
        } catch (Exception $e) {
        }

        $this->assertNoMarketingRowsWritten($reportId);
    }

    // =========================================================================
    // (4) Gate — attribution_verified
    // =========================================================================

    /** @test */
    public function it_throws_when_attribution_verified_is_false(): void
    {
        $reportId                                               = Str::uuid()->toString();
        $result                                                 = $this->makeValidOrchestrationResult($reportId);
        $result['generation']['report']['attribution_verified'] = false;

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/attribution_verified/');

        $this->service->persist($this->profile, $result);
    }

    /** @test */
    public function it_throws_when_attribution_verified_is_absent(): void
    {
        $reportId = Str::uuid()->toString();
        $result   = $this->makeValidOrchestrationResult($reportId);
        unset($result['generation']['report']['attribution_verified']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/attribution_verified/');

        $this->service->persist($this->profile, $result);
    }

    /** @test */
    public function it_writes_nothing_when_attribution_verified_gate_fails(): void
    {
        $reportId                                               = Str::uuid()->toString();
        $result                                                 = $this->makeValidOrchestrationResult($reportId);
        $result['generation']['report']['attribution_verified'] = false;

        try {
            $this->service->persist($this->profile, $result);
        } catch (Exception $e) {
        }

        $this->assertNoMarketingRowsWritten($reportId);
    }

    // =========================================================================
    // (5) Gate — each required section key must be present
    // =========================================================================

    /** @test */
    public function it_throws_for_each_missing_required_section_key(): void
    {
        foreach (self::REQUIRED_SECTION_KEYS as $missingKey) {
            $reportId = Str::uuid()->toString();
            $result   = $this->makeValidOrchestrationResult($reportId);
            unset($result['generation']['report']['sections'][$missingKey]);

            $threw = false;
            try {
                $this->service->persist($this->profile, $result);
            } catch (Exception $e) {
                $threw = true;
                $this->assertStringContainsString(
                    $missingKey,
                    $e->getMessage(),
                    "Exception message must name the missing key '{$missingKey}'"
                );
            }

            $this->assertTrue(
                $threw,
                "persist() must throw when section key '{$missingKey}' is absent"
            );

            $this->assertNoMarketingRowsWritten($reportId);
        }
    }

    /** @test */
    public function it_throws_when_sections_key_itself_is_absent(): void
    {
        $reportId = Str::uuid()->toString();
        $result   = $this->makeValidOrchestrationResult($reportId);
        unset($result['generation']['report']['sections']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/sections/');

        $this->service->persist($this->profile, $result);
    }

    // =========================================================================
    // (6) Duplicate report_id guard
    // =========================================================================

    /** @test */
    public function it_throws_when_report_id_already_exists_in_marketing_reports(): void
    {
        $reportId = Str::uuid()->toString();
        $this->preInsertMarketingReport($reportId);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/duplicate/i');

        $this->service->persist($this->profile, $this->makeValidOrchestrationResult($reportId));
    }

    /** @test */
    public function it_writes_no_versions_or_audits_when_duplicate_guard_fires(): void
    {
        $reportId = Str::uuid()->toString();
        $this->preInsertMarketingReport($reportId);

        try {
            $this->service->persist($this->profile, $this->makeValidOrchestrationResult($reportId));
        } catch (Exception $e) {
        }

        $this->assertSame(
            0,
            DB::table('marketing_report_versions')->where('marketing_report_id', $reportId)->count(),
            'No marketing_report_versions rows must be written when the duplicate guard fires'
        );
        $this->assertSame(
            0,
            DB::table('marketing_report_audits')->where('report_id', $reportId)->count(),
            'No marketing_report_audits rows must be written when the duplicate guard fires'
        );
    }

    // =========================================================================
    // (7) Happy path — six records created with correct field values
    // =========================================================================

    /** @test */
    public function it_returns_the_expected_result_shape_on_success(): void
    {
        $reportId = Str::uuid()->toString();
        $result   = $this->service->persist($this->profile, $this->makeValidOrchestrationResult($reportId));

        $this->assertSame($reportId, $result['marketing_report_id']);
        $this->assertSame(5, $result['versions_created']);
        $this->assertTrue($result['audit_created']);
        $this->assertSame('persisted', $result['status']);
    }

    /** @test */
    public function it_inserts_exactly_one_marketing_reports_row_with_correct_fields(): void
    {
        $reportId = Str::uuid()->toString();

        $this->service->persist($this->profile, $this->makeValidOrchestrationResult($reportId));

        $this->assertSame(
            1,
            DB::table('marketing_reports')->where('id', $reportId)->count(),
            'Exactly one marketing_reports row must be created'
        );

        $row = DB::table('marketing_reports')->where('id', $reportId)->first();

        $this->assertNotNull($row);
        $this->assertSame($reportId, $row->id);
        $this->assertSame(42, (int) $row->listing_id);
        $this->assertSame($this->profileId, (int) $row->profile_id);
        $this->assertSame('gpt-4o', $row->ai_model);
        $this->assertSame('phase-xd-v1', $row->prompt_template_version);
        $this->assertSame('phase-w-v1', $row->report_contract_version);
        $this->assertSame('phase-r-v1', $row->phase_r_brief_version);
        $this->assertSame('phase-u-v1', $row->phase_u_readiness_version);
        $this->assertTrue((bool) $row->attribution_verified);
        $this->assertSame('pending_review', $row->status);
    }

    /** @test */
    public function it_inserts_exactly_five_marketing_report_versions_rows(): void
    {
        $reportId = Str::uuid()->toString();

        $this->service->persist($this->profile, $this->makeValidOrchestrationResult($reportId));

        $this->assertSame(
            5,
            DB::table('marketing_report_versions')->where('marketing_report_id', $reportId)->count(),
            'Exactly five marketing_report_versions rows must be created — one per section key'
        );
    }

    /** @test */
    public function it_inserts_one_version_row_per_required_section_key_with_correct_fields(): void
    {
        $reportId = Str::uuid()->toString();

        $this->service->persist($this->profile, $this->makeValidOrchestrationResult($reportId));

        $rows = DB::table('marketing_report_versions')
            ->where('marketing_report_id', $reportId)
            ->orderBy('id')
            ->get()
            ->keyBy('section_key')
            ->all();

        foreach (self::REQUIRED_SECTION_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $rows,
                "A marketing_report_versions row must exist for section_key='{$key}'"
            );

            $row = $rows[$key];
            $this->assertSame($reportId, $row->marketing_report_id);
            $this->assertSame(1, (int) $row->version_number);
            $this->assertSame('Sample section text for testing.', $row->draft_text);
            $this->assertSame('pending_review', $row->status);
            $this->assertSame('ai_generated', $row->created_by);
        }
    }

    /** @test */
    public function it_inserts_exactly_one_marketing_report_audits_row_with_event_type_generation(): void
    {
        $reportId = Str::uuid()->toString();

        $this->service->persist($this->profile, $this->makeValidOrchestrationResult($reportId));

        $this->assertSame(
            1,
            DB::table('marketing_report_audits')->where('report_id', $reportId)->count(),
            'Exactly one marketing_report_audits row must be created'
        );

        $row = DB::table('marketing_report_audits')->where('report_id', $reportId)->first();

        $this->assertNotNull($row);
        $this->assertSame('generation', $row->event_type);
        $this->assertSame($reportId, $row->report_id);
        $this->assertSame(42, (int) $row->listing_id);
        $this->assertSame($this->profileId, (int) $row->profile_id);
        $this->assertNull($row->actor_id);

        $eventData = json_decode($row->event_data, true);
        $this->assertIsArray($eventData);
        $this->assertSame('gpt-4o', $eventData['ai_model']);
        $this->assertSame('phase-xd-v1', $eventData['prompt_template_version']);
        $this->assertSame('phase-w-v1', $eventData['report_contract_version']);
        $this->assertTrue($eventData['attribution_verified']);
    }

    // =========================================================================
    // (8) Transaction rollback — no rows survive a mid-transaction failure
    // =========================================================================

    /** @test */
    public function it_rolls_back_all_rows_when_a_failure_occurs_mid_transaction(): void
    {
        $reportId        = Str::uuid()->toString();
        $blockerName     = $this->installAuditInsertBlocker($reportId);
        $caughtException = null;

        // The service writes marketing_reports (1 row) and marketing_report_versions
        // (5 rows) first, then attempts the audit INSERT which is blocked by the
        // installed constraint / trigger.  DB::transaction() must catch the exception
        // and roll back the savepoint, leaving all three tables empty for this report_id.
        try {
            $this->service->persist($this->profile, $this->makeValidOrchestrationResult($reportId));
        } catch (Exception $e) {
            $caughtException = $e;
        }

        // Remove the blocker before assertions so subsequent tests are not affected.
        $this->removeAuditInsertBlocker($blockerName);

        $this->assertNotNull(
            $caughtException,
            'persist() must propagate an exception when a DB failure occurs inside DB::transaction()'
        );

        $this->assertSame(
            0,
            DB::table('marketing_reports')->where('id', $reportId)->count(),
            'marketing_reports must have 0 rows after rollback — the transaction must unwind all inserts'
        );
        $this->assertSame(
            0,
            DB::table('marketing_report_versions')->where('marketing_report_id', $reportId)->count(),
            'marketing_report_versions must have 0 rows after rollback'
        );
        $this->assertSame(
            0,
            DB::table('marketing_report_audits')->where('report_id', $reportId)->count(),
            'marketing_report_audits must have 0 rows after rollback'
        );
    }

    // =========================================================================
    // (9) Audit append-only design intent
    // =========================================================================

    /**
     * @test
     *
     * DESIGN INTENT — Append-only enforcement for marketing_report_audits.
     *
     * In the production PostgreSQL environment, a BEFORE UPDATE OR DELETE trigger
     * (`marketing_report_audits_no_update_delete`) unconditionally raises an
     * exception if any application code attempts to UPDATE or DELETE a row in
     * this table.  The trigger is installed by migration
     * 2026_05_31_000004_create_marketing_report_audits_table.php only when
     * DB::getDriverName() === 'pgsql'.
     *
     * This test verifies that the service writes one audit row and exposes no
     * public API to modify it.  The PostgreSQL trigger enforces the same
     * constraint at the database level in all deployed environments.
     */
    public function it_documents_that_the_audit_row_is_never_updated_or_deleted_by_the_service(): void
    {
        $reportId = Str::uuid()->toString();

        $this->service->persist($this->profile, $this->makeValidOrchestrationResult($reportId));

        $auditRow = DB::table('marketing_report_audits')
            ->where('report_id', $reportId)
            ->first();

        $this->assertNotNull($auditRow, 'The service must write exactly one audit row on a successful persist()');
        $this->assertSame('generation', $auditRow->event_type);

        // The service provides no public method to UPDATE or DELETE audit rows.
        // Append-only enforcement in production is provided by the PostgreSQL
        // trigger documented above; no application-level UPDATE or DELETE is
        // permitted.
        $this->assertFalse(
            method_exists($this->service, 'updateAudit'),
            'AiMarketingReportPersistenceService must not expose an updateAudit() method'
        );
        $this->assertFalse(
            method_exists($this->service, 'deleteAudit'),
            'AiMarketingReportPersistenceService must not expose a deleteAudit() method'
        );
    }
}
