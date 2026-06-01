<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyLocationDnaAuditsTable extends Migration
{
    public function up()
    {
        Schema::create('property_location_dna_audits', function (Blueprint $table) {
            $table->id();

            $table->string('listing_type');
            $table->unsignedBigInteger('listing_id');

            $table->string('event_type');
            $table->string('status');
            $table->string('source')->nullable();

            $table->json('input_snapshot')->nullable();
            $table->json('output_snapshot')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['listing_type', 'listing_id']);
            $table->index('event_type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_location_dna_audits');
    }
}
