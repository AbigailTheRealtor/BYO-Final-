<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR 2 / PHASE 1 — additive only.
 *
 * ── WHY ──────────────────────────────────────────────────────────────────────
 *
 * The two halves of one Tenant↔Agent negotiation were stored at different
 * granularities:
 *
 *   tenant_counter_bidding  (AGENT side)  user_id, tenant_agent_auction_id, tenant_agent_auction_bid_id
 *   tenant_counter_terms    (TENANT side) user_id, tenant_agent_auction_id
 *
 * So the finest boundary the owner's side could express was the LISTING. One
 * owner countering two agents on one listing had two agent-side threads and a
 * single shared owner-side row. That is not a query bug — no guard could fix it,
 * because the fact simply was not stored. It is why PR 1's ownership check on
 * $counterTermId could only compare the auction, even though its comment says it
 * verifies "the row belongs to this negotiation".
 *
 * ── WHY NULLABLE, PERMANENTLY ────────────────────────────────────────────────
 *
 * Historical rows cannot all be resolved to a bid. An auction with exactly one
 * surviving bid is deterministic; with several it is a guess, and with none the
 * referent may be gone entirely (bids are hard-deleted — see withdraw_bid — and
 * the model has no SoftDeletes). Writing a guessed negotiation link into what is
 * an evidentiary record is worse than an honest NULL, so ambiguous rows keep NULL
 * and readers must treat NULL as "legacy, unscoped": displayable in an archival
 * view, but NEVER adoptable as the current bid's editable counter.
 *
 * PHASE 2 (backfill of the deterministic single-bid case) IS DELIBERATELY NOT IN
 * THIS MIGRATION. It is gated on measuring the real production row distribution;
 * the only database reachable from the dev environment is APP_ENV=local with zero
 * rows, which proves nothing about production.
 *
 * ── FK BEHAVIOUR: nullOnDelete, NOT cascade ──────────────────────────────────
 *
 * tenant_counter_bidding cascades on bid delete. This column deliberately does
 * not. withdraw_bid() lets an AGENT hard-delete their own bid; under cascade that
 * would silently destroy the OWNER's countersigned terms — a data loss the
 * counterparty could trigger unilaterally. Nulling degrades the row to "legacy,
 * unscoped" instead, which the readers already handle safely.
 *
 * ── NO UNIQUE CONSTRAINT ─────────────────────────────────────────────────────
 *
 * A unique (bid, user, status) looks attractive but would break the legitimate
 * multi-round counter-back chain, and would collide across legacy NULL rows.
 * Revisit only once the chain semantics are settled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_counter_terms')) {
            return;
        }
        if (Schema::hasColumn('tenant_counter_terms', 'tenant_agent_auction_bid_id')) {
            return;
        }

        Schema::table('tenant_counter_terms', function (Blueprint $table) {
            // Nullable on purpose and for good — see the class docblock.
            // tenant_agent_auction_id is left exactly as it is: it remains the
            // auction reference it always was, and nothing here reinterprets it.
            $table->unsignedBigInteger('tenant_agent_auction_bid_id')
                ->nullable()
                ->after('tenant_agent_auction_id');

            // Supports the hot path: the EDIT-mode lookup in
            // TenantAgentAuctionCounterTerm::mount(), which now filters on
            // bid + user + status.
            $table->index(
                ['tenant_agent_auction_bid_id', 'user_id', 'status'],
                'tct_bid_user_status_index'
            );

            $table->foreign('tenant_agent_auction_bid_id')
                ->references('id')
                ->on('tenant_agent_auction_bids')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_counter_terms')) {
            return;
        }
        if (! Schema::hasColumn('tenant_counter_terms', 'tenant_agent_auction_bid_id')) {
            return;
        }

        Schema::table('tenant_counter_terms', function (Blueprint $table) {
            $table->dropForeign(['tenant_agent_auction_bid_id']);
            $table->dropIndex('tct_bid_user_status_index');
            $table->dropColumn('tenant_agent_auction_bid_id');
        });
    }
};
