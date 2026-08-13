<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d-1 — ZIP Code Tabulation Areas, 2020.
 *
 * WHY 2020 AND NOT A LATER VINTAGE
 * --------------------------------
 * ZCTAs are only published on the decennial census, so 2020 is the current one — and the
 * ZCTA-to-county relationship file exists at 2020 alone. Pairing 2020 ZCTAs with, say, 2025
 * places would break referential integrity across this schema, which is why every table here
 * is pinned to a single vintage recorded in `census_geography_meta`.
 *
 * WHAT THIS FIXES
 * ---------------
 * The shipped `us_zip_codes` holds 34,741 rows covering only 48 states: AZ, HI, NM, NV, UT
 * and the territories have NO ZIP data at all, and 280 counties can surface no ZIP option.
 * Real ZIPs are simply absent — 34689 Tarpon Springs, 34677 Oldsmar, 10583 Scarsdale.
 *
 * Source: tab20_zcta520_county20_natl.txt   Vintage: 2020
 */
class CreateCensusZctasTable extends Migration
{
    public function up()
    {
        Schema::create('census_zctas', function (Blueprint $table) {
            $table->char('zcta5', 5);
            $table->timestamps();

            $table->primary('zcta5');
        });
    }

    public function down()
    {
        Schema::dropIfExists('census_zctas');
    }
}
