<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d-1 — Census counties (and county equivalents).
 *
 * TWO NAME COLUMNS, AND BOTH ARE LOAD-BEARING
 * -------------------------------------------
 * `name` holds the published form — "Autauga County", "Orleans Parish", "Juneau City and
 * Borough" — because the labels already stored in production carry exactly that form
 * ("Pinellas County, FL"). Emitting anything else would leave every historical record
 * unmatched.
 *
 * `basename` holds the same value with the class word removed ("Autauga"), which is what
 * loose matching needs. Storing it rather than deriving it at query time keeps the
 * repository free of the `regexp_replace` the USPS-era join relied on.
 *
 * Source: national_county2020.txt   Vintage: 2020
 */
class CreateCensusCountiesTable extends Migration
{
    public function up()
    {
        Schema::create('census_counties', function (Blueprint $table) {
            $table->char('geoid', 5);            // STATEFP + COUNTYFP
            $table->char('state_geoid', 2);
            $table->char('countyfp', 3);
            $table->string('name', 120);         // published, e.g. "Autauga County"
            $table->string('basename', 120);     // class word stripped, e.g. "Autauga"
            $table->char('classfp', 2)->nullable();
            $table->char('funcstat', 1)->nullable();
            $table->string('countyns', 8)->nullable();
            $table->timestamps();

            $table->primary('geoid');
            $table->index('state_geoid', 'census_counties_state_index');
            $table->index(['state_geoid', 'name'], 'census_counties_state_name_index');
            $table->unique(['state_geoid', 'countyfp'], 'census_counties_state_fp_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('census_counties');
    }
}
