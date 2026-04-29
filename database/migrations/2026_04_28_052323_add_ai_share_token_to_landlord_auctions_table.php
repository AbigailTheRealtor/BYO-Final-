<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiShareTokenToLandlordAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // landlord_auctions has no standalone CREATE migration; guard defensively.
        if (Schema::hasTable('landlord_auctions') &&
            ! Schema::hasColumn('landlord_auctions', 'ai_share_token')) {
            Schema::table('landlord_auctions', function (Blueprint $table) {
                $table->string('ai_share_token', 64)->nullable()->unique()->after('id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('landlord_auctions') &&
            Schema::hasColumn('landlord_auctions', 'ai_share_token')) {
            Schema::table('landlord_auctions', function (Blueprint $table) {
                $table->dropColumn('ai_share_token');
            });
        }
    }
}
