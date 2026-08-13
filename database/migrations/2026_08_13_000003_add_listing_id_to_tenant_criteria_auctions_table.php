<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the missing `listing_id` column to `tenant_criteria_auctions`.
 *
 * This is a migration-ordering gap, not a design decision. The backfill migration
 * `2025_12_05_063000_add_listing_id_to_auctions_tables` lists `tenant_criteria_auctions`
 * among the tables it adds `listing_id` to, but it is guarded by `Schema::hasTable()`
 * and `create_tenant_criteria_auctions_table` did not run until `2026_06_14_000002` —
 * six months later. The table did not exist when the guard was evaluated, so it was
 * silently skipped, and the create migration that eventually built the table omitted
 * `listing_id`.
 *
 * `App\Models\TenantCriteriaAuction` uses the `HasListingId` trait, whose
 * `bootHasListingId()` creating-hook assigns `listing_id` on every insert, so the
 * legitimate TenantCriteriaAuction create path fails outright with
 * "table tenant_criteria_auctions has no column named listing_id".
 *
 * Definition is copied verbatim from the 2025_12_05 migration so this table matches
 * the seven siblings that were covered: string(20), nullable, unique. Preserves the
 * existing HasListingId architecture — the trait remains the sole producer of the
 * value, and this migration only gives it somewhere to land.
 *
 * Additive only. Pre-existing rows keep `listing_id = NULL`; the trait generates on
 * create, not retroactively, and a unique index permits multiple NULLs. Fabricating
 * listing IDs for historical rows would invent identifiers no other system has seen,
 * so no backfill is performed — this matches what the 2025_12_05 migration did for
 * the tables it reached.
 */
class AddListingIdToTenantCriteriaAuctionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('tenant_criteria_auctions') && ! Schema::hasColumn('tenant_criteria_auctions', 'listing_id')) {
            Schema::table('tenant_criteria_auctions', function (Blueprint $table) {
                $table->string('listing_id', 20)->nullable()->unique()->after('id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('tenant_criteria_auctions') && Schema::hasColumn('tenant_criteria_auctions', 'listing_id')) {
            Schema::table('tenant_criteria_auctions', function (Blueprint $table) {
                $table->dropColumn('listing_id');
            });
        }
    }
}
