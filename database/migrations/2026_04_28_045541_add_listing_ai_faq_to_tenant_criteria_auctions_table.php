<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddListingAiFaqToTenantCriteriaAuctionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // tenant_criteria_auctions has no standalone CREATE migration; guard defensively.
        if (Schema::hasTable('tenant_criteria_auctions') &&
            ! Schema::hasColumn('tenant_criteria_auctions', 'listing_ai_faq')) {
            Schema::table('tenant_criteria_auctions', function (Blueprint $table) {
                $table->json('listing_ai_faq')->nullable()->after('updated_at');
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
        if (Schema::hasTable('tenant_criteria_auctions') &&
            Schema::hasColumn('tenant_criteria_auctions', 'listing_ai_faq')) {
            Schema::table('tenant_criteria_auctions', function (Blueprint $table) {
                $table->dropColumn('listing_ai_faq');
            });
        }
    }
}
