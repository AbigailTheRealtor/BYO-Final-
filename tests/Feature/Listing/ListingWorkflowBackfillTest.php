<?php

namespace Tests\Feature\Listing;

use App\Services\Listing\ListingWorkflowBackfiller;
use App\Services\Listing\ListingWorkflowClassification;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * The backfill classifies what it can prove and leaves the rest alone — and says so.
 *
 * The unresolved count is the gate on any future NOT NULL enforcement, so the counting has
 * to be trustworthy in both directions: a backfill that guessed would produce a clean
 * inventory and a wrong database.
 */
class ListingWorkflowBackfillTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    private ListingWorkflowBackfiller $backfiller;

    protected function setUp(): void
    {
        parent::setUp();

        ListingWorkflow::forgetSchemaMemo();

        $this->backfiller = app(ListingWorkflowBackfiller::class);
    }

    public function test_migration_added_the_column_to_all_four_tables(): void
    {
        foreach (ListingWorkflow::roleModels() as $role => $modelClass) {
            $this->assertTrue(
                ListingWorkflow::columnAvailable($modelClass),
                "{$role}: the native workflow_type column must exist after migration"
            );
        }
    }

    /**
     * The two new migrations roll back cleanly.
     *
     * Asserted because step 1 creates a named composite index as well as the column, and a
     * `down()` that cannot find its own index leaves a table that will not migrate forward
     * again. Rolled back and re-applied so the rest of the suite is unaffected.
     */
    public function test_the_new_migrations_roll_back_and_reapply(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertExitCode(0);

        ListingWorkflow::forgetSchemaMemo();

        foreach (ListingWorkflow::roleModels() as $role => $modelClass) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Schema::hasColumn((new $modelClass())->getTable(), ListingWorkflow::COLUMN),
                "{$role}: down() must drop the column"
            );
        }

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        ListingWorkflow::forgetSchemaMemo();

        foreach (ListingWorkflow::roleModels() as $role => $modelClass) {
            $this->assertTrue(ListingWorkflow::columnAvailable($modelClass), "{$role}: re-applied");
        }
    }

    /**
     * With the column absent, nothing throws — the resolver simply has one fewer signal.
     *
     * This is the deploy window between "code is live" and "migration has run", and a
     * resolver that threw there would take down every listing screen.
     */
    public function test_the_resolver_still_works_with_the_column_absent(): void
    {
        $user  = $this->makeUser();
        $draft = $this->makeLegacyQuickImportDraft('seller', $user->id);

        $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertExitCode(0);
        ListingWorkflow::forgetSchemaMemo();

        $this->assertSame(
            ListingWorkflow::OFFER_LISTING,
            app(\App\Services\Listing\ListingWorkflowResolver::class)->resolve($draft->fresh()),
            'provenance still classifies the row without the native column'
        );

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        ListingWorkflow::forgetSchemaMemo();
    }

    /** THE SELLER DRAFT 123 CASE, through the backfill rather than the resolver. */
    public function test_backfill_classifies_a_legacy_quick_import_draft_as_offer_listing(): void
    {
        $user  = $this->makeUser();
        $draft = $this->makeLegacyQuickImportDraft('seller', $user->id);

        $this->assertNull($draft->getAttribute(ListingWorkflow::COLUMN));

        $report = $this->backfiller->backfill();

        $this->assertSame(
            ListingWorkflow::OFFER_LISTING,
            $draft->fresh()->getAttribute(ListingWorkflow::COLUMN)
        );
        $this->assertGreaterThanOrEqual(1, $report->totals()[ListingWorkflow::OFFER_LISTING]);
    }

    /** The equivalent Landlord Quick Import defect row. */
    public function test_backfill_classifies_the_landlord_quick_import_row(): void
    {
        $user  = $this->makeUser();
        $draft = $this->makeLegacyQuickImportDraft('landlord', $user->id);

        $this->backfiller->backfill();

        $this->assertSame(
            ListingWorkflow::OFFER_LISTING,
            $draft->fresh()->getAttribute(ListingWorkflow::COLUMN)
        );
    }

    public function test_backfill_classifies_a_legacy_hire_row_from_service_type(): void
    {
        $user = $this->makeUser();
        $row  = $this->makeLegacyHireDraft('buyer', $user->id);

        $this->backfiller->backfill();

        $this->assertSame(ListingWorkflow::HIRE_AGENT, $row->fresh()->getAttribute(ListingWorkflow::COLUMN));
    }

    public function test_backfill_leaves_unclassifiable_rows_null_and_counts_them(): void
    {
        $user   = $this->makeUser();
        $orphan = $this->makeUnstamped('seller', $user->id);

        $report = $this->backfiller->backfill();

        $this->assertNull($orphan->fresh()->getAttribute(ListingWorkflow::COLUMN),
            'an unclassifiable row must keep a NULL column — the backfill does not guess');
        $this->assertGreaterThanOrEqual(1, $report->totals()[ListingWorkflowClassification::UNCLASSIFIED]);
        $this->assertGreaterThanOrEqual(1, $report->unresolvedCount());
    }

    public function test_backfill_leaves_conflicting_rows_null_and_reports_them(): void
    {
        $user = $this->makeUser();
        $row  = $this->makeUnstamped('seller', $user->id, true, [
            'mls_quick_import' => '1',
            'service_type'     => 'full_service',
        ]);

        $report = $this->backfiller->backfill();

        $this->assertNull($row->fresh()->getAttribute(ListingWorkflow::COLUMN));
        $this->assertGreaterThanOrEqual(1, $report->totals()[ListingWorkflowClassification::CONFLICTING]);

        $sampled = collect($report->samples())->firstWhere('id', $row->id);
        $this->assertNotNull($sampled, 'a conflicting row must appear in the work list');
        $this->assertSame(ListingWorkflowClassification::CONFLICTING, $sampled['bucket']);
    }

    public function test_backfill_is_idempotent(): void
    {
        $user  = $this->makeUser();
        $draft = $this->makeLegacyQuickImportDraft('seller', $user->id);

        $first  = $this->backfiller->backfill();
        $second = $this->backfiller->backfill();

        $this->assertSame(ListingWorkflow::OFFER_LISTING, $draft->fresh()->getAttribute(ListingWorkflow::COLUMN));
        $this->assertGreaterThanOrEqual(1, $first->resolvedCount());
        $this->assertSame(0, $second->resolvedCount(),
            'a second pass has nothing left to classify');
    }

    public function test_dry_run_writes_nothing_but_still_counts(): void
    {
        $user  = $this->makeUser();
        $draft = $this->makeLegacyQuickImportDraft('seller', $user->id);

        $report = $this->backfiller->backfill(true);

        $this->assertNull($draft->fresh()->getAttribute(ListingWorkflow::COLUMN),
            'a dry run must not write');
        $this->assertGreaterThanOrEqual(1, $report->totals()[ListingWorkflow::OFFER_LISTING],
            'but it must still report what it would have done');
    }

    /** The backfill never touches an already-stamped row. */
    public function test_backfill_does_not_restamp_a_classified_row(): void
    {
        $user = $this->makeUser();
        $row  = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id);

        $this->backfiller->backfill();

        $this->assertSame(ListingWorkflow::HIRE_AGENT, $row->fresh()->getAttribute(ListingWorkflow::COLUMN));
    }

    // ── Inventory (read-only) ──────────────────────────────────────────────────

    public function test_inventory_writes_nothing(): void
    {
        $user   = $this->makeUser();
        $draft  = $this->makeLegacyQuickImportDraft('seller', $user->id);
        $orphan = $this->makeUnstamped('buyer', $user->id);

        $report = $this->backfiller->inventory();

        $this->assertNull($draft->fresh()->getAttribute(ListingWorkflow::COLUMN),
            'inventory is read-only');
        $this->assertNull($orphan->fresh()->getAttribute(ListingWorkflow::COLUMN));
        $this->assertGreaterThanOrEqual(1, $report->totals()[ListingWorkflow::OFFER_LISTING]);
        $this->assertGreaterThanOrEqual(1, $report->totals()[ListingWorkflowClassification::UNCLASSIFIED]);
    }

    public function test_inventory_command_runs_and_reports(): void
    {
        $user = $this->makeUser();
        $this->makeUnstamped('seller', $user->id);

        $this->artisan('listings:workflow-inventory')
            ->assertExitCode(0);
    }

    public function test_inventory_command_can_backfill_dry_run(): void
    {
        $user  = $this->makeUser();
        $draft = $this->makeLegacyQuickImportDraft('seller', $user->id);

        $this->artisan('listings:workflow-inventory --backfill --dry-run')
            ->assertExitCode(0);

        $this->assertNull($draft->fresh()->getAttribute(ListingWorkflow::COLUMN));
    }
}
