<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWizardEventsTable extends Migration
{
    public function up()
    {
        Schema::create('wizard_events', function (Blueprint $table) {
            $table->id();
            $table->string('listing_role', 20);
            $table->unsignedBigInteger('listing_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event_type', 30);
            $table->string('tab_name', 100);
            $table->string('session_id', 100)->nullable();
            $table->string('mode', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['listing_role', 'listing_id', 'session_id', 'tab_name', 'created_at'],
                'wizard_events_dedup_idx'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('wizard_events');
    }
}
