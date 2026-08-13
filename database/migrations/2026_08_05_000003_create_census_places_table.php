<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d-1 — Census places: incorporated municipalities AND census designated places.
 *
 * THIS IS THE TABLE THE AUDIT EXISTS FOR
 * --------------------------------------
 * The shipped `us_cities` is a USPS ZIP-locality list: 98.9% of its rows are USPS mailing
 * names, it carries artifacts like "Amf Ohare" and "Glenview Nas", it holds both "Saint
 * Petersburg" and "St. Petersburg" as separate rows, and it omits any municipality that
 * lacks its own ZIP city name — 12 of Pinellas County's 24, and 41 of Westchester's 45.
 * This table replaces that with 32,188 published places.
 *
 * `name` VS `namelsad`, AND WHY THE LSAD CODE IS STORED
 * ----------------------------------------------------
 * Census publishes names WITH their legal suffix: "Abbeville city", "Abanda CDP". Stored
 * labels are bare ("St. Petersburg, FL"). Stripping the suffix by guesswork would be
 * unreliable — "Kansas City city" and "Oklahoma City city" both end in the word "City".
 *
 * The Gazetteer's numeric LSAD code makes it deterministic: 25=city, 43=town, 47=village,
 * 21=borough, 57=CDP, 53="city and borough", and so on. `lsad` is retained so the strip is
 * auditable after the fact and so a future surface can say what KIND of place this is —
 * `classfp` (C* = incorporated, U* = CDP) answers the question the USPS data never could.
 *
 * Sources: national_place2020.txt + 2020_Gaz_place_national.zip   Vintage: 2020
 */
class CreateCensusPlacesTable extends Migration
{
    public function up()
    {
        Schema::create('census_places', function (Blueprint $table) {
            $table->char('geoid', 7);            // STATEFP + PLACEFP
            $table->char('state_geoid', 2);
            $table->char('placefp', 5);
            $table->string('name', 150);         // bare, LSAD suffix removed
            $table->string('namelsad', 150);     // as published, e.g. "Abbeville city"
            $table->char('lsad', 2)->nullable(); // numeric LSAD code from the Gazetteer
            $table->char('classfp', 2)->nullable();
            $table->char('funcstat', 1)->nullable();
            $table->string('placens', 8)->nullable();
            $table->decimal('intptlat', 10, 7)->nullable();
            $table->decimal('intptlong', 11, 7)->nullable();
            $table->timestamps();

            $table->primary('geoid');
            $table->index('state_geoid', 'census_places_state_index');
            $table->index(['state_geoid', 'name'], 'census_places_state_name_index');
            $table->index('classfp', 'census_places_classfp_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('census_places');
    }
}
