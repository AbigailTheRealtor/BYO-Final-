<?php

namespace Tests\Feature\AskAi;

use App\Models\AskAiAnswer;
use App\Models\AskAiFact;
use App\Models\AskAiKnowledgeSnapshot;
use App\Models\AskAiQuestion;
use App\Services\AskAi\AskAiContextBuilderService;
use App\Services\AskAi\AskAiKnowledgeSnapshotBuilderService;
use App\Services\AskAi\Snapshot\BuyerSnapshotBuilder;
use App\Services\AskAi\Snapshot\LandlordSnapshotBuilder;
use App\Services\AskAi\Snapshot\SellerSnapshotBuilder;
use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use App\Services\AskAi\Snapshot\TenantSnapshotBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * AskAiSnapshotBuilderTest — Phase 2: Persistent Knowledge Snapshot Layer
 *
 * Uses DatabaseTransactions so each test method runs inside a rolled-back
 * transaction, keeping the SQLite :memory: schema clean between methods.
 *
 * Test coverage:
 *   A. Container resolves AskAiKnowledgeSnapshotBuilderService without throwing.
 *   B. build() creates a snapshot record with status=ready.
 *   C. Version increments on each successive build call.
 *   D. buildSilently() never throws, even with a broken context builder.
 *   E. buildSilently() persists status=failed when the builder throws.
 *   F. TYPE_ALIASES normalise aliased type strings to canonical form.
 *   G. Unknown canonical type produces status=failed snapshot (not a PHP exception).
 *   H. Facts are persisted with non-empty canonical_key and value.
 *   I. Role builders can be resolved from the container individually.
 *   J. Artisan ask-ai:snapshot-audit command exits with code 0.
 *   K. Snapshot tables exist in the database with the expected columns.
 *   L. AskAiKnowledgeSnapshot model relationships return the correct related class.
 *   M. Successive builds produce sequential, gap-free version numbers.
 *   N. buildSilently with a non-existent listing ID does not throw.
 *   O. Pre-occupied version slot causes build() to use the next version (concurrency sim).
 *   P. Unique index rejects duplicate (listing_type, listing_id, version) rows.
 *   Q. Audit command "Failed Snapshots" section correctly reports existing failed rows.
 *   R. Audit command "Phantom Keys" section reports 0 when all canonical_keys are valid.
 *   S. Audit command "Counts by Role" section outputs a row for each role.
 *   T. SnapshotFactVisibility::classify() returns 'restricted' for compliance-sensitive
 *      keys and 'public_allowed' for standard keys; verified in persisted facts.
 */
class AskAiSnapshotBuilderTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeService(): AskAiKnowledgeSnapshotBuilderService
    {
        return $this->app->make(AskAiKnowledgeSnapshotBuilderService::class);
    }

    private function makeContextBuilderReturning(array $context): AskAiContextBuilderService
    {
        $mock = $this->createMock(AskAiContextBuilderService::class);
        $mock->method('buildForListing')->willReturn($context);
        return $mock;
    }

    private function makeContextBuilderThrowing(string $message = 'simulated failure'): AskAiContextBuilderService
    {
        $mock = $this->createMock(AskAiContextBuilderService::class);
        $mock->method('buildForListing')->willThrowException(new \RuntimeException($message));
        return $mock;
    }

    private function makeServiceWith(AskAiContextBuilderService $cb): AskAiKnowledgeSnapshotBuilderService
    {
        return new AskAiKnowledgeSnapshotBuilderService(
            new SellerSnapshotBuilder($cb),
            new BuyerSnapshotBuilder($cb),
            new LandlordSnapshotBuilder($cb),
            new TenantSnapshotBuilder($cb),
        );
    }

    private function fakeContext(array $listing = [], array $faqAnswers = []): array
    {
        return [
            'listing'     => $listing    ?: ['address' => '123 Main St', 'price' => '450000'],
            'faq_answers' => $faqAnswers ?: ['listing.address' => '123 Main St'],
        ];
    }

    private function seedSnapshot(string $role, int $listingId, int $version = 1, string $status = 'ready'): AskAiKnowledgeSnapshot
    {
        return AskAiKnowledgeSnapshot::create([
            'listing_type'   => $role,
            'listing_id'     => $listingId,
            'version'        => $version,
            'status'         => $status,
            'snapshot_uuid'  => (string) \Illuminate\Support\Str::uuid(),
            'built_at'       => $status === 'ready' ? now() : null,
        ]);
    }

    // =========================================================================
    // Case A — Container resolves the orchestrator service without throwing
    // =========================================================================

    public function test_case_A_container_resolves_orchestrator(): void
    {
        $service   = null;
        $exception = null;

        try {
            $service = $this->makeService();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Container threw: ' . ($exception?->getMessage() ?? ''));
        $this->assertInstanceOf(AskAiKnowledgeSnapshotBuilderService::class, $service);
    }

    // =========================================================================
    // Case B — build() with a mocked context creates a status=built snapshot
    // =========================================================================

    public function test_case_B_build_creates_built_snapshot(): void
    {
        $service  = $this->makeServiceWith($this->makeContextBuilderReturning($this->fakeContext()));
        $snapshot = $service->build('seller', 420001);

        $this->assertInstanceOf(AskAiKnowledgeSnapshot::class, $snapshot);
        $this->assertEquals('ready', $snapshot->status);
        $this->assertEquals('seller', $snapshot->listing_type);
        $this->assertEquals(420001, $snapshot->listing_id);
        $this->assertNotNull($snapshot->built_at);
        $this->assertNull($snapshot->error_message);
        $this->assertNotNull($snapshot->snapshot_uuid, 'snapshot_uuid must be generated on build.');
    }

    // =========================================================================
    // Case C — Version increments on each successive build call
    // =========================================================================

    public function test_case_C_version_increments_on_each_build(): void
    {
        $service   = $this->makeServiceWith($this->makeContextBuilderReturning($this->fakeContext()));
        $listingId = 420002;

        $v1 = $service->build('seller', $listingId);
        $v2 = $service->build('seller', $listingId);
        $v3 = $service->build('seller', $listingId);

        $this->assertGreaterThan(0, $v1->version, 'v1 must be positive');
        $this->assertEquals($v1->version + 1, $v2->version, 'v2 must be exactly v1 + 1');
        $this->assertEquals($v2->version + 1, $v3->version, 'v3 must be exactly v2 + 1');
    }

    // =========================================================================
    // Case D — buildSilently() never throws, even when the builder throws
    // =========================================================================

    public function test_case_D_buildSilently_never_throws(): void
    {
        $service   = $this->makeServiceWith($this->makeContextBuilderThrowing('boom'));
        $exception = null;

        try {
            $service->buildSilently('seller', 420003);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'buildSilently threw: ' . ($exception?->getMessage() ?? ''));
    }

    // =========================================================================
    // Case E — buildSilently() persists status=failed when builder throws
    // =========================================================================

    public function test_case_E_buildSilently_persists_failed_status_on_exception(): void
    {
        $service   = $this->makeServiceWith($this->makeContextBuilderThrowing('context builder explosion'));
        $listingId = 420004;

        $service->buildSilently('buyer', $listingId);

        $failedSnapshot = AskAiKnowledgeSnapshot::where('listing_type', 'buyer')
            ->where('listing_id', $listingId)
            ->where('status', 'failed')
            ->orderByDesc('version')
            ->first();

        $this->assertNotNull($failedSnapshot, 'Expected a failed snapshot row in DB.');
        $this->assertNotEmpty($failedSnapshot->error_message, 'error_message must not be blank.');
    }

    // =========================================================================
    // Case F — TYPE_ALIASES normalise aliased type strings
    // =========================================================================

    public function test_case_F_type_aliases_are_normalised(): void
    {
        $service  = $this->makeServiceWith($this->makeContextBuilderReturning($this->fakeContext()));

        $aliasMap = [
            'seller_agent_auction'    => 'seller',
            'buyer_agent_auction'     => 'buyer',
            'landlord_agent_auction'  => 'landlord',
            'tenant_agent_auction'    => 'tenant',
            'property_auction'        => 'seller',
            'buyer_criteria_auction'  => 'buyer',
            'landlord_auction'        => 'landlord',
            'tenant_criteria_auction' => 'tenant',
        ];

        foreach ($aliasMap as $alias => $expectedCanonical) {
            $snapshot = $service->build($alias, 420005);
            $this->assertEquals($expectedCanonical, $snapshot->listing_type,
                "Alias '{$alias}' should normalise to '{$expectedCanonical}'");
        }
    }

    // =========================================================================
    // Case G — Unknown canonical type produces a status=failed snapshot
    // =========================================================================

    public function test_case_G_unknown_type_produces_failed_snapshot(): void
    {
        $service   = $this->makeServiceWith($this->makeContextBuilderReturning($this->fakeContext()));
        $exception = null;
        $snapshot  = null;

        try {
            $snapshot = $service->build('unknown_type', 420006);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'build() must not re-throw for unknown types.');
        $this->assertNotNull($snapshot);
        $this->assertEquals('failed', $snapshot->status);
    }

    // =========================================================================
    // Case H — Facts are persisted with non-empty canonical_key and value
    // =========================================================================

    public function test_case_H_facts_are_persisted(): void
    {
        $service  = $this->makeServiceWith($this->makeContextBuilderReturning([
            'listing'     => ['address' => '10 Oak Ave', 'bedrooms' => '3'],
            'faq_answers' => [],
        ]));
        $snapshot = $service->build('seller', 420007);

        $this->assertGreaterThan(0, $snapshot->facts()->count(),
            'Expected at least one fact row after a successful build.');

        $snapshot->facts()->get()->each(function ($fact) use ($snapshot) {
            $this->assertNotNull($fact->canonical_key);
            $this->assertNotEmpty($fact->canonical_key);
            $this->assertNotNull($fact->value);
            // New Phase-2 schema fields
            $this->assertEquals($snapshot->listing_type, $fact->listing_type,
                'fact.listing_type must be denormalized from the snapshot.');
            $this->assertEquals($snapshot->listing_id, $fact->listing_id,
                'fact.listing_id must be denormalized from the snapshot.');
            $this->assertNotNull($fact->label, 'fact.label must be set.');
            $this->assertNotNull($fact->value_type, 'fact.value_type must be set.');
            $this->assertStringStartsWith('context.listing.', $fact->source_path,
                'fact.source_path must begin with context.listing.');
            $this->assertNotNull($fact->classification, 'fact.classification must be set.');
            $this->assertIsBool($fact->public_allowed);
            $this->assertIsBool($fact->restricted);
        });
    }

    // =========================================================================
    // Case I — Role builders resolve from the container individually
    // =========================================================================

    public function test_case_I_role_builders_resolve_from_container(): void
    {
        $this->assertInstanceOf(SellerSnapshotBuilder::class,   $this->app->make(SellerSnapshotBuilder::class));
        $this->assertInstanceOf(BuyerSnapshotBuilder::class,    $this->app->make(BuyerSnapshotBuilder::class));
        $this->assertInstanceOf(LandlordSnapshotBuilder::class, $this->app->make(LandlordSnapshotBuilder::class));
        $this->assertInstanceOf(TenantSnapshotBuilder::class,   $this->app->make(TenantSnapshotBuilder::class));
    }

    // =========================================================================
    // Case J — Artisan ask-ai:snapshot-audit command exits with code 0
    // =========================================================================

    public function test_case_J_audit_command_exits_zero(): void
    {
        $exitCode = Artisan::call('ask-ai:snapshot-audit');
        $this->assertEquals(0, $exitCode, 'ask-ai:snapshot-audit must exit with code 0.');
    }

    // =========================================================================
    // Case K — Snapshot tables exist with expected columns
    // =========================================================================

    public function test_case_K_snapshot_tables_exist_with_expected_columns(): void
    {
        $tables = [
            'ask_ai_knowledge_snapshots' => [
                'id', 'snapshot_uuid', 'listing_type', 'listing_id', 'version',
                'status', 'error_message', 'source_model', 'source_updated_at',
                'built_at', 'facts_count', 'questions_count', 'answers_count',
            ],
            'ask_ai_facts' => [
                'id', 'snapshot_id', 'canonical_key', 'value', 'visibility',
                'listing_type', 'listing_id', 'label', 'value_type', 'source_path',
                'classification', 'public_allowed', 'restricted', 'sort_order',
            ],
            'ask_ai_questions' => [
                'id', 'snapshot_id', 'canonical_key', 'field_type', 'keyword_route_status',
                'label', 'sample_question', 'sample_question_2',
                'question_text', 'question_type', 'source_path', 'sort_order',
            ],
            'ask_ai_answers' => [
                'id', 'snapshot_id', 'canonical_key', 'answer_text',
                'question_id', 'classification', 'visibility', 'source_path', 'sort_order',
            ],
        ];

        foreach ($tables as $table => $expectedColumns) {
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable($table), "Table {$table} does not exist.");
            foreach ($expectedColumns as $col) {
                $this->assertTrue(
                    \Illuminate\Support\Facades\Schema::hasColumn($table, $col),
                    "Column {$col} missing from {$table}."
                );
            }
        }
    }

    // =========================================================================
    // Case L — Model relationships return the correct related class
    // =========================================================================

    public function test_case_L_model_relationships_point_to_correct_classes(): void
    {
        $snapshot = $this->seedSnapshot('seller', 420008);

        AskAiFact::create([
            'snapshot_id'   => $snapshot->id,
            'canonical_key' => 'test.key',
            'value'         => 'test value',
            'visibility'    => 'public_allowed',
        ]);

        AskAiQuestion::create([
            'snapshot_id'   => $snapshot->id,
            'canonical_key' => 'test.key',
            'field_type'    => 'faq',
        ]);

        AskAiAnswer::create([
            'snapshot_id'   => $snapshot->id,
            'canonical_key' => 'test.key',
            'answer_text'   => 'test answer',
        ]);

        $freshSnapshot = AskAiKnowledgeSnapshot::with(['facts', 'questions', 'answers'])->find($snapshot->id);

        $this->assertCount(1, $freshSnapshot->facts);
        $this->assertCount(1, $freshSnapshot->questions);
        $this->assertCount(1, $freshSnapshot->answers);

        $this->assertInstanceOf(AskAiFact::class,     $freshSnapshot->facts->first());
        $this->assertInstanceOf(AskAiQuestion::class, $freshSnapshot->questions->first());
        $this->assertInstanceOf(AskAiAnswer::class,   $freshSnapshot->answers->first());
    }

    // =========================================================================
    // Case M — Successive builds produce sequential, gap-free version numbers
    // =========================================================================

    public function test_case_M_no_duplicate_versions_for_same_listing(): void
    {
        $service   = $this->makeServiceWith($this->makeContextBuilderReturning($this->fakeContext()));
        $listingId = 420009;
        $builds    = 5;

        for ($i = 0; $i < $builds; $i++) {
            $service->build('landlord', $listingId);
        }

        $versions = AskAiKnowledgeSnapshot::where('listing_type', 'landlord')
            ->where('listing_id', $listingId)
            ->pluck('version')
            ->sort()
            ->values()
            ->toArray();

        $this->assertGreaterThanOrEqual($builds, count($versions),
            "Expected at least {$builds} snapshot rows.");

        $this->assertCount(count($versions), array_unique($versions),
            'Versions must have no duplicates.');

        for ($i = 1; $i < count($versions); $i++) {
            $this->assertEquals(
                $versions[$i - 1] + 1,
                $versions[$i],
                "Versions must be consecutive: found gap between {$versions[$i-1]} and {$versions[$i]}."
            );
        }
    }

    // =========================================================================
    // Case N — buildSilently with a non-existent listing ID does not throw
    // =========================================================================

    public function test_case_N_buildSilently_does_not_throw_on_missing_listing(): void
    {
        $exception = null;
        try {
            $this->makeService()->buildSilently('seller', 9999999);
        } catch (\Throwable $e) {
            $exception = $e;
        }
        $this->assertNull($exception, 'buildSilently threw for missing listing: ' . ($exception?->getMessage() ?? ''));
    }

    // =========================================================================
    // Case O — Pre-occupied version slot forces build() to use next version
    //
    // Simulates a concurrent race where another process already committed
    // version 1. build() must re-read max version and produce version 2.
    // =========================================================================

    public function test_case_O_build_uses_next_version_when_slot_is_pre_occupied(): void
    {
        $listingId = 420010;

        // Pre-seed version 1 as if a concurrent build already committed it.
        $this->seedSnapshot('seller', $listingId, 1, 'ready');

        $service  = $this->makeServiceWith($this->makeContextBuilderReturning($this->fakeContext()));
        $snapshot = $service->build('seller', $listingId);

        $this->assertEquals('ready', $snapshot->status,
            'build() must succeed even when version 1 is pre-occupied.');
        $this->assertGreaterThan(1, $snapshot->version,
            'build() must use a version > 1 when version 1 is already taken.');

        // Both rows must coexist with unique versions.
        $versions = AskAiKnowledgeSnapshot::where('listing_type', 'seller')
            ->where('listing_id', $listingId)
            ->pluck('version')
            ->toArray();

        $this->assertCount(count($versions), array_unique($versions),
            'No duplicate versions must exist for the same listing.');
    }

    // =========================================================================
    // Case P — Unique index rejects duplicate (listing_type, listing_id, version)
    //
    // Verifies DB-level enforcement: a second insert with the same composite
    // key must throw QueryException. Uses try/catch (not expectException) so
    // the underlying SQLite connection remains usable after the constraint error.
    // =========================================================================

    public function test_case_P_unique_index_rejects_duplicate_version(): void
    {
        $listingId = 420011;

        // Insert version 1 successfully.
        $this->seedSnapshot('buyer', $listingId, 1, 'ready');

        $threw = false;
        try {
            // Same (listing_type, listing_id, version) — must be rejected.
            AskAiKnowledgeSnapshot::create([
                'listing_type'  => 'buyer',
                'listing_id'    => $listingId,
                'version'       => 1,
                'status'        => 'ready',
                'snapshot_uuid' => (string) \Illuminate\Support\Str::uuid(),
                'built_at'      => now(),
            ]);
        } catch (QueryException $e) {
            $threw = true;
            // Both PostgreSQL (23505) and SQLite (23000 / "UNIQUE constraint failed") qualify.
            $this->assertThat(
                $e->getMessage(),
                $this->logicalOr(
                    $this->stringContains('23505'),
                    $this->stringContains('unique constraint'),
                    $this->stringContains('UNIQUE constraint failed'),
                    $this->stringContains('Duplicate entry'),
                )
            );
        }

        $this->assertTrue($threw,
            'Expected a QueryException for duplicate (listing_type, listing_id, version).');
    }

    // =========================================================================
    // Case Q — Audit command "Failed Snapshots" section reports failed rows
    // =========================================================================

    public function test_case_Q_audit_reports_failed_snapshots(): void
    {
        // Seed a known failed snapshot so the section has data to report.
        AskAiKnowledgeSnapshot::create([
            'listing_type'  => 'seller',
            'listing_id'    => 420012,
            'version'       => 1,
            'status'        => 'failed',
            'error_message' => 'Test failure for audit Q',
            'built_at'      => null,
        ]);

        $exitCode = Artisan::call('ask-ai:snapshot-audit');
        $output   = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Failed Snapshots', $output);
        $this->assertStringContainsString('failed snapshot', $output);
    }

    // =========================================================================
    // Case R — Audit command "Phantom Keys" section reports 0 when keys are valid
    // =========================================================================

    public function test_case_R_audit_phantom_keys_section_reports_zero_when_clean(): void
    {
        // Seed only valid-key facts so the phantom-key section detects zero.
        $snapshot = $this->seedSnapshot('seller', 420013);

        AskAiFact::create([
            'snapshot_id'   => $snapshot->id,
            'canonical_key' => 'address',
            'value'         => '10 Clean St',
            'visibility'    => 'public_allowed',
        ]);

        $exitCode = Artisan::call('ask-ai:snapshot-audit');
        $output   = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Phantom', $output);
        $this->assertStringContainsString('No phantom canonical keys found', $output);
    }

    // =========================================================================
    // Case S — Audit command "Counts by Role" section outputs all four roles
    // =========================================================================

    public function test_case_S_audit_counts_by_role_outputs_all_roles(): void
    {
        $exitCode = Artisan::call('ask-ai:snapshot-audit');
        $output   = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('seller',   $output);
        $this->assertStringContainsString('buyer',    $output);
        $this->assertStringContainsString('landlord', $output);
        $this->assertStringContainsString('tenant',   $output);
    }

    // =========================================================================
    // Case T — SnapshotFactVisibility::classify() and fact visibility in DB
    //
    // Part 1: Unit — restricted keys return 'restricted', others 'public_allowed'.
    // Part 2: Integration — restricted keys in the context are stored with
    //         visibility='restricted' in ask_ai_facts after build().
    // =========================================================================

    public function test_case_T_fact_visibility_classification(): void
    {
        // --- Part 1: SnapshotFactVisibility::classify() unit assertions ---

        $restrictedKeys = [
            'flood_zone_code',
            'flood_zone_designation',
            'security_deposit',
            'income_requirement',
            'hoa_monthly_fee',
            'cdd_annual_amount',
            'rental_price',
            'max_rent',
        ];

        foreach ($restrictedKeys as $key) {
            $this->assertEquals('restricted', SnapshotFactVisibility::classify($key),
                "Key '{$key}' should be classified as 'restricted'.");
        }

        $publicKeys = ['address', 'bedrooms', 'bathrooms', 'description', 'asking_price'];

        foreach ($publicKeys as $key) {
            $this->assertEquals('public_allowed', SnapshotFactVisibility::classify($key),
                "Key '{$key}' should be classified as 'public_allowed'.");
        }

        // --- Part 2: Integration — restricted key stored with correct visibility ---

        $service = $this->makeServiceWith($this->makeContextBuilderReturning([
            'listing' => [
                'address'          => '99 Public Rd',
                'security_deposit' => '1500',
                'flood_zone_code'  => 'AE',
            ],
            'faq_answers' => [],
        ]));

        $snapshot = $service->build('landlord', 420014);

        $this->assertEquals('ready', $snapshot->status);

        $restrictedFact = $snapshot->facts()
            ->where('canonical_key', 'security_deposit')
            ->first();

        $this->assertNotNull($restrictedFact, 'security_deposit fact must be persisted.');
        $this->assertEquals('restricted', $restrictedFact->visibility,
            'security_deposit must be stored with visibility=restricted.');
        $this->assertEquals('compliance_sensitive', $restrictedFact->classification,
            'restricted facts must have classification=compliance_sensitive.');
        $this->assertFalse($restrictedFact->public_allowed,
            'restricted facts must have public_allowed=false.');
        $this->assertTrue($restrictedFact->restricted,
            'restricted facts must have restricted=true.');

        $publicFact = $snapshot->facts()
            ->where('canonical_key', 'address')
            ->first();

        $this->assertNotNull($publicFact, 'address fact must be persisted.');
        $this->assertEquals('public_allowed', $publicFact->visibility,
            'address must be stored with visibility=public_allowed.');
        $this->assertEquals('public_factual', $publicFact->classification,
            'public facts must have classification=public_factual.');
        $this->assertTrue($publicFact->public_allowed,
            'public facts must have public_allowed=true.');
        $this->assertFalse($publicFact->restricted,
            'public facts must have restricted=false.');
    }
}
