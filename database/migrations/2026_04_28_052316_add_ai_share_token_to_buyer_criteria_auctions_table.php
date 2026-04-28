<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiShareTokenToBuyerCriteriaAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('buyer_criteria_auctions', function (Blueprint $table) {
            $table->string('ai_share_token', 64)->nullable()->unique()->after('id');
        });
    }

    public function down()
    {
        Schema::table('buyer_criteria_auctions', function (Blueprint $table) {
            $table->dropColumn('ai_share_token');
        });
    }
}
