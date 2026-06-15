<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocationColumnsToAcceptedBidSummaries extends Migration
{
    public function up()
    {
        Schema::table('accepted_bid_summaries', function (Blueprint $table) {
            $table->string('property_address')->nullable()->after('summary_pdf_path');
            $table->string('property_city')->nullable()->after('property_address');
            $table->string('property_county')->nullable()->after('property_city');
            $table->string('property_state')->nullable()->after('property_county');
            $table->string('property_zip')->nullable()->after('property_state');
            $table->decimal('property_lat', 10, 7)->nullable()->after('property_zip');
            $table->decimal('property_lng', 10, 7)->nullable()->after('property_lat');
            $table->string('google_place_id')->nullable()->after('property_lng');
            $table->text('legal_description')->nullable()->after('google_place_id');
            $table->string('parcel_id')->nullable()->after('legal_description');
            $table->json('location_intelligence_snapshot')->nullable()->after('parcel_id');
        });
    }

    public function down()
    {
        Schema::table('accepted_bid_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'property_address',
                'property_city',
                'property_county',
                'property_state',
                'property_zip',
                'property_lat',
                'property_lng',
                'google_place_id',
                'legal_description',
                'parcel_id',
                'location_intelligence_snapshot',
            ]);
        });
    }
}
