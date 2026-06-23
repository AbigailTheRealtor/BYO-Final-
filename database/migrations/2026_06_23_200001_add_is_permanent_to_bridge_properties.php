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
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS bridge_properties_is_permanent_status_idx
            ON bridge_properties (is_permanent, standard_status)');
    }

    public function down()
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS bridge_properties_is_permanent_status_idx');

        Schema::table('bridge_properties', function (Blueprint $table) {
            $table->dropColumn('is_permanent');
        });
    }
}
