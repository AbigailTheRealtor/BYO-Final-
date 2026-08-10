<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records where a stored coordinate came from and how good it is.
 *
 * WHY THE EXISTING COLUMNS CANNOT CARRY THIS
 * ------------------------------------------
 * `property_location_dna` already stores `geocoded_lat`, `geocoded_lng` and
 * `geocode_source`, and none of them answer the question that decides whether a
 * coordinate may be measured from. `geocode_source` holds legacy values such as
 * 'saved_meta' that describe which code path wrote the row, not which provider
 * produced the point and not how precisely that point identifies the property.
 *
 * That gap is the reason {@see \App\Services\Location\Coordinates\CoordinatePrecision}
 * exists. A ZIP centroid and a rooftop fix are both a latitude and a longitude;
 * only the precision tier distinguishes "draw this on a map" from "measure a
 * flood-zone boundary against this". Today that tier is computed at resolution
 * time and then thrown away when the row is written, so a reader of this table
 * cannot recover it and has to assume — and the safe assumption is the one that
 * makes every stored coordinate useless.
 *
 * THREE COLUMNS, ADDITIVE ONLY
 * ----------------------------
 *   geocode_precision   the CoordinatePrecision tier ('interpolated', 'parcel', …)
 *   geocode_provider    who produced it ('us_census', 'bridge_mls', …)
 *   normalized_address  what the coordinate actually describes, normalized
 *
 * `normalized_address` is not a duplicate of `source_address`. The source
 * columns record what the listing claimed; this records the address the
 * provider says it matched, which is how a coordinate that drifted away from
 * its listing becomes detectable instead of merely wrong.
 *
 * Nothing existing is renamed, dropped, widened or backfilled. Every column
 * added here is nullable, so existing rows stay exactly as they are, and NULL
 * reads as "provenance unknown" — which for a row written before this migration
 * is the truth. Fabricating a precision for historical rows would be worse than
 * leaving them blank: it would let coordinates of unknown quality pass a gate
 * that exists precisely to stop them.
 *
 * NOT A STORAGE MOVE
 * ------------------
 * Canonical coordinate storage stays here. Relocating it to `listing_locations`
 * is a separate decision with its own migration path, and doing it in the same
 * change as adding provenance would make both harder to reverse.
 */
class AddCoordinateProvenanceToPropertyLocationDna extends Migration
{
    public function up()
    {
        Schema::table('property_location_dna', function (Blueprint $table) {
            // Short, low-cardinality enum-like values from CoordinatePrecision.
            $table->string('geocode_precision', 32)->nullable()->after('geocode_source');

            // Free-form on purpose: the set of providers must be able to grow
            // without a migration, exactly as PropertyCoordinateResult treats it.
            $table->string('geocode_provider', 64)->nullable()->after('geocode_precision');

            $table->string('normalized_address')->nullable()->after('geocode_provider');

            // The question worth indexing is "which listings hold a coordinate
            // good enough to measure from" — a precision filter, and the one a
            // backfill or an audit sweep would scan on.
            $table->index('geocode_precision');
        });
    }

    public function down()
    {
        Schema::table('property_location_dna', function (Blueprint $table) {
            $table->dropIndex(['geocode_precision']);
            $table->dropColumn(['geocode_precision', 'geocode_provider', 'normalized_address']);
        });
    }
}
