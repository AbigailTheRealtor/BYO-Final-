<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsDraftToBuyerAgentAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('buyer_agent_auctions', function (Blueprint $table) {
            if (!Schema::hasColumn('buyer_agent_auctions', 'is_draft')) {
                $table->boolean('is_draft')->default(false)->after('listing_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('buyer_agent_auctions', function (Blueprint $table) {
            if (Schema::hasColumn('buyer_agent_auctions', 'is_draft')) {
                $table->dropColumn('is_draft');
            }
        });
    }
}
