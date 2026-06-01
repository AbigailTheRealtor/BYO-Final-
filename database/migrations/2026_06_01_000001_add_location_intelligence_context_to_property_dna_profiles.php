<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocationIntelligenceContextToPropertyDnaProfiles extends Migration
{
    public function up()
    {
        Schema::table('property_dna_profiles', function (Blueprint $table) {
            $table->json('location_intelligence_context')->nullable()->after('ai_marketing_hooks');
        });
    }

    public function down()
    {
        Schema::table('property_dna_profiles', function (Blueprint $table) {
            $table->dropColumn('location_intelligence_context');
        });
    }
}
