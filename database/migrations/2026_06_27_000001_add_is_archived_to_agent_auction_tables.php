<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WF-2 — owner-controlled archive/unpublish of published listings.
 *
 * Adds a nullable boolean `is_archived` flag (default false) to the four
 * listing tables. When an owner archives a listing it is set to 1, which the
 * public/agent discovery queries exclude (the listing drops out of circulation)
 * while all related data (bids, accepted-bid summaries) is preserved. Setting it
 * back to 0 republishes. This is additive and reversible; no data is destroyed.
 */
return new class extends Migration
{
    private array $tables = [
        'seller_agent_auctions',
        'buyer_agent_auctions',
        'landlord_agent_auctions',
        'tenant_agent_auctions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'is_archived')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('is_archived')->default(false)->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_archived')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('is_archived');
                });
            }
        }
    }
};
