<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToSellerAgentAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('seller_agent_auctions', function (Blueprint $table) {
            if (!Schema::hasColumn('seller_agent_auctions', 'title')) {
                $table->string('title')->nullable()->after('address');
            }
            if (!Schema::hasColumn('seller_agent_auctions', 'is_draft')) {
                $table->boolean('is_draft')->default(false)->after('is_approved');
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
        Schema::table('seller_agent_auctions', function (Blueprint $table) {
            if (Schema::hasColumn('seller_agent_auctions', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('seller_agent_auctions', 'is_draft')) {
                $table->dropColumn('is_draft');
            }
        });
    }
}
