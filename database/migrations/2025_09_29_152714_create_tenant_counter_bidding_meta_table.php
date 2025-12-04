<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantCounterBiddingMetaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tenant_counter_bidding_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counter_bidding_id');
            $table->string('meta_key');
            $table->text('meta_value')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('counter_bidding_id')
                ->references('id')
                ->on('tenant_counter_bidding')
                ->onDelete('cascade');

            // Index for better performance
            $table->index(['counter_bidding_id', 'meta_key']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tenant_counter_bidding_meta');
    }
}
