<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiShareTokenToPropertyAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('property_auctions') &&
            ! Schema::hasColumn('property_auctions', 'ai_share_token')) {
            Schema::table('property_auctions', function (Blueprint $table) {
                $table->string('ai_share_token', 64)->nullable()->unique()->after('id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('property_auctions') &&
            Schema::hasColumn('property_auctions', 'ai_share_token')) {
            Schema::table('property_auctions', function (Blueprint $table) {
                $table->dropColumn('ai_share_token');
            });
        }
    }
}
