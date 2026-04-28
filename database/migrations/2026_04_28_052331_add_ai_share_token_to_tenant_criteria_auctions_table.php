<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiShareTokenToTenantCriteriaAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tenant_criteria_auctions', function (Blueprint $table) {
            $table->string('ai_share_token', 64)->nullable()->unique()->after('id');
        });
    }

    public function down()
    {
        Schema::table('tenant_criteria_auctions', function (Blueprint $table) {
            $table->dropColumn('ai_share_token');
        });
    }
}
