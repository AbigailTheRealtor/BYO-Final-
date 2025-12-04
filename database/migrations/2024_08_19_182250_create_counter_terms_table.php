<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('counter_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buyer_auction_id');
            $table->string('commissionOpt')->nullable();
            $table->string('otherCommission')->nullable();
            $table->string('otherComOptions')->nullable();
            $table->string('serviceOther')->nullable();
            $table->string('timeframe')->nullable();
            $table->string('commission');
            $table->string('compensation')->nullable();
            $table->json('services')->nullable();
            $table->string('additionalDetails')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('counter_terms');
    }
};
