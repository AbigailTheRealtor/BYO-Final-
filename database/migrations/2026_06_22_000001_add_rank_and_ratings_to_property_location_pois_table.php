<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRankAndRatingsToPropertyLocationPoisTable extends Migration
{
    public function up()
    {
        Schema::table('property_location_pois', function (Blueprint $table) {
            // Drop the existing unique constraint on (listing_type, listing_id, poi_category)
            // so that multiple ranked candidates per category can be stored.
            $table->dropUnique(['listing_type', 'listing_id', 'poi_category']);

            // rank: 1 = nearest/primary, 2 = second nearest, etc.
            // Existing rows (single-candidate) are backfilled to rank=1.
            $table->unsignedSmallInteger('rank')->default(1)->after('poi_category');

            // Rating and review count from Google Places response.
            $table->decimal('rating', 3, 1)->nullable()->after('distance_miles');
            $table->unsignedInteger('user_ratings_total')->nullable()->after('rating');

            // Full raw Google Places `types` array for the result, stored as JSON.
            // Used for post-storage filtering, auditability, and future category refinement.
            $table->json('types_json')->nullable()->after('user_ratings_total');

            // New unique constraint allows multiple ranked candidates per category.
            $table->unique(['listing_type', 'listing_id', 'poi_category', 'rank']);
        });
    }

    public function down()
    {
        Schema::table('property_location_pois', function (Blueprint $table) {
            $table->dropUnique(['listing_type', 'listing_id', 'poi_category', 'rank']);
            $table->dropColumn(['rank', 'rating', 'user_ratings_total', 'types_json']);
            $table->unique(['listing_type', 'listing_id', 'poi_category']);
        });
    }
}
