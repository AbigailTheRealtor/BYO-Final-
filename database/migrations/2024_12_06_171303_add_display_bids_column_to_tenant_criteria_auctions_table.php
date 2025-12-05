<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDisplayBidsColumnToTenantCriteriaAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('tenant_criteria_auctions')) {
            Schema::table('tenant_criteria_auctions', function (Blueprint $table) {
                $table->boolean('display_bids')->after('id')->default(true);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tenat_criteria_auctions', function (Blueprint $table) {
            $table->dropColumn('display_bids');
        });
    }
}
