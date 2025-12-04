<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuctionTermFinancingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auction_term_financings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_term_id')->constrained('auction_terms')->onDelete('cascade');
            $table->foreignId('financing_id')->constrained('financings')->onDelete('cascade');
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
        Schema::dropIfExists('auction_term_financings');
    }
}
