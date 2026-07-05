<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage E0 — row-level version stamps for provider-aware POI cache invalidation
 * (docs/canonical-field-mapping-spec.md §7).
 *
 * Additive and NULLABLE — no backfill in the migration itself; existing rows are
 * stamped to the current versions once via `ldna:stamp-versions`. Behavior is
 * unchanged until a fetch/scoring/provider input actually moves.
 *
 *   pois_fetch_version   — snapshot of the fetch-defining inputs (category defs,
 *                          groups, radius, provider surface). Mismatch => refetch.
 *   pois_scoring_version — snapshot of the scoring-defining inputs (ranking
 *                          profiles, exclusion rules, constants). Mismatch =>
 *                          recompute-from-cache, no API call.
 *
 * Both are indexed so the propagation query (WHERE pois_scoring_version <>
 * :current) is cheap.
 */
class AddVersionColumnsToPropertyLocationPoisTable extends Migration
{
    public function up()
    {
        Schema::table('property_location_pois', function (Blueprint $table) {
            $table->string('pois_fetch_version', 64)->nullable()->index()->after('human_corroborated');
            $table->string('pois_scoring_version', 64)->nullable()->index()->after('pois_fetch_version');
        });
    }

    public function down()
    {
        Schema::table('property_location_pois', function (Blueprint $table) {
            $table->dropColumn(['pois_fetch_version', 'pois_scoring_version']);
        });
    }
}
