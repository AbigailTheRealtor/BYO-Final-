<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the canonical Bidding Period start timestamp to offer_auctions.
 *
 * WHY offer_auctions and not the listing tables:
 *   seller_agent_auctions stores native columns while landlord_agent_auctions is
 *   EAV-by-design. offer_auctions is the single native table that both roles link
 *   to 1:1 via the listing meta `linked_offer_auction_id`, so it is the only place
 *   a native column can serve Seller and Landlord symmetrically without pushing the
 *   value into EAV meta.
 *
 * NO BACKFILL — deliberately.
 *   Existing rows stay NULL. The migration execution time is not the moment those
 *   listings went Active, so stamping it would fabricate a bidding start and could
 *   silently extend or truncate a live bidding window. NULL is the honest value and
 *   BiddingWindowService reads it as "use the documented legacy fallback".
 */
class AddBiddingStartedAtToOfferAuctionsTable extends Migration
{
    public function up()
    {
        Schema::table('offer_auctions', function (Blueprint $table) {
            $table->timestamp('bidding_started_at')->nullable()->after('is_sold');
        });
    }

    public function down()
    {
        Schema::table('offer_auctions', function (Blueprint $table) {
            $table->dropColumn('bidding_started_at');
        });
    }
}
