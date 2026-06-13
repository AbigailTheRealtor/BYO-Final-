<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
 * This migration corrects existing persisted rows so they align with the now-
 * fixed component property and mapper output.
 *
 * Rollback: restores the old misspelled key name (safe — counter-bid and
 * offer-listing components that still use the old name are unaffected because
 * they write to separate meta tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        $before = DB::table('landlord_agent_auction_bid_metas')
            ->where('meta_key', 'renewal_fee_flat_free')
            ->count();

        DB::table('landlord_agent_auction_bid_metas')
            ->where('meta_key', 'renewal_fee_flat_free')
            ->update(['meta_key' => 'renewal_fee_flat_fee']);

        $after = DB::table('landlord_agent_auction_bid_metas')
            ->where('meta_key', 'renewal_fee_flat_fee')
            ->count();

        \Illuminate\Support\Facades\Log::info(
            'P1A migration: renamed renewal_fee_flat_free → renewal_fee_flat_fee in landlord_agent_auction_bid_metas',
            ['rows_before' => $before, 'rows_after' => $after]
        );
    }

    public function down(): void
    {
        DB::table('landlord_agent_auction_bid_metas')
            ->where('meta_key', 'renewal_fee_flat_fee')
            ->update(['meta_key' => 'renewal_fee_flat_free']);
    }
};
