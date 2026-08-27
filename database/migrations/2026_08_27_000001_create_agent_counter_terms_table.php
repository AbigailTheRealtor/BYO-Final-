<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create `agent_counter_terms`, which no Laravel migration has ever created.
 *
 * WHY THE TABLE IS MISSING
 * ------------------------
 * Unlike the five stranded 2025_09_29 migrations that PR #92 dealt with, there is
 * no neutered or deleted migration to recover here: `git log --all --diff-filter=A`
 * over `*agent_counter_terms*` returns nothing. The table only ever existed as a
 * hand-made table in the pre-migration (phpMyAdmin/MariaDB) era, and its definition
 * survives solely inside `database/byo2.sql`, the September 2024 dump. When the
 * schema became migration-managed the table was simply never ported, so every
 * install created since has been missing it.
 *
 * Confirmed read-only against production rather than inferred from migrations:
 * `agent_counter_terms` does not exist there either.
 *
 * WHAT BREAKS
 * -----------
 * The four `agent.*-counter-terms` routes are live behind
 * web → auth → verified → agentAuth, and `AgentCounteredTermsController::store()`
 * ends in `$counter->save()`. Authorization is correct and already denies
 * non-parties with 403 (see AgentCounteredTermsAuthorizationTest), but a
 * LEGITIMATE party clears that guard and then hits
 *
 *     SQLSTATE[HY000]: no such table: agent_counter_terms
 *
 * so the feature has never been able to persist anything. The `agent_counter_terms.add`
 * and `.edit` Blade views exist (627 and 747 lines) and post to these endpoints with
 * field names matching the controller exactly, so this is an unfinished deployment of
 * a real feature rather than a dead Gen-1 path like the Seller/Landlord `store()`
 * methods that were retired — those had no view posting to them at all.
 *
 * SCHEMA SOURCE
 * -------------
 * Taken from the 2024 dump and cross-checked column-for-column against what the
 * controller actually writes. Nothing is added speculatively:
 *
 *   * NO `user_id` — `store()` never sets one and the historical table had none.
 *   * NO `parent_counter_id` — this flow has no counter-back chain.
 *   * NO meta table — `AgentCounterTerm` declares no meta relation, the dump has
 *     no such table, and production has none. Creating one would invent semantics
 *     that no code reads.
 *
 * Two deliberate departures from the 2024 definition, both defect repairs:
 *
 *   1. A REAL primary key. The dump's `id int(255) NOT NULL` carried no PRIMARY KEY
 *      and no AUTO_INCREMENT, which is why its own data contains duplicate `id = 0`
 *      rows. `update()` does `findOrFail($id)`, so a table without a usable key is
 *      not serviceable.
 *   2. A foreign key on `agent_auction_id`. The dump had none. The column name is
 *      historical shorthand: there has never been an `agent_auctions` table, and
 *      both `store()` and `update()` resolve it with
 *      `AgentServiceAuction::find($counter->agent_auction_id)` — so it references
 *      `agent_service_auctions.id`. Kept nullable exactly as the dump had it, so
 *      the constraint only binds a non-null value.
 *
 * NO BACKFILL IS REQUIRED. Verified read-only in production: `agent_service_auctions`
 * and `agent_service_auction_bids` both hold zero rows, and the only bare legacy
 * `counter_terms` table is Buyer-shaped (`buyer_auction_id`, `commission`), holds zero
 * rows, and carries none of the agent columns. There is no agent counter data anywhere
 * to migrate.
 *
 * Guarded with hasTable() in the same style as
 * 2026_08_26_000001_create_missing_counter_tables_fix, so it is a no-op on any
 * database where the table has been restored by other means.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('agent_counter_terms')) {
            return;
        }

        Schema::create('agent_counter_terms', function (Blueprint $table) {
            $table->id();

            // References agent_service_auctions.id despite the historical name.
            // Nullable as in the 2024 definition; the FK binds only non-null values.
            $table->unsignedBigInteger('agent_auction_id')->nullable();

            $table->string('timeframe')->nullable();
            $table->string('agentFee')->nullable();
            $table->string('agentFeeOther')->nullable();
            $table->string('agentCharge')->nullable();
            $table->string('agentChargeOther')->nullable();

            // json_encode()d array from the `services[]` multi-select.
            $table->text('services')->nullable();
            $table->string('other_services')->nullable();
            $table->text('additionalDetails')->nullable();

            // tinyint default 1, matching the 2024 definition and what store() writes.
            $table->tinyInteger('status')->default(1);

            $table->timestamps();

            $table->foreign('agent_auction_id')
                ->references('id')
                ->on('agent_service_auctions')
                ->onDelete('cascade');

            $table->index('agent_auction_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('agent_counter_terms');
    }
};
