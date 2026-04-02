<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLandlordCounterTermsTablesFix extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('landlord_counter_terms')) {
            Schema::create('landlord_counter_terms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('landlord_agent_auction_id');
                $table->string('property_type')->nullable();
                $table->unsignedBigInteger('parent_counter_id')->nullable();
                $table->string('status')->default('0');
                $table->timestamp('accepted_date')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('landlord_agent_auction_id')
                    ->references('id')
                    ->on('landlord_agent_auction_bids')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('landlord_counter_terms_meta')) {
            Schema::create('landlord_counter_terms_meta', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('counter_term_id');
                $table->string('meta_key');
                $table->text('meta_value')->nullable();
                $table->timestamps();

                $table->foreign('counter_term_id')
                    ->references('id')
                    ->on('landlord_counter_terms')
                    ->onDelete('cascade');

                $table->index(['counter_term_id', 'meta_key']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('landlord_counter_terms_meta');
        Schema::dropIfExists('landlord_counter_terms');
    }
}
