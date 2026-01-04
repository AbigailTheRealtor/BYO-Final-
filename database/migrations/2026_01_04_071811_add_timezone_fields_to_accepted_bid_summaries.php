<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimezoneFieldsToAcceptedBidSummaries extends Migration
{
    public function up()
    {
        Schema::table('accepted_bid_summaries', function (Blueprint $table) {
            $table->string('tenant_timezone')->nullable()->after('tenant_ip_address');
            $table->string('tenant_user_agent')->nullable()->after('tenant_timezone');
            $table->string('agent_timezone')->nullable()->after('agent_ip_address');
            $table->string('agent_user_agent')->nullable()->after('agent_timezone');
        });
    }

    public function down()
    {
        Schema::table('accepted_bid_summaries', function (Blueprint $table) {
            $table->dropColumn(['tenant_timezone', 'tenant_user_agent', 'agent_timezone', 'agent_user_agent']);
        });
    }
}
