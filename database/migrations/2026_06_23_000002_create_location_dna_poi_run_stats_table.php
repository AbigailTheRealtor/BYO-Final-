<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLocationDnaPoiRunStatsTable extends Migration
{
    public function up(): void
    {
        Schema::create('location_dna_poi_run_stats', function (Blueprint $table) {
            $table->id();
            $table->string('listing_type', 100);
            $table->unsignedBigInteger('listing_id');
            $table->unsignedSmallInteger('categories_fetched_fresh')->default(0)
                ->comment('API calls that hit Google Places (tile miss)');
            $table->unsignedSmallInteger('categories_from_tile_cache')->default(0)
                ->comment('API calls skipped due to tile cache hit');
            $table->unsignedSmallInteger('categories_grouped')->default(0)
                ->comment('API calls skipped due to category grouping (secondary categories)');
            $table->decimal('precision_used', 10, 6)->nullable()
                ->comment('Tile precision decimal degrees used for this run, null when tile cache disabled');
            $table->timestamp('run_at')->useCurrent();

            $table->index(['listing_type', 'listing_id']);
            $table->index('run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_dna_poi_run_stats');
    }
}
