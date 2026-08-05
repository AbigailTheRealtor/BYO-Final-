<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d-1 — the ZCTA-to-county relationship, authoritative and many-to-many.
 *
 * This replaces a name join. The shipped repository matches ZIPs to counties by comparing a
 * bare county name against `us_zip_codes.county`, stripping class suffixes ("County",
 * "Parish", "Borough", "Census Area", "Municipality") to make the two spellings meet. That
 * join is why 280 counties resolve no ZIPs at all. Here the relationship is published, so
 * nothing is inferred from spelling.
 *
 * `arealand_part` is the land area of the ZCTA lying inside that county. It is retained,
 * unused for now, so a later slice can suppress sliver overlaps — a ZCTA clipping a county
 * by a few square metres is technically an association and rarely a useful option. Deciding
 * that threshold is a product question, not an import one, so nothing is filtered here.
 *
 * Source: tab20_zcta520_county20_natl.txt   Vintage: 2020
 */
class CreateCensusZctaCountiesTable extends Migration
{
    public function up()
    {
        Schema::create('census_zcta_counties', function (Blueprint $table) {
            $table->char('zcta5', 5);
            $table->char('county_geoid', 5);
            $table->bigInteger('arealand_part')->nullable();
            $table->timestamps();

            $table->primary(['zcta5', 'county_geoid']);
            $table->index('county_geoid', 'census_zcta_counties_county_index');
            $table->index('zcta5', 'census_zcta_counties_zcta_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('census_zcta_counties');
    }
}
