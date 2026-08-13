<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d-3 — which ZIPs belong to which place.
 *
 * THE ASSOCIATION THE CENSUS CANNOT PUBLISH
 * -----------------------------------------
 * `census_zcta_counties` relates a ZCTA to a COUNTY, and that is the whole of what the Bureau
 * publishes. It is enough for the cascade's ZIP tier, which hangs ZIPs off selected counties —
 * and the Phase 1d-1 audit confirmed it is complete and orphan-free. It is not enough for the
 * hierarchy this phase adds, where a ZIP has to be reachable from a NEIGHBOURHOOD:
 * Clearwater Beach → 33767 is a place-level fact, and no Census table states it.
 *
 * WHY A PIVOT RATHER THAN A COLUMN
 * --------------------------------
 * The relationship is many-to-many in both directions and always has been. St. Petersburg holds
 * 32 ZIPs; a single ZIP can span several municipalities. Storing one "primary" ZIP per place
 * would answer the Clearwater Beach case and then be wrong for every city in the table.
 *
 * `is_zcta` IS THE PO-BOX FLAG, AND IT IS WHY THIS TABLE PRESERVES MORE THAN THE CORPUS
 * -------------------------------------------------------------------------------------
 * Of Pinellas County's USPS ZIPs, 23 are not ZCTAs at all — 33731, 33757, 33769 and their
 * siblings are PO-box and non-residential codes, and ZCTAs cover populated areas only. They are
 * real values that agents have typed and that stored records carry, so dropping them would be a
 * silent loss. They are kept here and marked, so a surface can offer residential ZIPs first
 * without pretending the others never existed.
 *
 * The reverse gap is recorded too: 9 North Pinellas ZCTAs (34677 Oldsmar, 34683-5 Palm Harbor,
 * 34688-9 Tarpon Springs, 34695 Safety Harbor, 34698 Dunedin) appear in NO USPS row, so this
 * table cannot associate them with a place. They still resolve at county level through the
 * Census corpus, which is the tier the cascade actually reads.
 */
class CreateLocationPlaceZipsTable extends Migration
{
    public function up()
    {
        Schema::create('location_place_zips', function (Blueprint $table) {
            $table->unsignedBigInteger('location_place_id');
            $table->char('zip', 5);

            // True when the ZIP is also a published ZCTA — i.e. it names residential geography
            // the Census recognises, not just a postal route or a bank of boxes.
            $table->boolean('is_zcta')->default(false);

            $table->string('source', 16);        // usps|curated
            $table->timestamps();

            $table->primary(['location_place_id', 'zip']);

            $table->foreign('location_place_id')
                ->references('id')->on('location_places')
                ->cascadeOnDelete();

            $table->index('zip', 'location_place_zips_zip_index');
            $table->index(['zip', 'is_zcta'], 'location_place_zips_zip_zcta_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('location_place_zips');
    }
}
