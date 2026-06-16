<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPhase1NativeColumnsToBridgeProperties extends Migration
{
    public function up()
    {
        Schema::table('bridge_properties', function (Blueprint $table) {
            // Geospatial
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Location
            $table->string('county_or_parish', 100)->nullable();

            // Property classification
            $table->string('property_sub_type', 100)->nullable();
            $table->string('mls_status', 50)->nullable();

            // Age
            $table->smallInteger('year_built')->nullable();

            // Financial
            $table->decimal('association_fee', 10, 2)->nullable();
            $table->decimal('tax_annual_amount', 10, 2)->nullable();

            // Size
            $table->integer('lot_size_sqft')->nullable();

            // Rental qualifiers
            $table->string('pets_allowed', 50)->nullable();
            // NOTE: 'furnished' is EXCLUDED from Phase 1 (blocked at Phase 0 — 35% population rate).
            //       Do not add it here. It is promoted in Phase 2R once the rental feed is active.

            // Boolean feature flags
            $table->boolean('senior_community_yn')->nullable();
            $table->boolean('garage_yn')->nullable();
            $table->boolean('pool_private_yn')->nullable();
            $table->boolean('waterfront_yn')->nullable();
            $table->boolean('association_yn')->nullable();
            $table->boolean('new_construction_yn')->nullable();
            $table->boolean('view_yn')->nullable();
            $table->boolean('water_view_yn')->nullable();
            $table->boolean('cdd_yn')->nullable();
        });
    }

    public function down()
    {
        Schema::table('bridge_properties', function (Blueprint $table) {
            $table->dropColumn([
                'latitude',
                'longitude',
                'county_or_parish',
                'property_sub_type',
                'mls_status',
                'year_built',
                'association_fee',
                'tax_annual_amount',
                'lot_size_sqft',
                'pets_allowed',
                'senior_community_yn',
                'garage_yn',
                'pool_private_yn',
                'waterfront_yn',
                'association_yn',
                'new_construction_yn',
                'view_yn',
                'water_view_yn',
                'cdd_yn',
            ]);
        });
    }
}
