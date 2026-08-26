<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the three counter tables that no migration has ever created.
 *
 * WHY THESE TABLES ARE MISSING
 * ----------------------------
 * Commit 9f9044a92 (2025-12-05, an automated checkpoint) applied a mechanical
 * edit to seven migrations in the 2025_09_29_1527xx family: it inserted a
 * guard-clause `up()` and renamed the real body to `up_original()`. In six of
 * the seven the body ended up OUTSIDE `up()`, so those migrations run, throw
 * nothing, and are recorded as successful while creating no table at all.
 * 1e909f25c later repaired two of them. Five were never repaired:
 *
 *   152714 create_tenant_counter_bidding_meta_table
 *   152715 create_landlord_counter_bidding_table
 *   152716 create_landlord_counter_bidding_meta_table
 *   152719 create_landlord_counter_terms_table
 *   152720 create_landlord_counter_terms_meta_table
 *
 * The last two were rescued by 2026_04_02_011954_create_landlord_counter_terms_tables_fix,
 * which is the pattern this migration follows. The other three are still absent
 * — verified read-only against production, not merely inferred from the schema
 * dump: `tenant_counter_bidding_meta`, `landlord_counter_bidding` and
 * `landlord_counter_bidding_meta` do not exist there either.
 *
 * The consequence is not cosmetic. `TenantAgentAuctionBidCounter::submit()`
 * inserts its counter row, then `saveAllMetaData()` raises "relation does not
 * exist", the surrounding catch rolls the row back, and the user is shown a
 * generic error — so no tenant counter bid has ever persisted (the table holds
 * zero rows in production). Landlord counter bidding fails harder still, at the
 * `create()` itself, because its main table is absent.
 *
 * WHY A NEW MIGRATION RATHER THAN REPAIRING THE HISTORICAL FILES
 * -------------------------------------------------------------
 * All five are already recorded in `migrations` on every existing database, so
 * restoring their bodies would not re-run them anywhere and production would
 * stay broken, while fresh installs would diverge from every existing database
 * for the same migration name. Their stranded bodies are also not correct: the
 * landlord one declares its bid foreign key against `landlord_auction_bids`, a
 * legacy table that does not exist, so moving that body back into `up()` would
 * turn a silent no-op into a hard migration failure.
 *
 * FOREIGN KEY TARGETS
 * -------------------
 * `landlord_agent_auction_id` references `landlord_agent_auctions`, matching the
 * model (`LandlordCounterBidding::auction()` is a belongsTo LandlordAgentAuction
 * on that column), the write path (`LandlordAgentAuctionBidCounter` assigns the
 * auction's id), and both sibling roles (tenant and buyer point their
 * `*_agent_auction_id` at `*_agent_auctions`). `landlord_agent_auction_bid_id`
 * references `landlord_agent_auction_bids`, the table that actually exists.
 *
 * NOTE: `landlord_counter_terms` carries a live foreign key that incorrectly
 * targets `landlord_agent_auction_bids` on its `landlord_agent_auction_id`
 * column. That is a real but SEPARATE defect and is deliberately not touched
 * here; it has its own follow-up item.
 *
 * IDEMPOTENT. Every create is guarded by Schema::hasTable(), so this is inert
 * where the tables already exist and effective where they do not — fresh
 * installs, production, incremental upgrades and dump restores all converge on
 * the same schema. There is no data to backfill: every counter table in
 * production holds zero rows.
 */
class CreateMissingCounterTablesFix extends Migration
{
    public function up(): void
    {
        // Parent first: the meta table below takes a foreign key against it.
        if (! Schema::hasTable('landlord_counter_bidding')) {
            Schema::create('landlord_counter_bidding', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('landlord_agent_auction_id');
                $table->unsignedBigInteger('landlord_agent_auction_bid_id');
                $table->string('property_type');
                $table->unsignedBigInteger('parent_counter_id')->nullable(); // counter-back chain

                $table->string('accepted')->default('0');
                $table->timestamp('accepted_date')->nullable();

                $table->timestamps();

                $table->foreign('user_id')
                    ->references('id')->on('users')->onDelete('cascade');

                $table->foreign('landlord_agent_auction_id')
                    ->references('id')->on('landlord_agent_auctions')->onDelete('cascade');

                $table->foreign('landlord_agent_auction_bid_id')
                    ->references('id')->on('landlord_agent_auction_bids')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('landlord_counter_bidding_meta')) {
            Schema::create('landlord_counter_bidding_meta', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('counter_bidding_id');
                $table->string('meta_key');
                $table->text('meta_value')->nullable();
                $table->timestamps();

                $table->foreign('counter_bidding_id')
                    ->references('id')->on('landlord_counter_bidding')->onDelete('cascade');

                $table->index(['counter_bidding_id', 'meta_key']);
            });
        }

        // `tenant_counter_bidding` itself already exists (152713 was guarded
        // correctly); only its meta table was lost.
        if (! Schema::hasTable('tenant_counter_bidding_meta')) {
            Schema::create('tenant_counter_bidding_meta', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('counter_bidding_id');
                $table->string('meta_key');
                $table->text('meta_value')->nullable();
                $table->timestamps();

                $table->foreign('counter_bidding_id')
                    ->references('id')->on('tenant_counter_bidding')->onDelete('cascade');

                $table->index(['counter_bidding_id', 'meta_key']);
            });
        }
    }

    public function down(): void
    {
        // Children before parents.
        Schema::dropIfExists('tenant_counter_bidding_meta');
        Schema::dropIfExists('landlord_counter_bidding_meta');
        Schema::dropIfExists('landlord_counter_bidding');
    }
}
