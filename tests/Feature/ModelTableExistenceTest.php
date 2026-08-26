<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every table an Eloquent model claims to use must exist after migrating.
 *
 * WHY THIS EXISTS
 * ---------------
 * Laravel records a migration as successful on the absence of an exception. A
 * migration whose `up()` does nothing is therefore indistinguishable from one
 * that worked — and this repo had five of them, stranded by an automated edit
 * that moved their bodies into an uncalled `up_original()`. The migration gates
 * reported "257 of 257 migrations executed" while three application tables did
 * not exist, in fresh installs AND in production.
 *
 * That class of false-green cannot be caught at the migration layer, because a
 * no-op `up()` is also exactly what a legitimate driver guard looks like (this
 * repo has ten valid examples, e.g. "skip unless pgsql"). It can only be caught
 * by asserting the OUTCOME.
 *
 * This check derives its expectation from the APPLICATION rather than from the
 * migrations, so it is independent of the thing it is testing. Adding a model
 * whose table no migration creates fails here; so does gutting a migration for a
 * table a model uses. Neither requires anyone to remember to update a list.
 *
 * DELIBERATELY NOT a hand-maintained roster of important table names. Such a list
 * only ever catches the tables someone already thought of — the 17-table smoke
 * list in the PostgreSQL gate did not include any of the three that were missing.
 */
class ModelTableExistenceTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Tables declared by a model that no migration creates, each retained on
     * purpose and each with a stated reason. TEMPORARY: every entry is a real
     * model-to-schema mismatch awaiting its own triage decision (delete the dead
     * model, or create the table). No wildcards — each entry names one table.
     *
     * The three tables repaired by 2026_08_26_000001_create_missing_counter_tables_fix
     * are deliberately NOT listed: this test is part of the proof that they exist.
     */
    private const ALLOWED_MISSING = [
        // Model: App\Models\AgentCounterTerm. Used by AgentCounteredTermsController
        // and by tests/Feature/Security/AgentCounteredTermsAuthorizationTest, which
        // documents the path as inert and skips one assertion with the reason
        // "`agent_counter_terms` has no migration (inert path)". That skip is the
        // single pre-existing skip in the security gate. Retained until the offer
        // counter-terms path is either finished or retired.
        'agent_counter_terms',

        // Models: App\Models\CounterBid, App\Models\CounterBidMeta. Used by
        // CounterBidController, which is still routed (routes/web.php,
        // 'add-counterBiding' / 'counterBiding'). Legacy generic counter-bid path
        // superseded by the per-role counter components. Retained until endpoint
        // retirement (an open queue item) decides its fate.
        'counter_bids',
        'counter_bid_metas',

        // Models: App\Models\LandlordAuctionBidMeta, App\Models\LandlordAuctionMeta,
        // reached through LandlordAuction / LandlordAuctionBid. These belong to the
        // legacy `landlord_auctions` / `landlord_auction_bids` subsystem, whose own
        // tables are also absent. Retained because the models are still referenced
        // by application code; removing them is a separate cleanup.
        'landlord_auction_meta',
        'landlord_auction_bid_meta',
    ];

    /** @test */
    public function every_table_a_model_declares_exists_after_migrating(): void
    {
        $missing = [];

        foreach ($this->declaredTables() as $table => $models) {
            if (in_array($table, self::ALLOWED_MISSING, true)) {
                continue;
            }

            if (! Schema::hasTable($table)) {
                $missing[] = "{$table} (declared by " . implode(', ', $models) . ')';
            }
        }

        $this->assertSame(
            [],
            $missing,
            "A model declares a table that no migration creates:\n  - " . implode("\n  - ", $missing)
                . "\n\nEither a migration is missing or silently does nothing (check for a gutted up()),"
                . ' or the model is dead and should be removed. Do not add it to ALLOWED_MISSING'
                . ' without a stated reason.'
        );
    }

    /**
     * @test
     *
     * Keeps the allowlist honest. An entry that starts existing is no longer an
     * exception and must be removed, or the list quietly becomes a place where
     * real regressions can hide.
     */
    public function the_allowlist_contains_no_stale_entries(): void
    {
        $stale = array_values(array_filter(
            self::ALLOWED_MISSING,
            fn (string $table): bool => Schema::hasTable($table)
        ));

        $this->assertSame(
            [],
            $stale,
            'These tables now exist and must be removed from ALLOWED_MISSING: ' . implode(', ', $stale)
        );
    }

    /**
     * @test
     *
     * The three tables this branch repairs must be proven present, not merely
     * absent from the failure list.
     */
    public function the_repaired_counter_tables_are_not_allowlisted_and_do_exist(): void
    {
        foreach (['landlord_counter_bidding', 'landlord_counter_bidding_meta', 'tenant_counter_bidding_meta'] as $table) {
            $this->assertNotContains(
                $table,
                self::ALLOWED_MISSING,
                "`{$table}` must never be allowlisted — it is the defect this check exists to prove fixed."
            );
            $this->assertTrue(Schema::hasTable($table), "`{$table}` does not exist.");
        }
    }

    /**
     * Map of table name => model class names that declare it via `protected $table`.
     *
     * Only explicit declarations are read. Models relying on Laravel's naming
     * convention are excluded deliberately: deriving a table name from a class
     * name would invent expectations for abstract bases, pivots and models bound
     * to other connections, and a check that cries wolf gets switched off.
     */
    private function declaredTables(): array
    {
        $tables = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $source = file_get_contents($file);

            if (! preg_match("/protected\s+\\\$table\s*=\s*'([^']+)'/", $source, $m)) {
                continue;
            }

            // Models pinned to another connection are outside this schema.
            if (preg_match("/protected\s+\\\$connection\s*=/", $source)) {
                continue;
            }

            $tables[$m[1]][] = basename($file, '.php');
        }

        return $tables;
    }
}
