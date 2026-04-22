<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferralColumnsToBuyerAgentAuctionsTable extends Migration
{
    public function up()
    {
        Schema::table('buyer_agent_auctions', function (Blueprint $table) {
            $table->unsignedBigInteger('referring_agent_id')->nullable()->after('sold_date');
            $table->string('referral_source_code')->nullable()->after('referring_agent_id');
            $table->timestamp('referral_captured_at')->nullable()->after('referral_source_code');
            $table->boolean('referral_locked')->default(false)->after('referral_captured_at');
        });
    }

    public function down()
    {
        Schema::table('buyer_agent_auctions', function (Blueprint $table) {
            $table->dropColumn(['referring_agent_id', 'referral_source_code', 'referral_captured_at', 'referral_locked']);
        });
    }
}
