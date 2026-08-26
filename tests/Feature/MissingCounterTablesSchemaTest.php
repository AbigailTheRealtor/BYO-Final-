<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The three counter tables that no migration created must exist after migrating.
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * Five migrations in the 2025_09_29_1527xx family have a gutted `up()` — a guard
 * clause and a bare `return`, with the real body stranded in an uncalled
 * `up_original()`. They run, throw nothing, and are recorded as successful while
 * creating nothing. Two were rescued by 2026_04_02_011954; three were not, and
 * were missing from fresh installs AND from production.
 *
 * A migration that silently does nothing cannot be caught at the migration layer:
 * Laravel records success on the absence of an exception, and a no-op `up()` is
 * indistinguishable from a legitimately-skipped driver guard (this repo has ten
 * valid examples of those). The only durable check is to assert the OUTCOME,
 * which is what this file does.
 *
 * These assertions are deliberately about schema, not behaviour, so they hold on
 * every engine and stay meaningful even if the counter features change shape.
 */
class MissingCounterTablesSchemaTest extends TestCase
{
    use DatabaseTransactions;

    /** The three tables repaired by 2026_08_26_000001_create_missing_counter_tables_fix. */
    private const REPAIRED = [
        'landlord_counter_bidding',
        'landlord_counter_bidding_meta',
        'tenant_counter_bidding_meta',
    ];

    /** @test */
    public function the_repaired_counter_tables_exist(): void
    {
        foreach (self::REPAIRED as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "`{$table}` does not exist after migrating. A migration reported success without creating it."
            );
        }
    }

    /** @test */
    public function the_landlord_counter_bidding_table_has_its_expected_columns(): void
    {
        $expected = [
            'id', 'user_id', 'landlord_agent_auction_id', 'landlord_agent_auction_bid_id',
            'property_type', 'parent_counter_id', 'accepted', 'accepted_date',
            'created_at', 'updated_at',
        ];

        foreach ($expected as $column) {
            $this->assertTrue(
                Schema::hasColumn('landlord_counter_bidding', $column),
                "`landlord_counter_bidding`.`{$column}` is missing."
            );
        }
    }

    /** @test */
    public function both_meta_tables_have_the_key_value_shape_their_models_expect(): void
    {
        foreach (['landlord_counter_bidding_meta', 'tenant_counter_bidding_meta'] as $table) {
            foreach (['id', 'counter_bidding_id', 'meta_key', 'meta_value', 'created_at', 'updated_at'] as $column) {
                $this->assertTrue(
                    Schema::hasColumn($table, $column),
                    "`{$table}`.`{$column}` is missing — saveMeta()/getMeta() depend on it."
                );
            }
        }
    }

    /**
     * @test
     *
     * The foreign key targets are the point of this migration, not incidental:
     * the stranded historical body pointed the landlord bid key at
     * `landlord_auction_bids`, a legacy table that does not exist, so restoring
     * that body verbatim would have failed outright.
     */
    public function the_foreign_keys_point_at_the_tables_that_actually_exist(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'sqlite') {
            $this->markTestSkipped('Foreign-key introspection here is written for SQLite; the pgsql gates cover that engine.');
        }

        $expected = [
            'landlord_counter_bidding' => [
                'user_id'                       => 'users',
                'landlord_agent_auction_id'     => 'landlord_agent_auctions',
                'landlord_agent_auction_bid_id' => 'landlord_agent_auction_bids',
            ],
            'landlord_counter_bidding_meta' => [
                'counter_bidding_id' => 'landlord_counter_bidding',
            ],
            'tenant_counter_bidding_meta' => [
                'counter_bidding_id' => 'tenant_counter_bidding',
            ],
        ];

        foreach ($expected as $table => $keys) {
            $actual = [];
            foreach (DB::select("PRAGMA foreign_key_list({$table})") as $fk) {
                $actual[$fk->from] = $fk->table;
            }

            foreach ($keys as $column => $target) {
                $this->assertArrayHasKey(
                    $column,
                    $actual,
                    "`{$table}`.`{$column}` has no foreign key."
                );
                $this->assertSame(
                    $target,
                    $actual[$column],
                    "`{$table}`.`{$column}` references `{$actual[$column]}`, expected `{$target}`."
                );
            }
        }
    }

    /**
     * @test
     *
     * Guards the idempotency claim the migration rests on: it must be safe to run
     * against a database that already has these tables, which is every existing
     * install including production.
     */
    public function the_corrective_migration_is_guarded_by_has_table_checks(): void
    {
        $path = database_path('migrations/2026_08_26_000001_create_missing_counter_tables_fix.php');

        $this->assertFileExists($path);
        $source = file_get_contents($path);

        foreach (self::REPAIRED as $table) {
            $this->assertStringContainsString(
                "! Schema::hasTable('{$table}')",
                $source,
                "The create for `{$table}` is not guarded by Schema::hasTable(), so the migration is not idempotent."
            );
        }
    }
}
