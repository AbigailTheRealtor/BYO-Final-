<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateListingCompatibilityScoresTable extends Migration
{
    public function up()
    {
        Schema::create('listing_compatibility_scores', function (Blueprint $table) {
            $table->id();

            $table->string('demand_listing_type');
            $table->unsignedBigInteger('demand_listing_id');
            $table->string('supply_listing_type');
            $table->unsignedBigInteger('supply_listing_id');

            $table->integer('version');
            $table->string('scoring_framework_version');

            $table->timestamp('demand_listing_updated_at_snapshot');
            $table->timestamp('supply_listing_updated_at_snapshot');

            $table->decimal('overall_score', 5, 2)->nullable();
            $table->decimal('physical_match_score', 5, 2)->nullable();
            $table->decimal('financial_match_score', 5, 2)->nullable();
            $table->decimal('location_match_score', 5, 2)->nullable();
            $table->decimal('terms_match_score', 5, 2)->nullable();

            $table->boolean('deal_breaker_triggered')->default(false);
            $table->json('deal_breaker_flags')->nullable();
            $table->json('score_explanation')->nullable();

            $table->timestamp('computed_at');
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['demand_listing_type', 'demand_listing_id', 'archived_at']);
            $table->index(['supply_listing_type', 'supply_listing_id', 'archived_at']);
            $table->index('overall_score');
        });
    }

    public function down()
    {
        Schema::dropIfExists('listing_compatibility_scores');
    }
}
