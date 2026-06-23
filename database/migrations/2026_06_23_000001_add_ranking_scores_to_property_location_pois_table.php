<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRankingScoresToPropertyLocationPoisTable extends Migration
{
    public function up()
    {
        Schema::table('property_location_pois', function (Blueprint $table) {
            $table->decimal('category_match_score', 5, 2)->nullable()->after('types_json');
            $table->decimal('consumer_relevance_score', 5, 2)->nullable()->after('category_match_score');
            $table->decimal('review_confidence_score', 5, 2)->nullable()->after('consumer_relevance_score');
            $table->decimal('ranking_score', 5, 2)->nullable()->index()->after('review_confidence_score');
            $table->json('ranking_reasons_json')->nullable()->after('ranking_score');
        });
    }

    public function down()
    {
        Schema::table('property_location_pois', function (Blueprint $table) {
            $table->dropColumn([
                'category_match_score',
                'consumer_relevance_score',
                'review_confidence_score',
                'ranking_score',
                'ranking_reasons_json',
            ]);
        });
    }
}
