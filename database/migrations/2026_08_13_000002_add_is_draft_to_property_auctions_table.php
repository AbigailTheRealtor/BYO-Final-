<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the missing `is_draft` column to `property_auctions`.
 *
 * `App\Models\PropertyAuction` declares `'is_draft' => false` in its `$attributes`
 * default block, so *every* insert Eloquent builds for this model carries an
 * `is_draft` value — but `create_property_auctions_table` never defined the column
 * and no later migration added it (seller/buyer/landlord/tenant agent auctions and
 * tenant_criteria_auctions all got one; property_auctions was missed). The result is
 * that the legitimate PropertyAuction create path fails outright with
 * "table property_auctions has no column named is_draft".
 *
 * Type/default mirror the sibling tables and the model's own default, so existing
 * rows read exactly as they behave today: published, not drafts.
 *
 * Additive only — no backfill is needed because the column is NOT NULL with a
 * default, so every pre-existing row resolves to `false` without a data write.
 */
class AddIsDraftToPropertyAuctionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('property_auctions') && ! Schema::hasColumn('property_auctions', 'is_draft')) {
            Schema::table('property_auctions', function (Blueprint $table) {
                $table->boolean('is_draft')->default(false)->after('is_approved');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('property_auctions') && Schema::hasColumn('property_auctions', 'is_draft')) {
            Schema::table('property_auctions', function (Blueprint $table) {
                $table->dropColumn('is_draft');
            });
        }
    }
}
