<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d-1 — the place-to-county relationship. MANY-TO-MANY, on purpose.
 *
 * WHY THIS TABLE CHANGES A PHASE 1b RULE
 * --------------------------------------
 * `GeographySelectionResolver` currently documents that "CITIES ARE CONTAINMENT… a city
 * belongs to exactly one county", because `us_cities.county_id` is a single foreign key.
 * The published data says otherwise: 33,618 relationship rows describe 32,188 places, so
 * roughly 1,430 places genuinely straddle a county line — Kansas City MO spans four.
 *
 * Collapsing those to a "primary" county was considered and rejected: it would reintroduce
 * exactly the class of quiet wrongness the USPS data already has. Places therefore become
 * ASSOCIATION, the same rule ZIPs already follow — a place survives while ANY of its
 * counties survives. Phase 1d-3 makes the resolver and validator match.
 *
 * The county index is the hot path: `citiesInCounties()` reads by county, never by place.
 *
 * Source: national_place_by_county2020.txt   Vintage: 2020
 */
class CreateCensusPlaceCountiesTable extends Migration
{
    public function up()
    {
        Schema::create('census_place_counties', function (Blueprint $table) {
            $table->char('place_geoid', 7);
            $table->char('county_geoid', 5);
            $table->timestamps();

            $table->primary(['place_geoid', 'county_geoid']);
            $table->index('county_geoid', 'census_place_counties_county_index');
            $table->index('place_geoid', 'census_place_counties_place_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('census_place_counties');
    }
}
