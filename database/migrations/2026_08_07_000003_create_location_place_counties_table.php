<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d-4 — the many-to-many `location_places.county_geoid` could not express.
 *
 * THE LIMITATION THIS REMOVES
 * ---------------------------
 * `location_places.county_geoid` holds ONE county: the lowest GEOID a place belongs to. For the
 * 30,884 places that sit in a single county that is the whole truth. For the 1,304 that straddle
 * a county line it is a coin toss, and the Phase 1d-3 build report measured the damage — six
 * counties resolved ZERO places, including:
 *
 *   Richmond County NY   — Staten Island. New York city spans five counties, and the scalar
 *                          column assigned it to Bronx County, the lowest GEOID of the five.
 *   Midland County TX    — Midland city straddles Midland and Martin; Martin sorts lower.
 *   Pasquotank County NC — Elizabeth City straddles Pasquotank and Camden; Camden sorts lower.
 *
 * A county-scoped picker reading the scalar column would have offered a Staten Island user
 * nothing at all, with no error to notice. That is the failure mode this table exists to prevent.
 *
 * IT MIRRORS `census_place_counties`, IT DOES NOT REPLACE IT
 * ----------------------------------------------------------
 * The Census relationship table remains the published source and is READ ONLY here, exactly as in
 * Phase 1d-1. This table restates it in terms of `location_places.id` so that ONE query answers
 * "what is selectable in these counties?" across all three sources — census, supplemental and
 * curated — instead of the caller having to union a GEOID join with two scalar-column lookups.
 * Supplemental and curated rows each contribute their single known county, so the shape is
 * uniform regardless of where a place came from.
 *
 * `is_primary` KEEPS THE SCALAR COLUMN HONEST
 * -------------------------------------------
 * `location_places.county_geoid` is not dropped: it is still the right answer to "which county is
 * this mainly in?", which is what a chip or a label needs, and dropping it would force every such
 * caller into a join for a single value. `is_primary` marks the row that agrees with it, so the
 * two can be asserted consistent rather than merely assumed to be — see the builder, which sets
 * both from the same source in the same pass.
 */
class CreateLocationPlaceCountiesTable extends Migration
{
    public function up()
    {
        Schema::create('location_place_counties', function (Blueprint $table) {
            $table->unsignedBigInteger('location_place_id');
            $table->char('county_geoid', 5);

            // True on the row matching `location_places.county_geoid`. Exactly one per place.
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->primary(['location_place_id', 'county_geoid']);

            $table->foreign('location_place_id')
                ->references('id')->on('location_places')
                ->cascadeOnDelete();

            // The cascade's question: "what places are in these counties?" — this is the index
            // that makes a whereIn over a handful of counties an index scan rather than a sweep
            // of 40,000 rows.
            $table->index('county_geoid', 'location_place_counties_county_index');
            $table->index(['county_geoid', 'is_primary'], 'location_place_counties_county_primary_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('location_place_counties');
    }
}
