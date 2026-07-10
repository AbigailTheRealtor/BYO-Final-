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

    /** Shared between the pgsql statement and the SQLite fallback so they cannot drift. */
    private const INDEX_NAME = 'bridge_properties_is_permanent_status_idx';

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
        // Driver-aware, per 2026_07_05_000001_add_lookup_indexes_to_bridge_properties: the
        // COLUMN above is created on every driver — that is what the schema and the model
        // depend on — while CREATE INDEX CONCURRENTLY, which SQLite has no syntax for,
        // falls back to the standard Schema builder. This index carries no WHERE predicate,
        // so the fallback is an exact equivalent rather than an approximation.
        if (! $this->isPostgres()) {
            Schema::table('bridge_properties', function (Blueprint $table) {
                $table->index(['is_permanent', 'standard_status'], self::INDEX_NAME);
            });

            return;
        }

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_is_permanent_status_idx
            ON bridge_properties (is_permanent, standard_status)');
    }

    public function down()
    {
        // Symmetric guard on the index only. The column drop must still happen on every
        // driver, so it sits outside the guard.
        if ($this->isPostgres()) {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_is_permanent_status_idx');
        } else {
            Schema::table('bridge_properties', function (Blueprint $table) {
                $table->dropIndex(self::INDEX_NAME);
            });
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
