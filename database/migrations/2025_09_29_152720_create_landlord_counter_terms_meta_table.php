<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLandlordCounterTermsMetaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('landlord_counter_terms_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counter_term_id');
            $table->string('meta_key');
            $table->text('meta_value')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('counter_term_id')
                ->references('id')
                ->on('landlord_counter_terms')
                ->onDelete('cascade');

            // Index for better performance
            $table->index(['counter_term_id', 'meta_key']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('landlord_counter_terms_meta');
    }
}
