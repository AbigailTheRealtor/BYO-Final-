<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * P1A — Safe Mapping Defect Fix
 *
 * Renames the meta key `renewal_fee_flat_free` → `renewal_fee_flat_fee` in
 * landlord_agent_auction_bid_metas.
 *
 * Background: AgentBidMapperService has always emitted the canonical key
 * `renewal_fee_flat_fee`, but LandlordAgentAuctionBid stored and loaded the
 * value under the misspelled key `renewal_fee_flat_free`.  As a result, every
 * preset → bid pre-fill attempt silently dropped the flat renewal fee value.
 *
 * Collision handling: a bid that already has a `renewal_fee_flat_fee` row
 * (written by any future code) would produce duplicate meta_key entries if we
 * blindly renamed the old row.  In that case we keep the canonical row and
 * delete the stale misspelled one.
 *
 * Rollback: restores the old misspelled key name using the same per-row
 * collision logic in reverse (safe — counter-bid and offer-listing components
 * write to separate meta tables and are unaffected).
 */
return new class extends Migration
{
    private const OLD_KEY = 'renewal_fee_flat_free';
    private const NEW_KEY = 'renewal_fee_flat_fee';

    public function up(): void
    {
        $renamed = 0;
        $deleted = 0;

        $oldRows = DB::table('landlord_agent_auction_bid_metas')
            ->where('meta_key', self::OLD_KEY)
            ->select(['id', 'landlord_agent_auction_bid_id'])
            ->get();

        foreach ($oldRows as $row) {
            $canonicalAlreadyExists = DB::table('landlord_agent_auction_bid_metas')
                ->where('landlord_agent_auction_bid_id', $row->landlord_agent_auction_bid_id)
                ->where('meta_key', self::NEW_KEY)
                ->exists();

            if ($canonicalAlreadyExists) {
                // A canonical row already exists — deleting the stale misspelled row
                // prevents a duplicate meta_key for this bid.
                DB::table('landlord_agent_auction_bid_metas')
                    ->where('id', $row->id)
                    ->delete();
                $deleted++;
            } else {
                // No collision — rename in-place.
                DB::table('landlord_agent_auction_bid_metas')
                    ->where('id', $row->id)
                    ->update(['meta_key' => self::NEW_KEY]);
                $renamed++;
            }
        }

        Log::info('P1A migration up: landlord_agent_auction_bid_metas', [
            'key_renamed' => $renamed,
            'duplicate_deleted' => $deleted,
        ]);
    }

    public function down(): void
    {
        $renamed = 0;
        $deleted = 0;

        $newRows = DB::table('landlord_agent_auction_bid_metas')
            ->where('meta_key', self::NEW_KEY)
            ->select(['id', 'landlord_agent_auction_bid_id'])
            ->get();

        foreach ($newRows as $row) {
            $staleAlreadyExists = DB::table('landlord_agent_auction_bid_metas')
                ->where('landlord_agent_auction_bid_id', $row->landlord_agent_auction_bid_id)
                ->where('meta_key', self::OLD_KEY)
                ->exists();

            if ($staleAlreadyExists) {
                DB::table('landlord_agent_auction_bid_metas')
                    ->where('id', $row->id)
                    ->delete();
                $deleted++;
            } else {
                DB::table('landlord_agent_auction_bid_metas')
                    ->where('id', $row->id)
                    ->update(['meta_key' => self::OLD_KEY]);
                $renamed++;
            }
        }

        Log::info('P1A migration down: landlord_agent_auction_bid_metas', [
            'key_renamed' => $renamed,
            'duplicate_deleted' => $deleted,
        ]);
    }
};
