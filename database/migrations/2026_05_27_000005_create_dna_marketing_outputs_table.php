<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDnaMarketingOutputsTable extends Migration
{
    public function up()
    {
        Schema::create('dna_marketing_outputs', function (Blueprint $table) {
            $table->id();
            $table->string('listing_type');
            $table->unsignedBigInteger('listing_id');
            $table->string('output_type');
            $table->integer('variant_index');
            $table->longText('content');
            $table->boolean('fair_housing_reviewed')->default(false);
            $table->json('fair_housing_flags')->nullable();
            $table->string('generated_by');
            $table->integer('version');
            $table->timestamp('source_listing_updated_at');
            $table->string('scoring_version')->nullable();
            $table->timestamp('generated_at');
            $table->timestamp('archived_at')->nullable()->default(null);
            $table->timestamp('created_at');

            $table->index(['listing_type', 'listing_id', 'output_type', 'archived_at']);
            $table->index('fair_housing_reviewed');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dna_marketing_outputs');
    }
}
