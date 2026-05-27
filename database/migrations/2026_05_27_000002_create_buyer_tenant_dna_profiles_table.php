<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBuyerTenantDnaProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('buyer_tenant_dna_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('listing_type');
            $table->unsignedBigInteger('listing_id');
            $table->integer('version');
            $table->timestamp('source_listing_updated_at');

            $table->decimal('preference_completeness', 5, 2)->nullable();
            $table->json('lifestyle_tags')->nullable();
            $table->json('deal_breaker_flags')->nullable();
            $table->string('archetype_label')->nullable();
            $table->longText('commute_polygon_cache')->nullable()->comment('Reserved / Future Use Only — Not Implemented (F-03). Must not be populated, read, or exposed in any Phase 3 phase.');

            $table->timestamp('computed_at');
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['listing_type', 'listing_id', 'archived_at']);
            $table->index('archetype_label');
        });
    }

    public function down()
    {
        Schema::dropIfExists('buyer_tenant_dna_profiles');
    }
}
