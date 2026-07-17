<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch B1.3 (Money Precision) — Part 1.
 *
 * Converts the actively-written native float/double money columns on the
 * bid/service auction tables from binary floating point to fixed-precision
 * DECIMAL, so stored monetary values are exact to the cent.
 *
 * Scope (deliberately narrow — see the B1.3 classification report):
 *   - Only float/double columns that are actually written natively (or are
 *     default-populated on every insert) on live bid/service tables.
 *   - Money amounts  -> DECIMAL(15,2)   (ceiling ~$10 trillion, cent scale)
 *   - Percentage rate -> DECIMAL(5,2)   (0.00–999.99, ample for a % rate)
 *
 * Explicitly NOT touched (out of B1.3 scope): dead tables (auctions,
 * auction_terms, property_auction_terms), dormant EAV-bypassed native columns
 * (property_auctions.*, buyer_agent_auctions.*, buyer_criteria_*), dead columns
 * (seller_agent_auctions.min_price/max_commission, agent_service_auctions.min_price),
 * dormant native string money columns, counter_terms.commission, crypto_budget,
 * and *_in unit-selector columns.
 *
 * A pre-migration data audit (2026-07-17) confirmed every target column holds
 * only 0/NULL/empty data in production, so no value overflows DECIMAL(15,2) /
 * DECIMAL(5,2) and no rounding of existing data occurs.
 *
 * Requires doctrine/dbal (installed) for the ->change() column alterations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_auction_bids', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->default(0)->change();
            $table->decimal('escrow_amount', 15, 2)->default(0)->change();
        });

        Schema::table('seller_agent_auction_bids', function (Blueprint $table) {
            $table->decimal('brokerage', 15, 2)->nullable()->change();
            $table->decimal('price', 15, 2)->default(0)->nullable()->change();
            $table->decimal('price_percent', 5, 2)->default(0)->change();
        });

        Schema::table('buyer_agent_auction_bids', function (Blueprint $table) {
            $table->decimal('brokerage', 15, 2)->nullable()->change();
            $table->decimal('price', 15, 2)->default(0)->nullable()->change();
            $table->decimal('price_percent', 5, 2)->default(0)->nullable()->change();
        });

        Schema::table('agent_service_auction_bids', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->nullable()->change();
        });

        Schema::table('seller_service_auctions', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->nullable()->change();
        });

        Schema::table('seller_service_auction_bids', function (Blueprint $table) {
            $table->decimal('brokerage', 15, 2)->change();
            $table->decimal('price', 15, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('property_auction_bids', function (Blueprint $table) {
            $table->float('price', 18, 2)->default(0)->change();
            $table->float('escrow_amount', 18, 2)->default(0)->change();
        });

        Schema::table('seller_agent_auction_bids', function (Blueprint $table) {
            $table->float('brokerage', 18, 2)->nullable()->change();
            $table->float('price', 18, 2)->default(0.00)->nullable()->change();
            $table->float('price_percent', 12, 2)->default(0)->change();
        });

        Schema::table('buyer_agent_auction_bids', function (Blueprint $table) {
            $table->float('brokerage', 18, 2)->nullable()->change();
            $table->float('price', 18, 2)->nullable()->default(0.00)->change();
            $table->float('price_percent', 12, 2)->nullable()->default(0)->change();
        });

        Schema::table('agent_service_auction_bids', function (Blueprint $table) {
            $table->double('price')->nullable()->change();
        });

        Schema::table('seller_service_auctions', function (Blueprint $table) {
            $table->double('price')->nullable()->change();
        });

        Schema::table('seller_service_auction_bids', function (Blueprint $table) {
            $table->double('brokerage')->change();
            $table->double('price')->change();
        });
    }
};
