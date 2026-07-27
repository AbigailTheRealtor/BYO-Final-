<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the rejected single-stamp timer with the canonical two-column window.
 *
 * WHY THIS MIGRATION EXISTS
 *   2026_07_27_000001 added offer_auctions.bidding_started_at and computed the
 *   deadline at runtime as bidding_started_at + auction_time. Owner-Approved
 *   Decision A (2026-07-27) rejects that architecture: the deadline must be
 *   STORED, not recomputed. See TIMED_OFFER_RUNTIME_INVESTIGATION.md, deviation
 *   D-13. The earlier migration is left untouched so any environment that has
 *   already run it migrates forward cleanly rather than being rewritten under.
 *
 * WHAT CHANGES
 *   bidding_started_at  ->  bidding_starts_at   (renamed; values preserved)
 *   bidding_ends_at                             (added, nullable, NO backfill)
 *
 *   The rename preserves every stamp written by the previous architecture. A
 *   value in that column marks the real moment a listing went Active, which is
 *   accurate information and must not be discarded.
 *
 * NO BACKFILL OF bidding_ends_at — deliberately (Decision B).
 *   Every existing row gets NULL. Two distinct populations end up here:
 *
 *     1. Rows that never had a stamp: both columns NULL. Nothing is known about
 *        when their window opened, so nothing is invented.
 *
 *     2. Rows stamped by the previous architecture: bidding_starts_at set,
 *        bidding_ends_at NULL. Their end could arithmetically be derived from
 *        the stored auction_time, but doing it here would be this migration
 *        deciding a live deadline on its own authority. Decision B reserves that
 *        to "an approved migration or product workflow" — this is not it.
 *
 *   Both populations are treated identically at runtime: a window is canonical
 *   only when BOTH timestamps are present. Anything else is UNINITIALIZED — it
 *   renders no countdown and blocks no bidder. See BiddingWindow::isCanonical().
 *
 *   Query for population 2 before deciding whether a follow-up initialization
 *   migration is warranted:
 *
 *     SELECT COUNT(*) FROM offer_auctions
 *     WHERE bidding_starts_at IS NOT NULL AND bidding_ends_at IS NULL;
 */
class ReplaceBiddingStartedAtWithCanonicalWindow extends Migration
{
    public function up()
    {
        // Rename in its own schema call: some drivers will not accept a rename
        // and an add in the same ALTER batch.
        if (Schema::hasColumn('offer_auctions', 'bidding_started_at')
            && ! Schema::hasColumn('offer_auctions', 'bidding_starts_at')) {
            Schema::table('offer_auctions', function (Blueprint $table) {
                $table->renameColumn('bidding_started_at', 'bidding_starts_at');
            });
        }

        Schema::table('offer_auctions', function (Blueprint $table) {
            if (! Schema::hasColumn('offer_auctions', 'bidding_starts_at')) {
                $table->timestamp('bidding_starts_at')->nullable()->after('is_sold');
            }

            if (! Schema::hasColumn('offer_auctions', 'bidding_ends_at')) {
                $table->timestamp('bidding_ends_at')->nullable()->after('bidding_starts_at');
            }
        });
    }

    public function down()
    {
        Schema::table('offer_auctions', function (Blueprint $table) {
            if (Schema::hasColumn('offer_auctions', 'bidding_ends_at')) {
                $table->dropColumn('bidding_ends_at');
            }
        });

        if (Schema::hasColumn('offer_auctions', 'bidding_starts_at')
            && ! Schema::hasColumn('offer_auctions', 'bidding_started_at')) {
            Schema::table('offer_auctions', function (Blueprint $table) {
                $table->renameColumn('bidding_starts_at', 'bidding_started_at');
            });
        }
    }
}
