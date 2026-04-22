<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferralColumnsToLandlordAgentAuctionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('landlord_agent_auctions')) {
            Schema::table('landlord_agent_auctions', function (Blueprint $table) {
                $table->unsignedBigInteger('referring_agent_id')->nullable();
                $table->string('referral_source_code')->nullable();
                $table->timestamp('referral_captured_at')->nullable();
                $table->boolean('referral_locked')->default(false);
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('landlord_agent_auctions')) {
            Schema::table('landlord_agent_auctions', function (Blueprint $table) {
                $table->dropColumn(['referring_agent_id', 'referral_source_code', 'referral_captured_at', 'referral_locked']);
            });
        }
    }
}
