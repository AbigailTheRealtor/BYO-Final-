<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferralColumnsToSellerAgentAuctionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seller_agent_auctions')) {
            Schema::table('seller_agent_auctions', function (Blueprint $table) {
                $table->unsignedBigInteger('referring_agent_id')->nullable()->after('sold_date');
                $table->string('referral_source_code')->nullable()->after('referring_agent_id');
                $table->timestamp('referral_captured_at')->nullable()->after('referral_source_code');
                $table->boolean('referral_locked')->default(false)->after('referral_captured_at');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('seller_agent_auctions')) {
            Schema::table('seller_agent_auctions', function (Blueprint $table) {
                $table->dropColumn(['referring_agent_id', 'referral_source_code', 'referral_captured_at', 'referral_locked']);
            });
        }
    }
}
