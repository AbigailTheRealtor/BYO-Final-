<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRentalQualificationChecksTable extends Migration
{
    public function up()
    {
        Schema::create('rental_qualification_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('landlord_listing_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name', 191);
            $table->string('email', 191);
            $table->string('phone', 64)->nullable();
            $table->string('estimated_credit_score', 50)->nullable();
            $table->string('monthly_household_income', 50)->nullable();
            $table->string('employment_status', 100)->nullable();
            $table->string('eviction_history', 100)->nullable();
            $table->string('bankruptcy_history', 100)->nullable();
            $table->unsignedSmallInteger('number_of_occupants')->nullable();
            $table->text('additional_notes')->nullable();
            $table->string('status', 30)->default('submitted');
            $table->timestamps();

            $table->foreign('landlord_listing_id')
                  ->references('id')
                  ->on('landlord_agent_auctions')
                  ->cascadeOnDelete();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->index('landlord_listing_id');
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rental_qualification_checks');
    }
}
