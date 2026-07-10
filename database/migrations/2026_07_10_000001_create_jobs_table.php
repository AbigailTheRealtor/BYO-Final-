<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The database queue substrate: the `jobs` table Laravel's `database` queue connection polls
 * (config/queue.php -> connections.database.table = 'jobs').
 *
 * PORTABLE ON BOTH DRIVERS — no guard. The table body is byte-for-byte Laravel 8.83's canonical
 * `queue:table` stub and uses only portable schema primitives (bigIncrements, string, longText,
 * unsignedTinyInteger, unsignedInteger, a plain index). Both the SQLite and Postgres grammars
 * compile it natively, so it is the real Laravel queue schema on every supported driver, not a
 * SQLite emulation of a PostgreSQL-only construct.
 *
 * This is deliberately NOT guarded like the CONCURRENTLY / partial-index / ALTER-ADD-CONSTRAINT
 * migrations (66dd6260a, f61221c6c): those guard genuinely PostgreSQL-specific behaviour that
 * has no faithful SQLite equivalent. `jobs` has none, so guarding it would create schema
 * divergence between the test harness and production and would deny the isolated async-dispatch
 * verification (Batch 6A, Step 5) a real `jobs` table to persist to under the database driver.
 *
 * Postdates database/schema/pgsql-schema.dump (which contains no `jobs` table), so it executes
 * on a fresh PostgreSQL database after the dump loads.
 */
class CreateJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Removes only the `jobs` table (and its inline `jobs_queue_index`) — no other object.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('jobs');
    }
}
