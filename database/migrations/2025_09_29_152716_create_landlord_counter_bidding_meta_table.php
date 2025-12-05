<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLandlordCounterBiddingMetaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('tenant_agent_auctions') && !Schema::hasTable('landlord_agent_auctions')) {
            return; // Skip if base tables do not exist
        }
    }

    public function up_original()
    {
        Schema::create('landlord_counter_bidding_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counter_bidding_id');
            $table->string('meta_key');
            $table->text('meta_value')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('counter_bidding_id')
                ->references('id')
                ->on('landlord_counter_bidding')
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
        Schema::dropIfExists('landlord_counter_bidding_meta');
    }
}
