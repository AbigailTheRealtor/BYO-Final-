<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIsPermanentToBridgeProperties extends Migration
{
    /**
     * Disable transaction wrapper — CREATE INDEX CONCURRENTLY cannot run
     * inside a PostgreSQL transaction.
     */
    public $withinTransaction = false;

    public function up()
    {
        Schema::table('bridge_properties', function (Blueprint $table) {
            // Permanent-retention flag.
            //
            // When true, this record must NEVER be deleted by bulk-cleanup or
            // eviction jobs. The flag is set programmatically only (e.g. by a
            // future admin command); there is no UI for it.
            //
            // All existing rows default to false — meaning no existing row is
            // protected by this flag after this migration runs. Any future
            // eviction logic must query WHERE is_permanent = false to respect
            // this contract. See BridgeProperty::$is_permanent docblock.
            $table->boolean('is_permanent')->default(false)->nullable(false);
        });

        // Composite index supports fast "active permanent" queries:
        // WHERE is_permanent = true AND standard_status = 'Active'
        //
        // PostgreSQL-only: CREATE INDEX CONCURRENTLY has no SQLite equivalent.
        //
        // The COLUMN above is created on every driver — that is what the schema and the
        // model depend on. Only the INDEX is skipped on SQLite. An index changes the plan
        // the planner picks, never the rows a query returns, so nothing loses coverage.
        // No SQLite fallback index is created, by project decision; see
        // 2026_06_16_000002_add_phase1_indexes_to_bridge_properties for the full rationale.
        if (! $this->isPostgres()) {
            return;
        }

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_is_permanent_status_idx
            ON bridge_properties (is_permanent, standard_status)');
    }

    public function down()
    {
        // Symmetric guard on the index only: on SQLite up() created no index, so there is
        // nothing to drop. The COLUMN drop must still happen on every driver, so it sits
        // outside the guard.
        if ($this->isPostgres()) {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_is_permanent_status_idx');
        }

        Schema::table('bridge_properties', function (Blueprint $table) {
            $table->dropColumn('is_permanent');
        });
    }

    private function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }
}
