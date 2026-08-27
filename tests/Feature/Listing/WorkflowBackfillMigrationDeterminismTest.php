<?php

namespace Tests\Feature\Listing;

use App\Models\SellerAgentAuction;
use App\Services\Listing\ListingWorkflowResolver;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * The backfill migration means today exactly what it will mean in five years.
 *
 * THE PROBLEM, AND WHY THE FIRST FIX WAS NOT ENOUGH
 * -------------------------------------------------
 * `2026_08_27_000003` originally called {@see ListingWorkflowResolver}, so that a row's
 * product meant the same thing to the backfill and to the runtime guard. The price was a
 * historical migration whose behaviour was defined by mutable application code: change a
 * rule in six months and `migrate:fresh` from an empty database would classify rows by the
 * NEW rules while presenting itself as the migration shipped in August 2026.
 *
 * The first mitigation was a version constant the migration pinned and compared. It caught
 * drift only when a developer remembered to bump it — detection by convention. The failure
 * mode survived; it just needed an oversight rather than an intention.
 *
 * The migration now carries a FROZEN classifier and references no application code at all.
 * This file proves that, and proves it the only way worth proving: not by reading the
 * source and hoping, but by putting a resolver in the container that would give visibly
 * different answers, running the migration, and showing the answers did not move.
 *
 * WHAT THIS FILE ASSERTS
 * ----------------------
 *   1. The migration reaches nothing in `app/`.
 *   2. A hostile runtime resolver cannot change a single row it writes.
 *   3. The frozen rules produce the intended buckets, including every refusal.
 *   4. `000002::down()` is non-destructive.
 *   5. `000001::down()` removes its own schema and nothing else.
 */
class WorkflowBackfillMigrationDeterminismTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    private const SCHEMA_MIGRATION   = '2026_08_27_000002_add_workflow_type_to_agent_auction_tables.php';
    private const BACKFILL_MIGRATION = '2026_08_27_000003_backfill_workflow_type_on_agent_auction_tables.php';

    protected function setUp(): void
    {
        parent::setUp();
        ListingWorkflow::forgetSchemaMemo();
    }

    private function migrationPath(string $file): string
    {
        return database_path('migrations/' . $file);
    }

    /** Load a migration file and return its anonymous-class instance. */
    private function migration(string $file): object
    {
        return require $this->migrationPath($file);
    }

    private function backfillSource(): string
    {
        return file_get_contents($this->migrationPath(self::BACKFILL_MIGRATION));
    }

    /**
     * The migration's source with all comments removed.
     *
     * The forbidden-reference scan runs against THIS, not the raw file, and the
     * distinction is deliberate. The migration's header explains at length why it no
     * longer calls `\App\Services\Listing\ListingWorkflowResolver` — naming the class it
     * broke away from is the clearest way to say it, and a scan that forbade the prose
     * would push the next developer into writing a vaguer comment to satisfy a test. What
     * must be absent is a REFERENCE the PHP engine can follow. Comments cannot be
     * followed; code can. So the comments are stripped and the code is what is judged.
     */
    private function backfillCode(): string
    {
        $out = '';

        foreach (token_get_all($this->backfillSource()) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $out .= $token[1];

                continue;
            }

            $out .= $token;
        }

        return $out;
    }

    /** Strip the native stamp so a fixture presents as a pre-backfill row. */
    private function unstampColumn($row): void
    {
        SellerAgentAuction::query()->whereKey($row->id)->update([ListingWorkflow::COLUMN => null]);
    }

    private function columnOf(int $id): ?string
    {
        return DB::table('seller_agent_auctions')->where('id', $id)->value(ListingWorkflow::COLUMN);
    }

    // ── 1. The migration is self-contained ─────────────────────────────────────

    /**
     * It imports and references NOTHING from `app/`.
     *
     * A source-level assertion, which is exactly the right shape here: the property being
     * protected IS "this file does not reach application code", and the cheapest way to
     * break it is for someone to add an import. The behavioural proof follows in the next
     * test; this one catches the mistake at the moment it is made.
     */
    public function test_the_backfill_migration_references_no_application_code(): void
    {
        $code = $this->backfillCode();

        foreach ([
            'App\\Services',
            'App\\Models',
            'App\\Support',
            'App\\Http',
            'ListingWorkflowResolver',
            'ListingWorkflowBackfiller',
            'ListingWorkflowClassification',
            'ListingWorkflow::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $code,
                "the frozen migration must not reach [{$forbidden}] — a historical migration "
                . 'whose meaning depends on mutable application code is not deterministic'
            );
        }
    }

    /**
     * It carries its own rules, rather than having simply stopped classifying.
     *
     * The control for the test above: "references no app code" is trivially satisfiable by
     * a migration that does nothing at all, so the evidence keys must actually be present.
     */
    public function test_the_backfill_migration_carries_its_own_frozen_rules(): void
    {
        $source = $this->backfillSource();

        foreach (['mls_quick_import', 'service_type', 'hire_agent', 'offer_listing'] as $token) {
            $this->assertStringContainsString("'{$token}'", $source,
                'the frozen classifier must spell out its own vocabulary');
        }

        $this->assertStringContainsString('DO NOT MODIFY', $source,
            'the freeze must be stated where the next developer will read it');
    }

    // ── 2. The behavioural proof ───────────────────────────────────────────────

    /**
     * A RUNTIME RESOLVER THAT DISAGREES CANNOT MOVE A SINGLE ROW.
     *
     * This is the assertion the whole hardening exists for. The container is given a
     * resolver that classifies EVERYTHING as hire_agent — a drastic, deliberately wrong
     * rule change of exactly the kind a future developer might introduce. If the migration
     * consulted the container in any way, the quick-import row below would come out
     * hire_agent and the orphan would come out hire_agent too.
     *
     * Both come out as the frozen rules say. Note this test needs no version constant, no
     * skip path and no developer discipline: it fails the moment anyone re-points this
     * migration at application code, whatever they call the version.
     */
    public function test_a_hostile_runtime_resolver_cannot_change_what_the_migration_writes(): void
    {
        $user = $this->makeUser();

        $quickImport = $this->makeLegacyQuickImportDraft('seller', $user->id);
        $legacyHire  = $this->makeLegacyHireDraft('seller', $user->id);
        $orphan      = $this->makeUnstamped('seller', $user->id, true);

        foreach ([$quickImport, $legacyHire, $orphan] as $row) {
            $this->unstampColumn($row);
        }

        // Everything is hire_agent, says the future.
        $hostile = new class extends ListingWorkflowResolver {
            public const CLASSIFICATION_VERSION = 'hostile-future-rules';

            public function classify($auction): \App\Services\Listing\ListingWorkflowClassification
            {
                return \App\Services\Listing\ListingWorkflowClassification::resolved(
                    ListingWorkflow::HIRE_AGENT,
                    ['hostile' => 'everything is hire_agent']
                );
            }
        };

        $this->app->instance(ListingWorkflowResolver::class, $hostile);
        $this->app->bind(ListingWorkflowResolver::class, fn () => $hostile);

        // Sanity: the double really would classify these rows differently.
        $this->assertTrue(
            app(ListingWorkflowResolver::class)->matches($orphan, ListingWorkflow::HIRE_AGENT),
            'precondition: the hostile resolver calls even an evidence-free row hire_agent'
        );

        $this->migration(self::BACKFILL_MIGRATION)->up();

        $this->assertSame(ListingWorkflow::OFFER_LISTING, $this->columnOf((int) $quickImport->id),
            'quick-import provenance must still decide offer_listing, not the container resolver');
        $this->assertSame(ListingWorkflow::HIRE_AGENT, $this->columnOf((int) $legacyHire->id),
            'service_type provenance must still decide hire_agent by the frozen rule');
        $this->assertNull($this->columnOf((int) $orphan->id),
            'an evidence-free row must stay NULL even while a resolver stands ready to name it');
    }

    /**
     * THE EXACT FAILURE THE VERSION PIN COULD NOT CATCH.
     *
     * The test above uses a double that announces itself, changing both the rules AND the
     * version string. The old migration declined on that — the pin worked, in the one case
     * where the developer remembered.
     *
     * This is the other case, and it is the realistic one: rules changed, version constant
     * NOT bumped. The double below classifies every row as hire_agent while still
     * declaring `2026-08-27.1`, exactly what an ordinary edit to the resolver that
     * overlooked the constant would produce. Against the old migration the evidence-free
     * orphan below would have been silently written `hire_agent` — a fresh install
     * inventing an identity for a row that has none, under an August 2026 migration name,
     * with nothing in the run to reveal it.
     *
     * Against the frozen migration it cannot happen, and no constant has to be remembered
     * for that to be true.
     */
    public function test_a_silently_drifted_resolver_cannot_change_what_the_migration_writes(): void
    {
        $user = $this->makeUser();

        $quickImport = $this->makeLegacyQuickImportDraft('seller', $user->id);
        $orphan      = $this->makeUnstamped('seller', $user->id, true);

        foreach ([$quickImport, $orphan] as $row) {
            $this->unstampColumn($row);
        }

        // Rules changed. Version constant NOT bumped — the developer forgot.
        $drifted = new class extends ListingWorkflowResolver {
            public function classify($auction): \App\Services\Listing\ListingWorkflowClassification
            {
                return \App\Services\Listing\ListingWorkflowClassification::resolved(
                    ListingWorkflow::HIRE_AGENT,
                    ['drifted' => 'everything is hire_agent now']
                );
            }
        };

        $this->assertSame(
            ListingWorkflowResolver::CLASSIFICATION_VERSION,
            $drifted::CLASSIFICATION_VERSION,
            'precondition: this double is INDISTINGUISHABLE by version — that is the point'
        );

        $this->app->instance(ListingWorkflowResolver::class, $drifted);
        $this->app->bind(ListingWorkflowResolver::class, fn () => $drifted);

        $this->migration(self::BACKFILL_MIGRATION)->up();

        $this->assertNull($this->columnOf((int) $orphan->id),
            'an evidence-free row must stay NULL — a drifted resolver must not be able to '
            . 'invent an identity for it through a historical migration');
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $this->columnOf((int) $quickImport->id),
            'and the frozen rule must still decide this one, not the drifted resolver');
    }

    // ── 3. The frozen rules themselves ─────────────────────────────────────────

    public function test_the_backfill_classifies_what_it_can_and_guesses_nothing(): void
    {
        $user = $this->makeUser();

        $quickImport = $this->makeLegacyQuickImportDraft('seller', $user->id);
        $legacyHire  = $this->makeLegacyHireDraft('seller', $user->id);
        $orphan      = $this->makeUnstamped('seller', $user->id, true);
        $stamped     = $this->makeUnstamped('seller', $user->id, true, [
            ListingWorkflow::META_KEY => ListingWorkflow::OFFER_LISTING,
        ]);

        foreach ([$quickImport, $legacyHire, $orphan, $stamped] as $row) {
            $this->unstampColumn($row);
        }

        $this->migration(self::BACKFILL_MIGRATION)->up();

        $this->assertSame(ListingWorkflow::OFFER_LISTING, $this->columnOf((int) $quickImport->id));
        $this->assertSame(ListingWorkflow::HIRE_AGENT, $this->columnOf((int) $legacyHire->id));
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $this->columnOf((int) $stamped->id),
            'the legacy EAV stamp is decisive on its own');
        $this->assertNull($this->columnOf((int) $orphan->id),
            'a row with no evidence must be left NULL, never guessed');
    }

    /** Both products' fingerprints on one row: refused, not resolved. */
    public function test_conflicting_evidence_is_left_null(): void
    {
        $user = $this->makeUser();

        $both = $this->makeUnstamped('seller', $user->id, true, [
            'mls_quick_import' => '1',
            'service_type'     => 'full_service',
        ]);
        $stampVsProvenance = $this->makeUnstamped('seller', $user->id, true, [
            ListingWorkflow::META_KEY => ListingWorkflow::HIRE_AGENT,
            'mls_quick_import'        => '1',
        ]);

        $this->migration(self::BACKFILL_MIGRATION)->up();

        $this->assertNull($this->columnOf((int) $both->id),
            'quick-import + service_type is the corruption signature, not a tiebreak');
        $this->assertNull($this->columnOf((int) $stampVsProvenance->id),
            'an identity contradicting its provenance must fail closed here too');
    }

    public function test_an_unrecognised_stamp_value_is_left_null(): void
    {
        $user = $this->makeUser();

        $weird = $this->makeUnstamped('seller', $user->id, true, [
            ListingWorkflow::META_KEY => 'something_else_entirely',
        ]);

        $this->migration(self::BACKFILL_MIGRATION)->up();

        $this->assertNull($this->columnOf((int) $weird->id),
            'an unrecognised workflow value is never guessed past');
    }

    public function test_the_backfill_is_idempotent_and_does_not_restamp(): void
    {
        $user        = $this->makeUser();
        $quickImport = $this->makeLegacyQuickImportDraft('seller', $user->id);
        $this->unstampColumn($quickImport);

        $migration = $this->migration(self::BACKFILL_MIGRATION);
        $migration->up();
        $first = $this->columnOf((int) $quickImport->id);
        $migration->up();

        $this->assertSame($first, $this->columnOf((int) $quickImport->id),
            're-running must be a no-op, not a re-decision');

        // A row already stamped the "other" way is not revisited: the WHERE NULL filter is
        // what makes a partially-completed run resumable without second-guessing itself.
        $hire = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true, [
            'mls_quick_import' => '1',
        ]);
        $migration->up();

        $this->assertSame(ListingWorkflow::HIRE_AGENT, $this->columnOf((int) $hire->id),
            'a row that already has a value is never touched, whatever its meta says');
    }

    // ── 4. Rollback A — the data migration is non-destructive ──────────────────

    /**
     * `000002::down()` MUST NOT TOUCH ROW DATA.
     *
     * The previous implementation nulled `workflow_type` across all four tables. By the
     * time anyone rolls back, that column also holds identities written by ordinary
     * runtime operation — every wizard save, Quick Import and draft version since deploy
     * stamps it — and nothing distinguishes those from the ones the backfill wrote. The
     * blanket null erased live data this migration never created.
     *
     * Both shapes are covered below in one run, because the distinction is the entire
     * point: a `down()` that spared only backfilled values would be just as wrong.
     */
    public function test_backfill_rollback_does_not_null_any_workflow_value(): void
    {
        $user = $this->makeUser();

        // (a) A value this migration itself wrote.
        $backfilled = $this->makeLegacyQuickImportDraft('seller', $user->id);
        $this->unstampColumn($backfilled);
        $this->migration(self::BACKFILL_MIGRATION)->up();
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $this->columnOf((int) $backfilled->id),
            'precondition: the backfill populated this row');

        // (b) A value written afterwards by ordinary runtime stamping.
        $runtime = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true, [
            'listing_title' => 'runtime-written',
        ]);
        $this->assertSame(ListingWorkflow::HIRE_AGENT, $this->columnOf((int) $runtime->id),
            'precondition: runtime stamping populated this row');

        $metaTable = 'seller_agent_auction_metas';
        $metaBefore = DB::table($metaTable)
            ->whereIn('seller_agent_auction_id', [$backfilled->id, $runtime->id])->count();
        $rowsBefore = DB::table('seller_agent_auctions')->count();

        $this->migration(self::BACKFILL_MIGRATION)->down();

        $this->assertSame(ListingWorkflow::OFFER_LISTING, $this->columnOf((int) $backfilled->id),
            'rollback must not null a backfilled workflow value');
        $this->assertSame(ListingWorkflow::HIRE_AGENT, $this->columnOf((int) $runtime->id),
            'rollback must not null a runtime-written workflow value');

        $this->assertSame($metaBefore, DB::table($metaTable)
            ->whereIn('seller_agent_auction_id', [$backfilled->id, $runtime->id])->count(),
            'rollback must not alter EAV metadata');
        $this->assertSame($rowsBefore, DB::table('seller_agent_auctions')->count(),
            'rollback must not delete rows');
        $this->assertSame('Fixture Title',
            DB::table('seller_agent_auctions')->where('id', $runtime->id)->value('title'),
            'rollback must not change unrelated columns');
    }

    /** And the column itself is still there — down() changed no schema either. */
    public function test_backfill_rollback_leaves_the_schema_alone(): void
    {
        $this->migration(self::BACKFILL_MIGRATION)->down();
        ListingWorkflow::forgetSchemaMemo();

        $this->assertTrue(
            Schema::hasColumn('seller_agent_auctions', ListingWorkflow::COLUMN),
            'the data migration does not own the column and must not remove it — '
            . '2026_08_27_000002 does'
        );
    }

    /** Rolling the data migration back and re-applying is clean. */
    public function test_backfill_can_be_rolled_back_and_reapplied(): void
    {
        $user        = $this->makeUser();
        $quickImport = $this->makeLegacyQuickImportDraft('seller', $user->id);
        $this->unstampColumn($quickImport);

        $migration = $this->migration(self::BACKFILL_MIGRATION);
        $migration->up();
        $migration->down();
        $migration->up();

        $this->assertSame(ListingWorkflow::OFFER_LISTING, $this->columnOf((int) $quickImport->id));
    }

    // ── 5. Rollback B — the schema migration owns the undo ─────────────────────

    /**
     * `000001::down()` removes the column and index, and nothing else.
     *
     * This is the meaningful undo: it takes every `workflow_type` value with it, which is
     * correct, because the column is what introduced them. Asserted on a row's ordinary
     * data because the risk with a `down()` that loops four tables is not that it fails —
     * it is that it takes something it was never asked to take.
     */
    public function test_schema_rollback_removes_only_the_workflow_column(): void
    {
        $user    = $this->makeUser();
        $listing = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true, [
            'listing_title' => 'survives-rollback',
        ]);

        $metaRelation = (new SellerAgentAuction())->meta();
        $metaTable    = $metaRelation->getRelated()->getTable();
        $metaFk       = $metaRelation->getForeignKeyName();
        $metaBefore   = DB::table($metaTable)->where($metaFk, $listing->id)->count();

        $this->migration(self::SCHEMA_MIGRATION)->down();
        ListingWorkflow::forgetSchemaMemo();

        $this->assertFalse(
            Schema::hasColumn('seller_agent_auctions', ListingWorkflow::COLUMN),
            'down() must drop the column it added'
        );

        $after = SellerAgentAuction::find($listing->id);

        $this->assertNotNull($after, 'the listing row itself must survive a rollback');
        $this->assertSame('Fixture Title', $after->title, 'unrelated columns must be untouched');
        $this->assertSame($metaBefore, DB::table($metaTable)->where($metaFk, $listing->id)->count(),
            'a rollback must not remove meta — including the EAV workflow stamp, which is '
            . 'the only identity a pre-column reader has');

        // Put the schema back for whatever runs next in this process.
        $this->migration(self::SCHEMA_MIGRATION)->up();
        ListingWorkflow::forgetSchemaMemo();
    }

    /** A fresh install reaches the same schema shape the column-add describes. */
    public function test_the_column_add_is_idempotent(): void
    {
        $migration = $this->migration(self::SCHEMA_MIGRATION);

        $migration->up();
        $migration->up();

        ListingWorkflow::forgetSchemaMemo();

        $this->assertTrue(
            Schema::hasColumn('seller_agent_auctions', ListingWorkflow::COLUMN),
            're-running the column add must be a safe no-op, not a duplicate-column error'
        );
    }

    /** The resolver still publishes a rules marker — descriptive now, not load-bearing. */
    public function test_the_resolver_still_publishes_a_classification_version(): void
    {
        $this->assertTrue(defined(ListingWorkflowResolver::class . '::CLASSIFICATION_VERSION'));
        $this->assertNotSame('', trim(ListingWorkflowResolver::CLASSIFICATION_VERSION));
    }
}
