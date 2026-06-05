<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShowingAvailabilitiesTable extends Migration
{
    public function up()
    {
        Schema::create('showing_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_auction_id')
                  ->constrained('offer_auctions')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->date('available_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('notes')->nullable();
            $table->unsignedInteger('max_showings')->nullable()->default(null);
            $table->timestamps();

            $table->index('offer_auction_id');
            $table->index('available_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('showing_availabilities');
    }
}
