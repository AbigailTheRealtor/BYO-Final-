<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTitleToLandlordAgentAuctionsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('landlord_agent_auctions', 'title')) {
            Schema::table('landlord_agent_auctions', function (Blueprint $table) {
                $table->string('title')->nullable()->after('user_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('landlord_agent_auctions', 'title')) {
            Schema::table('landlord_agent_auctions', function (Blueprint $table) {
                $table->dropColumn('title');
            });
        }
    }
}
