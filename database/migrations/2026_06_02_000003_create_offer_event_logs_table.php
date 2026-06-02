<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOfferEventLogsTable extends Migration
{
    public function up()
    {
        Schema::create('offer_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')
                  ->constrained('offers')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_role', 30)->nullable();
            $table->string('event_type', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            // No updated_at — event log rows are never modified after insertion.
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['offer_id', 'event_type']);
            $table->index('actor_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('offer_event_logs');
    }
}
