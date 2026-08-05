<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d-1 — provenance for every imported Census dataset.
 *
 * "WHICH DATA IS LOADED?" MUST BE ANSWERABLE WITHOUT GUESSING
 * -----------------------------------------------------------
 * The tables this replaces have no recorded provenance at all: `us_cities` has no migration,
 * no source, and an empty `fips_code` column, which is why establishing that it was USPS data
 * took a forensic audit rather than a lookup. This table exists so that never happens again.
 *
 * `sha256` is the digest of the source file as downloaded. It is what makes the importer
 * idempotent in the cheap sense — an unchanged file is skipped without re-parsing — and what
 * makes a silent upstream change visible instead of mysterious.
 */
class CreateCensusGeographyMetaTable extends Migration
{
    public function up()
    {
        Schema::create('census_geography_meta', function (Blueprint $table) {
            $table->id();
            $table->string('dataset', 64);       // states|counties|places|place_counties|zctas|zcta_counties
            $table->string('vintage', 8);        // 2020
            $table->text('source_url');
            $table->char('sha256', 64)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['dataset', 'vintage'], 'census_geography_meta_dataset_vintage_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('census_geography_meta');
    }
}
