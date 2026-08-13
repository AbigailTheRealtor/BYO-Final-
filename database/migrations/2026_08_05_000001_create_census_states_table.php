<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d-1 — authoritative Census geography, state tier.
 *
 * ADDITIVE. The `us_*` reference tables are untouched and still serve the shipped
 * `geography_source=eloquent` repository. Nothing reads these tables until Phase 1d-2
 * introduces `CensusCriteriaGeographyRepository`.
 *
 * GEOID IS THE PRIMARY KEY, and it is a CHAR, not an integer. `01` is Alabama; casting
 * to int would silently make it `1` and break every join in this schema. Every identifier
 * in these six tables is a fixed-width zero-padded string for that reason.
 *
 * Source: https://www2.census.gov/geo/docs/reference/codes2020/national_state2020.txt
 * Vintage: 2020 (see census_geography_meta).
 */
class CreateCensusStatesTable extends Migration
{
    public function up()
    {
        Schema::create('census_states', function (Blueprint $table) {
            $table->char('geoid', 2);            // STATEFP
            $table->char('usps', 2);             // two-letter abbreviation
            $table->string('name', 100);
            $table->string('statens', 8)->nullable();
            $table->timestamps();

            $table->primary('geoid');
            $table->unique('usps', 'census_states_usps_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('census_states');
    }
}
