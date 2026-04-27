<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferralColumnsToAcceptedBidSummariesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('accepted_bid_summaries') && !Schema::hasColumn('accepted_bid_summaries', 'referring_agent_id')) {
            Schema::table('accepted_bid_summaries', function (Blueprint $table) {
                $table->unsignedBigInteger('referring_agent_id')->nullable()->after('listing_type');
                $table->string('referral_source_code')->nullable()->after('referring_agent_id');
                $table->string('referral_status')->nullable()->after('referral_source_code');
                $table->decimal('platform_referral_amount', 10, 2)->nullable()->after('referral_status');
                $table->decimal('partner_referral_amount', 10, 2)->nullable()->after('platform_referral_amount');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('accepted_bid_summaries') && Schema::hasColumn('accepted_bid_summaries', 'referring_agent_id')) {
            Schema::table('accepted_bid_summaries', function (Blueprint $table) {
                $table->dropColumn([
                    'referring_agent_id',
                    'referral_source_code',
                    'referral_status',
                    'platform_referral_amount',
                    'partner_referral_amount',
                ]);
            });
        }
    }
}
