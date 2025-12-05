<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddListingIdToAuctionsTables extends Migration
{
    public function up()
    {
        $tables = [
            'tenant_agent_auctions',
            'landlord_agent_auctions',
            'buyer_agent_auctions',
            'seller_agent_auctions',
            'property_auctions',
            'buyer_criteria_auctions',
            'tenant_criteria_auctions',
            'landlord_auctions',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'listing_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('listing_id', 20)->nullable()->unique()->after('id');
                });
            }
        }
    }

    public function down()
    {
        $tables = [
            'tenant_agent_auctions',
            'landlord_agent_auctions',
            'buyer_agent_auctions',
            'seller_agent_auctions',
            'property_auctions',
            'buyer_criteria_auctions',
            'tenant_criteria_auctions',
            'landlord_auctions',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'listing_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('listing_id');
                });
            }
        }
    }
}
