<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShowingsTable extends Migration
{
    public function up()
    {
        Schema::create('showings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('showing_availability_id')
                  ->nullable()
                  ->constrained('showing_availabilities')
                  ->nullOnDelete();
            $table->foreignId('offer_auction_id')
                  ->constrained('offer_auctions')
                  ->cascadeOnDelete();
            $table->foreignId('requester_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->boolean('requested_by_agent')->default(false);
            $table->date('requested_date');
            $table->time('requested_start_time');
            $table->time('requested_end_time');
            $table->string('status', 30)->default('requested');
            $table->text('requester_message')->nullable();
            $table->text('owner_message')->nullable();
            $table->date('approved_date')->nullable();
            $table->time('approved_start_time')->nullable();
            $table->time('approved_end_time')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('offer_auction_id');
            $table->index('requester_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('showings');
    }
}
