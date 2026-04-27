<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddListingTypeToAcceptedBidSummaries extends Migration
{
    public function up()
    {
        if (Schema::hasTable('accepted_bid_summaries') && !Schema::hasColumn('accepted_bid_summaries', 'listing_type')) {
            Schema::table('accepted_bid_summaries', function (Blueprint $table) {
                $table->string('listing_type', 20)->nullable()->after('id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('accepted_bid_summaries') && Schema::hasColumn('accepted_bid_summaries', 'listing_type')) {
            Schema::table('accepted_bid_summaries', function (Blueprint $table) {
                $table->dropColumn('listing_type');
            });
        }
    }
}
