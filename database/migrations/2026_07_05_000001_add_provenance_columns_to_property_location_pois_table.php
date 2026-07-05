<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage C of the provider-agnostic Location Intelligence contract.
 *
 * Adds the canonical-field envelope metadata (docs/canonical-field-mapping-spec.md §1)
 * to POI rows: confidence, provenance, freshness, and human-corroboration.
 *
 * Purely ADDITIVE and NULLABLE — no backfill, no behavior change. The existing
 * Google pipeline neither reads nor writes these columns yet; they are populated
 * only once the adapter contract is extended in a later stage. `data_source`
 * already carries the winning provider id, so `source` is not re-added here.
 */
class AddProvenanceColumnsToPropertyLocationPoisTable extends Migration
{
    public function up()
    {
        Schema::table('property_location_pois', function (Blueprint $table) {
            // Canonical confidence 0.000–1.000 (canonical-field-mapping-spec §2). Null = not yet scored.
            $table->decimal('confidence', 4, 3)->nullable()->after('data_source');

            // Provenance envelope: { provider, method, raw_ref, license, contributors[] } (§3).
            $table->json('provenance_json')->nullable()->after('confidence');

            // Freshness: when the winning value was last fetched/derived (§4).
            $table->timestamp('last_refreshed')->nullable()->after('provenance_json');

            // Human confirmation/override — outranks any provider in precedence (§5, §6).
            $table->boolean('human_corroborated')->default(false)->after('last_refreshed');
        });
    }

    public function down()
    {
        Schema::table('property_location_pois', function (Blueprint $table) {
            $table->dropColumn([
                'confidence',
                'provenance_json',
                'last_refreshed',
                'human_corroborated',
            ]);
        });
    }
}
